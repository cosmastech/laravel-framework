<?php

namespace Illuminate\Foundation\Bus\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class OnConnection
{
    /**
     * @param  string|\BackedEnum $connection
     * @return void
     */
    public function __construct(public $connection)
    {
    }
}
