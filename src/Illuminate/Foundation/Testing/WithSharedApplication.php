<?php

namespace Illuminate\Foundation\Testing;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;

/**
 * Boot the application once per test class instead of once per test.
 *
 * The first test in the class creates the application normally; subsequent
 * tests reuse the same instance, skipping the per-test framework boot. The
 * framework's regular teardown still runs between tests (transaction
 * rollbacks, facade clearing, static state resets); only the application
 * itself is kept alive. It is flushed once the class has finished running.
 *
 * Isolation between tests is preserved via database transactions, a
 * configuration snapshot, and re-resolving request-scoped container
 * instances. Tests using this trait should therefore not permanently
 * mutate the container or event dispatcher (e.g. swapping instances or
 * registering listeners) without restoring them.
 *
 * When this trait is applied to a shared base test case, a single
 * application instance is shared by every extending test class.
 */
trait WithSharedApplication
{
    /**
     * The application instance shared between tests.
     *
     * @var \Illuminate\Foundation\Application|null
     */
    protected static $sharedApplication;

    /**
     * The configuration captured when the shared application was created.
     *
     * @var array<string, mixed>
     */
    protected static array $sharedApplicationConfig = [];

    /**
     * Indicates if database connections should be disconnected after the
     * test's transactions have been rolled back.
     *
     * Since the shared application's connections persist between tests,
     * disconnecting would only force a reconnect on the next test.
     *
     * @var bool
     */
    protected $disconnectAfterRollback = false;

    /**
     * Refresh the application instance.
     *
     * @return void
     */
    protected function refreshApplication()
    {
        if (! static::$sharedApplication) {
            $this->app = static::$sharedApplication = $this->createApplication();

            static::$sharedApplicationConfig = $this->app['config']->all();

            return;
        }

        $this->app = static::$sharedApplication;

        Container::setInstance($this->app);
        Facade::setFacadeApplication($this->app);

        $this->app['config']->set(static::$sharedApplicationConfig);

        foreach ($this->requestScopedInstances() as $abstract) {
            $this->app->forgetInstance($abstract);
        }
    }

    /**
     * The container instances holding request or test specific state that
     * should be re-resolved for each test.
     *
     * @return array<int, string>
     */
    protected function requestScopedInstances(): array
    {
        return ['auth', 'cookie', 'session', 'session.store', 'url', 'view'];
    }

    /**
     * Destroy the application instance.
     *
     * The shared application is only detached from this test instance. It is
     * kept alive for the remaining tests in the class and flushed once the
     * class has finished running.
     *
     * @return void
     */
    protected function destroyApplication(): void
    {
        $this->app = null;
    }

    /**
     * Clean up the testing environment before the next test case.
     *
     * @return void
     */
    public static function tearDownAfterClass(): void
    {
        if (static::$sharedApplication) {
            static::$sharedApplication->flush();

            static::$sharedApplication = null;
            static::$sharedApplicationConfig = [];
        }

        parent::tearDownAfterClass();
    }
}
