<?php

namespace Illuminate\Tests\Workflows\Stubs;

/**
 * A handle to one durable execution of a workflow definition.
 *
 * `ThirdPartyReturnWorkflow::class` is the workflow definition. A `WorkflowRun`
 * is one specific execution of that definition, such as "process return ret_123".
 * This is the object controllers, jobs, and webhooks can keep around to inspect
 * the run id, current lifecycle status, and last output produced by the workflow.
 * User code should normally find the run by workflow class plus a domain key;
 * the run id is still useful internally as the durable storage identifier.
 *
 * In a real implementation this would be backed by a durable repository row, not
 * by the in-memory DTO used in these shape tests.
 */
final class WorkflowRun
{
    public function __construct(
        public WorkflowRunId $id,
        public WorkflowStatus $status,
        public mixed $output = null,
        public ?string $workflowClass = null,
        public ?string $key = null,
    ) {
    }

    public function blocked(): bool
    {
        return $this->status === WorkflowStatus::Waiting;
    }
}
