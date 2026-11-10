<?php

namespace Tests\Feature;

use Tests\TestCase;

class DemoDatasetCommandTest extends TestCase
{
    public function test_demo_dataset_cannot_be_seeded_into_the_test_database(): void
    {
        $this->artisan('pms:seed-demo-dataset', ['profile' => 'padmatech'])
            ->expectsOutputToContain('This profile must run against payroll_pms')
            ->assertExitCode(1);
    }
}
