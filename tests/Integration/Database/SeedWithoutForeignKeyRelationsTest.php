<?php

namespace Illuminate\Tests\Integration\Database;

use Illuminate\Database\Console\Seeds\WithoutForeignKeyConstraints;
use Illuminate\Database\Seeder;
use Orchestra\Testbench\TestCase;

class SeedWithoutForeignKeyRelationsTest extends TestCase
{

}


class UserWithoutForeignKeyConstraintsSeeder extends Seeder
{
    use WithoutForeignKeyConstraints;

    public static int $timesCalled = 0;
    public function run()
    {
        self::$timesCalled++;
    }
}
