<?php

namespace Illuminate\Foundation\Http\Concerns;

use BackedEnum;
use Illuminate\Foundation\Http\Attributes\HydrateFromRequest;
use Illuminate\Foundation\Http\Attributes\WithoutInferringRules;
use Illuminate\Foundation\Http\TypedFormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use stdClass;

trait InfersValidationRules
{
    /**
     * The cached HydrateFromRequest attribute checks.
     *
     * @var array<class-string, bool>
     */
    protected array $hydrateFromRequestCache = [];

    /**
     * Infer validation rules from constructor parameter types.
     *
     * @return array<string, mixed>
     *
     * @throws \ReflectionException
     */
    protected function inferredRulesFromTypes(): array
    {
        if (($constructor = $this->reflectRequest()->getConstructor()) === null || $this->reflectRequest()->getAttributes(WithoutInferringRules::class) !== []) {
            return [];
        }

        $rules = [];

        foreach ($constructor->getParameters() as $param) {
            if ($param->getAttributes(WithoutInferringRules::class) !== []) {
                continue;
            }

            $fieldName = $this->fieldNameFor($param);
            $type = $param->getType();

            if ($type instanceof ReflectionNamedType && ($caster = $this->hasCaster($type))) {
                $this->mergeCasterRules($rules, $caster->rules($fieldName), $fieldName, $param);

                continue;
            }

            $paramRules = $this->rulesForParameter($param);

            if ($paramRules !== []) {
                $rules[$fieldName] = $paramRules;
            }
        }

        return $rules;
    }

    /**
     * Infer validation rules for the given constructor parameter.
     *
     * @return list<string|\Illuminate\Contracts\Validation\Rule|\Illuminate\Contracts\Validation\ValidatorAwareRule>
     */
    protected function rulesForParameter(ReflectionParameter $param): array
    {
        $type = $param->getType();

        if (! $type instanceof ReflectionType) {
            return [];
        }

        $rules = $this->baseRulesForParameter($param);

        if ($param->getAttributes(HydrateFromRequest::class) !== []) {
            $typeRule = 'array';
        } else {
            $typeRule = $type instanceof ReflectionUnionType
                ? $this->ruleForUnionType($type)
                : ($type instanceof ReflectionNamedType ? $this->ruleForNamedType($type) : null);
        }

        if ($typeRule !== null) {
            $rules[] = $typeRule;
        }

        return $rules;
    }

    /**
     * Get the base validation rules (required/nullable/sometimes) for a parameter.
     *
     * @return list<string>
     */
    protected function baseRulesForParameter(ReflectionParameter $param): array
    {
        $type = $param->getType();

        if (! $type instanceof ReflectionType) {
            return [];
        }

        $rules = [];

        if ($param->isDefaultValueAvailable()) {
            $rules[] = 'sometimes';
        } elseif ($type->allowsNull()) {
            $rules[] = 'present';
        } else {
            $rules[] = 'required';
        }

        if ($type->allowsNull()) {
            $rules[] = 'nullable';
        }

        return $rules;
    }

    /**
     * Merge caster-provided rules into the rules array, combining with base parameter rules.
     *
     * @param  array<string, mixed>  $rules
     */
    protected function mergeCasterRules(array &$rules, mixed $casterRules, string $fieldName, ReflectionParameter $param): void
    {
        $baseRules = $this->baseRulesForParameter($param);
        $normalized = $this->normalizeCasterRules($casterRules, $fieldName);

        // Merge base rules (required/nullable/sometimes) with the primary field's caster rules.
        $primaryRules = $normalized[$fieldName] ?? [];
        unset($normalized[$fieldName]);

        $merged = array_merge($baseRules, $primaryRules);

        if ($merged !== []) {
            $rules[$fieldName] = $merged;
        }

        // Merge any sibling field rules the caster declared.
        foreach ($normalized as $field => $fieldRules) {
            $rules[$field] = array_merge($rules[$field] ?? [], $fieldRules);
        }
    }

    /**
     * Normalize caster rules into a keyed array of [field => [rules]].
     *
     * @return array<string, list<mixed>>
     */
    protected function normalizeCasterRules(mixed $casterRules, string $fieldName): array
    {
        if ($casterRules === null) {
            return [];
        }

        if (is_string($casterRules)) {
            return [$fieldName => explode('|', $casterRules)];
        }

        if (! is_array($casterRules)) {
            return [$fieldName => [$casterRules]];
        }

        if (Arr::isAssoc($casterRules)) {
            return array_map(
                fn ($r) => is_string($r) ? explode('|', $r) : Arr::wrap($r),
                $casterRules,
            );
        }

        return [$fieldName => $casterRules];
    }

    /**
     * Infer a validation rule for a named type.
     *
     * @return string|\Illuminate\Contracts\Validation\ValidatorAwareRule|\Illuminate\Contracts\Validation\Rule|null
     */
    protected function ruleForNamedType(ReflectionNamedType $type): mixed
    {
        $name = $type->getName();

        if ($type->isBuiltin()) {
            return match ($name) {
                'int' => 'integer',
                'float' => 'numeric',
                'string' => 'string',
                'bool' => 'boolean',
                'true' => 'accepted',
                'false' => 'declined',
                'array', 'object', 'iterable' => 'array',
                default => null,
            };
        }

        return $this->ruleForNonBuiltinType($type);
    }

    /**
     * Infer a validation rule for a union type.
     *
     * @return \Illuminate\Contracts\Validation\ValidatorAwareRule|\Illuminate\Contracts\Validation\Rule|null
     */
    protected function ruleForUnionType(ReflectionUnionType $type): mixed
    {
        $branches = [];

        foreach ($type->getTypes() as $named) {
            if ($named->getName() === 'null') {
                continue;
            }

            $branchRule = $this->ruleForNamedType($named);

            if ($branchRule === null) {
                return null;
            }

            $branches[] = [$branchRule];
        }

        if ($branches === []) {
            return null;
        }

        return Rule::anyOf($branches);
    }

    /**
     * Infer a validation rule for a non-builtin named type.
     *
     * @return string|\Illuminate\Contracts\Validation\ValidatorAwareRule|\Illuminate\Contracts\Validation\Rule|null
     */
    protected function ruleForNonBuiltinType(ReflectionNamedType $type): mixed
    {
        $name = $type->getName();

        if ($this->shouldHydrateFromRequest($name)) {
            return 'array';
        }

        if (is_subclass_of($name, BackedEnum::class)) {
            return new Enum($name);
        }

        if ($this->isDateObjectType($name)) {
            return 'date';
        }

        if (is_subclass_of($name, TypedFormRequest::class) || is_a($name, Collection::class, true) || is_a($name, stdClass::class, true)) {
            return 'array';
        }

        if ($this->isFile($name)) {
            return 'file';
        }

        return null;
    }

    /**
     * Determine if the parameter needs an UploadedFile instance.
     *
     * @return bool
     */
    protected function isFile(string $name): bool
    {
        return is_a($name, UploadedFile::class, true);
    }

    /**
     * Determine if a given class should be hydrated from request data because
     * it has the HydrateFromRequest applied at the class-level.
     *
     * @param  class-string  $class
     */
    protected function shouldHydrateFromRequest(string $class): bool
    {
        if (isset($this->hydrateFromRequestCache[$class])) {
            return $this->hydrateFromRequestCache[$class];
        }

        $reflection = new ReflectionClass($class);

        return $this->hydrateFromRequestCache[$class] = $reflection->getAttributes(HydrateFromRequest::class) !== [];
    }
}
