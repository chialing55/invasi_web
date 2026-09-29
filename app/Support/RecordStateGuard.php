<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class RecordStateGuard
{
    /**
     * @param  Collection<int, object>  $records
     * @param  array<int, string>  $fields
     * @return array<int, array<string, mixed>>
     */
    public static function snapshot(Collection $records, array $fields): array
    {
        return $records->map(function ($record) use ($fields) {
            $row = [
                'id' => (string) $record->getKey(),
                'updated_at' => (string) $record->getRawOriginal('updated_at'),
            ];

            foreach ($fields as $field) {
                $row[$field] = $record->getRawOriginal($field);
            }

            return $row;
        })->sortBy('id', SORT_NATURAL)->values()->all();
    }

    public static function token(string $scope, array $state): string
    {
        return hash_hmac('sha256', json_encode([
            'scope' => $scope,
            'state' => $state,
        ], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    public static function assertToken(string $submitted, string $scope, array $state, string $field, string $message): void
    {
        if ($submitted === '' || ! hash_equals(self::token($scope, $state), $submitted)) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    /**
     * Check the complete row set and Eloquent's serialized timestamp, which is
     * the version format delivered to Livewire's table.
     *
     * @param  Collection<int, object>  $records
     * @param  array<int, array<string, mixed>>  $submittedRows
     */
    public static function assertSubmittedVersions(Collection $records, array $submittedRows, string $field): void
    {
        $submittedVersions = [];

        foreach ($submittedRows as $row) {
            $id = $row['id'] ?? null;
            $version = $row['updated_at'] ?? null;

            if ((! is_int($id) && ! is_string($id))
                || (string) $id === ''
                || array_key_exists((string) $id, $submittedVersions)
                || ! is_string($version)
                || $version === '') {
                throw ValidationException::withMessages([
                    $field => '送出資料與目前樣區清單不符，請重新選擇樣區後再試。',
                ]);
            }

            $submittedVersions[(string) $id] = $version;
        }

        $currentIds = $records->map(fn ($record) => (string) $record->getKey())->sort()->values()->all();
        $submittedIds = collect(array_keys($submittedVersions))
            ->map(fn ($id) => (string) $id)
            ->sort()
            ->values()
            ->all();
        if ($currentIds !== $submittedIds) {
            throw ValidationException::withMessages([
                $field => '小樣方清單已變更，請重新選擇樣區後再儲存。',
            ]);
        }

        foreach ($records as $record) {
            $id = (string) $record->getKey();
            $version = method_exists($record, 'toArray')
                ? (string) ($record->toArray()['updated_at'] ?? '')
                : (string) $record->getRawOriginal('updated_at');

            if ($version !== $submittedVersions[$id]) {
                throw ValidationException::withMessages([
                    $field => '資料已由其他分頁更新，請重新選擇樣區後再儲存。',
                ]);
            }
        }
    }
}
