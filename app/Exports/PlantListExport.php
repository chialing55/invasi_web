<?php
/**
 * ──────────────────────────────────────────────────────────────────────────────
 *  PlantList：改為「陣列輸出」與「多工作表」匯出範例
 *  - 將原本的 PlantListExport（FromQuery/WithMapping）重構為回傳 ['headings','rows'] 的靜態函式
 *  - 以 PlantListTableExport(FromArray) + StatsSheetLayouts(可選 layout) 處理樣式/群組
 *  - PlantListMultiSheetExport 組裝多分頁
 *
 *  檔案：App\Exports\PlantListExport.php（靜態陣列產生器）
 *       App\Exports\PlantListTableExport.php（通用陣列→工作表）
 *       App\Exports\PlantListMultiSheetExport.php（多工作表）
 *       App\Support\StatsSheetLayouts.php（樣式與群組的版面配置）
 * ──────────────────────────────────────────────────────────────────────────────
 */

// =============================================================================
// File: app/Exports/PlantListExport.php
// 目的：提供靜態方法，依不同用途，回傳 ['headings' => [...], 'rows' => [...]]
// =============================================================================

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use App\Models\SubPlotPlant2025;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use App\Support\SpNameHelper;

class PlantListExport
{
    /**
     * 類群×特性（全部 or 指定樣區）。對應原 type=1。
     * $mode = 'all'（忽略樣區過濾）或 'selected'（限制在 $selectedPlots）。
     */
    public static function PlantListAll(array $selectedPlots, string $format = 'xlsx'): array
    {

        // 物種大類排序順序（自訂）
        $pgOrder = ['石松類植物','蕨類植物','裸子植物','雙子葉植物','單子葉植物'];
        $pgPlaceholders = implode(',', array_fill(0, count($pgOrder), '?'));

        // sn_* 欄位僅供產生 RichText 用，不進 headings
        $snCols     = self::snCols();
        $snSelects  = array_map(fn($c) => "MAX(s.$c) AS sn_$c", $snCols);

        // Base Query
        $base = SubPlotPlant2025::query()
            ->from('im_spvptdata_2025 as p')
            ->join('im_splotdata_2025 as e', 'p.plot_full_id', '=', 'e.plot_full_id')
            ->join('spinfo as s', 'p.spcode', '=', 's.spcode')
            ->leftJoin('twredlist2017 as r', 'p.spcode', '=', 'r.spcode')
            ->whereNotNull('s.spcode');

        // Team pivot 欄位
        $teamMap   = self::teamMap();
        $teamSqls  = [];
        $bindings  = [];
        foreach ($teamMap as $code => $label) {
            $colName   = str_replace('`','``', $label);
            $teamSqls[] = "MAX(CASE WHEN e.team = ? THEN 'V' ELSE '' END) AS `{$colName}`";
            $bindings[] = $code;
        }

        $builder = (clone $base)
            ->groupBy('s.spcode')
            ->selectRaw("
                MAX(s.plantgroup) AS pg,
                MAX(s.family)     AS family,
                MAX(s.latinname)  AS latin,
                MAX(s.spcode)     AS spcode,

                MAX(COALESCE(NULLIF(s.chfamily,''), s.family)) AS `科名`,
                MAX(s.latinname) AS `學名`,
                MAX(s.chname)    AS `中文名`,

                MAX(
                    CASE WHEN s.naturalized!='1'
                         AND s.cultivated!='1'
                         AND (s.uncertain IS NULL OR s.uncertain!='1')
                    THEN '◎' ELSE '' END
                ) AS `原生種`,
                MAX(CASE WHEN s.endemic='1' THEN '◎' ELSE '' END)     AS `特有種`,
                MAX(CASE WHEN s.naturalized='1' THEN '◎' ELSE '' END) AS `歸化種`,
                MAX(CASE WHEN s.cultivated='1'  THEN '◎' ELSE '' END) AS `栽培種`,
                MAX(CASE WHEN s.naturalized='1' OR s.cultivated='1' THEN 'NA' ELSE r.IUCN END) AS `IUCN`
            ")
            ->selectRaw(implode(",\n", $snSelects))
            ->selectRaw(implode(",\n", $teamSqls), $bindings)
            ->orderByRaw("FIELD(pg, {$pgPlaceholders})", $pgOrder)
            ->orderBy('family')
            ->orderBy('latin');

        $rows = $builder
            ->toBase()               // ← 關鍵：不要回傳 Eloquent Model
            ->get()
            ->map(function ($r) use ($format, $snCols, $teamMap) {
                $arr = (array) $r;   // stdClass → 只有你選的欄位

                if ($format === 'xlsx') {
                    $sn = [];
                    foreach ($snCols as $k) $sn[$k] = $arr["sn_$k"] ?? '';
                    $sn['spcode'] = $arr['spcode'] ?? '';
                    $nameHtml = SpNameHelper::combine($sn)['name'] ?? ($arr['學名'] ?? '');
                    $arr['學名'] = self::emHtmlToRichText($nameHtml);
                }

                // 清掉中繼欄
                foreach ($snCols as $k) unset($arr["sn_$k"]);
                unset($arr['pg'], $arr['family'], $arr['latin'], $arr['spcode']);

                // 只輸出需要的欄位，並照 headings 排序
                $ordered = [];
                $headings = array_merge(
                    ['科名','學名','中文名','原生種','特有種','歸化種','栽培種','IUCN'],
                    array_values($teamMap)
                );
                foreach ($headings as $h) $ordered[$h] = $arr[$h] ?? '';

                return $ordered;
            })
            ->values()
            ->all();


        $headings = array_merge(
            ['科名','學名','中文名','原生種','特有種','歸化種','栽培種','IUCN'],
            array_values($teamMap)
        );
        // dd($rows);
        return ['headings' => $headings, 'rows' => $rows];
    }

    /**
     * 名錄（僅所選樣區去重）。對應原 type=2。
     */
    public static function PlantListDistinctForPlots(array $selectedPlots, string $format = 'xlsx'): array
    {
        if (empty($selectedPlots)) return ['headings' => [], 'rows' => []];
        // sn_* 欄位僅供產生 RichText 用，不進 headings
        // 物種大類排序順序（自訂）
        $pgOrder = self::pgOrder();
        $pgPlaceholders = implode(',', array_fill(0, count($pgOrder), '?'));

        // sn_* 欄位僅供產生 RichText 用，不進 headings
        $snCols     = self::snCols();
        $snSelects  = array_map(fn($c) => "MAX(s.$c) AS sn_$c", $snCols);

        // Base Query
        $base = SubPlotPlant2025::query()
            ->from('im_spvptdata_2025 as p')
            ->join('im_splotdata_2025 as e', 'p.plot_full_id', '=', 'e.plot_full_id')
            ->join('spinfo as s', 'p.spcode', '=', 's.spcode')
            ->leftJoin('twredlist2017 as r', 'p.spcode', '=', 'r.spcode')
            ->whereNotNull('s.spcode')
            ->whereIn('e.plot', $selectedPlots);

        $builder = (clone $base)
            ->groupBy('s.spcode')
            ->selectRaw("
                MAX(s.plantgroup) AS pg,
                MAX(s.family)     AS family,
                MAX(s.latinname)  AS latin,
                MAX(s.spcode)     AS spcode,

                MAX(COALESCE(NULLIF(s.chfamily,''), s.family)) AS `科名`,
                MAX(s.latinname) AS `學名`,
                MAX(s.chname)    AS `中文名`,

                MAX(
                    CASE WHEN s.naturalized!='1'
                         AND s.cultivated!='1'
                         AND (s.uncertain IS NULL OR s.uncertain!='1')
                    THEN '1' ELSE '' END
                ) AS `原生種`,
                MAX(CASE WHEN s.endemic='1' THEN '1' ELSE '' END)     AS `特有種`,
                MAX(CASE WHEN s.naturalized='1' THEN '1' ELSE '' END) AS `歸化種`,
                MAX(CASE WHEN s.cultivated='1'  THEN '1' ELSE '' END) AS `栽培種`,
                MAX(CASE WHEN s.naturalized='1' OR s.cultivated='1' THEN 'NA' ELSE r.IUCN END) AS `IUCN`
            ")
            ->selectRaw(implode(",\n", $snSelects))
            ->orderByRaw("FIELD(pg, {$pgPlaceholders})", $pgOrder)
            ->orderBy('family')
            ->orderBy('latin');

        $rows = $builder
            ->toBase()               // ← 關鍵：不要回傳 Eloquent Model
            ->get()
            ->map(function ($r) use ($format, $snCols) {
                $arr = (array) $r;   // stdClass → 只有你選的欄位

                if ($format === 'xlsx') {
                    $sn = [];
                    foreach ($snCols as $k) $sn[$k] = $arr["sn_$k"] ?? '';
                    $sn['spcode'] = $arr['spcode'] ?? '';
                    $nameHtml = SpNameHelper::combine($sn)['name'] ?? ($arr['學名'] ?? '');
                    $arr['學名'] = self::emHtmlToRichText($nameHtml);
                }

                // 清掉中繼欄
                foreach ($snCols as $k) unset($arr["sn_$k"]);
                unset($arr['pg'], $arr['family'], $arr['latin'], $arr['spcode']);

                // 只輸出需要的欄位，並照 headings 排序
                $ordered = [];
                $headings = array_merge(
                    ['科名','學名','中文名','原生種','特有種','歸化種','栽培種','IUCN'],
                );
                foreach ($headings as $h) $ordered[$h] = $arr[$h] ?? '';

                return $ordered;
            })
            ->values()
            ->all();


        $headings = array_merge(
            ['科名','學名','中文名','原生種','特有種','歸化種','栽培種','IUCN'],
        );
        // dd($rows);
        return ['headings' => $headings, 'rows' => $rows];
    }

    /**
     * 棲地代碼 01~20 pivot。對應原 type=3。
     */
    public static function PlantListHabitatPivot(array $selectedPlots, string $format = 'xlsx'): array
    {
        // 01~20
        $habCodes = array_map(fn($i) => str_pad((string)$i, 2, '0', STR_PAD_LEFT), range(1, 20));
        $habSqls = [];
        $habBindings = [];
        foreach ($habCodes as $code) {
            $alias = 'h'.$code;
            $habSqls[] = "MAX(CASE WHEN e.habitat_code = ? THEN e.habitat_code ELSE '' END) AS `{$alias}`";
            $habBindings[] = $code;
        }

        $pgOrder = self::pgOrder();
        $pgPlaceholders = implode(',', array_fill(0, count($pgOrder), '?'));
        $snCols    = self::snCols();
        $snSelects = array_map(fn($c) => "MAX(s.$c) AS sn_$c", $snCols);

        $base = SubPlotPlant2025::query()
            ->from('im_spvptdata_2025 as p')
            ->join('im_splotdata_2025 as e', 'p.plot_full_id', '=', 'e.plot_full_id')
            ->join('spinfo as s', 'p.spcode', '=', 's.spcode')
            ->leftJoin('twredlist2017 as r', 'p.spcode', '=', 'r.spcode')
            ->whereNotNull('s.spcode');

        $builder = (clone $base)
            ->groupBy('s.spcode')
            ->selectRaw("
                MAX(s.plantgroup) AS pg,
                MAX(s.family)     AS family,
                MAX(s.latinname)  AS latin,
                MAX(s.spcode)     AS spcode,
                MAX(COALESCE(NULLIF(s.chfamily,''), s.family)) AS `科名`,
                MAX(s.latinname)  AS `學名`,
                MAX(s.chname)     AS `中文名`,
                MAX(CASE WHEN s.endemic='1' THEN '原生 特有'
                         WHEN s.naturalized='1' THEN '歸化'
                         WHEN s.cultivated='1'  THEN '栽培'
                         ELSE '原生' END) AS `狀態`,
                MAX(CASE WHEN s.naturalized='1' OR s.cultivated='1' THEN 'NA' ELSE r.IUCN END) AS `IUCN`
            ")
            ->selectRaw(implode(",\n", $snSelects))
            ->selectRaw(implode(",\n", $habSqls), $habBindings)
            ->orderByRaw("FIELD(pg, {$pgPlaceholders})", $pgOrder)
            ->orderBy('family')
            ->orderBy('latin');

        if (!empty($selectedPlots)) {
            $builder->whereIn('e.plot', $selectedPlots);
        }

        $rows = $builder->toBase()->get()->map(function ($r) use ($format, $snCols, $habCodes) {
            $arr = (array) $r;

            if ($format === 'xlsx') {
                $sn = [];
                foreach ($snCols as $k) { $sn[$k] = $arr["sn_$k"] ?? ''; }
                $sn['spcode'] = $arr['spcode'] ?? '';
                $nameHtml = SpNameHelper::combine($sn)['name'] ?? ($arr['學名'] ?? '');
                $arr['學名'] = self::emHtmlToRichText($nameHtml);
            }

            foreach ($snCols as $k) unset($arr["sn_$k"]);
            unset($arr['pg'], $arr['family'], $arr['latin'], $arr['spcode']);

            // 轉換 h01→'01' ... h20→'20'
            $out = [
                '科名'   => $arr['科名'] ?? '',
                '學名'   => $arr['學名'] ?? '',
                '中文名' => $arr['中文名'] ?? '',
                '狀態'   => $arr['狀態'] ?? '',
                'IUCN'   => $arr['IUCN'] ?? '',
            ];
            foreach ($habCodes as $code) {
                $out[$code] = $arr['h'.$code] ?? '';
            }
            return $out;
        })->toArray();

        $headings = array_merge(['科名','學名','中文名','狀態','IUCN'], $habCodes);
        return ['headings' => $headings, 'rows' => $rows];
    }

    /**
     * 棲地代碼 01~20 pivot + 群組列（需要輔助欄 __pg/__fam/__chfam）。對應原 type=4。
     */
    public static function PlantListHabitatPivotWithGroups(array $selectedPlots, string $format = 'xlsx'): array
    {
        // 01~20 habitat pivot 欄
        $habCodes = array_map(fn($i) => str_pad((string)$i, 2, '0', STR_PAD_LEFT), range(1, 20));
        $habSqls = []; $habBindings = [];
        foreach ($habCodes as $code) {
            $alias = 'h'.$code;
            $habSqls[]     = "MAX(CASE WHEN e.habitat_code = ? THEN e.habitat_code ELSE '' END) AS `{$alias}`";
            $habBindings[] = $code;
        }

        $pgOrder         = self::pgOrder();
        $pgPlaceholders  = implode(',', array_fill(0, count($pgOrder), '?'));
        $snCols          = self::snCols();
        $snSelects       = array_map(fn($c) => "MAX(s.$c) AS sn_$c", $snCols);

        $base = SubPlotPlant2025::query()
            ->from('im_spvptdata_2025 as p')
            ->join('im_splotdata_2025 as e', 'p.plot_full_id', '=', 'e.plot_full_id')
            ->join('spinfo as s', 'p.spcode', '=', 's.spcode')
            ->leftJoin('twredlist2017 as r', 'p.spcode', '=', 'r.spcode')
            ->whereNotNull('s.spcode');

        $builder = (clone $base)
            ->groupBy('s.spcode')
            ->selectRaw("
                MAX(s.plantgroup) AS pg,
                MAX(s.family)     AS family,
                MAX(s.chfamily)   AS chfamily,
                MAX(s.latinname)  AS latin,
                MAX(s.spcode)     AS spcode,

                MAX(COALESCE(NULLIF(s.chfamily,''), s.family)) AS `科名`,
                MAX(s.latinname)  AS `學名`,
                MAX(s.chname)     AS `中文名`,
                MAX(CASE WHEN s.endemic='1'     THEN '原生  特有'
                        WHEN s.naturalized='1' THEN '歸化'
                        WHEN s.cultivated='1'  THEN '栽培'
                        ELSE '原生' END) AS `類別`,
                MAX(CASE WHEN s.naturalized='1' OR s.cultivated='1' THEN 'NA' ELSE r.IUCN END) AS `IUCN`,

                -- 群組用輔助欄
                MAX(s.plantgroup) AS `__pg`,
                MAX(s.family)     AS `__fam`,
                MAX(s.chfamily)   AS `__chfam`
            ")
            ->selectRaw(implode(",\n", $snSelects))
            ->selectRaw(implode(",\n", $habSqls), $habBindings)
            ->orderByRaw("FIELD(pg, {$pgPlaceholders})", $pgOrder)
            ->orderBy('family')
            ->orderBy('latin');

        // 先取「一般資料列」
        $dataRows = $builder->toBase()->get()->map(function ($r) use ($format, $snCols, $habCodes) {
            $arr = (array) $r;

            if ($format === 'xlsx') {
                $sn = [];
                foreach ($snCols as $k) { $sn[$k] = $arr["sn_$k"] ?? ''; }
                $sn['spcode'] = $arr['spcode'] ?? '';
                $nameHtml = \App\Support\SpNameHelper::combine($sn)['name'] ?? ($arr['學名'] ?? '');
                $arr['學名'] = self::emHtmlToRichText($nameHtml);
            }
            foreach ($snCols as $k) unset($arr["sn_$k"]);

            $row = [
                '科名'   => '',
                '學名'   => $arr['學名'] ?? '',
                '中文名' => $arr['中文名'] ?? '',
                '類別'   => $arr['類別'] ?? '',
                'IUCN'   => $arr['IUCN'] ?? '',
            ];
            foreach ($habCodes as $code) {
                $row[$code] = $arr['h'.$code] ?? '';
            }
            // 保留輔助欄，等下要用來決定插入群組列
            $row['__pg']    = $arr['__pg'] ?? '';
            $row['__fam']   = $arr['__fam'] ?? '';
            $row['__chfam'] = $arr['__chfam'] ?? '';
            $row['__group'] = 'row';   // 標記一般資料列
            return $row;
        })->values()->all();

        // 接著：在陣列中插入「類群」與「科名」標頭列
        $rows = [];
        $prevPg = null; $prevFam = null;

        // 先準備空白列模板（讓群組列能有所有欄位）
        $headings = array_merge(['科名','學名','中文名','類別','IUCN'], $habCodes, ['__pg','__fam','__chfam','__group']);
        $blankRow = array_fill_keys($headings, '');

        foreach ($dataRows as $r) {
            if ($r['__pg'] !== $prevPg) {
                // 插入【類群】行：寫在「科名」欄，之後交給樣式合併 A~最後一欄
                $g = $blankRow;
                $g['科名']   = '【'.$r['__pg'].'】';
                $g['__group'] = 'pg';
                $rows[] = $g;

                $prevPg  = $r['__pg'];
                $prevFam = null; // 類群變了，強制下個 family 也插一次
            }

            if ($r['__fam'] !== $prevFam) {
                // 插入 Family 標頭列（顯示 family + chfamily）
                $f = $blankRow;
                $f['科名']    = trim(($r['__fam'] ?? '').' '.($r['__chfam'] ?? ''));
                $f['__group'] = 'fam';
                $rows[] = $f;

                $prevFam = $r['__fam'];
            }

            // 真正的物種資料列（保留 __pg/__fam/__chfam 給需要的樣式；或可清掉）
            $rows[] = $r;
        }

        // 表頭：加入 __group（AfterSheet 會把它隱藏）
        return ['headings' => $headings, 'rows' => $rows];
    }


    // ──────────────────────── Private helpers ─────────────────────────

    private static function teamMap(): array
    {
        return [
            'NIU'   => '國立宜蘭大學',
            'NTU'   => '國立臺灣大學',
            'NCHU'  => '國立中興大學',
            'NCYU'  => '國立嘉義大學',
            'NSYSU' => '國立中山大學',
            'NPUST' => '國立屏東科技大學',
        ];
    }


    private static function snCols(): array
    {
        return [
            'genus','species',
            'autsp1','autsp2',
            'ssp','autssp1','autssp2',
            'var','autvar1','autvar2',
            'subvar','autsubvar1','autsubvar2',
            'f','autf1','autf2',
            'cv','autcv1','autcv2'
        ];
    }

    private static function pgOrder(): array
    {
        return [
            '石松類植物','蕨類植物','裸子植物','雙子葉植物','單子葉植物'
        ];
    }

    private static function emHtmlToRichText(string $html): RichText
    {
        $rt = new RichText();
        $html = str_replace(["\r","\n"], ' ', $html);
        $html = str_replace(['&nbsp;'], ' ', $html);
        $html = str_replace(['<em>','</em>'], ["\x01","\x02"], $html);
        $parts = preg_split('/(\x01|\x02)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $italic = false;
        foreach ($parts as $p) {
            if ($p === "\x01") { $italic = true; continue; }
            if ($p === "\x02") { $italic = false; continue; }
            if ($p === '') continue;
            $run = $rt->createTextRun($p);
            $run->getFont()->setItalic($italic);
        }
        return $rt;
    }
}
