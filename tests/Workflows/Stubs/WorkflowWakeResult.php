<?php

namespace Illuminate\Tests\Workflows\Stubs;

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
