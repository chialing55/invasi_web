<?php

namespace App\Support;

final class HabitatCode
{
    /** 主生育地 => 對應地被。新增成對類型時只需維護此處。 */
    private const UNDERSTORY_PAIRS = [
        '08' => '88',
        '09' => '99',
        '19' => '77',
    ];

    /** 目前可輸入的主生育地代碼。 */
    private const MAIN_CODES = [
        '01', '02', '03', '04', '05', '06', '07', '08', '09', '10',
        '11', '12', '13', '14', '15', '16', '17', '18', '19', '20',
    ];

    /** 2010 確實存在且可衍生地被查詢的配對；不要由新制設定自動擴張。 */
    private const LEGACY_UNDERSTORY_PAIRS = [
        '08' => '88',
        '09' => '99',
    ];

    public static function pairs(): array
    {
        return self::UNDERSTORY_PAIRS;
    }

    public static function legacyPairs(): array
    {
        return self::LEGACY_UNDERSTORY_PAIRS;
    }

    public static function legacyMainCodes(): array
    {
        return array_map(static fn (int|string $code): string => (string) $code, array_keys(self::LEGACY_UNDERSTORY_PAIRS));
    }

    public static function legacyUnderstoryCodes(): array
    {
        return array_values(self::LEGACY_UNDERSTORY_PAIRS);
    }

    public static function legacyMainFor(?string $understoryCode): ?string
    {
        $mainCode = array_search($understoryCode, self::LEGACY_UNDERSTORY_PAIRS, true);

        return $mainCode === false ? null : (string) $mainCode;
    }

    public static function mainCodes(): array
    {
        return self::MAIN_CODES;
    }

    public static function understoryCodes(): array
    {
        return array_values(self::UNDERSTORY_PAIRS);
    }

    public static function allowedCodes(): array
    {
        return array_values(array_unique(array_merge(self::MAIN_CODES, self::understoryCodes())));
    }

    public static function woodCodes(): array
    {
        return array_map(static fn (int|string $code): string => (string) $code, array_keys(self::UNDERSTORY_PAIRS));
    }

    public static function herbMainCodes(): array
    {
        return array_values(array_diff(self::MAIN_CODES, self::woodCodes()));
    }

    public static function sqlList(array $codes): string
    {
        return implode(', ', array_map(
            static fn (string $code): string => "'{$code}'",
            $codes
        ));
    }

    public static function isWood(?string $code): bool
    {
        return in_array($code, self::woodCodes(), true);
    }

    public static function isUnderstory(?string $code): bool
    {
        return in_array($code, self::understoryCodes(), true);
    }

    public static function understoryFor(?string $mainCode): ?string
    {
        return self::UNDERSTORY_PAIRS[$mainCode] ?? null;
    }

    public static function mainFor(?string $understoryCode): ?string
    {
        $mainCode = array_search($understoryCode, self::UNDERSTORY_PAIRS, true);

        return $mainCode === false ? null : (string) $mainCode;
    }

    public static function appendDerivedCodes(array $codes): array
    {
        foreach (self::UNDERSTORY_PAIRS as $main => $understory) {
            $main = (string) $main;
            if (in_array($main, $codes, true)) {
                $codes[] = $understory;
            }
        }

        return array_values(array_unique($codes));
    }

    public static function syncSelectedCodes(array $codes): array
    {
        foreach (self::UNDERSTORY_PAIRS as $main => $understory) {
            $main = (string) $main;
            if (in_array($main, $codes, true)) {
                $codes[] = $understory;
            } else {
                $codes = array_values(array_diff($codes, [$understory]));
            }
        }

        return array_values(array_unique($codes));
    }

    /** 將地被代碼合併回主生育地，供 Shannon／IV 等 SQL 共用。 */
    public static function normalizedSql(string $column): string
    {
        $cases = [];
        foreach (self::UNDERSTORY_PAIRS as $main => $understory) {
            $cases[] = "WHEN {$column} IN ('{$understory}', {$understory}) THEN '{$main}'";
        }

        return 'CASE ' . implode(' ', $cases)
            . " ELSE LPAD(CAST({$column} AS CHAR), 2, '0') END";
    }
}
