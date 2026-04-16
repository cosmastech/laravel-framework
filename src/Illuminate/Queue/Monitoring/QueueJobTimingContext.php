<?php

namespace Illuminate\Queue\Monitoring;

use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Timing and metadata for a single queue job attempt (Datadog, metrics, logging, etc.).
 */
class QueueJobTimingContext
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public readonly JobContract $queueJob,
        public readonly ?object $command,
        public readonly string $connectionName,
        public readonly string $queueName,
        public readonly ?string $uuid,
        public readonly string|int|null $jobId,
        public readonly string $displayName,
        public readonly int $attempt,
        public readonly ?int $payloadCreatedAtTimestamp,
        public readonly float $processingStartedAtMicrotime,
        public readonly float $processingCompletedAtMicrotime,
        public readonly ?float $queueWaitSeconds,
        public readonly ?float $configuredDelaySeconds,
        public readonly ?int $firstAvailableAtTimestamp,
        public readonly ?float $waitAfterAvailableSeconds,
        public readonly float $wallTimeSeconds,
        public readonly ?float $totalElapsedSinceDispatchSeconds,
        public readonly bool $successful,
        public readonly ?Throwable $exception,
        public readonly array $extra = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'connection' => $this->connectionName,
            'queue' => $this->queueName,
            'uuid' => $this->uuid,
            'job_id' => $this->jobId,
            'display_name' => $this->displayName,
            'attempt' => $this->attempt,
            'payload_created_at_timestamp' => $this->payloadCreatedAtTimestamp,
            'processing_started_at_microtime' => $this->processingStartedAtMicrotime,
            'processing_completed_at_microtime' => $this->processingCompletedAtMicrotime,
            'queue_wait_seconds' => $this->queueWaitSeconds,
            'configured_delay_seconds' => $this->configuredDelaySeconds,
            'first_available_at_timestamp' => $this->firstAvailableAtTimestamp,
            'wait_after_available_seconds' => $this->waitAfterAvailableSeconds,
            'wall_time_seconds' => $this->wallTimeSeconds,
            'total_elapsed_since_dispatch_seconds' => $this->totalElapsedSinceDispatchSeconds,
            'successful' => $this->successful,
            'exception' => $this->exception,
            'extra' => $this->extra,
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function withExtra(array $extra): static
    {
        return new static(
            queueJob: $this->queueJob,
            command: $this->command,
            connectionName: $this->connectionName,
            queueName: $this->queueName,
            uuid: $this->uuid,
            jobId: $this->jobId,
            displayName: $this->displayName,
            attempt: $this->attempt,
            payloadCreatedAtTimestamp: $this->payloadCreatedAtTimestamp,
            processingStartedAtMicrotime: $this->processingStartedAtMicrotime,
            processingCompletedAtMicrotime: $this->processingCompletedAtMicrotime,
            queueWaitSeconds: $this->queueWaitSeconds,
            configuredDelaySeconds: $this->configuredDelaySeconds,
            firstAvailableAtTimestamp: $this->firstAvailableAtTimestamp,
            waitAfterAvailableSeconds: $this->waitAfterAvailableSeconds,
            wallTimeSeconds: $this->wallTimeSeconds,
            totalElapsedSinceDispatchSeconds: $this->totalElapsedSinceDispatchSeconds,
            successful: $this->successful,
            exception: $this->exception,
            extra: array_merge($this->extra, $extra),
        );
    }

    /**
     * When this job attempt became eligible to run (database "available_at", or inferred from dispatch + delay).
     */
    public function firstAvailableAt(): ?Carbon
    {
        if ($this->firstAvailableAtTimestamp === null) {
            return null;
        }

        return Carbon::createFromTimestamp($this->firstAvailableAtTimestamp);
    }

    /**
     * Seconds from {@see firstAvailableAt} until processing started (backlog after the job was runnable).
     */
    public function waitAfterAvailable(): ?float
    {
        return $this->waitAfterAvailableSeconds;
    }

    /**
     * When the job payload was first created (dispatch).
     */
    public function payloadCreatedAt(): ?Carbon
    {
        if ($this->payloadCreatedAtTimestamp === null) {
            return null;
        }

        return Carbon::createFromTimestamp($this->payloadCreatedAtTimestamp);
    }

    /**
     * When processing finished ({@see \Illuminate\Queue\Events\JobAttempted}).
     */
    public function processingCompletedAt(): Carbon
    {
        return Carbon::createFromTimestamp($this->processingCompletedAtMicrotime);
    }
}
