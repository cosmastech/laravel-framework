<?php

namespace Illuminate\Tests\Workflows\Stubs;

/**
 * Result returned after delivering a signal to an existing workflow run.
 *
 * Waking a workflow has two useful outputs:
 * - the updated `WorkflowRun` lifecycle handle; and
 * - the response/output produced while advancing the workflow.
 *
 * This keeps API handlers from needing to load the run again just to determine
 * whether the workflow completed, blocked on another wait, or returned data to
 * the caller.
 */
final class WorkflowWakeResult
{
    public function __construct(
        public WorkflowRun $run,
        public mixed $response,
        public bool $completed,
        public bool $blocked,
    ) {
    }
}
