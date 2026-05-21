# Laravel Workflows Decisions

This is a running decision log for the workflow component shape. It is not final
documentation; it records the current design direction so the tests and
implementation do not drift from the intended developer experience.

## Current API Shape

Workflow classes should feel like normal Laravel classes with an opt-in trait:

```php
#[Workflow(name: 'third-party-return')]
final class ThirdPartyReturnWorkflow
{
    use AsWorkflow;

    public function handle(WorkflowContext $workflow, ReturnModel $return): mixed
    {
        //
    }
}
```

Application code should be able to start and wake workflows by domain key or
model:

```php
ThirdPartyReturnWorkflow::start($return);

ThirdPartyReturnWorkflow::wake(
    $return,
    'exchange-selected',
    $variant,
    $someOtherArg,
);

ThirdPartyReturnWorkflow::wakeLater($return, 'carrier-received');
```

The lower-level facade remains useful when the workflow class is dynamic:

```php
Workflow::start(ThirdPartyReturnWorkflow::class, $return);
Workflow::wake(ThirdPartyReturnWorkflow::class, $return, 'exchange-selected', $variant);
```

## Terms

### Workflow Definition

The PHP class that describes the durable business process, such as
`ThirdPartyReturnWorkflow::class`.

### Workflow Run

One durable execution of a workflow definition for a specific domain key. For
example, one run of `ThirdPartyReturnWorkflow` for return `ret_123`.

### Workflow Key

The application-facing correlation key used to find a workflow run. This may be
a string, `Stringable`, or Eloquent model. Models are normalized to a stable key
derived from Laravel's `ModelIdentifier`.

### WorkflowRunId

Internal storage identity for a persisted workflow run. Application code should
usually use workflow class plus workflow key instead of passing a `WorkflowRunId`.

### Signal

An external event delivered to a waiting workflow, such as `exchange-selected`.
Signals may carry variadic arguments.

### Step

A named durable unit of work inside a workflow. Step names are used for replay so
the runtime can return a recorded result instead of repeating a side effect.

### WorkflowContext

The runtime object passed into `handle()`. It exposes durable primitives such as
`step()`, `stepWithOptions()`, `waitFor()`, and `sleepFor()`.

## Decisions

### Use `AsWorkflow` For Class-Level API

Workflow classes should opt into the ergonomic static API with a trait:

```php
use AsWorkflow;
```

This avoids forcing workflow classes to extend a base class and keeps constructor
injection natural.

### `#[Workflow]` Is Metadata

The `#[Workflow]` attribute marks a class as a workflow definition and carries
metadata/defaults such as name, tries, queue/storage options, or future
retention settings.

It does not add behavior. The trait provides the class-level API.

### Public API Uses Domain Keys, Not `WorkflowRunId`

The preferred public API is:

```php
ThirdPartyReturnWorkflow::wake($return, 'exchange-selected', $variant);
```

not:

```php
Workflow::wake($workflowRunId, 'exchange-selected', $variant);
```

The runtime should resolve the durable run by workflow class plus normalized key.

### Eloquent Models Use Laravel `ModelIdentifier`

Do not introduce a parallel model-reference DTO. Laravel already has
`Illuminate\Contracts\Database\ModelIdentifier`.

Workflow keys derived from models should use the model's queueable identity:
class, queueable id, and connection. Model arguments passed to signals or steps
should normalize to `ModelIdentifier`, not to a serialized model snapshot.

Unsaved models should not be valid workflow keys because they do not have stable
identity.

### `wake()` Runs Inline By Default

For interactive workflows, waking should run synchronously until the workflow
blocks, completes, or fails. This supports HTTP/API flows where the caller wants
the next response immediately.

Queued wakeups should be explicit:

```php
ThirdPartyReturnWorkflow::wakeLater($return, 'carrier-received');
```

### `startLater()` And `wakeLater()` Remain Explicit For Now

The implementation may share one internal queued runner, but the public shape
currently keeps start and wake explicit.

## Open Questions

### Storage Schema

We still need to decide the table shape for workflow runs, step results, signals,
timers, failures, and history.

### Step Replay Model

We need to define how step names are generated, how repeated steps in loops are
identified, and how recorded results are restored.

### Wait/Sleep Implementation

We need to decide how `waitFor()` and `sleepFor()` suspend execution without
depending on PHP call stack state.

### Queue Semantics

We need to decide how workflow queue/connection settings are configured and how
queued steps interact with inline `wake()` calls.

### Failure Semantics

We need to define what happens when a workflow step fails, when a model cannot be
restored, or when duplicate signals arrive.
