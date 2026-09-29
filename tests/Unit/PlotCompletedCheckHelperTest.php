<?php

namespace Tests\Unit;

use App\Helpers\PlotCompletedCheckHelper;
use PHPUnit\Framework\TestCase;

class PlotCompletedCheckHelperTest extends TestCase
{
    public function test_zero_or_missing_error_codes_are_valid(): void
    {
        $rows = collect([
            ['data_error' => 0],
            ['data_error' => '0'],
            [],
        ]);

        $this->assertFalse(PlotCompletedCheckHelper::hasPlantDataError($rows));
    }

    public function test_every_non_zero_error_code_is_invalid(): void
    {
        $this->assertTrue(PlotCompletedCheckHelper::hasPlantDataError(collect([
            ['data_error' => 1],
        ])));
        $this->assertTrue(PlotCompletedCheckHelper::hasPlantDataError(collect([
            ['data_error' => 2],
        ])));
        $this->assertTrue(PlotCompletedCheckHelper::hasPlantDataError(collect([
            ['data_error' => '2'],
        ])));
    }
}
