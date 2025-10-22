<?php
namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Arrayable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Illuminate\Support\Facades\DB;
use App\Models\SubPlotMissing;
use App\Models\Reasons;


class MissingPlotExport implements FromQuery, WithMapping, WithHeadings, WithCustomCsvSettings, WithTitle
{
    protected array $reasonLabelMap = [];

    public function __construct(
        protected array   $selectedPlots,
        protected string  $format,
        protected string  $title  = '小樣方未調查原因',
        protected array   $excluded = ['created_by','created_at','updated_at','updated_by'],
        protected ?array  $headings = null // 若想固定表頭，可在建構子直接給
    ) {}

    public function query(): Builder
    {
        // 建立一次：原因碼 → 階層標籤
        if (empty($this->reasonLabelMap)) {
            $rows   = Reasons::query()->orderBy('level')->orderBy('code')->get();
            $byCode = $rows->keyBy('code');

            $buildLabel = function ($code) use ($byCode) {
                $chain = [];
                $cur = $byCode[$code] ?? null;
                while ($cur) {
                    array_unshift($chain, $cur->title); // 父 / 子 / 孫
                    if (empty($cur->parent_code)) break;
                    $cur = $byCode[$cur->parent_code] ?? null;
                }
                return implode(' / ', $chain); // 例：「地被覆蓋度低 / 完全沒有地被」
            };

            $this->reasonLabelMap = $rows
                ->mapWithKeys(fn($r) => [$r->code => $buildLabel($r->code)])
                ->all(); // 轉成純陣列
        }

        return SubPlotMissing::whereIn('plot', $this->selectedPlots);
    }

    // === 每列映射為陣列（會自動排除不需要的欄位） ===
    public function map($row): array
    {
        $arr = $row instanceof Arrayable ? $row->toArray() : (array)$row;

        // 先排除不需要的欄位
        $arr = collect($arr)->except($this->excluded)->all();

        // 根據 not_done_reason 產生階層標籤
        $code  = $arr['not_done_reason_code'] ?? null;
        $label = $code !== null ? ($this->reasonLabelMap[$code] ?? '') : '';

        // 將 title 插在 not_done_reason 後方
        $out = [];
        foreach ($arr as $k => $v) {
            $out[$k] = $v;
            if ($k === 'not_done_reason_code') {
                $out['title'] = $label;
            }
        }
        // 若沒有 not_done_reason 欄位，放在最後
        if (!array_key_exists('not_done_reason_code', $arr)) {
            $out['title'] = $label;
        }

        return $out;
    }

    // === 表頭 ===
    public function headings(): array
    {
        if ($this->headings !== null) return $this->headings;

        // 動態抓第一列的鍵當表頭（跑一次輕量 query）
        $first = $this->query()->clone()->limit(1)->get()
            ->map(fn($r) => $this->map($r))
            ->first();

        return $first ? array_keys($first) : ['資料為空'];
    }

    // === CSV/TXT 設定 ===
    public function getCsvSettings(): array
    {
        return ['delimiter' => $this->format === 'txt' ? "\t" : ','];
    }

    public function title(): string
    {
        return $this->title;
    }
}

