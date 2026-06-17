<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;

class FileController extends Controller
{
    public function download($path)
    {
        $fullPath = $this->resolveAllowedPublicPath($path);

        return response()->download($fullPath);
    }

    public function view($path)
    {
        $fullPath = $this->resolveAllowedPublicPath($path);

        return response()->file($fullPath);
    }

    private function resolveAllowedPublicPath(string $path): string
    {
        $normalized = str_replace(chr(92), chr(47), ltrim($path, chr(47)));

        abort_if(str_contains($normalized, '../') || str_starts_with($normalized, '..'), 404);

        $fullPath = realpath(public_path($normalized));
        abort_unless($fullPath && File::exists($fullPath) && File::isFile($fullPath), 404);

        $allowedRoots = [
            realpath(public_path('invasi_files/plotData')),
            realpath(public_path('invasi_files/subPlotPhoto')),
            realpath(public_path('invasi_files/exports')),
        ];

        foreach (array_filter($allowedRoots) as $root) {
            if (str_starts_with($fullPath, $root . DIRECTORY_SEPARATOR)) {
                return $fullPath;
            }
        }

        abort(404);
    }
}
