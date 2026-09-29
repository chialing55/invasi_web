<?php

namespace App\Services;

use App\Models\SpcodeIndex;
use App\Models\TaiwanChecklist;
use Illuminate\Support\Collection;

final class PlantIdentityResolver
{
    public static function resolve(Collection $rows): Collection
    {
        $names = $rows->pluck('chname_index')->filter()->map(fn ($name) => trim((string) $name))->unique();
        $submittedCodes = $rows->pluck('spcode')->filter()->map(fn ($code) => trim((string) $code))->unique();
        $aliases = SpcodeIndex::query()
            ->whereIn('chname_index', $names)
            ->get(['chname_index', 'spcode']);
        $candidateCodes = $submittedCodes->merge($aliases->pluck('spcode'))->filter()->unique();
        $rawChecklist = TaiwanChecklist::query()
            ->whereIn('spcode', $candidateCodes)
            ->get(['spcode', 'spcode_current', 'spcode_status', 'chname']);
        $currentCodes = $rawChecklist->map(fn ($row) => self::currentCode($row))->filter()->unique();
        $currentChecklist = TaiwanChecklist::query()
            ->whereIn('spcode', $currentCodes)
            ->where('spcode_status', 'active')
            ->get(['spcode', 'chname'])
            ->keyBy('spcode');
        $rawByCode = $rawChecklist->keyBy('spcode');
        $validAliases = $aliases
            ->map(fn ($alias) => [
                'name' => trim((string) $alias->chname_index),
                'spcode' => self::currentCode($rawByCode->get($alias->spcode)),
            ])
            ->filter(fn ($alias) => $alias['name'] !== '' && $alias['spcode'] !== '')
            ->groupBy('name');

        return $rows->map(function (array $row) use ($rawByCode, $currentChecklist, $validAliases) {
            $name = trim((string) ($row['chname_index'] ?? ''));
            $submittedCode = trim((string) ($row['spcode'] ?? ''));
            $currentCode = self::currentCode($rawByCode->get($submittedCode));
            $current = $currentChecklist->get($currentCode);
            $matchesOfficialName = $current && trim((string) $current->chname) === $name;
            $matchesAlias = ($validAliases->get($name) ?? collect())->contains(
                fn ($alias) => $alias['spcode'] === $currentCode
            );

            $row['spcode'] = ($matchesOfficialName || $matchesAlias) ? $currentCode : null;
            $row['unidentified'] = $row['spcode'] === null ? 1 : 0;

            return $row;
        });
    }

    private static function currentCode($row): string
    {
        if (! $row) {
            return '';
        }

        $status = strtolower(trim((string) ($row->spcode_status ?? '')));
        $current = trim((string) ($row->spcode_current ?? ''));
        $spcode = trim((string) ($row->spcode ?? ''));

        return $status !== 'active' && $current !== '' ? $current : ($spcode ?: $current);
    }
}
