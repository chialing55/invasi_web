<?php

namespace Tests\Unit;

use App\Livewire\EntryEntry;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class EntryOptimisticLockTest extends TestCase
{
    public function test_matching_versions_are_accepted(): void
    {
        $this->assertVersionSet(
            collect([$this->record(1, '2026-09-25 10:00:00')]),
            ['1' => '2026-09-25 10:00:00']
        );

        $this->addToAssertionCount(1);
    }

    public function test_changed_record_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->assertVersionSet(
            collect([$this->record(1, '2026-09-25 10:01:00')]),
            ['1' => '2026-09-25 10:00:00']
        );
    }

    public function test_added_or_deleted_record_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->assertVersionSet(
            collect([
                $this->record(1, '2026-09-25 10:00:00'),
                $this->record(2, '2026-09-25 10:00:00'),
            ]),
            ['1' => '2026-09-25 10:00:00']
        );
    }

    public function test_loaded_version_map_accepts_its_signed_token(): void
    {
        $component = new EntryEntry;
        $component->thisSubPlot = '6000010801';
        $versions = ['2' => '2026-09-25 10:01:00', '1' => '2026-09-25 10:00:00'];
        $token = $this->versionToken($component, 'plants', $versions);

        $this->assertToken($component, 'plants', array_reverse($versions, true), $token);
        $this->addToAssertionCount(1);
    }

    public function test_tampered_version_map_is_rejected(): void
    {
        $component = new EntryEntry;
        $component->thisSubPlot = '6000010801';
        $token = $this->versionToken($component, 'environment', ['1' => '2026-09-25 10:00:00']);

        $this->expectException(ValidationException::class);
        $this->assertToken($component, 'environment', ['1' => '2026-09-25 10:01:00'], $token);
    }

    public function test_token_cannot_be_reused_for_another_subplot(): void
    {
        $component = new EntryEntry;
        $component->thisSubPlot = '6000010801';
        $versions = ['1' => '2026-09-25 10:00:00'];
        $token = $this->versionToken($component, 'plants', $versions);
        $component->thisSubPlot = '6000010802';

        $this->expectException(ValidationException::class);
        $this->assertToken($component, 'plants', $versions, $token);
    }

    private function assertVersionSet(Collection $records, array $versions): void
    {
        $method = new ReflectionMethod(EntryEntry::class, 'assertVersionSet');
        $method->invoke(new EntryEntry, $records, $versions, '測試資料');
    }

    private function versionToken(EntryEntry $component, string $kind, array $versions): string
    {
        return (new ReflectionMethod(EntryEntry::class, 'recordVersionToken'))
            ->invoke($component, $kind, $versions);
    }

    private function assertToken(EntryEntry $component, string $kind, array $versions, string $token): void
    {
        (new ReflectionMethod(EntryEntry::class, 'assertRecordVersionToken'))
            ->invoke($component, $kind, $versions, $token, '測試資料');
    }

    private function record(int $id, string $version): object
    {
        return new class($id, $version)
        {
            public function __construct(public int $id, private string $version) {}

            public function getKey(): int
            {
                return $this->id;
            }

            public function getRawOriginal(string $field): ?string
            {
                return $field === 'updated_at' ? $this->version : null;
            }
        };
    }
}
