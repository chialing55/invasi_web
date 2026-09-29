<?php

namespace App\Http\Controllers;

use App\Models\PlotList2025;
use App\Models\SubPlotEnv2025;
use App\Support\PlotFileAccess;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FileController extends Controller
{
    private const DOCUMENTS = [
        'survey-guide' => 'survey-guide.pdf',
        'habitat-record' => '生育地類型紀錄表.pdf',
        'subplot-record' => '小樣方調查紀錄表.pdf',
        'plant-record' => '植物調查空白紀錄表.pdf',
    ];

    public function plot(Request $request, string $plot): BinaryFileResponse
    {
        $plotRow = PlotList2025::where('plot', $plot)->firstOrFail();
        abort_unless(PlotFileAccess::allows($request->user(), (string) $plotRow->team), 403);

        return $this->inline("plotData/{$plotRow->county}/{$plotRow->plot}.pdf");
    }

    public function subplotPhoto(Request $request, string $plotFullId): BinaryFileResponse
    {
        $subplot = SubPlotEnv2025::where('plot_full_id', $plotFullId)->firstOrFail();
        $plotRow = PlotList2025::where('plot', $subplot->plot)->firstOrFail();
        abort_unless(PlotFileAccess::allows($request->user(), (string) $plotRow->team), 403);

        $habitat = substr($plotFullId, 6, 2);
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
            $path = "subPlotPhoto/{$plotRow->county}/{$plotRow->plot}/{$habitat}/{$plotFullId}.{$extension}";
            if ($this->disk()->exists($path)) {
                return $this->inline($path);
            }
        }

        abort(404);
    }

    public function document(string $document): BinaryFileResponse
    {
        abort_unless(isset(self::DOCUMENTS[$document]), 404);

        return $this->inline('files/'.self::DOCUMENTS[$document]);
    }

    public function export(Request $request, string $token): BinaryFileResponse
    {
        abort_unless(preg_match('/^[a-f0-9]{32}$/D', $token) === 1, 404);
        $path = $request->session()->pull("file_exports.{$token}");
        abort_unless(is_string($path) && str_starts_with($path, "exports/{$token}-"), 404);

        $response = $this->download($path);
        $response->deleteFileAfterSend(true);

        return $response;
    }

    private function inline(string $path): BinaryFileResponse
    {
        abort_unless($this->disk()->exists($path), 404);

        return response()->file($this->disk()->path($path), $this->noCacheHeaders());
    }

    private function download(string $path): BinaryFileResponse
    {
        abort_unless($this->disk()->exists($path), 404);

        return response()->download($this->disk()->path($path), null, $this->noCacheHeaders());
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk('invasi_files');
    }

    private function noCacheHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }
}
