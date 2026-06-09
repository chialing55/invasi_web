<?php

namespace App\Helpers;

use App\Models\SpcodeIndex;
use App\Models\TaiwanChecklist;

class EntryPlantSearchHelper
{
    public static function entryPlantNameSearchHelper($value)
    {
        $startsWith = self::activeChecklistQuery()
            ->where(function ($query) use ($value) {
                $query->where('chname', 'like', "{$value}%")
                    ->orWhere('canonical_name', 'like', "{$value}%")
                    ->orWhere('full_name', 'like', "{$value}%");
            })
            ->limit(20)
            ->get()
            ->flatMap(fn ($item) => self::nameOptions($item));

        $contains = self::activeChecklistQuery()
            ->where(function ($query) use ($value) {
                $query->where('chname', 'like', "%{$value}%")
                    ->orWhere('canonical_name', 'like', "%{$value}%")
                    ->orWhere('full_name', 'like', "%{$value}%");
            })
            ->limit(50)
            ->get()
            ->flatMap(fn ($item) => self::nameOptions($item))
            ->reject(fn ($item) => $startsWith->contains('label', $item['label']));

        $indexMatches = self::indexMatches($value);

        return $startsWith
            ->concat($contains)
            ->merge($indexMatches)
            ->unique(fn ($item) => ($item['spcode'] ?? '') . '|' . ($item['value'] ?? '') . '|' . ($item['label'] ?? ''))
            ->sortByDesc(fn ($item) => $item['label'] === $value ? 1 : 0)
            ->values()
            ->toArray();
    }

    private static function indexMatches(string $value)
    {
        $rows = SpcodeIndex::query()
            ->where('chname_index', 'like', "%{$value}%")
            ->join('taiwan_checklist', 'spcode_index.spcode', '=', 'taiwan_checklist.spcode')
            ->select(
                'spcode_index.chname_index as chname_index',
                'spcode_index.spcode as original_spcode',
                'taiwan_checklist.spcode as checklist_spcode',
                'taiwan_checklist.spcode_current as spcode_current',
                'taiwan_checklist.spcode_status as spcode_status'
            )
            ->limit(20)
            ->get();

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
                $currentCode = self::currentCode($row);
                $current = $currentRows->get($currentCode);

                if (!$current) {
                    return null;
                }

                return [
                    'family' => $current->chfamily,
                    'label' => $row->chname_index . ' / ' . $current->chname,
                    'value' => $row->chname_index,
                    'hint' => $current->chname . ' / ' . $current->chfamily,
                    'spcode' => $current->spcode,
                ];
            })
            ->filter()
            ->values();
    }

    private static function activeChecklistQuery()
    {
        return TaiwanChecklist::query()
            ->where('spcode_status', 'active')
            ->whereNotNull('chname');
    }

    private static function currentCode($item): string
    {
        $status = strtolower(trim((string) ($item->spcode_status ?? '')));
        $current = trim((string) ($item->spcode_current ?? ''));
        $spcode = trim((string) ($item->checklist_spcode ?? $item->spcode ?? ''));

        return $status !== 'active' && $current !== '' ? $current : $spcode;
    }

    private static function nameOptions($item): array
    {
        $list = [];

        if ($item->chname) {
            $list[] = [
                'family' => $item->chfamily,
                'label' => $item->chname,
                'value' => $item->chname,
                'hint' => $item->chname . ' / ' . $item->chfamily,
                'spcode' => $item->spcode,
            ];
        }

        if ($item->canonical_name) {
            $list[] = [
                'family' => $item->chfamily,
                'label' => $item->canonical_name . ' / ' . $item->chname,
                'value' => $item->chname,
                'hint' => $item->chname . ' / ' . $item->chfamily,
                'spcode' => $item->spcode,
            ];
        }

        if ($item->full_name && $item->full_name !== $item->canonical_name) {
            $list[] = [
                'family' => $item->chfamily,
                'label' => $item->full_name . ' / ' . $item->chname,
                'value' => $item->chname,
                'hint' => $item->chname . ' / ' . $item->chfamily,
                'spcode' => $item->spcode,
            ];
        }

        return $list;
    }
}
