<?php

namespace Tests\Feature;

use Tests\TestCase;

class TestDatabaseConfigurationTest extends TestCase
{
    public function test_effective_test_database_configuration_is_allowed(): void
    {
        $name = config('database.default');
        $connection = config('database.connections.'.$name);

        $this->assertSame('mysql', $connection['driver']);
        $this->assertSame('mysql', $connection['host']);
        $this->assertSame('3306', (string) $connection['port']);
        $this->assertSame('testing', $connection['database']);
        $this->assertSame('attendance_test', $connection['username']);
    }
}
