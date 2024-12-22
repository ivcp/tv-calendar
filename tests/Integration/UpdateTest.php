<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class UpdateTest extends TestCase
{

    public function setUp(): void
    {
        exec('./bin/doctrine migrations:migrate --no-interaction', $_, $result);
        if ($result > 0) {
            exit('runing migrations failed' . PHP_EOL);
        }
    }

    public function test_update(): void
    {
        //code...
    }


    public function tearDown(): void
    {
        exec('./bin/doctrine orm:schema-tool:drop --full-database --force', $_, $result);
        if ($result > 0) {
            exit('db teardown failed' . PHP_EOL);
        }
    }
}
