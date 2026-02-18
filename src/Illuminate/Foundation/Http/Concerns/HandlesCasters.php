<?php

namespace Illuminate\Foundation\Http\Concerns;

use Illuminate\Contracts\Foundation\TypedRequestCaster;
use ReflectionNamedType;

trait HandlesCasters
{
    /**
     * @var array<string, class-string<TypedRequestCaster>|TypedRequestCaster>
     */
    protected static array $casters = [];

    /**
     * @var array<string, TypedRequestCaster>
     */
    protected array $castersCache = [];

    /**
     * @param  array<string, class-string<TypedRequestCaster>|TypedRequestCaster>  $casters
     */
    public static function withCasters(array $casters, bool $merge = true): void
    {
        static::$casters = $merge ? array_merge(static::$casters, $casters) : $casters;
    }

    protected function hasCaster(ReflectionNamedType $type): ?TypedRequestCaster
    {
        $this->prepareCasters($this->mergeCasts());

        // Loop through all the cached casters to see if the requested
        // parameter is the type or is a child of the type.
        foreach ($this->castersCache as $typeName => $typedRequestCaster) {
            if (is_a($type->getName(), $typeName, true)) {
                return $typedRequestCaster;
            }
        }

        return null;
    }

    /**
     * @param  array<string, class-string<TypedRequestCaster>|TypedRequestCaster>  $casters
     * @return void
     */
    protected function prepareCasters(array $casters)
    {
        foreach ($casters as $typeName => $caster) {
            // Here we only build the cache of casters once.
            $this->castersCache[$typeName] ??= $caster instanceof TypedRequestCaster
                ? $caster
                : $this->container->make($caster);
        }
    }

    /**
     * @return array<string, class-string<TypedRequestCaster>|TypedRequestCaster>
     */
    protected function mergeCasts(): array
    {
        return array_merge(
            static::$casters, // @todo add in a request level casts
        );
    }
}
