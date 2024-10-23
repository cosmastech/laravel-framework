<?php

namespace Illuminate\Database\Console\Seeds;

use Illuminate\Support\Facades\Schema;

trait WithoutForeignKeyConstraints
{
    /**
     * Prevent model events from being dispatched by the given callback.
     *
     * @param  callable  $callback
     * @return callable
     */
    public function withoutForeignKeyConstraints(callable $callback)
    {
        return fn () => Schema::withoutForeignKeyConstraints($callback);
    }
}
