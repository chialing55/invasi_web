<?php

namespace Tests\Unit;

use App\Support\HabitatShannonIndex;
use PHPUnit\Framework\TestCase;

class HabitatShannonIndexTest extends TestCase
{
    public function test_metric_keys_are_stable_for_one_and_two_digit_habitat_codes(): void
    {
        $metrics = [
            HabitatShannonIndex::habitatMetricKey('09') => 40.98,
            HabitatShannonIndex::habitatMetricKey(10) => 12.34,
            HabitatShannonIndex::habitatMetricKey('11') => 23.45,
            HabitatShannonIndex::habitatMetricKey('combined-08') => 13.51,
        ];

        $this->assertSame(40.98, $metrics[HabitatShannonIndex::habitatMetricKey('09')]);
        $this->assertSame(12.34, $metrics[HabitatShannonIndex::habitatMetricKey('10')]);
        $this->assertSame(23.45, $metrics[HabitatShannonIndex::habitatMetricKey(11)]);
        $this->assertSame(13.51, $metrics[HabitatShannonIndex::habitatMetricKey('combined-08')]);
    }
}
