<?php

namespace Illuminate\Tests\Workflows\Stubs;

use Stringable;

/**
 * Stub for the eventual \Illuminate\Support\Facades\Workflow API.
 *
 * This documents the intended developer surface; the real implementation will
 * coordinate durable storage, replay, signals, queueing, and cancellation.
 */
final class Workflow
{
    /**
     * Start a workflow and run it until it blocks, completes, or fails (inline / HTTP-friendly).
     *
     * @param  class-string  $workflowClass
     */
    public static function start(string $workflowClass, mixed ...$args): WorkflowRun
    {
        return new WorkflowRun(
            new WorkflowRunId('wf_run_started_inline'),
            WorkflowStatus::Waiting,
            null,
        );
    }

    /**
     * Enqueue a new workflow run; returns immediately with a pending run handle.
     *
     * @param  class-string  $workflowClass
     */
    public static function startLater(string $workflowClass, mixed ...$args): WorkflowRun
    {
        return new WorkflowRun(
            new WorkflowRunId('wf_run_enqueued'),
            WorkflowStatus::Pending,
            null,
        );
    }

    /**
     * Deliver a signal and advance the workflow until it blocks, completes, or fails.
     *
     * @param  string|Stringable  $runId
     */
    public static function wake(string|Stringable $runId, string $signal, array $payload = []): WorkflowWakeResult
    {
        $id = self::normalizeRunId($runId);
        $run = new WorkflowRun($id, WorkflowStatus::Waiting, null);

        return new WorkflowWakeResult(
            run: $run,
            response: ['signal' => $signal, 'payload' => $payload],
            completed: false,
            blocked: true,
        );
    }

    /**
     * Enqueue signal delivery and workflow advancement.
     *
     * @param  string|Stringable  $runId
     */
    public static function wakeLater(string|Stringable $runId, string $signal, array $payload = []): void
    {
        //
    }

    /**
     * @param  string|Stringable  $runId
     */
    public static function normalizeRunId(string|Stringable $runId): WorkflowRunId
    {
        $value = $runId instanceof Stringable ? (string) $runId : $runId;

        return new WorkflowRunId($value);
    }
}
