<?php

namespace Illuminate\Tests\Workflows\Stubs;

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
