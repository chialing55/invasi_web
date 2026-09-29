<?php

namespace Tests\Unit;

use App\Livewire\EntryEntry;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class SubPlotPhotoRenameTest extends TestCase
{
    public function test_wood_and_understory_photos_move_together_and_can_be_rolled_back(): void
    {
        Storage::fake('invasi_files');
        $disk = Storage::disk('invasi_files');
        $component = $this->entryComponent();
        $oldMain = 'subPlotPhoto/臺北市/100001/08/1000010801.jpg';
        $newMain = 'subPlotPhoto/臺北市/100001/09/1000010902.jpg';
        $oldUnderstory = 'subPlotPhoto/臺北市/100001/88/1000018801.jpg';
        $newUnderstory = 'subPlotPhoto/臺北市/100001/99/1000019902.jpg';
        $disk->put($oldMain, 'main-photo');
        $disk->put($oldUnderstory, 'understory-photo');

        $operations = $this->rename($component, '1000010801', '1000010902', '08', '09');

        $disk->assertMissing([$oldMain, $oldUnderstory]);
        $disk->assertExists([$newMain, $newUnderstory]);

        $this->rollback($component, $operations);

        $disk->assertExists([$oldMain, $oldUnderstory]);
        $disk->assertMissing([$newMain, $newUnderstory]);
    }

    public function test_changing_to_wood_creates_understory_photo_copy(): void
    {
        Storage::fake('invasi_files');
        $disk = Storage::disk('invasi_files');
        $component = $this->entryComponent();
        $oldMain = 'subPlotPhoto/臺北市/100001/01/1000010101.png';
        $newMain = 'subPlotPhoto/臺北市/100001/08/1000010802.png';
        $newUnderstory = 'subPlotPhoto/臺北市/100001/88/1000018802.png';
        $disk->put($oldMain, 'photo-content');

        $this->rename($component, '1000010101', '1000010802', '01', '08');

        $disk->assertMissing($oldMain);
        $disk->assertExists([$newMain, $newUnderstory]);
        $this->assertSame('photo-content', $disk->get($newUnderstory));
    }

    public function test_existing_destination_photo_is_never_overwritten(): void
    {
        Storage::fake('invasi_files');
        $disk = Storage::disk('invasi_files');
        $component = $this->entryComponent();
        $old = 'subPlotPhoto/臺北市/100001/01/1000010101.jpg';
        $new = 'subPlotPhoto/臺北市/100001/02/1000010202.jpg';
        $disk->put($old, 'old-photo');
        $disk->put($new, 'existing-photo');

        try {
            $this->rename($component, '1000010101', '1000010202', '01', '02');
            $this->fail('Expected destination collision to be rejected.');
        } catch (ValidationException) {
            $this->assertSame('old-photo', $disk->get($old));
            $this->assertSame('existing-photo', $disk->get($new));
        }
    }

    private function entryComponent(): EntryEntry
    {
        $component = new EntryEntry;
        $component->thisCounty = '臺北市';
        $component->thisPlot = '100001';

        return $component;
    }

    private function rename(
        EntryEntry $component,
        string $oldId,
        string $newId,
        string $oldHabitat,
        string $newHabitat
    ): array {
        $method = new ReflectionMethod(EntryEntry::class, 'movePhotosForSubPlotRename');

        return $method->invoke($component, $oldId, $newId, $oldHabitat, $newHabitat);
    }

    private function rollback(EntryEntry $component, array $operations): void
    {
        $method = new ReflectionMethod(EntryEntry::class, 'rollbackPhotoOperations');
        $method->invoke($component, $operations);
    }
}
