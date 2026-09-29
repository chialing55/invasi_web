<?php

namespace App\Helpers;

use App\Models\PlotHab;
use App\Models\PlotHabitatCompletionException;
use App\Models\PlotList2025;
use App\Models\SubPlotEnv2025;
use App\Models\SubPlotPlant2025;
use App\Support\HabitatCompletionThreshold;

class PlotCompletedCheckHelper
{
    public static function getPlotCompletedInfo(string $plot): array
    {
        // 1. 沒有錯誤資料

        $dataCorrect = '0';   // 1
        $subPlotImage = '0';   // 1
        $subPlotData = '0';   // 1
        $plotFile = '0';   // 1
        $plotHabData = '0';   // 1
        $plotCompleted = '0';   // 1

        $prefix = substr($plot, 0, 6);

        $thisEnvData = SubPlotEnv2025::where('plot', $plot)->get();
        $thisPlantData = SubPlotPlant2025::whereRaw('LEFT(plot_full_id, 6) = ?', [$prefix])->get();
        $thisPLotData = PlotList2025::where('plot', $plot)->get();
        $thisHabData = PlotHab::where('plot', $plot)->get();
        $completionExceptions = PlotHabitatCompletionException::query()
            ->where('plot', $plot)
            ->pluck('actual_subplot_count', 'habitat_code');
        $habCodesInHab = $thisHabData->pluck('habitat_code')->unique()->sort()->values();
        $habitatThresholds = $habCodesInHab->mapWithKeys(fn ($code) => [
            (string) $code => HabitatCompletionThreshold::requiredCount((string) $code, $completionExceptions),
        ])->all();

        if ($thisEnvData->isNotEmpty()) {
            // 1. $dataCorrect：任何非 0 的 data_error 都代表資料尚未修正。
            $dataCorrect = self::hasPlantDataError($thisPlantData) ? '0' : '1';

            // 2. $subPlotImage：檢查 $thisEnvData 中 file_uploaded_at 欄位是否全都有值
            $subPlotImage = $thisEnvData->every(function ($row) {
                return ! empty($row->file_uploaded_at);
            }) ? '1' : '0';

            // 3. $plotHabData：比較 habitat_code 是否一致
            $habCodesInEnv = $thisEnvData->pluck('habitat_code')->unique()->sort()->values();
            $plotHabData = $habCodesInEnv->diff($habCodesInHab)->isEmpty() && $habCodesInHab->diff($habCodesInEnv)->isEmpty() ? '1' : '0';

            // 4. $subPlotData：每個 habitat_code 達到一般門檻 5 或已登錄的例外門檻。
            $grouped = $thisEnvData->groupBy('habitat_code');
            $subPlotData = $habCodesInHab->every(function ($code) use ($grouped, $habitatThresholds) {
                $required = $habitatThresholds[(string) $code] ?? HabitatCompletionThreshold::DEFAULT_COUNT;

                return isset($grouped[$code])
                    && $grouped[$code]->pluck('plot_full_id')->unique()->count() >= $required;
            }) ? '1' : '0';

            // 5. $plotFile：檢查 $thisPLotData 的 file_uploaded_at 是否有任何非空值
            $plotFile = ! empty($thisPLotData->first()?->file_uploaded_at) ? '1' : '0';

        }

        $plotCompleted = (
            $dataCorrect === '1' &&
            $subPlotImage === '1' &&
            $subPlotData === '1' &&
            $plotFile === '1' &&
            $plotHabData === '1'
        ) ? '1' : '0';

        return [
            'dataCorrect' => $dataCorrect,
            'subPlotImage' => $subPlotImage,
            'subPlotData' => $subPlotData,
            'plotFile' => $plotFile,
            'plotHabData' => $plotHabData,
            'plotCompleted' => $plotCompleted,
            'habitatThresholds' => $habitatThresholds,
        ];

    }

    public static function getPlotCompletedInfoForPlots($plots)
    {
        $plotList = collect($plots)
            ->filter()
            ->unique()
            ->values();

        if ($plotList->isEmpty()) {
            return collect();
        }

        $plotListByPlot = PlotList2025::whereIn('plot', $plotList)->get()->keyBy('plot');
        $envDataByPlot = SubPlotEnv2025::whereIn('plot', $plotList)->get()->groupBy('plot');
        $habDataByPlot = PlotHab::whereIn('plot', $plotList)->get()->groupBy('plot');
        $completionExceptionsByPlot = PlotHabitatCompletionException::query()
            ->whereIn('plot', $plotList)
            ->get()
            ->groupBy('plot');
        $prefixes = $plotList->map(fn ($plot) => substr($plot, 0, 6))->unique()->values();

        $plantQuery = SubPlotPlant2025::query();
        $prefixes->each(function ($prefix, $index) use ($plantQuery) {
            $method = $index === 0 ? 'where' : 'orWhere';
            $plantQuery->{$method}('plot_full_id', 'like', $prefix.'%');
        });

        $plantDataByPrefix = $plantQuery
            ->get()
            ->groupBy(fn ($row) => substr($row->plot_full_id, 0, 6));

        return $plotList
            ->map(function ($plot) use (
                $envDataByPlot,
                $plantDataByPrefix,
                $plotListByPlot,
                $habDataByPlot,
                $completionExceptionsByPlot
            ) {
                $plotRow = $plotListByPlot[$plot] ?? null;

                if (! $plotRow) {
                    return null;
                }

                $status = self::getPlotCompletedInfo_v2(
                    $plot,
                    $envDataByPlot,
                    $plantDataByPrefix,
                    $plotListByPlot,
                    $habDataByPlot,
                    $completionExceptionsByPlot
                );

                return array_merge($plotRow->toArray(), $status);
            })
            ->filter()
            ->values();
    }

    public static function getPlotCompletedInfo_v2(
        string $plot,
        $envDataByPlot,
        $plantDataByPrefix,
        $plotListByPlot,
        $habDataByPlot,
        $completionExceptionsByPlot
    ): array {
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
        $completionExceptions = ($completionExceptionsByPlot[$plot] ?? collect())
            ->pluck('actual_subplot_count', 'habitat_code');
        $habCodesInHab = $thisHabData->pluck('habitat_code')->unique()->sort()->values();
        $habitatThresholds = $habCodesInHab->mapWithKeys(fn ($code) => [
            (string) $code => HabitatCompletionThreshold::requiredCount((string) $code, $completionExceptions),
        ])->all();
        // dd($thisEnvData->toArray(), $thisPlantData->toArray(), $thisPLotData->toArray(), $thisHabData->toArray());
        if ($thisEnvData->isNotEmpty()) {
            $dataCorrect = self::hasPlantDataError($thisPlantData) ? '0' : '1';

            $subPlotImage = $thisEnvData->every(fn ($row) => ! empty($row->file_uploaded_at)) ? '1' : '0';

            $habCodesInEnv = $thisEnvData->pluck('habitat_code')->unique()->sort()->values();
            $plotHabData = $habCodesInEnv->diff($habCodesInHab)->isEmpty() && $habCodesInHab->diff($habCodesInEnv)->isEmpty() ? '1' : '0';

            $grouped = $thisEnvData->groupBy('habitat_code');
            $subPlotData = $habCodesInHab->every(function ($code) use ($grouped, $habitatThresholds) {
                $required = $habitatThresholds[(string) $code] ?? HabitatCompletionThreshold::DEFAULT_COUNT;

                return isset($grouped[$code])
                    && $grouped[$code]->pluck('plot_full_id')->unique()->count() >= $required;
            }) ? '1' : '0';

            $plotFile = ! empty($thisPLotData?->file_uploaded_at) ? '1' : '0';
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
            'plotFile', 'plotHabData', 'plotCompleted', 'plotHasData', 'plotCensusYear',
            'habitatThresholds'
        );
    }

    public static function hasPlantDataError(iterable $plantData): bool
    {
        foreach ($plantData as $row) {
            if ((int) data_get($row, 'data_error', 0) !== 0) {
                return true;
            }
        }

        return false;
    }
}
