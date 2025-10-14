<?php
namespace App\Helpers;


use App\Models\SubPlotEnv2025;
use App\Models\SubPlotPlant2025;
use App\Models\PlotList2025;
use App\Models\PlotHab;

use App\Helpers\HabHelper;

class PlotCompletedCheckHelper
{
    public static function getPlotCompletedInfo(string $plot): array
    {
//1. 沒有錯誤資料

        $dataCorrect = '0';   //1 
        $subPlotImage = '0';   //1
        $subPlotData = '0';   //1
        $plotFile = '0';   //1
        $plotHabData = '0';   //1
        $plotCompleted = '0';   //1

        $prefix = substr($plot, 0, 6);

        $thisEnvData = SubPlotEnv2025::where('plot', $plot)->get();
        $thisPlantData = SubPlotPlant2025::whereRaw('LEFT(plot_full_id, 6) = ?', [$prefix])->get();
        $thisPLotData = PlotList2025::where('plot', $plot)->get();
        $thisHabData = PlotHab::where('plot', $plot)->get();

        if ($thisEnvData) {
            // 1. $dataCorrect：如果 $thisPlantData 有 data_error = 1 的資料
            $dataCorrect = $thisPlantData->contains('data_error', 1) ? '0' : '1';

            // 2. $subPlotImage：檢查 $thisEnvData 中 file_uploaded_at 欄位是否全都有值
            $subPlotImage = $thisEnvData->every(function ($row) {
                return !empty($row->file_uploaded_at);
            }) ? '1' : '0';

            // 3. $plotHabData：比較 habitat_code 是否一致
            $habCodesInEnv = $thisEnvData->pluck('habitat_code')->unique()->sort()->values();
            $habCodesInHab = $thisHabData->pluck('habitat_code')->unique()->sort()->values();
            $plotHabData = $habCodesInEnv->diff($habCodesInHab)->isEmpty() && $habCodesInHab->diff($habCodesInEnv)->isEmpty() ? '1' : '0';

            // 4. $subPlotData：每個 habitat_code 在 $thisEnvData 中出現超過 5 筆
            $grouped = $thisEnvData->groupBy('habitat_code');
            $subPlotData = $habCodesInHab->every(function ($code) use ($grouped) {
                return isset($grouped[$code]) && count($grouped[$code]) > 4;
            }) ? '1' : '0';

            // 5. $plotFile：檢查 $thisPLotData 的 file_uploaded_at 是否有任何非空值
            $plotFile = !empty($thisPLotData->first()?->file_uploaded_at) ? '1' : '0';

        }

        $plotCompleted = (
            $dataCorrect === '1' &&
            $subPlotImage === '1' &&
            $subPlotData === '1' &&
            $plotFile === '1' &&
            $plotHabData === '1'
        ) ? '1' : '0';


        return [
            'dataCorrect'      => $dataCorrect,
            'subPlotImage'   => $subPlotImage,
            'subPlotData'    => $subPlotData,
            'plotFile'       => $plotFile,
            'plotHabData'    => $plotHabData,
            'plotCompleted'  => $plotCompleted,
        ];

    }

    public static function getPlotCompletedInfo_v2(
        string $plot,
        $envDataByPlot,
        $plantDataByPrefix,
        $plotListByPlot,
        $habDataByPlot
    ): array
    {
        $dataCorrect = '0';
        $subPlotImage = '0';
        $subPlotData = '0';
        $plotFile = '0';
        $plotHabData = '0';
        $plotCompleted = '0';
        $plotHasData = '0';
        $plotCensusYear = null;

        $prefix = substr($plot, 0, 6);

        $thisEnvData = $envDataByPlot[$plot] ?? collect();
        $thisPlantData = $plantDataByPrefix[$prefix] ?? collect();
        $thisPLotData = $plotListByPlot[$plot] ?? null;
        $thisHabData = $habDataByPlot[$plot] ?? collect();
// dd($thisEnvData->toArray(), $thisPlantData->toArray(), $thisPLotData->toArray(), $thisHabData->toArray());
        if ($thisEnvData->isNotEmpty()) {
            $dataCorrect = $thisPlantData->contains('data_error', 1) ? '0' : '1';

            $subPlotImage = $thisEnvData->every(fn ($row) => !empty($row->file_uploaded_at)) ? '1' : '0';

            $habCodesInEnv = $thisEnvData->pluck('habitat_code')->unique()->sort()->values();
            $habCodesInHab = $thisHabData->pluck('habitat_code')->unique()->sort()->values();
            $plotHabData = $habCodesInEnv->diff($habCodesInHab)->isEmpty() && $habCodesInHab->diff($habCodesInEnv)->isEmpty() ? '1' : '0';

            $grouped = $thisEnvData->groupBy('habitat_code');
            $subPlotData = $habCodesInHab->every(function ($code) use ($grouped) {
                return isset($grouped[$code]) && count($grouped[$code]) > 4;
            }) ? '1' : '0';

            $plotFile = !empty($thisPLotData?->file_uploaded_at) ? '1' : '0';
            $plotHasData = '1';
            $plotCensusYear = $thisPLotData?->census_year;
        }

        $plotCompleted = (
            $dataCorrect === '1' &&
            $subPlotImage === '1' &&
            $subPlotData === '1' &&
            $plotFile === '1' &&
            $plotHabData === '1'
        ) ? '1' : '0';

        return compact(
            'dataCorrect', 'subPlotImage', 'subPlotData',
            'plotFile', 'plotHabData', 'plotCompleted', 'plotHasData', 'plotCensusYear'
        );
    }

    
}
