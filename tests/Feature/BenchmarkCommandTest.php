<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class BenchmarkCommandTest extends TestCase
{
    protected string $originalDriver;
    protected string $originalDb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalDriver = config('database.default', 'mysql');
        $this->originalDb = config("database.connections.{$this->originalDriver}.database", 'sabapos_test');
    }

    protected function tearDown(): void
    {
        config(['database.default' => $this->originalDriver]);
        config(["database.connections.{$this->originalDriver}.database" => $this->originalDb]);
        DB::purge('mysql');
        DB::purge('sqlite');
        parent::tearDown();
    }

    public function test_benchmark_command_refuses_to_run_on_sqlite_driver(): void
    {
        DB::purge();
        config(['database.default' => 'sqlite']);

        $exitCode = Artisan::call('pos:benchmark');
        $output = Artisan::output();

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('SAFETY ERROR', $output);
        $this->assertStringContainsString('Benchmark must be executed on MySQL', $output);
    }

    public function test_benchmark_command_refuses_to_run_on_non_benchmark_database_name(): void
    {
        DB::purge();
        config(['database.default' => 'mysql']);
        config(['database.connections.mysql.database' => 'production_db']);

        $exitCode = Artisan::call('pos:benchmark');
        $output = Artisan::output();

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('SAFETY ERROR', $output);
        $this->assertStringContainsString('Database safety violation', $output);
    }
}
