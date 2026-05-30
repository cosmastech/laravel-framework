<?php

namespace Illuminate\Tests\Workflows;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Container\Container;
use Illuminate\Contracts\Database\ModelIdentifier;
use Illuminate\Support\Stringable;
use Illuminate\Tests\Workflows\Attributes\Step;
use Illuminate\Tests\Workflows\Attributes\Workflow;
use Illuminate\Tests\Workflows\Fixtures\OrderModel;
use Illuminate\Tests\Workflows\Fixtures\ReturnLookup;
use Illuminate\Tests\Workflows\Fixtures\ReturnModel;
use Illuminate\Tests\Workflows\Fixtures\ThirdPartyReturnWorkflow;
use Illuminate\Tests\Workflows\Stubs\Workflow as WorkflowFacadeStub;
use Illuminate\Tests\Workflows\Stubs\WorkflowContext;
use Illuminate\Tests\Workflows\Stubs\WorkflowRun;
use Illuminate\Tests\Workflows\Stubs\WorkflowStatus;
use Illuminate\Tests\Workflows\Stubs\WorkflowWakeResult;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class WorkflowShapeTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        Container::setInstance();
    }

    #[Test]
    public function workflow_entry_class_carries_workflow_attribute(): void
    {
        $reflection = new ReflectionClass(ThirdPartyReturnWorkflow::class);

        $attributes = $reflection->getAttributes(Workflow::class);
        $this->assertCount(1, $attributes);

        $workflow = $attributes[0]->newInstance();
        $this->assertSame('third-party-return', $workflow->name);
        $this->assertSame(2, $workflow->tries);
        $this->assertSame(['connection' => 'workflow-db'], $workflow->options);
    }

    #[Test]
    public function workflow_class_can_be_resolved_from_the_container(): void
    {
        Container::getInstance()->instance(
            ReturnLookup::class,
            $concrete = new class extends ReturnLookup
            {
            }
        );
        $workflow = Container::getInstance()->make(ThirdPartyReturnWorkflow::class);

        $this->assertSame($concrete, $workflow->returns);
    }

    #[Test]
    public function step_methods_carry_retry_and_name_configuration(): void
    {
        $reflection = new ReflectionClass(ThirdPartyReturnWorkflow::class);

        $loadReturn = $reflection->getMethod('loadReturn');
        $loadAttrs = $loadReturn->getAttributes(Step::class);
        $this->assertCount(1, $loadAttrs);
        $loadStep = $loadAttrs[0]->newInstance();
        $this->assertSame('load-return', $loadStep->name);
        $this->assertSame(3, $loadStep->tries);

        $validate = $reflection->getMethod('validateReturn');
        $validateAttrs = $validate->getAttributes(Step::class);
        $this->assertCount(1, $validateAttrs);
        $validateStep = $validateAttrs[0]->newInstance();
        $this->assertSame('validate-return', $validateStep->name);
        $this->assertNull($validateStep->tries);

        $apply = $reflection->getMethod('applyExchangeSelection');
        $applyAttrs = $apply->getAttributes(Step::class);
        $this->assertCount(1, $applyAttrs);
        $applyStep = $applyAttrs[0]->newInstance();
        $this->assertSame('apply-exchange', $applyStep->name);
        $this->assertSame(5, $applyStep->tries);
    }

    #[Test]
    public function handle_uses_explicit_workflow_context_primitives(): void
    {
        $context = new class implements WorkflowContext
        {
            public array $calls = [];

            #[Override]
            public function step(Closure $callback, mixed ...$args): mixed
            {
                $this->calls[] = ['step', $args];

                return $callback(...$args);
            }

            #[Override]
            public function stepWithOptions(Closure $callback, array $arguments, array $options = []): mixed
            {
                $this->calls[] = ['stepWithOptions', $arguments, $options];

                return $callback(...$arguments);
            }

            #[Override]
            public function waitFor(string $signal): mixed
            {
                $this->calls[] = ['waitFor', $signal];

                return ['line_item_id' => 'li_1', 'variant_id' => 'var_9'];
            }

            #[Override]
            public function sleepFor(DateInterval|DateTimeInterface|string $duration): void
            {
                $this->calls[] = ['sleepFor', $duration];
            }
        };

        $result = (new Container)->make(ThirdPartyReturnWorkflow::class)->handle($context, 'ret_123');

        $this->assertSame([
            ['stepWithOptions', ['ret_123'], ['tries' => 7]],
            ['step', [['return_id' => 'ret_123', 'state' => 'open']]],
            ['waitFor', 'exchange-selected'],
            ['step', ['ret_123', ['line_item_id' => 'li_1', 'variant_id' => 'var_9'], ['return_id' => 'ret_123', 'state' => 'open']]],
        ], $context->calls);

        $this->assertSame([
            'return_id' => 'ret_123',
            'state' => 'exchanged',
            'exchange' => ['line_item_id' => 'li_1', 'variant_id' => 'var_9'],
        ], $result);
    }

    #[Test]
    public function workflow_run_represents_one_execution_not_definition(): void
    {
        // Given a workflow class that can be executed many times
        $workflowClass = ThirdPartyReturnWorkflow::class;

        // When one durable run of that workflow has completed
        $run = new WorkflowRun(
            id: 'wf_run_return_123',
            status: WorkflowStatus::Completed,
            output: ['return_id' => 'ret_123', 'state' => 'exchanged'],
            workflowClass: ThirdPartyReturnWorkflow::class,
            key: 'ret_123',
        );

        // Then the run is the persisted execution handle, not the workflow class name
        $this->assertSame(ThirdPartyReturnWorkflow::class, $workflowClass);
        $this->assertSame('wf_run_return_123', $run->id);
        $this->assertSame(ThirdPartyReturnWorkflow::class, $run->workflowClass);
        $this->assertSame('ret_123', $run->key);
        $this->assertSame(WorkflowStatus::Completed, $run->status);
        $this->assertFalse($run->blocked());
        $this->assertSame(['return_id' => 'ret_123', 'state' => 'exchanged'], $run->output);
    }

    #[Test]
    public function workflow_run_blocked_status_means_it_is_waiting_on_a_continuation(): void
    {
        $run = new WorkflowRun('wf_run_blocked', WorkflowStatus::Waiting);

        $this->assertTrue($run->blocked());

        foreach ([WorkflowStatus::Pending, WorkflowStatus::Running, WorkflowStatus::Completed, WorkflowStatus::Failed, WorkflowStatus::Cancelled] as $status) {
            $run = new WorkflowRun('wf_run_not_blocked', $status);

            $this->assertFalse($run->blocked(), $status->value);
        }
    }

    #[Test]
    public function workflow_wake_result_separates_run_state_from_response(): void
    {
        // Given an existing run that has been woken by an external signal
        $run = new WorkflowRun('wf_run_return_123', WorkflowStatus::Waiting);

        // When the workflow advances and returns data for the caller
        $result = new WorkflowWakeResult(
            run: $run,
            response: ['next_screen' => 'review-exchange'],
            completed: false,
            blocked: true,
        );

        // Then the caller can inspect both the durable run and the workflow response
        $this->assertSame($run, $result->run);
        $this->assertSame(WorkflowStatus::Waiting, $result->run->status);
        $this->assertSame(['next_screen' => 'review-exchange'], $result->response);
        $this->assertFalse($result->completed);
        $this->assertTrue($result->blocked);
    }

    #[Test]
    public function workflow_class_starts_and_wakes_by_domain_key(): void
    {
        $started = ThirdPartyReturnWorkflow::start('ret_123');

        $this->assertSame(ThirdPartyReturnWorkflow::class, $started->workflowClass);
        $this->assertSame('ret_123', $started->key);
        $this->assertSame(WorkflowStatus::Waiting, $started->status);
        $this->assertTrue($started->blocked());

        $wake = ThirdPartyReturnWorkflow::wake(
            'ret_123',
            'exchange-selected',
            'variant_1',
            ['line_item_id' => 'li_1'],
        );

        $this->assertSame(ThirdPartyReturnWorkflow::class, $wake->run->workflowClass);
        $this->assertSame('ret_123', $wake->run->key);
        $this->assertFalse($wake->completed);
        $this->assertTrue($wake->blocked);
        $this->assertSame(ThirdPartyReturnWorkflow::class, $wake->response['workflow']);
        $this->assertSame('ret_123', $wake->response['key']);
        $this->assertSame('exchange-selected', $wake->response['signal']);
        $this->assertSame(['variant_1', ['line_item_id' => 'li_1']], $wake->response['arguments']);
    }

    #[Test]
    public function workflow_class_accepts_model_as_domain_key(): void
    {
        $return = $this->returnModel('ret_123');

        $started = ThirdPartyReturnWorkflow::start($return);

        $this->assertSame(ThirdPartyReturnWorkflow::class, $started->workflowClass);
        $this->assertSame('model:testing:'.ReturnModel::class.':ret_123', $started->key);
    }

    #[Test]
    public function workflow_wake_accepts_models_as_key_and_arguments(): void
    {
        $return = $this->returnModel('ret_123');
        $order = $this->orderModel('ord_456');

        $wake = ThirdPartyReturnWorkflow::wake($return, 'order-selected', $order);

        $this->assertSame('model:testing:'.ReturnModel::class.':ret_123', $wake->run->key);
        $this->assertSame('model:testing:'.ReturnModel::class.':ret_123', $wake->response['key']);

        $this->assertInstanceOf(ModelIdentifier::class, $wake->response['arguments'][0]);
        $this->assertSame(OrderModel::class, $wake->response['arguments'][0]->class);
        $this->assertSame('ord_456', $wake->response['arguments'][0]->id);
        $this->assertSame('testing', $wake->response['arguments'][0]->connection);
    }

    #[Test]
    public function workflow_model_keys_must_be_persisted_models(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to use an unsaved model in a workflow.');

        ThirdPartyReturnWorkflow::start(new ReturnModel);
    }

    #[Test]
    public function workflow_class_can_queue_start_and_wake_by_domain_key(): void
    {
        $started = ThirdPartyReturnWorkflow::startLater('ret_123');

        $this->assertSame(ThirdPartyReturnWorkflow::class, $started->workflowClass);
        $this->assertSame('ret_123', $started->key);
        $this->assertSame(WorkflowStatus::Pending, $started->status);
        $this->assertFalse($started->blocked());

        $woken = ThirdPartyReturnWorkflow::wakeLater('ret_123', 'exchange-selected', 'variant_1');

        $this->assertSame(ThirdPartyReturnWorkflow::class, $woken->workflowClass);
        $this->assertSame('ret_123', $woken->key);
        $this->assertSame(WorkflowStatus::Pending, $woken->status);
    }

    #[Test]
    public function workflow_facade_can_start_and_wake_specific_definition(): void
    {
        $started = WorkflowFacadeStub::start(ThirdPartyReturnWorkflow::class, 'ret_123');

        $this->assertSame(ThirdPartyReturnWorkflow::class, $started->workflowClass);
        $this->assertSame('ret_123', $started->key);

        $wakeStringable = WorkflowFacadeStub::wake(ThirdPartyReturnWorkflow::class, new Stringable('ret_123'), 'ping');

        $this->assertSame(ThirdPartyReturnWorkflow::class, $wakeStringable->run->workflowClass);
        $this->assertSame('ret_123', $wakeStringable->run->key);
        $this->assertSame('ping', $wakeStringable->response['signal']);
    }

    private function returnModel(string $id): ReturnModel
    {
        $return = new ReturnModel;
        $return->setRawAttributes(['id' => $id], true);
        $return->exists = true;

        return $return;
    }

    private function orderModel(string $id): OrderModel
    {
        $order = new OrderModel;
        $order->setRawAttributes(['id' => $id], true);
        $order->exists = true;

        return $order;
    }
}
