<?php

namespace Illuminate\Tests\Foundation\Testing;

use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as FoundationTestCase;
use Illuminate\Foundation\Testing\WithSharedApplication;
use Mockery as m;
use Orchestra\Testbench\Concerns\CreatesApplication;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

class SharedApplicationTestCase extends FoundationTestCase
{
    use CreatesApplication;
    use WithSharedApplication;
}

class SharedApplicationWithTransactionsTestCase extends FoundationTestCase
{
    use CreatesApplication;
    use DatabaseTransactions;
    use WithSharedApplication;
}

class StockTransactionsTestCase extends FoundationTestCase
{
    use CreatesApplication;
    use DatabaseTransactions;
}

class WithSharedApplicationTest extends TestCase
{
    protected function tearDown(): void
    {
        SharedApplicationTestCase::tearDownAfterClass();
        SharedApplicationWithTransactionsTestCase::tearDownAfterClass();
        StockTransactionsTestCase::tearDownAfterClass();

        Container::setInstance();

        m::close();

        parent::tearDown();
    }

    public function test_application_is_created_once_and_reused_between_tests(): void
    {
        $first = new SharedApplicationTestCase('test_one');
        $this->setUpTestCase($first);
        $app = $this->app($first);
        $this->tearDownTestCase($first);

        $this->assertNull($this->app($first));

        $second = new SharedApplicationTestCase('test_two');
        $this->setUpTestCase($second);

        $this->assertSame($app, $this->app($second));
        $this->assertTrue($app->bound('events'));

        $this->tearDownTestCase($second);
    }

    public function test_configuration_is_restored_between_tests(): void
    {
        $first = new SharedApplicationTestCase('test_one');
        $this->setUpTestCase($first);
        $original = $this->app($first)['config']->get('app.name');
        $this->app($first)['config']->set('app.name', 'Mutated');
        $this->tearDownTestCase($first);

        $second = new SharedApplicationTestCase('test_two');
        $this->setUpTestCase($second);

        $this->assertSame($original, $this->app($second)['config']->get('app.name'));

        $this->tearDownTestCase($second);
    }

    public function test_request_scoped_instances_are_re_resolved_between_tests(): void
    {
        $first = new SharedApplicationTestCase('test_one');
        $this->setUpTestCase($first);
        $session = $this->app($first)['session.store'];
        $session->put('shared-key', 'shared-value');
        $this->tearDownTestCase($first);

        $second = new SharedApplicationTestCase('test_two');
        $this->setUpTestCase($second);

        $this->assertNotSame($session, $this->app($second)['session.store']);
        $this->assertNull($this->app($second)['session.store']->get('shared-key'));

        $this->tearDownTestCase($second);
    }

    public function test_global_container_instance_points_at_shared_application(): void
    {
        $first = new SharedApplicationTestCase('test_one');
        $this->setUpTestCase($first);
        $app = $this->app($first);
        $this->tearDownTestCase($first);

        Container::setInstance(new Container);

        $second = new SharedApplicationTestCase('test_two');
        $this->setUpTestCase($second);

        $this->assertSame($app, Container::getInstance());

        $this->tearDownTestCase($second);
    }

    public function test_shared_application_is_flushed_after_the_class_finishes(): void
    {
        $first = new SharedApplicationTestCase('test_one');
        $this->setUpTestCase($first);
        $app = $this->app($first);
        $this->tearDownTestCase($first);

        SharedApplicationTestCase::tearDownAfterClass();

        $this->assertFalse($app->bound('events'));

        $second = new SharedApplicationTestCase('test_two');
        $this->setUpTestCase($second);

        $this->assertNotSame($app, $this->app($second));

        $this->tearDownTestCase($second);
    }

    public function test_database_transactions_disconnect_after_rollback_by_default(): void
    {
        $testCase = new StockTransactionsTestCase('test_one');

        $this->setUpTestCaseWithConnection($testCase, $this->mockConnection($disconnect = true));
        $this->tearDownTestCase($testCase);
    }

    public function test_shared_application_does_not_disconnect_after_rollback(): void
    {
        $testCase = new SharedApplicationWithTransactionsTestCase('test_one');

        $this->setUpTestCaseWithConnection($testCase, $this->mockConnection($disconnect = false));
        $this->tearDownTestCase($testCase);
    }

    protected function setUpTestCaseWithConnection(FoundationTestCase $testCase, Connection $connection): void
    {
        (new ReflectionMethod($testCase, 'refreshApplication'))->invoke($testCase);

        $database = m::mock(DatabaseManager::class);
        $database->shouldReceive('connection')->with(null)->andReturn($connection);

        $this->app($testCase)->instance('db', $database);

        (new ReflectionMethod($testCase, 'setUpTheTestEnvironment'))->invoke($testCase);
    }

    protected function mockConnection(bool $disconnect): Connection
    {
        $dispatcher = new Dispatcher(new Container);

        $connection = m::mock(Connection::class);
        $connection->shouldReceive('setTransactionManager')->once();
        $connection->shouldReceive('getEventDispatcher')->twice()->andReturn($dispatcher);
        $connection->shouldReceive('unsetEventDispatcher')->twice();
        $connection->shouldReceive('setEventDispatcher')->twice();
        $connection->shouldReceive('beginTransaction')->once();
        $connection->shouldReceive('rollBack')->once();
        $connection->shouldReceive('disconnect')->{$disconnect ? 'once' : 'never'}();

        return $connection;
    }

    protected function setUpTestCase(FoundationTestCase $testCase): void
    {
        (new ReflectionMethod($testCase, 'setUpTheTestEnvironment'))->invoke($testCase);
    }

    protected function tearDownTestCase(FoundationTestCase $testCase): void
    {
        (new ReflectionMethod($testCase, 'tearDownTheTestEnvironment'))->invoke($testCase);
    }

    protected function app(FoundationTestCase $testCase)
    {
        return (new ReflectionProperty($testCase, 'app'))->getValue($testCase);
    }
}
