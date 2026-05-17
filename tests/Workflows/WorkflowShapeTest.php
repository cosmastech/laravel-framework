<?php

namespace Illuminate\Tests\Workflows;

use Illuminate\Support\Stringable;
use Illuminate\Tests\Workflows\Attributes\Step;
use Illuminate\Tests\Workflows\Attributes\Workflow;
use Illuminate\Tests\Workflows\Fixtures\ReturnLookup;
use Illuminate\Tests\Workflows\Fixtures\ThirdPartyReturnWorkflow;
use Illuminate\Tests\Workflows\Stubs\Workflow as WorkflowFacadeStub;
use Illuminate\Tests\Workflows\Stubs\WorkflowStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class WorkflowShapeTest extends TestCase
{
    #[Test]
    public function workflow_entry_class_carries_workflow_attribute(): void
    {
        $reflection = new ReflectionClass(ThirdPartyReturnWorkflow::class);

        $attributes = $reflection->getAttributes(Workflow::class);
        $this->assertCount(1, $attributes);

        $workflow = $attributes[0]->newInstance();
        $this->assertSame('third-party-return', $workflow->name);
        $this->assertSame(2, $workflow->defaultTries);
        $this->assertSame(['connection' => 'workflow-db'], $workflow->options);
    }

    #[Test]
    public function workflow_class_supports_container_constructor_dependencies(): void
    {
        $reflection = new ReflectionClass(ThirdPartyReturnWorkflow::class);
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);

        $params = $constructor->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('returns', $params[0]->getName());
        $type = $params[0]->getType();
        $this->assertNotNull($type);
        $this->assertSame(ReturnLookup::class, $type->getName());
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
        $reflection = new ReflectionClass(ThirdPartyReturnWorkflow::class);
        $handle = $reflection->getMethod('handle');

        $source = $this->readMethodBodyPreview($handle);

        $this->assertStringContainsString('$workflow->stepWithOptions(', $source);
        $this->assertStringContainsString('\'tries\' => 7', $source);
        $this->assertStringContainsString('$workflow->step(', $source);
        $this->assertStringContainsString('$workflow->waitFor(', $source);
        $this->assertStringContainsString('exchange-selected', $source);
    }

    #[Test]
    public function intended_facade_start_and_wake_surface(): void
    {
        $started = WorkflowFacadeStub::start(ThirdPartyReturnWorkflow::class, 'ret_123');

        $this->assertSame(WorkflowStatus::Waiting, $started->status);
        $this->assertTrue($started->blocked());

        $enqueued = WorkflowFacadeStub::startLater(ThirdPartyReturnWorkflow::class, 'ret_123');

        $this->assertSame(WorkflowStatus::Pending, $enqueued->status);
        $this->assertTrue($enqueued->blocked());

        $wake = WorkflowFacadeStub::wake('wf_run_example', 'exchange-selected', [
            'line_item_id' => 'li_1',
            'variant_id' => 'var_9',
        ]);

        $this->assertSame('wf_run_example', $wake->run->id->value);
        $this->assertFalse($wake->completed);
        $this->assertTrue($wake->blocked);
        $this->assertSame('exchange-selected', $wake->response['signal']);
        $this->assertSame('li_1', $wake->response['payload']['line_item_id']);

        $wakeStringable = WorkflowFacadeStub::wake(new Stringable('wf_run_from_stringable'), 'ping', []);
        $this->assertSame('wf_run_from_stringable', $wakeStringable->run->id->value);

        WorkflowFacadeStub::wakeLater('wf_run_example', 'exchange-selected', []);
    }

    private function readMethodBodyPreview(ReflectionMethod $method): string
    {
        $file = new ReflectionClass($method->class);
        $filename = $file->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();

        if ($filename === false || $startLine === false || $endLine === false) {
            return '';
        }

        $lines = file($filename);
        if ($lines === false) {
            return '';
        }

        $slice = array_slice($lines, $startLine - 1, $endLine - $startLine + 1);

        return implode('', $slice);
    }
}
