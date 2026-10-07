<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

class BenchmarkCommandTest extends TestCase
{
    public function test_benchmark_command_refuses_to_run_on_sqlite_or_non_benchmark_database(): void
    {
        $exitCode = Artisan::call('pos:benchmark');
        $output = Artisan::output();

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('SAFETY ERROR', $output);
        $this->assertStringContainsString('Benchmark must be executed on MySQL', $output);
    }
}
