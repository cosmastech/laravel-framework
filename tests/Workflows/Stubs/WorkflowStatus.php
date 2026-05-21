<?php

namespace Illuminate\Tests\Workflows\Stubs;

/**
 * Coarse lifecycle state for a workflow run.
 *
 * `Pending` means execution has been queued but has not advanced yet. `Waiting`
 * means execution ran and intentionally blocked on a signal, timer, or other
 * continuation point. Terminal states are represented separately so callers can
 * distinguish a completed workflow from one that failed or was cancelled.
 */
enum WorkflowStatus: string
{
    /**
     * The run has been created or queued, but the workflow body has not started advancing yet.
     */
    case Pending = 'pending';

    /**
     * The run is actively advancing through workflow code.
     */
    case Running = 'running';

    /**
     * The run advanced and intentionally paused until a signal, timer, or other continuation arrives.
     */
    case Waiting = 'waiting';

    /**
     * The run reached the end of the workflow successfully.
     */
    case Completed = 'completed';

    /**
     * The run stopped because execution raised an unrecovered failure.
     */
    case Failed = 'failed';

    /**
     * The run was intentionally stopped before completion.
     */
    case Cancelled = 'cancelled';
}
