<?php

namespace App\Exports;

use App\Support\FloraChartData;
use RuntimeException;
use Symfony\Component\Process\Process;

class StatsChartsPdfExport
{
    private array $tempFiles = [];

    public function __construct(private array $selectedPlots) {}

    public function publicDownloadUrl(string $filename): string
    {
        $downloadName = $this->safeFilename($filename);
        $relativePath = 'invasi_files/exports/' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '/' . $downloadName;
        $pdfPath = public_path($relativePath);

        $dir = dirname($pdfPath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('統計圖 PDF 產生失敗：無法建立下載目錄。');
        }

        $this->buildPdf($pdfPath);

        return route('file.download', ['path' => $relativePath]);
    }

    private function buildPdf(string $pdfPath): string
    {
        $rscript = $this->findRscript();
        $script = resource_path('scripts/stats_charts.R');
        if (!is_file($script)) {
            throw new RuntimeException('統計圖 PDF 產生失敗：找不到 R 繪圖腳本。');
        }

        $resourceFontDir = resource_path('fonts');
        $storageFontDir = storage_path('app/fonts');
        $chineseFont = $this->firstExistingFont([
            $resourceFontDir . '/NotoSansCJK-Regular.ttc',
            $resourceFontDir . '/NotoSerifCJK-Regular.ttc',
            $storageFontDir . '/NotoSansCJK-Regular.ttc',
            $storageFontDir . '/NotoSerifCJK-Regular.ttc',
            $storageFontDir . '/kaiu.ttf',
            '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.ttc',
            '/usr/share/fonts/opentype/noto/NotoSerifCJK-Regular.ttc',
        ]);
        $times = $this->firstExistingFont([
            $resourceFontDir . '/times.ttf',
            $resourceFontDir . '/Times New Roman.ttf',
            $resourceFontDir . '/Times_New_Roman.ttf',
            $storageFontDir . '/times.ttf',
            $storageFontDir . '/Times New Roman.ttf',
            $storageFontDir . '/Times_New_Roman.ttf',
        ]);
        if ($chineseFont === null || $times === null) {
            throw new RuntimeException('統計圖 PDF 產生失敗：缺少 resources/fonts/NotoSansCJK-Regular.ttc 或 times.ttf。');
        }

        $fig1 = FloraChartData::topNaturalizedFamilies($this->selectedPlots, 10);
        $fig2 = FloraChartData::lowElevationNaturalizedFamilyComparison($this->selectedPlots, 500, 15);

        $fig1Csv = $this->tempPath('stats-fig1-', '.csv');
        $fig2Csv = $this->tempPath('stats-fig2-', '.csv');
        $this->writeCsv($fig1Csv, ['植物科名', '物種數'], $fig1['rows'] ?? []);
        $this->writeCsv($fig2Csv, ['植物科名', '前次調查', '本次調查'], $fig2['rows'] ?? []);

        $fontCacheDir = $this->tempDir('fontconfig-cache-');
        $process = new Process([$rscript, $script, $fig1Csv, $fig2Csv, $pdfPath, $chineseFont, $times]);
        $process->setEnv([
            'XDG_CACHE_HOME' => $fontCacheDir,
            'FONTCONFIG_CACHE' => $fontCacheDir,
        ]);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful() || !is_file($pdfPath)) {
            throw new RuntimeException('統計圖 PDF 產生失敗：' . trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        $this->cleanup();

        return $pdfPath;
    }

    private function firstExistingFont(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function safeFilename(string $filename): string
    {
        $filename = basename(str_replace(chr(92), chr(47), $filename));
        $filename = preg_replace('/[^\pL\pN._-]+/u', '-', $filename) ?: 'statsCharts.pdf';
        $filename = trim($filename, '-');

        return str_ends_with(strtolower($filename), '.pdf') ? $filename : $filename . '.pdf';
    }

    private function findRscript(): string
    {
        $candidates = array_filter([
            env('RSCRIPT_PATH'),
            '/usr/bin/Rscript',
            '/usr/local/bin/Rscript',
        ]);

        foreach ($candidates as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        $process = Process::fromShellCommandline('command -v Rscript');
        $process->run();
        $path = trim($process->getOutput());
        if ($process->isSuccessful() && $path !== '') {
            return $path;
        }

        throw new RuntimeException('統計圖 PDF 產生失敗：伺服器找不到 Rscript，請安裝 R 或設定 RSCRIPT_PATH。');
    }

    private function writeCsv(string $path, array $headings, array $rows): void
    {
        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new RuntimeException('統計圖 PDF 產生失敗：無法建立暫存 CSV。');
        }

        fputcsv($handle, $headings);
        foreach ($rows as $row) {
            $row = (array) $row;
            fputcsv($handle, array_map(fn($heading) => $row[$heading] ?? '', $headings));
        }
        fclose($handle);
    }

    private function tempPath(string $prefix, string $suffix, bool $track = true): string
    {
        $base = tempnam(sys_get_temp_dir(), $prefix);
        if ($base === false) {
            throw new RuntimeException('統計圖 PDF 產生失敗：無法建立暫存檔。');
        }

        $path = $base . $suffix;
        @rename($base, $path);
        if ($track) {
            $this->tempFiles[] = $path;
        }
        return $path;
    }

    private function tempDir(string $prefix): string
    {
        $base = tempnam(sys_get_temp_dir(), $prefix);
        if ($base === false) {
            throw new RuntimeException('統計圖 PDF 產生失敗：無法建立暫存目錄。');
        }

        @unlink($base);
        if (!mkdir($base, 0775, true) && !is_dir($base)) {
            throw new RuntimeException('統計圖 PDF 產生失敗：無法建立暫存目錄。');
        }

        $this->tempFiles[] = $base;
        return $base;
    }

    private function cleanup(): void
    {
        foreach (array_reverse($this->tempFiles) as $file) {
            $this->removePath($file);
        }
        $this->tempFiles = [];
    }

    private function removePath(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $this->removePath($path . DIRECTORY_SEPARATOR . $item);
        }
        @rmdir($path);
    }

    public function __destruct()
    {
        $this->cleanup();
    }
}
