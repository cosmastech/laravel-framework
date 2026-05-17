<?php

namespace Illuminate\Tests\Workflows\Fixtures;

/**
 * Example constructor dependency: resolved from the container when the workflow class is instantiated.
 */
final class ReturnLookup
{
    public function keyFor(string $returnId): string
    {
        return 'lookup:'.$returnId;
    }
}
