<?php

namespace Illuminate\Tests\Workflows\Attributes;

use Attribute;

/**
 * Marks a class as a workflow definition.
 *
 * The class-level attribute configures defaults for every run of this workflow
 * definition. Per-step attributes and per-call options can still override these
 * defaults for individual pieces of work.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Workflow
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public ?string $name = null,
        public ?int $defaultTries = null,
        public array $options = [],
    ) {
    }
}
