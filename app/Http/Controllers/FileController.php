<?php 

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class FileController extends Controller
{
    public function download($path)
    {
        $fullPath = public_path($path);
        abort_unless(File::exists($fullPath), 404);
        return response()->download($fullPath);
    }
    
    public function view($path)
    {
        $fullPath = public_path($path);
        abort_unless(File::exists($fullPath), 404);
        return response()->file($fullPath);
    }
    
    
}
