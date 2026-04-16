<?php

namespace Illuminate\Queue\Monitoring;

use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\DatabaseJob;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Register matchers and callbacks, then wire {@see onJobProcessing} and {@see onJobAttempted}
 * to {@see JobProcessing} and {@see JobAttempted} in your application.
 */
class QueueJobTimingMonitor
{
    /**
     * @var array<int, array{0: callable(object): bool, 1: callable(QueueJobTimingContext): void}>
     */
    protected array $rules = [];

    /**
     * Microtime when {@see JobProcessing} ran, keyed by job UUID or job id.
     *
     * @var array<string, float>
     */
    protected array $processingStartedAt = [];

    /**
     * Create a new monitor instance.
     */
    public function __construct(protected Container $container)
    {
    }

    /**
     * Register a matcher and a callback. The callback receives a {@see QueueJobTimingContext}
     * when the matcher returns true for the unserialized job command.
     *
     * @param  callable(object): bool  $matcher
     * @param  callable(QueueJobTimingContext): void  $callback
     */
    public function when(callable $matcher, callable $callback): self
    {
        $this->rules[] = [$matcher, $callback];

        return $this;
    }

    /**
     * Call from a listener on {@see JobProcessing}.
     */
    public function onJobProcessing(JobProcessing $event): void
    {
        if ($this->rules === []) {
            return;
        }

        $job = $event->job;

        $key = $this->jobKey($job);

        if ($key === null) {
            return;
        }

        $this->processingStartedAt[$key] = microtime(true);
    }

    /**
     * Call from a listener on {@see JobAttempted}.
     */
    public function onJobAttempted(JobAttempted $event): void
    {
        $job = $event->job;

        $key = $this->jobKey($job);

        if ($key === null) {
            return;
        }

        $startedAt = $this->processingStartedAt[$key] ?? null;

        unset($this->processingStartedAt[$key]);

        if ($startedAt === null) {
            return;
        }

        if ($this->rules === []) {
            return;
        }

        $completedAt = microtime(true);

        $command = $this->resolveCommand($job);

        if ($command === null || $command instanceof \__PHP_Incomplete_Class) {
            return;
        }

        $payload = $job->payload();
        $payloadCreatedAtRaw = $payload['createdAt'] ?? null;
        $payloadCreatedAt = is_numeric($payloadCreatedAtRaw) ? (int) $payloadCreatedAtRaw : null;

        $queueWaitSeconds = null;
        if ($payloadCreatedAt !== null) {
            $queueWaitSeconds = $startedAt - $payloadCreatedAt;
        }

        $configuredDelaySeconds = $this->inferConfiguredDelaySeconds($command, $payloadCreatedAt);

        $firstAvailableAtTimestamp = $this->resolveFirstAvailableAtTimestamp($job, $payloadCreatedAt, $command);

        $waitAfterAvailableSeconds = null;
        if ($firstAvailableAtTimestamp !== null) {
            $waitAfterAvailableSeconds = max(0.0, $startedAt - $firstAvailableAtTimestamp);
        }

        $totalElapsed = null;
        if ($payloadCreatedAt !== null) {
            $totalElapsed = $completedAt - $payloadCreatedAt;
        }

        $context = new QueueJobTimingContext(
            queueJob: $job,
            command: $command,
            connectionName: $job->getConnectionName(),
            queueName: $job->getQueue(),
            uuid: $job->uuid(),
            jobId: $job->getJobId(),
            displayName: $job->resolveName(),
            attempt: $job->attempts(),
            payloadCreatedAtTimestamp: $payloadCreatedAt,
            processingStartedAtMicrotime: $startedAt,
            processingCompletedAtMicrotime: $completedAt,
            queueWaitSeconds: $queueWaitSeconds,
            configuredDelaySeconds: $configuredDelaySeconds,
            firstAvailableAtTimestamp: $firstAvailableAtTimestamp,
            waitAfterAvailableSeconds: $waitAfterAvailableSeconds,
            wallTimeSeconds: $completedAt - $startedAt,
            totalElapsedSinceDispatchSeconds: $totalElapsed,
            successful: $event->successful(),
            exception: $event->exception,
        );

        foreach ($this->rules as [$matcher, $callback]) {
            try {
                if ($matcher($command)) {
                    $callback($context);
                }
            } catch (Throwable $e) {
                if ($this->container->bound(ExceptionHandler::class)) {
                    $this->container->make(ExceptionHandler::class)->report($e);
                }
            }
        }
    }

    /**
     * @return array<int, array{0: callable(object): bool, 1: callable(QueueJobTimingContext): void}>
     */
    public function rules(): array
    {
        return $this->rules;
    }

    protected function jobKey(JobContract $job): ?string
    {
        if ($job->uuid() !== null) {
            return $job->uuid();
        }

        if ($job->getJobId() !== null && $job->getJobId() !== '') {
            return (string) $job->getJobId();
        }

        return null;
    }

    /**
     * Unserialize the job command from the queue payload (same strategy as {@see \Illuminate\Queue\CallQueuedHandler}).
     */
    protected function resolveCommand(JobContract $job): ?object
    {
        $payload = $job->payload();
        $data = $payload['data'] ?? [];

        if (! isset($data['command'])) {
            return null;
        }

        try {
            if (str_starts_with($data['command'], 'O:')) {
                $command = unserialize($data['command']);
            } elseif ($this->container->bound(Encrypter::class)) {
                $command = unserialize($this->container->make(Encrypter::class)->decrypt($data['command']));
            } else {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        return is_object($command) ? $command : null;
    }

    /**
     * Best-effort: {@see \Illuminate\Bus\Queueable::$delay} at dispatch time (seconds, or seconds until run when a DateTime was used).
     */
    protected function inferConfiguredDelaySeconds(object $command, ?int $payloadCreatedAt): ?float
    {
        if (! property_exists($command, 'delay')) {
            return null;
        }

        $delay = $command->delay;

        if ($delay === null) {
            return null;
        }

        if (is_int($delay)) {
            return (float) $delay;
        }

        if ($delay instanceof DateTimeInterface) {
            if ($payloadCreatedAt === null) {
                return null;
            }

            return max(0.0, (float) $delay->getTimestamp() - $payloadCreatedAt);
        }

        if ($delay instanceof DateInterval) {
            $now = Carbon::now();

            return (float) $now->diffInSeconds($now->copy()->add($delay), true);
        }

        return null;
    }

    /**
     * When this attempt became eligible to be picked up: database {@see DatabaseJob} "available_at",
     * else payload "createdAt" + {@see \Illuminate\Bus\Queueable::$delay} when inferable, else payload "createdAt".
     */
    protected function resolveFirstAvailableAtTimestamp(JobContract $job, ?int $payloadCreatedAt, object $command): ?int
    {
        if ($job instanceof DatabaseJob) {
            $availableAt = $job->getJobRecord()->available_at ?? null;

            if ($availableAt !== null) {
                return (int) $availableAt;
            }
        }

        $inferred = $this->inferFirstAvailableAtFromDelay($command, $payloadCreatedAt);

        if ($inferred !== null) {
            return $inferred;
        }

        return $payloadCreatedAt;
    }

    /**
     * @return int|null  Unix timestamp when the job first became runnable for this attempt (best-effort without database driver).
     */
    protected function inferFirstAvailableAtFromDelay(object $command, ?int $payloadCreatedAt): ?int
    {
        if (! property_exists($command, 'delay')) {
            return null;
        }

        $delay = $command->delay;

        if ($delay === null) {
            return $payloadCreatedAt;
        }

        if (is_int($delay)) {
            return $payloadCreatedAt !== null ? $payloadCreatedAt + $delay : null;
        }

        if ($delay instanceof DateTimeInterface) {
            return (int) $delay->getTimestamp();
        }

        if ($delay instanceof DateInterval) {
            if ($payloadCreatedAt === null) {
                return null;
            }

            $now = Carbon::now();

            return $payloadCreatedAt + (int) $now->diffInSeconds($now->copy()->add($delay), true);
        }

        return null;
    }
}
