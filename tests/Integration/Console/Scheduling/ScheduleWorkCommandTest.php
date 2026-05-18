<?php

namespace Illuminate\Tests\Integration\Console\Scheduling;

use Illuminate\Console\Events\ScheduleWorkLooping;
use Illuminate\Console\Scheduling\ScheduleWorkCommand;
use Illuminate\Support\Carbon;
use Orchestra\Testbench\TestCase;
use ReflectionMethod;

class ScheduleWorkCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_looping_event_can_prevent_iteration_from_running()
    {
        Carbon::setTestNow('2026-03-25 12:00:00');

        $loops = 0;

        $this->app['events']->listen(ScheduleWorkLooping::class, function () use (&$loops) {
            $loops++;

            return false;
        });

        $command = new ScheduleWorkCommand;
        $executions = [];
        $lastExecutionStartedAt = Carbon::now()->subMinutes(10);

        $reflection = new ReflectionMethod($command, 'runLoopIteration');
        $reflection->setAccessible(true);

        $lastExecutionStartedAtAfter = $reflection->invokeArgs($command, [
            $this->app['events'],
            'php -r "exit;"',
            $lastExecutionStartedAt,
            &$executions,
        ]);

        $this->assertSame(1, $loops);
        $this->assertSame($lastExecutionStartedAt, $lastExecutionStartedAtAfter);
        $this->assertEmpty($executions);
    }
}
