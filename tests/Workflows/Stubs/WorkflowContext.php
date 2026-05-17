<?php

namespace Illuminate\Tests\Workflows\Stubs;

/**
 * Runtime object injected into a workflow method while it is being advanced.
 *
 * The workflow class describes business flow. The context is the durable runtime
 * boundary: steps are recorded here, waits are registered here, and sleeps/timers
 * are scheduled here. The context itself is not the workflow state; it is the API
 * the runner gives the workflow so the runner can persist enough information to
 * replay or resume safely later.
 */
interface WorkflowContext
{
    /**
     * Execute a step once for this workflow run, persisting the result for replay.
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    public function step(\Closure $callback, mixed ...$args): mixed;

    /**
     * Execute a step with per-invocation options (e.g. tries override).
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @param  list<mixed>  $arguments
     * @param  array<string, mixed>  $options
     * @return T
     */
    public function stepWithOptions(\Closure $callback, array $arguments, array $options = []): mixed;

    /**
     * Block until an external signal is delivered (e.g. HTTP wakes the workflow).
     */
    public function waitFor(string $signal): mixed;

    /**
     * Suspend the workflow until a duration elapses (durable timer).
     *
     * @param  \DateInterval|\DateTimeInterface|string  $duration
     */
    public function sleepFor(\DateInterval|\DateTimeInterface|string $duration): void;
}
