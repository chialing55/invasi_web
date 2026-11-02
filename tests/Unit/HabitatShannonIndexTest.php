<?php

namespace Tests\Unit;

use App\Support\HabitatShannonIndex;
use PHPUnit\Framework\TestCase;

class HabitatShannonIndexTest extends TestCase
{
    public function test_merging_combined_habitats_preserves_numeric_habitat_codes(): void
    {
        $metrics = HabitatShannonIndex::mergeHabitatMetricMaps(
            ['09' => 40.98, 10 => 12.34, 11 => 23.45],
            ['combined-08' => 13.51]
        );

        $this->assertSame(40.98, $metrics['09']);
        $this->assertSame(12.34, $metrics[10]);
        $this->assertSame(23.45, $metrics[11]);
        $this->assertSame(13.51, $metrics['combined-08']);
    }
}
