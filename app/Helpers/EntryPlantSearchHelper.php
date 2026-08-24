<?php

namespace App\Helpers;

use App\Models\TaiwanChecklist;

class EntryPlantSearchHelper
{
    public static function entryPlantNameSearchHelper($value)
    {
        $matches = collect(PlantSearchHelper::plantNameSearchHelper($value));
        $currentRows = self::activeChecklistQuery()
            ->whereIn('spcode', $matches->pluck('spcode')->filter()->unique())
            ->get()
            ->keyBy('spcode');

        $suggestions = $matches
            ->map(function ($match) use ($currentRows, $value) {
                $current = $currentRows->get($match['spcode']);

                $isAlias = ($match['match_type'] ?? '') === 'alias';

                if (!$current || !$current->chname
                    || (!$isAlias && !PlantSearchHelper::matchesSearchValue($current, $value))) {
                    return null;
                }

                $isScientificName = $match['label'] === $current->canonical_name;

                return [
                    'family' => $current->chfamily,
                    'label' => $isScientificName
                        ? $match['label'] . ' / ' . $current->chname
                        : $match['label'],
                    'value' => $match['value'] ?? $current->chname,
                    'hint' => $current->chname . ' / ' . $current->chfamily,
                    'spcode' => $current->spcode,
                ];
            })
            ->filter();

        return $suggestions
            ->unique(fn ($item) => ($item['spcode'] ?? '') . '|' . ($item['value'] ?? '') . '|' . ($item['label'] ?? ''))
            ->sortByDesc(fn ($item) => $item['label'] === $value ? 1 : 0)
            ->values()
            ->toArray();
    }

    private static function activeChecklistQuery()
    {
        return TaiwanChecklist::query()
            ->where('spcode_status', 'active')
            ->whereNotNull('chname');
    }

}
