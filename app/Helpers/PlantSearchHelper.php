<?php

namespace App\Helpers;

use App\Models\SpcodeIndex;
use App\Models\TaiwanChecklist;

class PlantSearchHelper
{
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
            ->map(fn ($item) => self::currentCode($item))
            ->filter()
            ->values();

        $indexCodes = SpcodeIndex::query()
            ->where('chname_index', 'like', "%{$value}%")
            ->join('taiwan_checklist', 'spcode_index.spcode', '=', 'taiwan_checklist.spcode')
            ->limit(40)
            ->get()
            ->map(fn ($item) => self::currentCode($item))
            ->filter()
            ->values();

        $currentCodes = $startsWithCodes
            ->concat($containsCodes)
            ->concat($indexCodes)
            ->unique()
            ->values();

        $merged = self::currentChecklistRows($currentCodes)
            ->flatMap(fn ($item) => self::nameOptions($item))
            ->unique(fn ($item) => ($item['spcode'] ?? '') . '|' . ($item['label'] ?? ''))
            ->sortByDesc(fn ($item) => $item['label'] === $value ? 1 : 0)
            ->values();

        if ($merged->isEmpty()) {
            $familyCodes = self::activeChecklistQuery()
                ->where(function ($query) use ($value) {
                    $query->where('family', 'like', "%$value%")
                        ->orWhere('chfamily', 'like', "%$value%");
                })
                ->limit(20)
                ->get()
                ->map(fn ($item) => self::currentCode($item))
                ->filter()
                ->unique()
                ->values();

            $merged = self::currentChecklistRows($familyCodes)
                ->map(fn ($item) => [
                    'family' => $item->chfamily,
                    'label' => $item->chname,
                    'spcode' => self::currentCode($item),
                ]);
        }

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
        return trim((string) ($item->spcode_current ?: $item->spcode));
    }

    private static function nameOptions($item): array
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

        if ($item->canonical_name) {
            $list[] = [
                'family' => $item->chfamily,
                'label' => $item->canonical_name,
                'spcode' => $spcode,
            ];
        }

        if ($item->full_name && $item->full_name !== $item->canonical_name) {
            $list[] = [
                'family' => $item->chfamily,
                'label' => $item->full_name,
                'spcode' => $spcode,
            ];
        }

        return $list;
    }
}
