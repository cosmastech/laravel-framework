<?php

namespace Illuminate\Support;

class ClassUsesRecursiveStore
{
    /**
     * @var array<class-string, list<class-string>>
     */
    protected static array $classToUsesMap = [];

    /**
     * @var array<class-string, list<class-string>>
     */
    protected static array $traitUsesMap = [];

    public static function flush()
    {
        static::setClassUsesMap([]);
        static::setTraitUsesMap([]);
    }

    /**
     * @param  array<class-string, list<class-string>>  $classUses
     * @return void
     */
    public static function setClassUsesMap(array $classUses)
    {
        static::$classToUsesMap = $classUses;
    }

    /**
     * @param  array<class-string, list<class-string>>  $traitUses
     * @return void
     */
    public static function setTraitUsesMap(array $traitUses)
    {
        static::$traitUsesMap = $traitUses;
    }

    /**
     * Returns all traits used by a class, its parent classes and trait of their traits.
     *
     * @param  object|class-string  $class
     * @return list<class-string>
     */
    public static function getClassUsesRecursive($class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        if (isset(static::$classToUsesMap[$class])) {
            return static::$classToUsesMap[$class];
        }

        $results = [];

        foreach (array_reverse(class_parents($class) ?: []) + [$class => $class] as $targetClass) {
            $results += static::getTraitUsesRecursive($targetClass);
        }

        return static::$classToUsesMap[$class] = array_values(array_unique($results));
    }

    public static function classUses($target, ...$classes): bool
    {
        foreach($classes as $findClass) {
            if (in_array($findClass, self::$classToUsesMap[$target])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  class-string|object  $trait
     * @return list<class-string>
     */
    public static function getTraitUsesRecursive($trait)
    {
        $trait = is_object($trait) ? $trait::class : $trait;

        if (isset(static::$traitUsesMap[$trait])) {
            return static::$traitUsesMap[$trait];
        }

        $traits = class_uses($trait) ?: [];

        foreach ($traits as $childTrait) {
            $traits += static::getTraitUsesRecursive($childTrait);
        }

        return self::$traitUsesMap[$trait] = array_values(array_unique($traits));
    }
}
