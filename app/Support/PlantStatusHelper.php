<?php

declare(strict_types=1);

namespace App\Support;

final class PlantStatusHelper
{
    public static function flags(?string $originStatus, mixed $isEndemic = null): array
    {
        $status = self::normalize($originStatus);

        return [
            'endemic' => self::truthy($isEndemic) ? 1 : 0,
            'native' => $status === 'native' ? 1 : 0,
            'naturalized' => in_array($status, ['naturalized', 'alien', 'introduced', 'exotic'], true) ? 1 : 0,
            'cultivated' => $status === 'cultivated' ? 1 : 0,
            'uncertain' => in_array($status, ['', 'uncertain', 'unknown'], true) ? 1 : 0,
        ];
    }

    public static function labels(?string $originStatus, mixed $isEndemic = null): array
    {
        $flags = self::flags($originStatus, $isEndemic);
        $labels = [];

        if ($flags['endemic'] === 1) {
            $labels[] = '特有';
        }

        if ($flags['naturalized'] === 1) {
            $labels[] = '外來';
        } elseif ($flags['cultivated'] === 1) {
            $labels[] = '栽培';
        } elseif ($flags['native'] === 1) {
            $labels[] = '原生';
        } elseif ($flags['uncertain'] === 1) {
            $labels[] = '不明';
        }

        return $labels;
    }

    public static function label(?string $originStatus): string
    {
        $status = self::normalize($originStatus);

        return match ($status) {
            'native' => '原生',
            'naturalized', 'alien', 'introduced', 'exotic' => '外來',
            'cultivated' => '栽培',
            'uncertain', 'unknown', '' => '不明',
            default => $originStatus ?? '',
        };
    }

    private static function normalize(?string $originStatus): string
    {
        return strtolower(trim((string) $originStatus));
    }

    private static function truthy(mixed $value): bool
    {
        return in_array($value, [1, '1', true, 'true', 'TRUE', 'yes', 'YES'], true);
    }
}
