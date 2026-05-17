<?php

namespace Illuminate\Tests\Workflows\Stubs;

use Illuminate\Contracts\Database\ModelIdentifier;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Stringable;
use function get_class;

/**
 * Stub for the eventual \Illuminate\Support\Facades\Workflow API.
 *
 * This documents the intended developer surface; the real implementation will
 * coordinate durable storage, replay, signals, queueing, and cancellation.
 *
 * The facade is responsible for starting new runs and waking existing runs by
 * workflow definition plus domain key. The key may be a string or Eloquent
 * model; models are converted to a stable key derived from Laravel's
 * `ModelIdentifier` instead of being snapshotted.
 * The facade is not the workflow definition itself; a workflow definition is the
 * user's class, while a run is one durable execution of that class for a
 * specific business key.
 */
final class Workflow
{
    /**
     * Start a workflow for a domain key and run it until it blocks, completes, or fails.
     *
     * @param  class-string  $workflowClass
     */
    public static function start(string $workflowClass, Model|string|Stringable $key, mixed ...$arguments): WorkflowRun
    {
        $key = self::normalizeKey($key);

        return new WorkflowRun(
            id: self::newRunId($workflowClass, $key),
            status: WorkflowStatus::Waiting,
            output: null,
            workflowClass: $workflowClass,
            key: $key,
        );
    }

    /**
     * Enqueue a new workflow run for a domain key.
     *
     * @param  class-string  $workflowClass
     */
    public static function startLater(string $workflowClass, Model|string|Stringable $key, mixed ...$arguments): WorkflowRun
    {
        $key = self::normalizeKey($key);

        return new WorkflowRun(
            id: self::newRunId($workflowClass, $key),
            status: WorkflowStatus::Pending,
            output: null,
            workflowClass: $workflowClass,
            key: $key,
        );
    }

    /**
     * Deliver a signal to the workflow run identified by class and domain key.
     *
     * @param  class-string  $workflowClass
     */
    public static function wake(string $workflowClass, Model|string|Stringable $key, string $signal, mixed ...$arguments): WorkflowWakeResult
    {
        $key = self::normalizeKey($key);
        $run = new WorkflowRun(
            id: self::newRunId($workflowClass, $key),
            status: WorkflowStatus::Waiting,
            output: null,
            workflowClass: $workflowClass,
            key: $key,
        );

        return new WorkflowWakeResult(
            run: $run,
            response: [
                'workflow' => $workflowClass,
                'key' => $key,
                'signal' => $signal,
                'arguments' => self::normalizeArguments($arguments),
            ],
            completed: false,
            blocked: true,
        );
    }

    /**
     * Enqueue signal delivery and workflow advancement.
     *
     * @param  class-string  $workflowClass
     */
    public static function wakeLater(string $workflowClass, Model|string|Stringable $key, string $signal, mixed ...$arguments): WorkflowRun
    {
        $key = self::normalizeKey($key);

        return new WorkflowRun(
            id: self::newRunId($workflowClass, $key),
            status: WorkflowStatus::Pending,
            output: null,
            workflowClass: $workflowClass,
            key: $key,
        );
    }

    /**
     * @param  Model|string|Stringable  $key
     */
    public static function normalizeKey(Model|string|Stringable $key): string
    {
        if (! $key instanceof Model) {
            return (string) $key;
        }

        return self::keyForModelIdentifier(self::modelIdentifier($key));
    }

    public static function newRunId(string $workflowClass, string $key): WorkflowRunId
    {
        return new WorkflowRunId('wf_run_for_'.$workflowClass.'#'.$key);
    }

    public static function normalizeArguments(array $arguments): array
    {
        $normalized = [];

        foreach ($arguments as $argument) {
            $normalized[] = $argument instanceof Model
                ? self::modelIdentifier($argument)
                : $argument;
        }

        return $normalized;
    }

    public static function modelIdentifier(Model $model): ModelIdentifier
    {
        if ($model->exists === false) {
            throw new InvalidArgumentException('Unable to use an unsaved model in a workflow.');
        }

        $id = $model->getQueueableId();

        if ($id === null) {
            throw new InvalidArgumentException('Unable to use a model without a queueable id in a workflow.');
        }

        return new ModelIdentifier(
            get_class($model),
            $id,
            [],
            $model->getQueueableConnection(),
        );
    }

    public static function keyForModelIdentifier(ModelIdentifier $identifier): string
    {
        return 'model:'.($identifier->connection ?? 'default').':'.$identifier->class.':'.$identifier->id;
    }
}
