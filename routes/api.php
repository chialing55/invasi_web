<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\SpInfo;

// routes/web.php 或 api.php
Route::get('/plant-suggestions', function (Request $request) {
    $value = $request->get('q');

    $data = SpInfo::where(function ($query) use ($value) {
        $query->where('chname', 'like', "%$value%")
              ->orWhere('simname', 'like', "%$value%");
    })
    ->limit(10)
    ->get()
    ->flatMap(function ($item) {
        $list = [];
        if ($item->chname) {
            $list[] = [
                'family' => $item->chfamily,
                'label' => $item->chname,
                'spcode' => $item->spcode,
                'type' => 'chname',
            ];
        }
        if ($item->simname) {
            $list[] = [
                'family' => $item->chfamily,
                'label' => $item->simname,
                'spcode' => $item->spcode,
                'type' => 'simname',
            ];
        }
        return $list;
    })->values()->toArray();

    if (empty($data)) {
        $data = SpInfo::where(function ($query) use ($value) {
            $query->where('family', 'like', "%$value%")
                  ->orWhere('chfamily', 'like', "%$value%");
        })
        ->limit(10)
        ->get()
        ->flatMap(function ($item) {
            $list = [];
            $list[] = [
                'family' => $item->chfamily,
                'label' => $item->chname,
                'spcode' => $item->spcode,
                'type' => 'family',
            ];
            return $list;
        })->values()->toArray();
    }

    return response()->json($data);
});


