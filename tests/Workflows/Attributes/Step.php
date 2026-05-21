<?php

namespace Illuminate\Tests\Workflows\Attributes;

use Attribute;

/**
 * Marks a workflow method as a durable step.
 *
 * A step name is the durable identity for the work. On replay, the runner can use
 * that name to return a previously recorded result instead of repeating the side
 * effect. Retry configuration belongs here only when it differs from the
 * workflow default.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Step
{
    /**
     * @param  ?int  $tries  Explicit attempts for this step, or null to inherit {@see Workflow::$tries} / framework default.
     */
    public function __construct(
        public ?string $name = null,
        public ?int $tries = null,
    ) {
    }
}
