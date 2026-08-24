<?php

namespace App\Http\Controllers;

use App\Exports\MissingPlotExport;
use App\Exports\PlantDataExport;
use App\Exports\PlantDataExport2010;
use App\Exports\PlantListDocxExport;
use App\Exports\PlantListExport;
use App\Exports\PlantListExport2010;
use App\Exports\PlantListMultiSheetExport;
use App\Exports\PlantListTableExport;
use App\Exports\PlotExport;
use App\Exports\PlotExport2010;
use App\Exports\StatsChartsPdfExport;
use App\Exports\StatsDocxExport;
use App\Exports\StatsMultiSheetExport;
use App\Models\PlotList2025;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\BooleanCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options as StreamingXlsxOptions;
use OpenSpout\Writer\XLSX\Writer as StreamingXlsxWriter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataDownloadController extends Controller
{
    private const RAW_TYPES = [
        'env2010.xlsx', 'env2010.txt', 'plant2010.xlsx', 'plant2010.txt',
        'plantList2010.xlsx', 'plantList2010.txt', 'env.xlsx', 'env.txt',
        'plant.xlsx', 'plant.txt', 'plantList.xlsx', 'plantList.txt',
        'plantList.docx', 'reasonsTable', 'allPlantList',
    ];

    public function raw(Request $request): Response
    {
        $validated = $request->validate([
            'dataType' => ['required', Rule::in(self::RAW_TYPES)],
            'selectedPlots' => ['required', 'array', 'min:1'],
            'selectedPlots.*' => ['required', 'string', 'max:30'],
            'team' => ['nullable', 'string', 'max:100'],
            'county' => ['nullable', 'string', 'max:100'],
        ]);

        $plots = $this->validPlots($validated['selectedPlots']);
        abort_if($plots === [], 422, '請至少選擇一個有效樣區。');

        $dataType = $validated['dataType'];
        $format = $this->formatFor($dataType);
        $prefix = $this->filenamePrefix($validated['team'] ?? '', $validated['county'] ?? '');

        if ($format === 'docx') {
            $list = PlantListExport::PlantListForWord($plots);

            return (new PlantListDocxExport($list['rows'], '植物名錄'))
                ->download("{$prefix}-plantList.docx");
        }

        [$export, $filename] = $this->rawExport($dataType, $plots, $prefix, $format);

        if ($format === 'txt') {
            return $this->streamText($export, $filename);
        }

        if ($format === 'xlsx' && $dataType !== 'allPlantList') {
            return $this->streamXlsx($export, $filename);
        }

        $writer = $format === 'xlsx' ? Excel::XLSX : Excel::CSV;

        return ExcelFacade::download($export, $filename, $writer);
    }

    private function streamXlsx(object $export, string $filename): Response
    {
        $path = tempnam(sys_get_temp_dir(), 'invasiflora-xlsx-');
        if ($path === false) {
            throw new \RuntimeException('無法建立 XLSX 暫存檔。');
        }

        try {
            $options = new StreamingXlsxOptions();
            $options->DEFAULT_ROW_STYLE = (new Style())
                ->setFontName('Calibri')
                ->setFontSize(11)
                ->setShouldWrapText(false);
            $writer = new StreamingXlsxWriter($options);
            $writer->openToFile($path);

            if (method_exists($export, 'title')) {
                $writer->getCurrentSheet()->setName($export->title());
            }

            $writer->addRow($this->rawXlsxRow($export->headings()));

            if (method_exists($export, 'query')) {
                $export->query()->chunk(500, function ($rows) use ($export, $writer): void {
                    foreach ($rows as $row) {
                        $writer->addRow($this->rawXlsxRow($export->map($row)));
                    }
                });
            } else {
                foreach ($export->array() as $row) {
                    $writer->addRow($this->rawXlsxRow($row));
                }
            }

            $writer->close();
        } catch (\Throwable $exception) {
            if (is_string($path) && is_file($path)) {
                @unlink($path);
            }
            throw $exception;
        }

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ])->deleteFileAfterSend(true);
    }

    private function rawXlsxRow(array $values): Row
    {
        $cells = array_map(static function ($value) {
            if ($value === null || $value === '') {
                return new EmptyCell(null, null);
            }

            if (is_bool($value)) {
                return new BooleanCell($value, null);
            }

            if (is_int($value) || is_float($value)) {
                return new NumericCell($value, null);
            }

            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            } elseif ($value instanceof \DateInterval) {
                $value = $value->format('%r%a days %H:%I:%S');
            } elseif (!is_scalar($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
            }

            $value = (string) $value;
            if (preg_match('/^[+\-]?(\d+\.?\d*|\d*\.?\d+)([Ee][\-+]?[0-2]?\d{1,3})?$/', $value)) {
                $unsigned = ltrim($value, '+-');
                $hasLeadingZero = strlen($unsigned) > 1 && $unsigned[0] === '0' && $unsigned[1] !== '.';
                $tooLargeForInteger = !str_contains($value, '.') && (float) $value > PHP_INT_MAX;

                if (!$hasLeadingZero && !$tooLargeForInteger && is_numeric($value)) {
                    $numeric = strpbrk($value, '.Ee') === false ? (int) $value : (float) $value;
                    return new NumericCell($numeric, null);
                }
            }

            // 文字一律明確指定，避免「=」開頭的原始值被當成公式。
            return new StringCell($value, null);
        }, array_values($values));

        return new Row($cells);
    }

    private function streamText(object $export, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($export): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, $export->headings(), "\t", '"', '\\');
            fflush($output);

            if (method_exists($export, 'query')) {
                $export->query()->chunk(500, function ($rows) use ($export, $output): void {
                    foreach ($rows as $row) {
                        fputcsv($output, $export->map($row), "\t", '"', '\\');
                    }
                    fflush($output);
                });
            } else {
                foreach ($export->array() as $row) {
                    fputcsv($output, $row, "\t", '"', '\\');
                }
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function stats(Request $request): Response
    {
        $validated = $request->validate([
            'format' => ['required', Rule::in(['xlsx', 'docx', 'pdf'])],
            'selectedPlots' => ['required', 'array', 'min:1'],
            'selectedPlots.*' => ['required', 'string', 'max:30'],
            'county' => ['nullable', 'string', 'max:100'],
            'team' => ['nullable', 'string', 'max:100'],
            'censusYear' => ['nullable', 'string', 'max:20'],
        ]);

        $plots = $this->validPlots($validated['selectedPlots']);
        abort_if($plots === [], 422, '請至少選擇一個有效樣區。');

        $scope = ($validated['county'] ?? '') !== ''
            ? $validated['county']
            : ((($validated['team'] ?? '') !== '') ? $validated['team'] : '全部縣市');
        $prefix = $this->safeFilenamePart($scope) . '_' . date('Ymd');
        $county = $this->statsCountyLabel($plots, $validated['censusYear'] ?? '');

        return match ($validated['format']) {
            'xlsx' => ExcelFacade::download(
                new StatsMultiSheetExport($plots, 'xlsx'),
                "{$prefix}-statsTable.xlsx",
                Excel::XLSX
            ),
            'docx' => (new StatsDocxExport($plots, $county))
                ->download("{$prefix}-statsTable.docx"),
            'pdf' => redirect((new StatsChartsPdfExport($plots))
                ->publicDownloadUrl("{$prefix}-statsCharts.pdf")),
        };
    }

    private function statsCountyLabel(array $plots, string $censusYear): string
    {
        $year = $censusYear !== '' ? $censusYear . '年調查' : '全部調查年度';
        $counties = PlotList2025::query()
            ->whereIn('plot', $plots)
            ->whereNotNull('county')
            ->distinct()
            ->orderBy('county')
            ->pluck('county')
            ->filter();

        return $year . '之' . ($counties->isNotEmpty() ? $counties->implode('、') : '未選擇縣市');
    }

    private function validPlots(array $plots): array
    {
        $plots = array_values(array_unique(array_map('strval', $plots)));

        return PlotList2025::query()
            ->whereIn('plot', $plots)
            ->pluck('plot')
            ->map(fn ($plot) => (string) $plot)
            ->values()
            ->all();
    }

    private function formatFor(string $dataType): string
    {
        if ($dataType === 'plantList.docx') return 'docx';
        if (str_ends_with($dataType, '.txt')) return 'txt';

        return 'xlsx';
    }

    private function filenamePrefix(string $team, string $county): string
    {
        $prefix = $team !== '' ? $this->safeFilenamePart($team) . '_' : '';
        $prefix .= $this->safeFilenamePart($county !== '' ? $county : '全部縣市');

        return $prefix . '_' . date('Ymd');
    }

    private function safeFilenamePart(string $value): string
    {
        $value = preg_replace('/[\\x00-\\x1F\\x7F\\\\\/:*?"<>|]+/u', '_', $value) ?? '';

        return trim($value, " ._") ?: '全部縣市';
    }

    private function rawExport(string $dataType, array $plots, string $prefix, string $format): array
    {
        $ext = $format;

        return match ($dataType) {
            'allPlantList' => [new PlantListMultiSheetExport($plots, 'xlsx'), 'allPlantList.xlsx'],
            'reasonsTable' => [new MissingPlotExport($plots, 'xlsx', '小樣區未調查原因'), "{$prefix}-unSurveyedSubplotReasons.xlsx"],
            'env2010.xlsx', 'env2010.txt' => [new PlotExport2010($plots, $format, '2010 環境資料'), "{$prefix}-env2010.{$ext}"],
            'env.xlsx', 'env.txt' => [new PlotExport($plots, $format, '環境資料'), "{$prefix}-env.{$ext}"],
            'plant2010.xlsx', 'plant2010.txt' => [new PlantDataExport2010($plots, $format, '2010 植物資料'), "{$prefix}-plant2010.{$ext}"],
            'plant.xlsx', 'plant.txt' => [new PlantDataExport($plots, $format, '植物資料'), "{$prefix}-plant.{$ext}"],
            'plantList2010.xlsx', 'plantList2010.txt' => $this->plantList2010Export($plots, $prefix, $format),
            'plantList.xlsx', 'plantList.txt' => $this->plantListExport($plots, $prefix, $format),
        };
    }

    private function plantList2010Export(array $plots, string $prefix, string $format): array
    {
        $list = PlantListExport2010::PlantListDistinctForPlots($plots, $format);

        return [
            new PlantListTableExport($list['rows'], '2010 植物名錄', $list['headings'], '', "\t"),
            "{$prefix}-plantList2010.{$format}",
        ];
    }

    private function plantListExport(array $plots, string $prefix, string $format): array
    {
        $list = PlantListExport::PlantListDistinctForPlots($plots, $format);

        return [
            new PlantListTableExport($list['rows'], '植物名錄', $list['headings'], '', "\t"),
            "{$prefix}-plantList.{$format}",
        ];
    }
}
