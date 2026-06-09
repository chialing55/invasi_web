<?php

namespace App\Support;

use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class TaiwanChecklistQuery
{
    public static function currentSpcodeExpr(string $rawAlias = 'raw', string $plantAlias = 'p'): string
    {
        return "COALESCE(NULLIF(CASE WHEN {$rawAlias}.spcode_status <> 'active' THEN {$rawAlias}.spcode_current ELSE {$rawAlias}.spcode END, ''), {$plantAlias}.spcode)";
    }

    public static function joinCurrent($query, string $plantAlias = 'p', string $rawAlias = 'raw', string $currentAlias = 's')
    {
        $query->leftJoin("taiwan_checklist as {$rawAlias}", "{$plantAlias}.spcode", '=', "{$rawAlias}.spcode");

        return $query->leftJoin("taiwan_checklist as {$currentAlias}", function (JoinClause $join) use ($plantAlias, $rawAlias, $currentAlias) {
            $join->on("{$currentAlias}.spcode", '=', DB::raw(self::currentSpcodeExpr($rawAlias, $plantAlias)));
        });
    }

    public static function nativeExpr(string $alias = 's'): string
    {
        return "CASE WHEN {$alias}.origin_status = 'native' THEN 1 ELSE 0 END";
    }

    public static function endemicExpr(string $alias = 's'): string
    {
        return "CASE WHEN {$alias}.is_endemic IN (1, '1', 'true', 'TRUE', 'yes', 'YES', 'y', 'Y') THEN 1 ELSE 0 END";
    }

    public static function naturalizedExpr(string $alias = 's'): string
    {
        return "CASE WHEN {$alias}.origin_status IN ('naturalized', 'alien', 'introduced', 'exotic') THEN 1 ELSE 0 END";
    }

    public static function cultivatedExpr(string $alias = 's'): string
    {
        return "CASE WHEN {$alias}.origin_status = 'cultivated' THEN 1 ELSE 0 END";
    }

    public static function uncertainExpr(string $alias = 's'): string
    {
        return "CASE WHEN {$alias}.spcode IS NULL OR {$alias}.origin_status IS NULL OR {$alias}.origin_status = '' OR {$alias}.origin_status IN ('uncertain', 'unknown') THEN 1 ELSE 0 END";
    }

    public static function statusExpr(string $alias = 's'): string
    {
        return "CASE
            WHEN {$alias}.spcode IS NULL THEN 'uncertain'
            WHEN {$alias}.origin_status IN ('naturalized', 'alien', 'introduced', 'exotic') THEN 'naturalized'
            WHEN {$alias}.origin_status = 'cultivated' THEN 'cultivated'
            WHEN {$alias}.origin_status IS NULL OR {$alias}.origin_status = '' OR {$alias}.origin_status IN ('uncertain', 'unknown') THEN 'uncertain'
            ELSE 'native'
        END";
    }
}
