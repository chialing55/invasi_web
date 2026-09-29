<?php

namespace Tests\Unit;

use App\Livewire\EntryMissingnote;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class MissingReasonOptimisticLockTest extends TestCase
{
    public function test_matching_row_ids_and_versions_are_accepted(): void
    {
        $this->assertVersions(
            collect([
                $this->record(1, '2026-09-25 10:00:00'),
                $this->record(2, '2026-09-25 10:01:00'),
            ]),
            [
                ['id' => 2, 'updated_at' => '2026-09-25 10:01:00'],
                ['id' => 1, 'updated_at' => '2026-09-25 10:00:00'],
            ]
        );

        $this->addToAssertionCount(1);
    }

    public function test_changed_version_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->assertVersions(
            collect([$this->record(1, '2026-09-25 10:01:00')]),
            [['id' => 1, 'updated_at' => '2026-09-25 10:00:00']]
        );
    }

    public function test_added_or_removed_row_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->assertVersions(
            collect([
                $this->record(1, '2026-09-25 10:00:00'),
                $this->record(2, '2026-09-25 10:00:00'),
            ]),
            [['id' => 1, 'updated_at' => '2026-09-25 10:00:00']]
        );
    }

    public function test_duplicate_row_id_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->assertVersions(
            collect([$this->record(1, '2026-09-25 10:00:00')]),
            [
                ['id' => 1, 'updated_at' => '2026-09-25 10:00:00'],
                ['id' => 1, 'updated_at' => '2026-09-25 10:00:00'],
            ]
        );
    }

    private function assertVersions(Collection $records, array $submittedRows): void
    {
        $method = new ReflectionMethod(EntryMissingnote::class, 'assertReasonFormVersions');
        $method->invoke(new EntryMissingnote, $records, $submittedRows);
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
