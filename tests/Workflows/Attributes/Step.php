<?php

namespace Illuminate\Tests\Workflows\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Step
{
    /**
     * @param  ?int  $tries  Explicit attempts for this step, or null to inherit {@see Workflow::$defaultTries} / framework default.
     */
    public function __construct(
        public ?string $name = null,
        public ?int $tries = null,
    ) {
    }
}
