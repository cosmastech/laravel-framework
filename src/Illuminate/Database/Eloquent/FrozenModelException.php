<?php

namespace Illuminate\Database\Eloquent;

use RuntimeException;

class FrozenModelException extends RuntimeException
{
    public Model $model;
    public string $operation;

    public function __construct(Model $model, string $operation)
    {
        $this->model = $model;
        $this->operation = $operation;

        parent::__construct(
            "Cannot perform {$operation} on frozen model [{$model::class}]"
        );
    }
}
