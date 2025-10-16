<?php
namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Arrayable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Support\Facades\DB;
use App\Models\SubPlotPlant2025;
use App\Models\PlotList2025;
use App\Models\SubPlotEnv2025;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use App\Support\SpNameHelper;

class PlantListExport implements FromQuery, WithMapping, WithHeadings, WithCustomCsvSettings, WithTitle
{
    public function __construct(
        protected array  $selectedPlots,
        protected string $type, // 可傳入外部準備好的 Builder；也可先給 null，之後自己實作 query()
        protected string   $format,
        protected string   $title  = '植物名錄',
        protected bool     $mergeFamily, // ← 只有名錄需要
        protected array    $excluded = [
            'island_category','plot_env','validation_message','created_by','created_at',
            'updated_at','updated_by','file_uploaded_at','file_uploaded_by','data_error'
        ],
        protected ?array   $headings = null
    ) {}

    private ?array $computedHeadings = null;
    private function teamMap(): array
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

    private function snCols(): array
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

    public function query(): Builder
    {
        if ($this->type == '1'){  //全部資料

        // 1) 固定的 team 對照（順序就是輸出欄位順序）
        $teamMap = $this->teamMap();

        // 2) 動態產生各 team 欄位 SQL（顯示 V；要改成 ⭕/◎ 自行替換）
        $teamSqls = [];
        $bindings = [];
        foreach ($teamMap as $code => $label) {
            $colName = str_replace('`','``', $label); // 欄名跳脫，避免有標點
            $teamSqls[] = "MAX(CASE WHEN e.team = ? THEN 'V' ELSE '' END) AS `{$colName}`";
            $bindings[] = $code;
        }
        $groups = ['石松類植物','蕨類植物','裸子植物','雙子葉植物','單子葉植物'];
        $placeholders = implode(',', array_fill(0, count($groups), '?')); // "?,?,?,?,?"

        $snCols = $this->snCols();
        // 動態組 selectRaw，避免手寫
        $snSelects = array_map(fn($c) => "MAX(spinfo.$c) AS sn_$c", $snCols);        

        // 3) 主查詢：僅以 spcode 分組，其餘欄位用 MAX()（相容 ONLY_FULL_GROUP_BY）
        $builder = SubPlotPlant2025::query()
            ->join('im_splotdata_2025 as e', 'im_spvptdata_2025.plot_full_id', '=', 'e.plot_full_id')
            ->join('spinfo', 'im_spvptdata_2025.spcode', '=', 'spinfo.spcode')
            ->leftJoin('twredlist2017 as r', 'im_spvptdata_2025.spcode', '=', 'r.spcode')
            ->whereNotNull('spinfo.spcode')
            ->groupBy('spinfo.spcode')
            ->selectRaw("
                -- 這三個是排序用的 ASCII 別名
                MAX(spinfo.plantgroup) AS pg,
                MAX(spinfo.family)     AS fam,
                MAX(spinfo.latinname)  AS latin,

                -- 連結學名用
                MAX(spinfo.spcode)     AS spcode,

                -- 以下是實際輸出的中文欄
                MAX(COALESCE(NULLIF(spinfo.chfamily,''), spinfo.family)) AS `科名`,
                MAX(spinfo.latinname)                                    AS `學名`,
                MAX(spinfo.chname)                                       AS `中文名`,
                MAX(
                    CASE WHEN spinfo.naturalized!='1'
                        AND spinfo.cultivated!='1'
                        AND (spinfo.uncertain IS NULL OR spinfo.uncertain!='1')
                    THEN '◎' ELSE '' END
                )                                                        AS `原生種`,
                MAX(CASE WHEN spinfo.endemic='1' THEN '◎' ELSE '' END)     AS `特有種`,
                MAX(CASE WHEN spinfo.naturalized='1' THEN '◎' ELSE '' END) AS `歸化種`,
                MAX(CASE WHEN spinfo.cultivated='1'  THEN '◎' ELSE '' END) AS `栽培種`,
                MAX(CASE 
                        WHEN spinfo.naturalized='1' OR spinfo.cultivated='1' THEN 'NA'
                        ELSE r.IUCN
                    END
                )                                                        AS `IUCN`
            ")
            ->selectRaw(implode(",\n", $snSelects))   // ★ 加入 sn_* 欄位
            ->selectRaw(implode(",\n", $teamSqls), $bindings)  // 你的 team 欄位
            // 依：plantgroup(自訂順序) → family → 學名
            ->orderByRaw("FIELD(pg, {$placeholders})", $groups)
            ->orderBy('fam')
            ->orderBy('latin');

            
            // 告訴 Export 固定表頭順序（中文）
            $this->headings = array_merge(
                ['科名','學名','中文名','原生種','特有種','歸化種','栽培種','IUCN'],
                array_values($teamMap)
            );

            return $builder;

        } else {
            return SubPlotPlant2025::query()
                // 用 join 取代子查詢，效率與可讀性都更好
                ->join('im_splotdata_2025 as e', 'im_spvptdata_2025.plot_full_id', '=', 'e.plot_full_id')
                ->leftJoin('spinfo as s', 'im_spvptdata_2025.spcode', '=', 's.spcode')       // 建議 leftJoin，避免被吃光
                ->leftJoin('twredlist2017 as r', 'im_spvptdata_2025.spcode', '=', 'r.spcode')
                ->whereNotNull('s.spcode')   // 或 whereNotNull('s.family')
                ->whereIn('e.plot', $this->selectedPlots)

                // 只取名錄需要的欄位（避免 DISTINCT 負擔）
                ->select([
                    // 's.spcode',  // 若要唯一鍵可以加
                    's.plantgroup',
                    's.family',
                    's.chfamily',
                    's.latinname',
                    's.chname',
                    's.growth_form',
                    DB::raw("
                        CASE 
                        WHEN s.naturalized != '1' 
                        AND s.cultivated  != '1' 
                        AND (s.uncertain IS NULL OR s.uncertain != '1')
                        THEN 1 ELSE 0 
                        END AS native
                    "),
                    's.endemic',
                    's.naturalized',
                    's.cultivated',
                    DB::raw("
                        CASE 
                        WHEN s.naturalized = '1' OR s.cultivated = '1' THEN 'NA'
                        ELSE r.IUCN
                        END AS IUCN
                    "),
                ])
                ->distinct()                       // 取唯一物種列
                ->orderBy('s.family')
                ->orderBy('s.latinname');
        }
    }

    public function map($row): array
    {
        // 取一份可修改的陣列
        $arr = $row instanceof \Illuminate\Contracts\Support\Arrayable ? $row->toArray() : (array) $row;

        $snCols= $this->snCols();
        // 只在 xlsx 才做 RichText；其餘格式保留純文字
        if ($this->format === 'xlsx') {
            // 從 sn_* 還原成 SpNameHelper 需要的鍵名
            $sn = [];
            foreach ($snCols as $k) {
                $sn[$k] = $arr["sn_$k"] ?? '';
            }
            $sn['spcode'] = $arr['spcode'] ?? '';

            // 用你的 helper 生出含 <em> 的學名 HTML
            $nameHtml = \App\Support\SpNameHelper::combine($sn)['name'];

            // 轉成 Excel 原生 RichText（<em> → 斜體）
            $arr['學名'] = $this->emHtmlToRichText($nameHtml);
        }

        // 依 headings 輸出數值陣列
        $out = [];
        foreach ($this->headings() as $h) {
            $out[] = $arr[$h] ?? null;
        }
        return $out;
    }

    private function emHtmlToRichText(string $html): RichText
    {
        $rt = new RichText();

        // 先做最基本清理
        $html = str_replace(["\r","\n"], ' ', $html);
        $html = str_replace(['&nbsp;'], ' ', $html);

        // 把 <em>…</em> 轉成簡單標記後切分（不用複雜 HTML parser）
        $html = str_replace(['<em>','</em>'], ["\x01","\x02"], $html);

        $parts = preg_split('/(\x01|\x02)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $italic = false;

        foreach ($parts as $p) {
            if ($p === "\x01") { $italic = true;  continue; }
            if ($p === "\x02") { $italic = false; continue; }

            if ($p === '') continue;
            $run = $rt->createTextRun($p);
            $run->getFont()->setItalic($italic);
        }

        return $rt;
    }

    public function headings(): array
    {
        // 若外部有明確傳入，就直接用
        if ($this->headings !== null) return $this->headings;

        // 用快取避免重算
        if ($this->computedHeadings !== null) return $this->computedHeadings;

        // type==1：我們要固定欄位（含各校）
        if ($this->type === '1') {
            return $this->computedHeadings = array_merge(
                ['科名','學名','中文名','原生種','特有種','歸化種','栽培種','IUCN'],
                array_values($this->teamMap())
            );
        }

        // 其他型別：fallback 用第一列推表頭
        $first = $this->query()->clone()->limit(1)->get()
            ->map(fn($r) => $this->map($r))
            ->first();

        return $this->computedHeadings = ($first ? array_map(fn($i) => "欄位{$i}", array_keys($first)) : ['資料為空']);
    }


    public function getCsvSettings(): array
    {
        return ['delimiter' => $this->format === 'txt' ? "\t" : ','];
    }

    public function title(): string
    {
        return $this->title;
    }

    public function registerEvents(): array
    {
        if ($this->format !== 'xlsx' || !$this->mergeFamily) return [];

        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // ① 從工作表實際表頭找目標欄（避免與 headings() 不一致）
                $lastCol = $sheet->getHighestDataColumn();
                $lastIdx = Coordinate::columnIndexFromString($lastCol);

                $targets = ['科名', 'family'];  // 兼容舊表頭
                $familyIdx = null;

                for ($i = 1; $i <= $lastIdx; $i++) {
                    $col = Coordinate::stringFromColumnIndex($i);
                    $val = trim((string)$sheet->getCell("{$col}1")->getValue());
                    if (in_array($val, $targets, true)) { $familyIdx = $i; break; }
                }
                if ($familyIdx === null) return;

                $col = Coordinate::stringFromColumnIndex($familyIdx);
                $highest = $sheet->getHighestRow();
                if ($highest <= 2) return;

                // ② 合併連續相同的「科名」儲存格（值先 trim）
                $start = 2;
                $prev  = trim((string)$sheet->getCell("{$col}{$start}")->getCalculatedValue());

                for ($r = 3; $r <= $highest; $r++) {
                    $cur = trim((string)$sheet->getCell("{$col}{$r}")->getCalculatedValue());

                    if ($cur !== $prev) {
                        if ($r - 1 > $start) {
                            $range = "{$col}{$start}:{$col}".($r - 1);
                            $sheet->mergeCells($range);
                            $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                        }
                        $start = $r;
                        $prev  = $cur;
                    }
                }
                // 收尾
                if ($highest >= $start) {
                    $range = "{$col}{$start}:{$col}{$highest}";
                    $sheet->mergeCells($range);
                    $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                }
            },
        ];
    }


}
