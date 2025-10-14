<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Exports\PlotExport;
use App\Exports\PlantDataExport;
use App\Exports\PlantListExport;
use App\Support\AnalysisHelper;
use App\Exports\Sheets\HabitatShannonIndexSheet; 

class MultiSheetExport implements WithMultipleSheets
{
    protected $envdata;
    protected $plantdata;
    protected $plantlist;
    protected $format;
    protected $selectedPlots;

    public function __construct(array $selectedPlots, string $format)
    {
        $this->selectedPlots = $selectedPlots;
        // $this->envdata = $envdata;
        // $this->plantdata = $plantdata;
        // $this->plantlist = $plantlist;
        $this->format = $format;
    }

    public function sheets(): array
    {
        $sheets = [
            new PlotExport($this->selectedPlots, $this->format, '環境資料'),
            new PlantDataExport($this->selectedPlots, $this->format, '植物資料'),
            new PlantListExport($this->selectedPlots, '2', $this->format, '植物名錄', false), // 不合併科名
        ];

        // 加上分析活頁（如果有資料）
        // if ($analysisSheet = $this->analysisSheet()) {
        //     $sheets[] = $analysisSheet;
        // }

        return $sheets;
    }

    protected function analysisSheet(): ?HabitatShannonIndexSheet
    {
        $rows = AnalysisHelper::buildHabitatShannonIndexByQuery(
            selectedPlots: $this->selectedPlots,
            weightByArea : false,           // 要面積加權改 true
            logBase      : 'e',
            areaField    : null             // 有面積欄位就填入，例如 'subplot_area_m2'
        );

        return empty($rows) ? null : new HabitatShannonIndexSheet($rows, '生育地多樣性指數');
    }
}
