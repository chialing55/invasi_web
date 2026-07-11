<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

class IviComparisonTable
{
    public static function build(array $selectedPlots, ?string $county = null, float $maxElevation = 500.0, float $minCurrentIvi = 0.0): array
    {
        $plots = self::eligiblePlots($selectedPlots, $county, $maxElevation);
        $countyLabel = self::countyLabel($plots, $selectedPlots);

        if (empty($plots)) {
            return ['headings' => self::headings(), 'rows' => [], 'countyLabel' => $countyLabel, 'plotCount' => 0];
        }

        $current = self::rankedIvi('2025', $plots);
        $previous = self::rankedIvi('2010', $plots);
        $previousBySpcode = collect($previous)->keyBy('spcode');

        $rows = [];
        foreach ($current as $row) {
            if ((float) $row['ivi_raw'] < $minCurrentIvi) {
                continue;
            }

            $prev = $previousBySpcode->get($row['spcode']);
            $rows[] = [
                '中文名' => $row['中文名'],
                '學名' => $row['學名'],
                '學名_html' => $row['學名_html'],
                '本次調查_相對覆蓋度(%)' => self::fmt($row['relative_coverage_raw']),
                '本次調查_相對頻度(%)' => self::fmt($row['relative_frequency_raw']),
                '本次調查_IVI重要值(%)' => self::fmt($row['ivi_raw']),
                '本次調查_名次' => $row['rank'],
                '前次調查_相對覆蓋度(%)' => $prev ? self::fmt($prev['relative_coverage_raw']) : '-',
                '前次調查_相對頻度(%)' => $prev ? self::fmt($prev['relative_frequency_raw']) : '-',
                '前次調查_IVI重要值(%)' => $prev ? self::fmt($prev['ivi_raw']) : '-',
                '前次調查_名次' => $prev['rank'] ?? '-',
            ];
        }

        return ['headings' => self::headings(), 'rows' => $rows, 'countyLabel' => $countyLabel, 'plotCount' => count($plots)];
    }

    public static function headings(): array
    {
        return [
            '中文名',
            '學名',
            '本次調查_相對覆蓋度(%)',
            '本次調查_相對頻度(%)',
            '本次調查_IVI重要值(%)',
            '本次調查_名次',
            '前次調查_相對覆蓋度(%)',
            '前次調查_相對頻度(%)',
            '前次調查_IVI重要值(%)',
            '前次調查_名次',
        ];
    }

    private static function eligiblePlots(array $selectedPlots, ?string $county, float $maxElevation): array
    {
        $selectedPlots = array_values(array_filter(array_map('strval', $selectedPlots), fn($plot) => $plot !== ''));
        if (empty($selectedPlots)) {
            return [];
        }

        $query = DB::connection('invasiflora')
            ->table('im_splotdata_2025 as e')
            ->join('plot_list as pl', 'e.plot', '=', 'pl.plot')
            ->whereIn('e.plot', $selectedPlots);

        if ($county !== null && $county !== '') {
            $query->where('pl.county', $county);
        }

        return $query
            ->groupBy('e.plot')
            ->havingRaw('MAX(COALESCE(e.elevation, 99999)) <= ?', [$maxElevation])
            ->orderBy('e.plot')
            ->pluck('e.plot')
            ->map(fn($plot) => (string) $plot)
            ->values()
            ->all();
    }


    private static function countyLabel(array $eligiblePlots, array $selectedPlots): string
    {
        $plots = array_values(array_filter(array_map('strval', $selectedPlots), fn($plot) => $plot !== ''));

        if (empty($plots)) {
            return '選取縣市';
        }

        $counties = DB::connection('invasiflora')
            ->table('plot_list')
            ->whereIn('plot', $plots)
            ->whereNotNull('county')
            ->distinct()
            ->orderBy('county')
            ->pluck('county')
            ->filter()
            ->values()
            ->all();

        $allCounties = DB::connection('invasiflora')
            ->table('plot_list')
            ->whereNotNull('county')
            ->distinct()
            ->orderBy('county')
            ->pluck('county')
            ->filter()
            ->values()
            ->all();

        if (!empty($allCounties) && $counties === $allCounties) {
            return '全部縣市';
        }

        return !empty($counties) ? implode('、', $counties) : '選取縣市';
    }

    private static function rankedIvi(string $year, array $plots): array
    {
        if ($year === '2010') {
            $rows = self::speciesAgg2010($plots);
            [$totalCov, $totalFreq] = self::totals2010($plots);
        } else {
            $rows = self::speciesAgg2025($plots);
            [$totalCov, $totalFreq] = self::totals2025($plots);
        }

        if ($rows->isEmpty()) {
            return [];
        }

        $totalCov = max(0.000001, (float) $totalCov);
        $totalFreq = max(1, (int) $totalFreq);

        return $rows
            ->map(function ($row) use ($totalCov, $totalFreq) {
                $relativeCoverage = ((float) $row->cov_sum) / $totalCov * 100;
                $relativeFrequency = ((int) $row->freq_cnt) / $totalFreq * 100;
                $ivi = $relativeCoverage + $relativeFrequency;

                return [
                    'spcode' => (string) $row->spcode,
                    '中文名' => (string) $row->chname,
                    '學名' => self::canonicalRichText((string) ($row->canonical_name ?? '')),
                    '學名_html' => ScientificNameHelper::canonicalToHtml((string) ($row->canonical_name ?? '')),
                    'relative_coverage_raw' => $relativeCoverage,
                    'relative_frequency_raw' => $relativeFrequency,
                    'ivi_raw' => $ivi,
                ];
            })
            ->sortByDesc('ivi_raw')
            ->values()
            ->map(function (array $row, int $index) {
                $row['rank'] = $index + 1;
                return $row;
            })
            ->all();
    }

    private static function speciesAgg2025(array $plots)
    {
        $base = DB::connection('invasiflora')
            ->table('im_spvptdata_2025 as p')
            ->join('im_splotdata_2025 as e', 'p.plot_full_id', '=', 'e.plot_full_id')
            ->whereIn('e.plot', $plots)
            ->whereNotNull('p.spcode');
        TaiwanChecklistQuery::joinCurrent($base, 'p');

        $naturalizedExpr = TaiwanChecklistQuery::naturalizedExpr('s');

        return (clone $base)
            ->whereRaw("({$naturalizedExpr}) = 1")
            ->selectRaw('s.spcode as spcode, MAX(s.chname) as chname, MAX(s.canonical_name) as canonical_name, SUM(COALESCE(p.coverage, 0)) as cov_sum, COUNT(DISTINCT p.plot_full_id) as freq_cnt')
            ->groupBy('s.spcode')
            ->get();
    }

    private static function speciesAgg2010(array $plots)
    {
        $base = DB::connection('invasiflora')
            ->table('im_spvptdata_2010 as p')
            ->join('im_splotdata_2010 as e', function ($join) {
                $join->on('p.PLOT_ID', '=', 'e.PLOT_ID')
                    ->on('p.HAB_TYPE', '=', 'e.HAB_TYPE')
                    ->on('p.SUB_ID', '=', 'e.SUB_ID');
            })
            ->whereIn('p.PLOT_ID', $plots)
            ->whereNotNull('p.spcode');
        TaiwanChecklistQuery::joinCurrent($base, 'p');

        $naturalizedExpr = TaiwanChecklistQuery::naturalizedExpr('s');

        return (clone $base)
            ->whereRaw("({$naturalizedExpr}) = 1")
            ->selectRaw("s.spcode as spcode, MAX(s.chname) as chname, MAX(s.canonical_name) as canonical_name, SUM(CASE WHEN p.COV REGEXP '^-?[0-9]+(\\\\.[0-9]+)?$' THEN CAST(p.COV AS DECIMAL(12,4)) ELSE 0 END) as cov_sum, COUNT(DISTINCT CONCAT(p.PLOT_ID, '-', p.HAB_TYPE, '-', LPAD(p.SUB_ID, 2, '0'))) as freq_cnt")
            ->groupBy('s.spcode')
            ->get();
    }


    private static function totals2025(array $plots): array
    {
        $base = DB::connection('invasiflora')
            ->table('im_spvptdata_2025 as p')
            ->join('im_splotdata_2025 as e', 'p.plot_full_id', '=', 'e.plot_full_id')
            ->whereIn('e.plot', $plots)
            ->whereNotNull('p.spcode');
        TaiwanChecklistQuery::joinCurrent($base, 'p');

        $totalCov = (float) (clone $base)->sum('p.coverage');
        $totalFreq = (int) (clone $base)
            ->selectRaw('s.spcode as spcode, COUNT(DISTINCT p.plot_full_id) as n')
            ->groupBy('s.spcode')
            ->get()
            ->sum('n');

        return [$totalCov, $totalFreq];
    }

    private static function totals2010(array $plots): array
    {
        $base = DB::connection('invasiflora')
            ->table('im_spvptdata_2010 as p')
            ->join('im_splotdata_2010 as e', function ($join) {
                $join->on('p.PLOT_ID', '=', 'e.PLOT_ID')
                    ->on('p.HAB_TYPE', '=', 'e.HAB_TYPE')
                    ->on('p.SUB_ID', '=', 'e.SUB_ID');
            })
            ->whereIn('p.PLOT_ID', $plots)
            ->whereNotNull('p.spcode');
        TaiwanChecklistQuery::joinCurrent($base, 'p');

        $covExpr = "CASE WHEN p.COV REGEXP '^-?[0-9]+(\\\\.[0-9]+)?$' THEN CAST(p.COV AS DECIMAL(12,4)) ELSE 0 END";
        $totalCov = (float) (clone $base)->sum(DB::raw($covExpr));
        $totalFreq = (int) (clone $base)
            ->selectRaw("s.spcode as spcode, COUNT(DISTINCT CONCAT(p.PLOT_ID, '-', p.HAB_TYPE, '-', LPAD(p.SUB_ID, 2, '0'))) as n")
            ->groupBy('s.spcode')
            ->get()
            ->sum('n');

        return [$totalCov, $totalFreq];
    }

    private static function canonicalRichText(string $canonicalName): RichText
    {
        $rt = new RichText();
        $run = $rt->createTextRun(html_entity_decode($canonicalName, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $run->getFont()->setItalic(true);
        return $rt;
    }

    private static function fmt(float $value): string
    {
        return $value > 0 && $value < 0.01 ? '<0.01' : number_format($value, 2, '.', '');
    }
}
