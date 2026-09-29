<?php

namespace Tests\Unit;

use App\Livewire\EntryEntry;
use App\Models\PlotHab;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

class EntryHabitatSelectionTest extends TestCase
{
    public function test_same_selection_preserves_all_existing_rows(): void
    {
        $records = collect([
            $this->habitat(1, '08'),
            $this->habitat(2, '88'),
        ]);

        $this->assertSame([[], []], $this->changes($records, ['08', '88']));
    }

    public function test_only_removed_rows_are_deleted_and_only_new_codes_added(): void
    {
        $records = collect([
            $this->habitat(1, '08'),
            $this->habitat(2, '88'),
            $this->habitat(3, '05'),
        ]);

        $this->assertSame([[3], ['06']], $this->changes($records, ['08', '88', '06']));
    }

    public function test_legacy_numeric_code_can_be_removed_by_row_id(): void
    {
        $this->assertSame([[1], []], $this->changes(
            collect([$this->habitat(1, '8')]),
            []
        ));
    }

    private function changes(Collection $records, array $selected): array
    {
        return (new ReflectionMethod(EntryEntry::class, 'habitatSelectionChanges'))
            ->invoke(new EntryEntry, $records, $selected);
    }

    private function habitat(int $id, string $code): PlotHab
    {
        $record = new PlotHab;
        $record->setRawAttributes(['id' => $id, 'habitat_code' => $code], true);

        return $record;
    }
}
