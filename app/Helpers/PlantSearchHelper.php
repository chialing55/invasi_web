<?php

namespace App\Helpers;

use App\Models\SpcodeIndex;
use App\Models\TaiwanChecklist;

class PlantSearchHelper
{
    public static function matchesSearchValue($item, string $value): bool
    {
        $needle = mb_strtolower(trim($value));

        if ($needle === '') {
            return false;
        }

        foreach (['chname', 'canonical_name', 'full_name', 'family', 'chfamily'] as $field) {
            if (str_contains(mb_strtolower((string) $item->{$field}), $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function plantNameSearchHelper($value)
    {
        $startsWithCodes = self::activeChecklistQuery()
            ->where(function ($query) use ($value) {
                $query->where('chname', 'like', "{$value}%")
                    ->orWhere('canonical_name', 'like', "{$value}%")
                    ->orWhere('full_name', 'like', "{$value}%");
            })
            ->limit(40)
            ->get()
            ->toBase()
            ->map(fn ($item) => self::currentCode($item))
            ->filter()
            ->values();

        $containsCodes = self::activeChecklistQuery()
            ->where(function ($query) use ($value) {
                $query->where('chname', 'like', "%{$value}%")
                    ->orWhere('canonical_name', 'like', "%{$value}%")
                    ->orWhere('full_name', 'like', "%{$value}%");
            })
            ->limit(80)
            ->get()
            ->toBase()
            ->map(fn ($item) => self::currentCode($item))
            ->filter()
            ->values();

        $currentCodes = $startsWithCodes
            ->concat($containsCodes)
            ->unique()
            ->values();

        $nameMatches = self::currentChecklistRows($currentCodes)
            ->flatMap(fn ($item) => self::nameOptions($item, $value))
            ->unique(fn ($item) => ($item['spcode'] ?? '') . '|' . ($item['label'] ?? ''))
            ->sortByDesc(fn ($item) => $item['label'] === $value ? 1 : 0)
            ->values();

        $familyCodes = self::activeChecklistQuery()
            ->where(function ($query) use ($value) {
                $query->where('family', 'like', "%$value%")
                    ->orWhere('chfamily', 'like', "%$value%");
            })
            ->orderByRaw('CASE WHEN chfamily LIKE ? OR family LIKE ? THEN 0 ELSE 1 END', ["{$value}%", "{$value}%"])
            ->limit(20)
            ->get()
            ->toBase()
            ->map(fn ($item) => self::currentCode($item))
            ->filter()
            ->unique()
            ->values();

        $familyMatches = self::currentChecklistRows($familyCodes)
            ->map(fn ($item) => [
                'family' => $item->chfamily,
                'label' => $item->chname,
                'spcode' => self::currentCode($item),
            ]);

        $indexMatches = self::indexMatches($value);

        $merged = $nameMatches
            ->concat($familyMatches)
            ->concat($indexMatches)
            ->unique(fn ($item) => ($item['spcode'] ?? '') . '|' . ($item['label'] ?? ''))
            ->values();

        return $merged->toArray();
    }

    private static function activeChecklistQuery()
    {
        return TaiwanChecklist::query()->where('spcode_status', 'active');
    }

    private static function currentChecklistRows($currentCodes)
    {
        $codes = collect($currentCodes)->filter()->unique()->values();

        if ($codes->isEmpty()) {
            return collect();
        }

        $rows = self::activeChecklistQuery()
            ->whereIn('spcode', $codes)
            ->get()
            ->keyBy('spcode');

        return $codes
            ->map(fn ($code) => $rows->get($code))
            ->filter()
            ->values();
    }

    private static function currentCode($item): string
    {
        $status = strtolower(trim((string) ($item->spcode_status ?? '')));
        $current = trim((string) ($item->spcode_current ?? ''));
        $spcode = trim((string) ($item->checklist_spcode ?? $item->spcode ?? ''));

        return $status !== 'active' && $current !== '' ? $current : ($current ?: $spcode);
    }

    private static function indexMatches(string $value)
    {
        $rows = SpcodeIndex::query()
            ->where('chname_index', 'like', "%{$value}%")
            ->join('taiwan_checklist', 'spcode_index.spcode', '=', 'taiwan_checklist.spcode')
            ->select(
                'spcode_index.chname_index as chname_index',
                'taiwan_checklist.spcode as checklist_spcode',
                'taiwan_checklist.spcode_current as spcode_current',
                'taiwan_checklist.spcode_status as spcode_status'
            )
            ->limit(40)
            ->get()
            ->toBase();

        $currentCodes = $rows
            ->map(fn ($row) => self::currentCode($row))
            ->filter()
            ->unique()
            ->values();

        $currentRows = self::activeChecklistQuery()
            ->whereIn('spcode', $currentCodes)
            ->get()
            ->keyBy('spcode');

        return $rows
            ->map(function ($row) use ($currentRows) {
                $current = $currentRows->get(self::currentCode($row));

                if (!$current || !$current->chname) {
                    return null;
                }

                return [
                    'family' => $current->chfamily,
                    'label' => $row->chname_index . ' / ' . $current->chname,
                    'value' => $row->chname_index,
                    'match_type' => 'alias',
                    'spcode' => $current->spcode,
                ];
            })
            ->filter()
            ->values();
    }

    private static function nameOptions($item, string $value): array
    {
        $spcode = self::currentCode($item);
        $list = [];

        if ($item->chname) {
            $list[] = [
                'family' => $item->chfamily,
                'label' => $item->chname,
                'spcode' => $spcode,
            ];
        }

        $scientificNameMatches = str_contains(mb_strtolower((string) $item->canonical_name), mb_strtolower($value))
            || str_contains(mb_strtolower((string) $item->full_name), mb_strtolower($value));

        if ($item->canonical_name && $scientificNameMatches) {
            $list[] = [
                'family' => $item->chfamily,
                'label' => $item->canonical_name,
                'spcode' => $spcode,
            ];
        }

        return $list;
    }
}
