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

    public function test_missing_plot_year_defaults_to_current_year(): void
    {
        $plot = new PlotList2025;
        $plot->setRawAttributes(['census_year' => null]);

        $year = (new ReflectionMethod(EntryEntry::class, 'plotFormYear'))
            ->invoke(new EntryEntry, $plot);

        $this->assertSame(date('Y'), $year);
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

    public function test_matching_plot_file_version_is_accepted(): void
    {
        $component = new EntryEntry;
        $plot = $this->plot(1, '600001', '2025', '2026-10-02 10:00:00');
        $this->setFileVersion($component, $plot);

        $this->assertFileState($component, $plot);
        $this->addToAssertionCount(1);
    }

    public function test_changed_plot_file_version_is_rejected(): void
    {
        $component = new EntryEntry;
        $this->setFileVersion($component, $this->plot(1, '600001', '2025', '2026-10-02 10:00:00'));

        $this->expectException(ValidationException::class);
        $this->assertFileState(
            $component,
            $this->plot(1, '600001', '2025', '2026-10-02 10:01:00')
        );
    }

    public function test_plot_file_version_refresh_keeps_current_selection(): void
    {
        $component = new EntryEntry;
        $component->thisPlot = '600001';
        $component->thisSubPlot = '6000010801';

        $this->setFileVersion(
            $component,
            $this->plot(1, '600001', '2025', '2026-10-02 10:01:00')
        );

        $this->assertSame('600001', $component->thisPlot);
        $this->assertSame('6000010801', $component->thisSubPlot);
        $this->assertSame('2026-10-02 10:01:00', $component->loadedPlotFileUploadedAt);
    }

    private function plot(int $id, string $plot, string $year, ?string $fileUploadedAt = null): PlotList2025
    {
        $record = new PlotList2025;
        $record->setRawAttributes([
            'id' => $id,
            'plot' => $plot,
            'census_year' => $year,
            'file_uploaded_at' => $fileUploadedAt,
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

    private function setFileVersion(EntryEntry $component, PlotList2025 $plot): void
    {
        (new ReflectionMethod(EntryEntry::class, 'setPlotFileVersion'))->invoke($component, $plot);
    }

    private function assertFileState(EntryEntry $component, PlotList2025 $plot): void
    {
        (new ReflectionMethod(EntryEntry::class, 'assertPlotFileState'))->invoke($component, $plot);
    }
}
