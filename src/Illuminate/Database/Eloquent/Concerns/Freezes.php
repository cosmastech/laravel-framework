<?php

namespace Illuminate\Database\Eloquent\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\FrozenModelException;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TFrozen of bool = false
 */
trait Freezes
{
    /**
     * Indicates if the model is frozen.
     *
     * @var TFrozen
     */
    protected bool $frozen = false;

    /**
     * Indicates if all models should be frozen in the current context.
     *
     * @var bool
     */
    protected static bool $frozenContext = false;

    /**
     * Execute a callback within a frozen context.
     * All models retrieved within the callback will be automatically frozen.
     *
     * @param  \Closure  $callback
     * @return mixed
     */
    public static function frozen(Closure $callback): mixed
    {
        $originalContext = self::$frozenContext;
        self::$frozenContext = true;

        try {
            return $callback();
        } finally {
            self::$frozenContext = $originalContext;
        }
    }

    /**
     * Determine if currently in a frozen context.
     *
     * @return bool
     */
    public static function isInFrozenContext(): bool
    {
        return self::$frozenContext;
    }

    /**
     * Freeze the model, preventing mutations and lazy loading.
     *
     * @template T of bool = true
     * @return self<T>
     *
     * @phpstan-self-out self<T>
     */
    public function freeze(bool $isFrozen = true): static
    {
        $this->frozen = $isFrozen;

        $isFrozen ? $this->freezeRelations() : $this->unfreezeRelations();

        return $this;
    }

    /**
     * Unfreeze the model, allowing mutations and lazy loading.
     *
     * @return self<false>
     * @phpstan-self-out self<false>
     */
    public function unfreeze(): static
    {
        $this->frozen = false;

        $this->unfreezeRelations();

        return $this;
    }

    /**
     * Determine if the model is frozen.
     *
     * @return bool
     */
    public function isFrozen(): bool
    {
        return $this->frozen;
    }

    /**
     * Throw an exception if the model is frozen.
     *
     * @param  string  $operation
     * @return void
     *
     * @throws \Illuminate\Database\Eloquent\FrozenModelException
     */
    protected function throwIfFrozen(string $operation): void
    {
        if ($this->frozen || self::$frozenContext) {
            throw new FrozenModelException($this, $operation);
        }
    }

    /**
     * Freeze all loaded relations.
     *
     * @return void
     */
    protected function freezeRelations(): void
    {
        foreach ($this->relations as $relation) {
            if ($relation instanceof Model) {
                $relation->freeze();
            } elseif ($relation instanceof EloquentCollection) {
                $relation->each->freeze();
            }
        }
    }

    /**
     * Unfreeze all loaded relations.
     *
     * @return void
     */
    protected function unfreezeRelations(): void
    {
        foreach ($this->relations as $relation) {
            if ($relation instanceof Model) {
                $relation->unfreeze();
            } elseif ($relation instanceof EloquentCollection) {
                $relation->each->unfreeze();
            }
        }
    }
}
