<?php
namespace App\Helpers;

use App\Models\SpInfo;
use App\Models\SpcodeIndex;

class PlantSearchHelper
{

    public static function plantNameSearchHelper($value)
    {
        // 🔍 1. 開頭比對 (priority 1)
        $startsWith = SpInfo::where(function ($query) use ($value) {
                $query->where('chname', 'like', "{$value}%")
                    ->orWhere('simname', 'like', "{$value}%");
            })
            ->limit(20)
            ->get()
            ->flatMap(function ($item) {
                $list = [];
                if ($item->chname) {
                    $list[] = [
                        'family' => $item->chfamily,
                        'label' => $item->chname,
                        'spcode' => $item->spcode,
                    ];
                }
                if ($item->simname) {
                    $list[] = [
                        'family' => $item->chfamily,
                        'label' => $item->simname,
                        'spcode' => $item->spcode,
                    ];
                }
                return $list;
            });

        // 🔍 2. 包含比對，但排除已出現過的
        $contains = SpInfo::where(function ($query) use ($value) {
                $query->where('chname', 'like', "%{$value}%")
                    ->orWhere('simname', 'like', "%{$value}%");
            })
            ->limit(50)
            ->get()
            ->flatMap(function ($item) {
                $list = [];
                if ($item->chname) {
                    $list[] = [
                        'family' => $item->chfamily,
                        'label' => $item->chname,
                        'spcode' => $item->spcode,
                    ];
                }
                if ($item->simname) {
                    $list[] = [
                        'family' => $item->chfamily,
                        'label' => $item->simname,
                        'spcode' => $item->spcode,
                    ];
                }
                return $list;
            })
            ->reject(function ($item) use ($startsWith) {
                return $startsWith->contains('label', $item['label']);
            });

        $mainMatches = $startsWith->concat($contains);

        // 🔍 3. chname_index 比對
        $indexMatches = SpcodeIndex::query()
            ->where('chname_index', 'like', "%{$value}%")
            ->join('spinfo', 'spcode_index.spcode', '=', 'spinfo.spcode')
            ->select(
                'spinfo.chfamily as family',
                'spcode_index.chname_index as label',
                'spcode_index.spcode as spcode'
            )
            ->limit(20)
            ->get()
            ->map(function ($row) {
                return [
                    'family' => $row->family,
                    'label' => $row->label,
                    'spcode' => $row->spcode,
                ];
            });

        // ✅ 4. 合併主名與別名結果
        $merged = $mainMatches
            ->merge($indexMatches)
            ->sortByDesc(fn($item) => $item['label'] === $value ? 1 : 0) // 完全符合優先
            ->values();

        // 🔍 5. fallback: 用 family 查找
        if ($merged->isEmpty()) {
            $merged = SpInfo::where(function ($query) use ($value) {
                    $query->where('family', 'like', "%$value%")
                        ->orWhere('chfamily', 'like', "%$value%");
                })
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    return [
                        'family' => $item->chfamily,
                        'label' => $item->chname,
                        'spcode' => $item->spcode,
                    ];
                });
        }

        return $merged->toArray();
    }
 

}
