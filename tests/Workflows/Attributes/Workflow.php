<?php

namespace Illuminate\Tests\Workflows\Attributes;

use Attribute;

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
