<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Exports\PlotExport;
use App\Exports\PlantDataExport;
use App\Exports\PlantListExport;
use App\Support\HabitatShannonIndex;
use App\Exports\Sheets\HabitatShannonIndexSheet; 
use App\Support\HabitatIVIndex;
use App\Support\FloraGroupStats;
use App\Support\FloraChartData;
use App\Support\FloraIVISupport;


           // 或改用你分拆後的 Stats\HabitatStats
use App\Exports\StatsTableExport;

class StatsMultiSheetExport implements WithMultipleSheets
{
    public function __construct(
        protected array $selectedPlots,
        protected string $format
    ) {}

    public function sheets(): array
    {
        $sheets = [];
        // A) 全部物種
        $all = FloraGroupStats::taxonLifeformSummaryByQuery(
            selectedPlots: $this->selectedPlots,
            mode: 'all'
        );
        if (!empty($all['rows'])) {
            $sheets[] = new StatsTableExport(
                rows: $all['rows'],
                title: '類群×特性（全部）',
                headings: $all['headings'],
                numberCols: [],              // 需要兩位小數的欄位才填
                fillEmptyWithZero: true,
                layouts: ['row-groups', 'merge-a1b1'] 
            );
        }

        // B) 只有歸化（含不含栽培可切換）
        $alien = FloraGroupStats::taxonLifeformSummaryByQuery(
            selectedPlots: $this->selectedPlots,
            mode: 'alien-only',
            includeCultivated: false   // 要把栽培也算外來就改 true
        );
        if (!empty($alien['rows'])) {
            $sheets[] = new StatsTableExport(
                rows: $alien['rows'],
                title: '類群×特性（歸化）',
                headings: $alien['headings'],
                numberCols: [],              // 需要兩位小數的欄位才填
                fillEmptyWithZero: true,
                layouts: ['row-groups', 'merge-a1b1']  
            );
        }

        // dd($sheets);

        // 例：生育地 Shannon 指數
        $rows = HabitatShannonIndex::buildHabitatShannonIndexByQuery(
            selectedPlots: $this->selectedPlots,
            logBase      : 'e',

        );

        if (!empty($rows)) {
            $sheets[] = new StatsTableExport(
                rows: $rows,
                title: '生育地多樣性指數',
                headings: null, // 用 rows 第一列鍵名
                numberCols: ['歸化種數比例(%)','歸化物種平均覆蓋度(%)','Shannon_歸化','Shannon_原生','Shannon_全部'],
                fillEmptyWithZero: true,
                layouts: ['two-row-group-header'],
                headerGroups: ['Shannon_' => 'Shannon index']
            );
        }

        // 生育地 × 歸化物種重要值 Top10
        $data = HabitatIVIndex::alienImportanceTopNByQuery(
            selectedPlots: $this->selectedPlots,
            topN: 10,
            labelField: 'chname',      // 想用拉丁名就改 'latinname'
            includeCultivated: false   // 要含栽培改 true
        );
        if (!empty($data['rows'])) {
            $sheets[] = new StatsTableExport(
                rows: $data['rows'],
                title: '生育地歸化物種IV',
                headings: $data['headings'],
                layouts: ['rowWrap']
            );
        }

        // 之後再加其他統計：
        // $rows2 = SpeciesStats::summaryByQuery($this->selectedPlots);
        // if ($rows2) $sheets[] = new StatsTableExport($rows2, '物種彙整', null, ['平均覆蓋度(%)','物種豐度']);

        // 取得資料（Top 10 歸化優勢科）
        $support = FloraChartData::topNaturalizedFamilies($this->selectedPlots, 10);
        // 1) 明細表（用你現有的 StatsTableExport）
        if (!empty($support)) {
            $sheets[] = new StatsTableExport(
                rows:     $support['rows'],
                title:    '歸化物種優勢科 Top 10',
                headings: $support['headings'],

            );

            // 2) 圖表頁
            // $sheets[] = new StatsFigExport(
            //     rows: $support['rows'],
            //     title: '歸化物種優勢科 Top 10',
            //     headings: $support['headings'],
            //     chartSpec: [
            //         'type'       => 'column',
            //         'category'   => '植物科名',
            //         'series'     => [['name' => '物種數', 'value' => '物種數']],
            //         'legend'     => 'none',
            //         'dataLabels' => true,
            //         'xTitle'     => '植物科名',
            //         'yTitle'     => '物種數',
            //         'position'   => ['topLeft'=>'D2','bottomRight'=>'M22'],
            //     ]
            // );
    
        }
//草本小樣方歸化物種重要數值表
        $ivi = FloraIVISupport::iviTable(
            selectedPlots: $this->selectedPlots,
            habMode: 'herb',            // herb | wood | wood-08 | wood-09 | all
            includeCultivated: false,
        );

        $sheets[] = new StatsTableExport(
            rows: $ivi['rows'],
            title: '草本小樣方歸化物種重要數值表',
            headings: $ivi['headings'],
            numberCols: ['平均覆蓋度(%)'=> 3,'相對覆蓋度(%)'=> 3,'相對頻度(%)'=> 3,'IVI 重要值(%)'=> 3],
            fillEmptyWithZero: false,
            layouts: ['showZeros']
        );

        $ivi2 = FloraIVISupport::iviTable(
            selectedPlots: $this->selectedPlots,
            habMode: 'wood-08',            // herb | wood | wood-08 | wood-09 | all
            includeCultivated: false,
        );

        $sheets[] = new StatsTableExport(
            rows: $ivi2['rows'],
            title: '木本(08)小樣方歸化物種重要數值表',
            headings: $ivi2['headings'],
            numberCols: ['平均覆蓋度(%)'=> 3,'相對覆蓋度(%)'=> 3,'相對頻度(%)'=> 3,'IVI 重要值(%)'=> 3],
            fillEmptyWithZero: false,
            layouts: ['showZeros']
        );  
        
        $ivi3 = FloraIVISupport::iviTable(
            selectedPlots: $this->selectedPlots,
            habMode: 'wood-09',            // herb | wood | wood-08 | wood-09 | all
            includeCultivated: false,
            
        );

        $sheets[] = new StatsTableExport(
            rows: $ivi3['rows'],
            title: '木本(09)小樣方歸化物種重要數值表',
            headings: $ivi3['headings'],
            numberCols: ['平均覆蓋度(%)'=> 3,'相對覆蓋度(%)'=> 3,'相對頻度(%)'=> 3,'IVI 重要值(%)'=> 3],
            fillEmptyWithZero: false,
            layouts: ['showZeros']
        ); 
        return $sheets;
    }
}

