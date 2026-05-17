<?php

namespace Illuminate\Tests\Workflows\Stubs;

use Illuminate\Database\Eloquent\Model;
use Stringable;

/**
 * Mixin for user-defined workflow classes.
 *
 * This gives a workflow definition the domain-first API a Laravel user would
 * naturally reach for: start or wake "this workflow for this business key".
 * The business key might be a return id, order id, tenant id, Eloquent model, or
 * other application-level correlation value.
 */
trait AsWorkflow
{
    /**
     * Start this workflow for a domain key and run it until it blocks, completes, or fails.
     */
    public static function start(Model|string|Stringable $key, mixed ...$arguments): WorkflowRun
    {
        return Workflow::start(static::class, $key, ...$arguments);
    }

    /**
     * Queue this workflow for a domain key and return the pending run handle.
     */
    public static function startLater(Model|string|Stringable $key, mixed ...$arguments): WorkflowRun
    {
        return Workflow::startLater(static::class, $key, ...$arguments);
    }

    /**
     * Wake this workflow for a domain key with a signal and signal arguments.
     */
    public static function wake(Model|string|Stringable $key, string $signal, mixed ...$arguments): WorkflowWakeResult
    {
        return Workflow::wake(static::class, $key, $signal, ...$arguments);
    }

    /**
     * Queue a signal for this workflow and return the pending run handle.
     */
    public static function wakeLater(Model|string|Stringable $key, string $signal, mixed ...$arguments): WorkflowRun
    {
        return Workflow::wakeLater(static::class, $key, $signal, ...$arguments);
    }
}
