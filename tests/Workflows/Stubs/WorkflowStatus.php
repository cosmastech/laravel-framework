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
    case Pending = 'pending';
    case Running = 'running';
    case Waiting = 'waiting';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
