<?php

namespace Illuminate\Tests\Workflows\Stubs;

final class WorkflowRun
{
    public function __construct(
        public WorkflowRunId $id,
        public WorkflowStatus $status,
        public mixed $output = null,
    ) {
    }

    public function blocked(): bool
    {
        return $this->status === WorkflowStatus::Waiting
            || $this->status === WorkflowStatus::Pending;
    }
}
