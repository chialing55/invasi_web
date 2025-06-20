<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Helpers\EntryPlantSearchHelper;

// routes/web.php 或 api.php
Route::get('/plant-suggestions', function (Request $request) {
    $value = $request->get('q');

    $data = EntryPlantSearchHelper::entryPlantNameSearchHelper($value);

    return response()->json($data);
});


