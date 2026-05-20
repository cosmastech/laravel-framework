<?php

namespace Illuminate\Tests\Workflows\Fixtures;

use Illuminate\Database\Eloquent\Model;

final class ReturnModel extends Model
{
    protected $connection = 'testing';

    protected $table = 'returns';

    protected $keyType = 'string';
}
