<?php

namespace Tests\Unit;

use App\Console\Commands\SeedDemoDataset;
use PHPUnit\Framework\TestCase;

class DemoDatasetDistributionTest extends TestCase
{
    public function test_demo_dataset_contains_exactly_five_thousand_rows(): void
    {
        $this->assertSame(5000, array_sum(SeedDemoDataset::RECORD_COUNTS));
    }
}
