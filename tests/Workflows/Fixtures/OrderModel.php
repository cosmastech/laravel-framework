<?php

namespace Illuminate\Tests\Workflows\Fixtures;

use Illuminate\Database\Eloquent\Model;

final class OrderModel extends Model
{
    protected $connection = 'testing';

    protected $table = 'orders';

    protected $keyType = 'string';
}
