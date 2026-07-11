<?php

namespace App\Exports;

use App\Models\SubPlotPlant2025;
use App\Support\ScientificNameHelper;
use App\Support\TaiwanChecklistQuery;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

class PlantListExport
{
    public static function PlantListForWord(array $selectedPlots): array
    {
        if (empty($selectedPlots)) {
            return ['headings' => [], 'rows' => []];
        }

        $headings = ['行號', '科名', '學名', '中文名', '原生', '特有', '外來', '栽培'];
        $builder = self::baseQuery($selectedPlots)
            ->groupBy('s.spcode')
            ->selectRaw(self::listSelectSql('●'));
        self::applyListOrder($builder);

        $rows = $builder->toBase()->get()
            ->map(function ($row, int $index) {
                $row = self::formatScientificName((array) $row, 'txt');

                return [
                    '行號' => $index + 1,
                    '科名' => $row['科名'] ?? '',
                    '學名' => $row['學名'] ?? '',
                    '中文名' => $row['中文名'] ?? '',
                    '原生' => $row['原生種'] ?? '',
                    '特有' => $row['特有種'] ?? '',
                    '外來' => $row['歸化種'] ?? '',
                    '栽培' => $row['栽培種'] ?? '',
                ];
            })
            ->values()
            ->all();

        return ['headings' => $headings, 'rows' => $rows];
    }

    public static function PlantListAll(array $selectedPlots, string $format = 'xlsx'): array
    {
        $teamMap = self::teamMap();
        $teamSqls = [];
        $bindings = [];
        foreach ($teamMap as $code => $label) {
            $colName = str_replace('`', '``', $label);
            $teamSqls[] = "MAX(CASE WHEN e.team = ? THEN 'V' ELSE '' END) AS `{$colName}`";
            $bindings[] = $code;
        }

        $builder = self::baseQuery()
            ->groupBy('s.spcode')
            ->selectRaw(self::listSelectSql('◎'))
            ->selectRaw(implode(",\n", $teamSqls), $bindings);

        self::applyListOrder($builder);

        $headings = array_merge(['科名', '學名', '中文名', '原生種', '特有種', '歸化種', '栽培種', 'IUCN'], array_values($teamMap), ['taicol_taxon_id', '簡化學名', '生長型', 'spcode']);

        $rows = $builder->toBase()->get()
            ->map(fn($r) => self::formatSpeciesRow((array) $r, $headings, $format))
            ->values()
            ->all();

        return ['headings' => $headings, 'rows' => $rows];
    }

    public static function PlantListDistinctForPlots(array $selectedPlots, string $format = 'xlsx'): array
    {
        if (empty($selectedPlots)) return ['headings' => [], 'rows' => []];

        $builder = self::baseQuery($selectedPlots)
            ->groupBy('s.spcode')
            ->selectRaw(self::listSelectSql('1'));

        self::applyListOrder($builder);

        $headings = ['科名', '學名', '中文名', '原生種', '特有種', '歸化種', '栽培種', 'IUCN', 'taicol_taxon_id', '簡化學名', '生長型', 'spcode'];
        $rows = $builder->toBase()->get()
            ->map(fn($r) => self::formatSpeciesRow((array) $r, $headings, $format))
            ->values()
            ->all();

        return ['headings' => $headings, 'rows' => $rows];
    }

    public static function PlantListHabitatPivot(array $selectedPlots, string $format = 'xlsx'): array
    {
        $habCodes = self::habCodes();
        [$habSqls, $habBindings] = self::habitatPivotSql($habCodes);

        $builder = self::baseQuery($selectedPlots)
            ->groupBy('s.spcode')
            ->selectRaw(self::pivotSelectSql('狀態'))
            ->selectRaw(implode(",\n", $habSqls), $habBindings);

        self::applyListOrder($builder);

        $headings = array_merge(['科名', '學名', '中文名', '狀態', 'IUCN'], $habCodes, ['taicol_taxon_id', '簡化學名']);
        $rows = $builder->toBase()->get()->map(function ($r) use ($format, $habCodes) {
            $arr = self::formatScientificName((array) $r, $format);
            $out = [
                '科名' => $arr['科名'] ?? '',
                '學名' => $arr['學名'] ?? '',
                '中文名' => $arr['中文名'] ?? '',
                '狀態' => $arr['狀態'] ?? '',
                'IUCN' => $arr['IUCN'] ?? '',
            ];
            foreach ($habCodes as $code) {
                $out[$code] = $arr['h' . $code] ?? '';
            }
            $out['taicol_taxon_id'] = $arr['taicol_taxon_id'] ?? '';
            $out['簡化學名'] = $arr['簡化學名'] ?? '';
            return $out;
        })->values()->all();

        return ['headings' => $headings, 'rows' => $rows];
    }

    public static function PlantListHabitatPivotWithGroups(array $selectedPlots, string $format = 'xlsx', bool $limitBySelectedPlots = false): array
    {
        $habCodes = self::habCodes();
        [$habSqls, $habBindings] = self::habitatPivotSql($habCodes);
        $plots = $limitBySelectedPlots ? $selectedPlots : [];

        $builder = self::baseQuery($plots)
            ->groupBy('s.spcode')
            ->selectRaw(self::pivotSelectSql('類別') . ",
                MAX(s.plantgroup) AS `__pg`,
                MAX(s.family) AS `__fam`,
                MAX(s.chfamily) AS `__chfam`
            ")
            ->selectRaw(implode(",\n", $habSqls), $habBindings);

        self::applyListOrder($builder);

        $dataRows = $builder->toBase()->get()->map(function ($r) use ($format, $habCodes) {
            $arr = self::formatScientificName((array) $r, $format);
            $row = [
                '科名' => '',
                '學名' => $arr['學名'] ?? '',
                '中文名' => $arr['中文名'] ?? '',
                '類別' => $arr['類別'] ?? '',
                'IUCN' => $arr['IUCN'] ?? '',
            ];
            foreach ($habCodes as $code) {
                $row[$code] = $arr['h' . $code] ?? '';
            }
            $row['taicol_taxon_id'] = $arr['taicol_taxon_id'] ?? '';
            $row['簡化學名'] = $arr['簡化學名'] ?? '';
            $row['生長型'] = $arr['生長型'] ?? '';
            $row['spcode'] = $arr['spcode'] ?? '';
            $row['__pg'] = $arr['__pg'] ?? '';
            $row['__fam'] = $arr['__fam'] ?? '';
            $row['__chfam'] = $arr['__chfam'] ?? '';
            $row['__group'] = 'row';
            return $row;
        })->values()->all();

        $headings = array_merge(['科名', '學名', '中文名', '類別', 'IUCN'], $habCodes, ['taicol_taxon_id', '簡化學名', '生長型', 'spcode'], ['__pg', '__fam', '__chfam', '__group']);
        $blankRow = array_fill_keys($headings, '');
        $rows = [];
        $prevPg = null;
        $prevFam = null;

        foreach ($dataRows as $r) {
            if ($r['__pg'] !== $prevPg) {
                $g = $blankRow;
                $g['科名'] = '【' . $r['__pg'] . '】';
                $g['__group'] = 'pg';
                $rows[] = $g;
                $prevPg = $r['__pg'];
                $prevFam = null;
            }

            if ($r['__fam'] !== $prevFam) {
                $f = $blankRow;
                $f['科名'] = trim(($r['__fam'] ?? '') . ' ' . ($r['__chfam'] ?? ''));
                $f['__group'] = 'fam';
                $rows[] = $f;
                $prevFam = $r['__fam'];
            }

            $rows[] = $r;
        }

        return ['headings' => $headings, 'rows' => $rows];
    }

    private static function baseQuery(array $selectedPlots = [])
    {
        $query = SubPlotPlant2025::query()
            ->from('im_spvptdata_2025 as p')
            ->join('im_splotdata_2025 as e', 'p.plot_full_id', '=', 'e.plot_full_id')
            ->whereNotNull('p.spcode');

        TaiwanChecklistQuery::joinCurrent($query, 'p');
        $query->whereNotNull('s.spcode');

        if (!empty($selectedPlots)) {
            $query->whereIn('e.plot', $selectedPlots);
        }

        return $query;
    }

    private static function listSelectSql(string $mark): string
    {
        return "
            MAX(s.plantgroup) AS pg,
            MAX(s.family) AS family,
            MAX(s.full_name) AS latin,
            MAX(s.full_name) AS full_name,
            MAX(s.canonical_name) AS canonical_name,
            MAX(COALESCE(NULLIF(s.chfamily, ''), s.family)) AS `科名`,
            MAX(s.full_name) AS `學名`,
            MAX(s.canonical_name) AS `簡化學名`,
            MAX(s.chname) AS `中文名`,
            MAX(s.growth_form) AS `生長型`,
            MAX(CASE WHEN " . TaiwanChecklistQuery::nativeExpr('s') . " = 1 THEN '{$mark}' ELSE '' END) AS `原生種`,
            MAX(CASE WHEN " . TaiwanChecklistQuery::endemicExpr('s') . " = 1 THEN '{$mark}' ELSE '' END) AS `特有種`,
            MAX(CASE WHEN " . TaiwanChecklistQuery::naturalizedExpr('s') . " = 1 THEN '{$mark}' ELSE '' END) AS `歸化種`,
            MAX(CASE WHEN " . TaiwanChecklistQuery::cultivatedExpr('s') . " = 1 THEN '{$mark}' ELSE '' END) AS `栽培種`,
            MAX(s.taicol_taxon_id) AS `taicol_taxon_id`,
            MAX(COALESCE(NULLIF(s.spcode_current, ''), s.spcode)) AS `spcode`,
            MAX(s.IUCN) AS `IUCN`
        ";
    }

    private static function pivotSelectSql(string $statusColumn): string
    {
        return "
            MAX(s.plantgroup) AS pg,
            MAX(s.family) AS family,
            MAX(s.full_name) AS latin,
            MAX(s.full_name) AS full_name,
            MAX(s.canonical_name) AS canonical_name,
            MAX(COALESCE(NULLIF(s.chfamily, ''), s.family)) AS `科名`,
            MAX(s.full_name) AS `學名`,
            MAX(s.canonical_name) AS `簡化學名`,
            MAX(s.chname) AS `中文名`,
            MAX(s.growth_form) AS `生長型`,
            MAX(CASE
                WHEN " . TaiwanChecklistQuery::endemicExpr('s') . " = 1 THEN '原生 特有'
                WHEN " . TaiwanChecklistQuery::naturalizedExpr('s') . " = 1 THEN '歸化'
                WHEN " . TaiwanChecklistQuery::cultivatedExpr('s') . " = 1 THEN '栽培'
                WHEN " . TaiwanChecklistQuery::uncertainExpr('s') . " = 1 THEN '不明'
                ELSE '原生'
            END) AS `{$statusColumn}`,
            MAX(s.taicol_taxon_id) AS `taicol_taxon_id`,
            MAX(COALESCE(NULLIF(s.spcode_current, ''), s.spcode)) AS `spcode`,
            MAX(s.IUCN) AS `IUCN`
        ";
    }

    private static function applyListOrder($builder): void
    {
        $pgOrder = self::pgOrder();
        $pgPlaceholders = implode(',', array_fill(0, count($pgOrder), '?'));
        $builder
            ->orderByRaw("FIELD(pg, {$pgPlaceholders})", $pgOrder)
            ->orderBy('family')
            ->orderBy('latin');
    }

    private static function formatSpeciesRow(array $arr, array $headings, string $format): array
    {
        $arr = self::formatScientificName($arr, $format);
        $ordered = [];
        foreach ($headings as $h) {
            $ordered[$h] = $arr[$h] ?? '';
        }
        return $ordered;
    }

    private static function formatScientificName(array $arr, string $format): array
    {
        if ($format === 'xlsx') {
            $html = ScientificNameHelper::italicize($arr['full_name'] ?? ($arr['學名'] ?? ''), $arr['canonical_name'] ?? '');
            $arr['學名'] = self::emHtmlToRichText($html);
        } else {
            $arr['學名'] = self::decodeText((string) ($arr['學名'] ?? ''));
        }

        $arr['簡化學名'] = self::decodeText((string) ($arr['簡化學名'] ?? ($arr['canonical_name'] ?? '')));

        unset($arr['pg'], $arr['family'], $arr['latin'], $arr['full_name'], $arr['canonical_name']);
        return $arr;
    }

    private static function teamMap(): array
    {
        return [
            'NIU' => '國立宜蘭大學',
            'NTU' => '國立臺灣大學',
            'NCHU' => '國立中興大學',
            'NCYU' => '國立嘉義大學',
            'NSYSU' => '國立中山大學',
            'NPUST' => '國立屏東科技大學',
        ];
    }

    private static function pgOrder(): array
    {
        return ['石松類植物', '蕨類植物', '裸子植物', '雙子葉植物', '單子葉植物'];
    }

    private static function habCodes(): array
    {
        return array_map(fn($i) => str_pad((string) $i, 2, '0', STR_PAD_LEFT), range(1, 20));
    }

    private static function habitatPivotSql(array $habCodes): array
    {
        $sqls = [];
        $bindings = [];
        foreach ($habCodes as $code) {
            $alias = 'h' . $code;
            $sqls[] = "MAX(CASE WHEN e.habitat_code = ? THEN e.habitat_code ELSE '' END) AS `{$alias}`";
            $bindings[] = $code;
        }
        return [$sqls, $bindings];
    }

    private static function decodeText(string $text): string
    {
        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function emHtmlToRichText(string $html): RichText
    {
        $rt = new RichText();
        $html = str_replace(["\r", "\n"], ' ', $html);
        $html = str_replace(['&nbsp;'], ' ', $html);
        $html = str_replace(['<em>', '</em>'], ["\x01", "\x02"], $html);
        $parts = preg_split('/(\x01|\x02)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $italic = false;
        foreach ($parts as $p) {
            if ($p === "\x01") { $italic = true; continue; }
            if ($p === "\x02") { $italic = false; continue; }
            if ($p === '') continue;
            $run = $rt->createTextRun(self::decodeText($p));
            $run->getFont()->setItalic($italic);
        }
        return $rt;
    }
}
