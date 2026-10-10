<?php

namespace Tests\Feature;

use Tests\TestCase;

class CiProofFailingTest extends TestCase
{
    public function test_deliberate_ci_failure_proof(): void
    {
        $this->assertTrue(false, 'DELIBERATE CI FAILURE PROOF FOR GATE SECTION A');
    }
}
