<?php

namespace App\Helpers;

use App\Models\SubPlotEnv2010;
use App\Models\SubPlotEnv2025;
use App\Models\SubPlotPlant2010;
use App\Models\SubPlotPlant2025;
use App\Models\TaiwanChecklist;
use App\Support\PlantStatusHelper;
use App\Support\HabitatCode;
use Illuminate\Support\Facades\DB;

class PlantListHelper
{
    public static function mergeSpeciesLists(array $list2010, array $list2025, $totalPlots2010, $totalPlots2025): array
    {
        $merged = [];

        $map2010 = collect($list2010)->mapWithKeys(function ($item) {
            $key = $item['merge_spcode'] ?? ($item['spcode'] ?: "un_2010_" . ($item['chname_index'] ?? ""));
            $item['__key'] = $key;
            return [$key => $item];
        });

        $map2025 = collect($list2025)->mapWithKeys(function ($item) {
            $key = $item['merge_spcode'] ?? ($item['spcode'] ?: "un_2025_" . ($item['chname_index'] ?? ""));
            $item['__key'] = $key;
            return [$key => $item];
        });

        $allSpcodes = $map2010->keys()->merge($map2025->keys())->unique();

        foreach ($allSpcodes as $key) {
            $item2010 = $map2010->get($key);
            $item2025 = $map2025->get($key);

            $chname = $item2010['chname'] ?? $item2025['chname'] ?? $item2010['chname_index'] ?? $item2025['chname_index'] ?? '未鑑定';
            $chfamily = $item2010['chfamily'] ?? $item2025['chfamily'] ?? '--';

            $nat_type = '';
            if (($item2010['naturalized'] ?? $item2025['naturalized'] ?? 0) == 1) {
                $nat_type = '外來';
            } elseif (($item2010['cultivated'] ?? $item2025['cultivated'] ?? 0) == 1) {
                $nat_type = '栽培';
            }

            $covAvg2010 = round($item2010['cov_avg'] ?? 0, 2) ?? null;
            $covSd2010 = round($item2010['cov_sd'] ?? 0, 2) ?? null;
            $covFreq2010 = $item2010['cov_freq'] ?? 0;

            $cov2010Display = $covAvg2010 !== null
                ? round($covAvg2010, 2)
                    . ($covFreq2010 > 1 ? ' ± ' . round($covSd2010 ?? 0, 2) : '')
                    . ($totalPlots2010 > 1 && $covFreq2010 > 0 ? ' (' . $covFreq2010 . ' / ' . $totalPlots2010 . ')' : '')
                : '';

            $covAvg2025 = round($item2025['cov_avg'] ?? 0, 2) ?? null;
            $covSd2025 = round($item2025['cov_sd'] ?? 0, 2) ?? null;
            $covFreq2025 = $item2025['cov_freq'] ?? 0;

            $cov2025Display = $covAvg2025 !== null
                ? round($covAvg2025, 2)
                    . ($covFreq2025 > 1 ? ' ± ' . round($covSd2025 ?? 0, 2) : '')
                    . ($totalPlots2025 > 1 && $covFreq2025 > 0 ? ' (' . $covFreq2025 . ' / ' . $totalPlots2025 . ')' : '')
                : '';

            $merged[] = [
                'spcode' => $item2010['spcode'] ?? $item2025['spcode'] ?? '',
                'original_spcodes' => array_values(array_unique(array_merge(
                    $item2010['original_spcodes'] ?? [],
                    $item2025['original_spcodes'] ?? []
                ))),
                'chfamily' => $chfamily,
                'chname' => $chname,
                'nat_type' => $nat_type,
                'covsd2010' => $cov2010Display,
                'covsd2025' => $cov2025Display,
                'cov2010' => number_format($covAvg2010, 2),
                'sd2010' => $covFreq2010 > 1 ? "±{$covSd2010}" : "",
                'freq2010' => ($totalPlots2010 > 1 && $covFreq2010 > 0 ? ' (' . $covFreq2010 . ' / ' . $totalPlots2010 . ')' : ''),
                'plot2010' => $totalPlots2010,
                'sub2010' => intval($covFreq2010 ?? 0),
                'cov2025' => number_format($covAvg2025, 2),
                'sd2025' => $covFreq2025 > 1 ? "±{$covSd2025}" : "",
                'freq2025' => ($totalPlots2025 > 1 && $covFreq2025 > 0 ? ' (' . $covFreq2025 . ' / ' . $totalPlots2025 . ')' : ''),
                'plot2025' => $totalPlots2025,
                'sub2025' => intval($covFreq2025 ?? 0),
                'cov2010_sort' => $covAvg2010 ?? 0,
                'cov2025_sort' => $covAvg2025 ?? 0,
            ];
        }

        return collect($merged)->sortBy([['cov2025_sort', 'desc'], ['cov2010_sort', 'desc']])->values()->toArray();
    }

    public static function getMergedPlotPlantList(string $plot, array $filter = []): array
    {
        $query2010 = SubPlotPlant2010::select(
            'im_spvptdata_2010.spcode',
            DB::raw('AVG(im_spvptdata_2010.COV) as cov_avg'),
            DB::raw('STD(im_spvptdata_2010.COV) as cov_sd'),
            DB::raw('COUNT(*) as cov_freq')
        )->where('im_spvptdata_2010.PLOT_ID', $plot);

        if (isset($filter['hab_type'])) {
            if (in_array($filter['hab_type'], HabitatCode::legacyMainCodes(), true)) {
                $query2010->where('im_spvptdata_2010.HAB_TYPE', $filter['hab_type'])
                    ->where('im_spvptdata_2010.SUB_TYPE', '2');
            } elseif (in_array($filter['hab_type'], HabitatCode::legacyUnderstoryCodes(), true)) {
                $originalHab = HabitatCode::legacyMainFor($filter['hab_type']);
                $query2010->where('im_spvptdata_2010.HAB_TYPE', $originalHab)
                    ->where('im_spvptdata_2010.SUB_TYPE', '!=', '2');
            } else {
                $query2010->where('im_spvptdata_2010.HAB_TYPE', $filter['hab_type']);
            }
        }

        if (isset($filter['sub_id'])) {
            $query2010->where('im_spvptdata_2010.SUB_ID', $filter['sub_id']);
        }

        $plotPlant2010 = $query2010->groupBy('im_spvptdata_2010.spcode')
            ->get()
            ->toArray();

        $plotPlant2010 = self::enrichAndMergeChecklistRows($plotPlant2010, '2010');

        $query2025 = SubPlotPlant2025::select(
            'im_spvptdata_2025.spcode',
            DB::raw('im_spvptdata_2025.chname_index as chname_index'),
            DB::raw('AVG(im_spvptdata_2025.coverage) as cov_avg'),
            DB::raw('STD(im_spvptdata_2025.coverage) as cov_sd'),
            DB::raw('COUNT(*) as cov_freq')
        );

        if (isset($filter['sub_plot'])) {
            $query2025->where('plot_full_id', $filter['sub_plot']);
        } elseif (isset($filter['hab_type'])) {
            $filterHab = $plot . $filter['hab_type'];
            $query2025->whereRaw('LEFT(plot_full_id, 8) = ?', [$filterHab]);
        } else {
            $query2025->whereRaw('LEFT(plot_full_id, 6) = ?', [$plot]);
        }

        $plotPlant2025 = $query2025
            ->groupBy('im_spvptdata_2025.spcode', 'im_spvptdata_2025.chname_index')
            ->get()
            ->toArray();

        $plotPlant2025 = self::enrichAndMergeChecklistRows($plotPlant2025, '2025');

        $subQuery = SubPlotEnv2010::selectRaw("DISTINCT CONCAT(PLOT_ID, '.', HAB_TYPE, '.', SUB_ID) AS subkey")
            ->where('PLOT_ID', $plot)
            ->when(isset($filter['hab_type']), function ($query) use ($filter) {
                return $query->where('HAB_TYPE', $filter['hab_type']);
            });

        $totalPlots2010 = DB::connection('invasiflora')
            ->table(DB::raw("({$subQuery->toSql()}) as sub"))
            ->mergeBindings($subQuery->getQuery())
            ->count();

        $totalPlots2025Query = SubPlotEnv2025::whereRaw('LEFT(plot_full_id, 6) = ?', [$plot]);

        if (isset($filter['hab_type'])) {
            $filterHab = $plot . $filter['hab_type'];
            $totalPlots2025Query->whereRaw('LEFT(plot_full_id, 8) = ?', [$filterHab]);
        }

        $totalPlots2025 = $totalPlots2025Query
            ->distinct('plot_full_id')
            ->count('plot_full_id');

        if (isset($filter['sub_id'])) {
            $totalPlots2010 = 1;
            $totalPlots2025 = 1;
        }

        return self::mergeSpeciesLists($plotPlant2010, $plotPlant2025, $totalPlots2010, $totalPlots2025);
    }

    private static function enrichAndMergeChecklistRows(array $rows, string $year): array
    {
        $spcodes = collect($rows)->pluck('spcode')->filter()->unique()->values();
        $rawInfo = TaiwanChecklist::whereIn('spcode', $spcodes)->get()->keyBy('spcode');
        $currentCodes = $rawInfo
            ->map(fn ($info) => self::displaySpcode($info))
            ->filter()
            ->unique()
            ->values();
        $currentInfo = TaiwanChecklist::whereIn('spcode', $currentCodes)->get()->keyBy('spcode');

        return collect($rows)
            ->map(function ($item) use ($rawInfo, $currentInfo, $year) {
                $spcode = $item['spcode'] ?? null;
                $raw = $spcode ? $rawInfo->get($spcode) : null;
                $displaySpcode = $raw ? self::displaySpcode($raw) : $spcode;
                $displayInfo = $displaySpcode ? ($currentInfo->get($displaySpcode) ?: $raw) : null;

                $item['spcode'] = $displaySpcode ?: $spcode;
                $item['merge_spcode'] = $displaySpcode ?: ($spcode ?: "un_{$year}_" . ($item['chname_index'] ?? ''));
                $item['original_spcodes'] = array_values(array_filter([$spcode]));

                if ($displayInfo) {
                    $flags = PlantStatusHelper::flags($displayInfo->origin_status, $displayInfo->is_endemic);
                    $item['chname'] = $displayInfo->chname;
                    $item['chfamily'] = $displayInfo->chfamily;
                    $item['naturalized'] = $flags['naturalized'];
                    $item['cultivated'] = $flags['cultivated'];
                } else {
                    $item['chname'] = $item['chname_index'] ?? null;
                    $item['chfamily'] = null;
                    $item['naturalized'] = null;
                    $item['cultivated'] = null;
                }

                return $item;
            })
            ->groupBy('merge_spcode')
            ->map(fn ($group) => self::combineAggregatedRows($group->values()->all()))
            ->values()
            ->toArray();
    }

    private static function displaySpcode($info): string
    {
        $status = strtolower(trim((string) ($info->spcode_status ?? '')));
        $current = trim((string) ($info->spcode_current ?? ''));
        $spcode = trim((string) ($info->spcode ?? ''));

        return $status !== 'active' && $current !== '' ? $current : $spcode;
    }

    private static function combineAggregatedRows(array $rows): array
    {
        $first = $rows[0];
        $totalFreq = array_sum(array_map(fn ($row) => (int) ($row['cov_freq'] ?? 0), $rows));

        if ($totalFreq <= 0) {
            return $first;
        }

        $weightedMean = array_sum(array_map(
            fn ($row) => (float) ($row['cov_avg'] ?? 0) * (int) ($row['cov_freq'] ?? 0),
            $rows
        )) / $totalFreq;

        $weightedSecondMoment = array_sum(array_map(function ($row) {
            $freq = (int) ($row['cov_freq'] ?? 0);
            $mean = (float) ($row['cov_avg'] ?? 0);
            $sd = (float) ($row['cov_sd'] ?? 0);

            return (($sd ** 2) + ($mean ** 2)) * $freq;
        }, $rows)) / $totalFreq;

        $first['cov_avg'] = $weightedMean;
        $first['cov_sd'] = sqrt(max(0, $weightedSecondMoment - ($weightedMean ** 2)));
        $first['cov_freq'] = $totalFreq;
        $first['original_spcodes'] = collect($rows)
            ->flatMap(fn ($row) => $row['original_spcodes'] ?? [])
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $first;
    }
}
