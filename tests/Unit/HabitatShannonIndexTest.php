<?php

namespace Tests\Unit;

use App\Support\HabitatCode;
use PHPUnit\Framework\TestCase;

class HabitatShannonIndexTest extends TestCase
{
    public function test_metric_keys_are_stable_for_one_and_two_digit_habitat_codes(): void
    {
        $metrics = [
            HabitatCode::mapKey('09') => 40.98,
            HabitatCode::mapKey(10) => 12.34,
            HabitatCode::mapKey('11') => 23.45,
            HabitatCode::mapKey('combined-08') => 13.51,
        ];

        $this->assertSame(40.98, $metrics[HabitatCode::mapKey('09')]);
        $this->assertSame(12.34, $metrics[HabitatCode::mapKey('10')]);
        $this->assertSame(23.45, $metrics[HabitatCode::mapKey(11)]);
        $this->assertSame(13.51, $metrics[HabitatCode::mapKey('combined-08')]);
    }
}
