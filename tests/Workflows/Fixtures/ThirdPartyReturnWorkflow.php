<?php

namespace Illuminate\Tests\Workflows\Fixtures;

use Illuminate\Tests\Workflows\Attributes\Step;
use Illuminate\Tests\Workflows\Attributes\Workflow;
use Illuminate\Tests\Workflows\Stubs\AsWorkflow;
use Illuminate\Tests\Workflows\Stubs\WorkflowContext;

/**
 * Example interactive workflow: third-party return portal advances as the shopper
 * fills in exchange details. Each user action wakes the run with a signal payload.
 */
#[Workflow(
    name: 'third-party-return',
    tries: 2,
    options: ['connection' => 'workflow-db'],
)]
final class ThirdPartyReturnWorkflow
{
    use AsWorkflow;

    public function __construct(
        public readonly ReturnLookup $returns,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(WorkflowContext $workflow, string $returnId): array
    {
        $return = $workflow->stepWithOptions($this->loadReturn(...), [$returnId], [
            'tries' => 7,
        ]);

        $this->returns->keyFor($returnId);

        $workflow->step($this->validateReturn(...), $return);

        $exchange = $workflow->waitFor('exchange-selected');

        return $workflow->step($this->applyExchangeSelection(...), $returnId, $exchange, $return);
    }

    /**
     * @return array<string, mixed>
     */
    #[Step(name: 'load-return', tries: 3)]
    private function loadReturn(string $returnId): array
    {
        return ['return_id' => $returnId, 'state' => 'open'];
    }

    /**
     * No {@see Step::$tries}: inherits {@see Workflow::$tries} / framework default.
     */
    #[Step(name: 'validate-return')]
    private function validateReturn(array $return): true
    {
        return true;
    }

    /**
     * @param  array<string, mixed>  $exchange
     * @param  array<string, mixed>  $return
     * @return array<string, mixed>
     */
    #[Step(name: 'apply-exchange', tries: 5)]
    private function applyExchangeSelection(string $returnId, array $exchange, array $return): array
    {
        return array_merge($return, ['exchange' => $exchange, 'state' => 'exchanged']);
    }
}
