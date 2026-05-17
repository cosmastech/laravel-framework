<?php

namespace Illuminate\Tests\Workflows\Stubs;

/**
 * Stable identifier for a workflow run.
 *
 * The run id identifies one persisted execution row, not the workflow class or
 * an individual step. Most application code should not need to pass it around;
 * it can wake a workflow by workflow class plus domain key instead.
 */
final readonly class WorkflowRunId
{
    public function __construct(public string $value)
    {
    }
}
