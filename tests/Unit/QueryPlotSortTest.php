<?php

namespace Tests\Unit;

use App\Livewire\QueryPlot;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QueryPlotSortTest extends TestCase
{
    public function test_coverage_is_sorted_by_numeric_value(): void
    {
        $component = new QueryPlot;
        $component->plotplantList = [
            ['cov2025' => '9.00', 'cov2025_sort' => 9.0],
            ['cov2025' => '80.00', 'cov2025_sort' => 80.0],
        ];

        $component->sortBy('cov2025');

        $this->assertSame(['9.00', '80.00'], collect($component->plotplantList)->pluck('cov2025')->all());
    }

    public function test_unknown_sort_field_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        (new QueryPlot)->sortBy('not-a-column');
    }
}
