<?php

namespace Tests\Unit;

use App\Livewire\EntryEntry;
use App\Models\PlotList2025;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class EntryPlotYearTest extends TestCase
{
    public function test_existing_plot_year_is_used_instead_of_current_calendar_year(): void
    {
        $plot = new PlotList2025;
        $plot->setRawAttributes(['census_year' => 2025]);

        $method = new ReflectionMethod(EntryEntry::class, 'plotFormYear');
        $year = $method->invoke(new EntryEntry, $plot);

        $this->assertSame('2025', $year);
        $this->assertNotSame(date('Y'), $year);
    }

    public function test_matching_loaded_and_database_years_are_accepted(): void
    {
        $component = new EntryEntry;
        $plot = $this->plot(1, '600001', '2025');
        $component->loadedPlotCensusYear = '2025';
        $component->loadedPlotYearToken = $this->token($component, $plot);

        $this->assertYearState($component, $plot);
        $this->addToAssertionCount(1);
    }

    public function test_tampered_loaded_year_is_rejected(): void
    {
        $component = new EntryEntry;
        $plot = $this->plot(1, '600001', '2025');
        $component->loadedPlotCensusYear = '2025';
        $component->loadedPlotYearToken = $this->token($component, $plot);
        $component->loadedPlotCensusYear = '2026';

        $this->expectException(ValidationException::class);
        $this->assertYearState($component, $plot);
    }

    public function test_database_year_change_is_rejected(): void
    {
        $component = new EntryEntry;
        $plot = $this->plot(1, '600001', '2025');
        $component->loadedPlotCensusYear = '2025';
        $component->loadedPlotYearToken = $this->token($component, $plot);

        $this->expectException(ValidationException::class);
        $this->assertYearState($component, $this->plot(1, '600001', '2026'));
    }

    public function test_token_cannot_be_reused_for_another_plot(): void
    {
        $component = new EntryEntry;
        $component->loadedPlotCensusYear = '2025';
        $component->loadedPlotYearToken = $this->token($component, $this->plot(1, '600001', '2025'));

        $this->expectException(ValidationException::class);
        $this->assertYearState($component, $this->plot(2, '600002', '2025'));
    }

    private function plot(int $id, string $plot, string $year): PlotList2025
    {
        $record = new PlotList2025;
        $record->setRawAttributes([
            'id' => $id,
            'plot' => $plot,
            'census_year' => $year,
        ], true);

        return $record;
    }

    private function token(EntryEntry $component, PlotList2025 $plot): string
    {
        return (new ReflectionMethod(EntryEntry::class, 'plotYearToken'))->invoke($component, $plot);
    }

    private function assertYearState(EntryEntry $component, PlotList2025 $plot): void
    {
        (new ReflectionMethod(EntryEntry::class, 'assertPlotYearState'))->invoke($component, $plot);
    }
}
