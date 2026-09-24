<?php

namespace Tests\Unit;

use App\Support\PlanYearPlotFilter;
use PHPUnit\Framework\TestCase;

class PlanYearPlotFilterTest extends TestCase
{
    public function test_current_year_is_preferred_when_available(): void
    {
        $this->assertSame('2026', PlanYearPlotFilter::defaultYear(['2027', '2026', '2025'], 2026));
    }

    public function test_latest_available_year_is_used_when_current_year_is_missing(): void
    {
        $this->assertSame('2025', PlanYearPlotFilter::defaultYear(['2025'], 2026));
    }

    public function test_empty_year_list_has_no_default(): void
    {
        $this->assertSame('', PlanYearPlotFilter::defaultYear([], 2026));
    }
}
