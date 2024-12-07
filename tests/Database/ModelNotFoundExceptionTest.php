<?php

namespace Database;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ModelNotFoundExceptionTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        ModelNotFoundException::setModelNameResolver(null);
    }

    #[DataProvider('modelDataProvider')]
    public function test_builds_message(
        array|null $ids,
        string|null $modelMapsTo,
        string $expectedMessage
    ): void
    {
        ModelNotFoundException::setModelNameResolver(static fn () => $modelMapsTo);

        $exception = (new ModelNotFoundException())->setModel(ModelNotFoundTestModel::class, $ids);
        $this->assertSame($expectedMessage, $exception->getMessage());
    }

    public static function modelDataProvider(): array
    {
        return [
            'mapped to string, multiple ids' => [[1,2,3], 'model_1', 'No query results for model [model_1] 1, 2, 3'],
            'mapped to string, no ids' => [null, 'model_1', 'No query results for model [model_1] 1, 2, 3']
        ];
    }
}

class ModelNotFoundTestModel extends Model
{
    //
}
