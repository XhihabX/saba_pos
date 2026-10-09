<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

class BenchmarkCommandTest extends TestCase
{
    public function test_benchmark_command_refuses_to_run_on_sqlite_driver(): void
    {
        config(['database.default' => 'sqlite']);

        $exitCode = Artisan::call('pos:benchmark');
        $output = Artisan::output();

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('SAFETY ERROR', $output);
        $this->assertStringContainsString('Benchmark must be executed on MySQL', $output);
    }

    public function test_benchmark_command_refuses_to_run_on_non_benchmark_database_name(): void
    {
        config(['database.default' => 'mysql']);
        config(['database.connections.mysql.database' => 'production_db']);

        $exitCode = Artisan::call('pos:benchmark');
        $output = Artisan::output();

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('SAFETY ERROR', $output);
        $this->assertStringContainsString('Database safety violation', $output);
    }
}
