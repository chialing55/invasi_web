<?php

namespace Tests\Unit;

use App\Support\HabitatCompletionThreshold;
use PHPUnit\Framework\TestCase;

class HabitatCompletionThresholdTest extends TestCase
{
    public function test_default_threshold_is_five(): void
    {
        $this->assertSame(5, HabitatCompletionThreshold::requiredCount('01', []));
    }

    public function test_valid_exception_becomes_the_threshold(): void
    {
        $this->assertSame(3, HabitatCompletionThreshold::requiredCount('01', ['01' => 3]));
    }

    public function test_invalid_exception_values_fall_back_to_five(): void
    {
        $this->assertSame(5, HabitatCompletionThreshold::requiredCount('01', ['01' => 0]));
        $this->assertSame(5, HabitatCompletionThreshold::requiredCount('01', ['01' => 5]));
        $this->assertSame(5, HabitatCompletionThreshold::requiredCount('01', ['01' => 9]));
    }
}
