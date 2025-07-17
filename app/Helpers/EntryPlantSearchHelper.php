<?php
namespace App\Helpers;

use App\Models\SpInfo;
use App\Models\SpcodeIndex;

class EntryPlantSearchHelper
{

    public static function entryPlantNameSearchHelper($value)
    {
        // 🔍 1. 開頭比對 (priority 1)
        $startsWith = SpInfo::where(function ($query) use ($value) {
                $query->where('chname', 'like', "{$value}%")->whereNotNull('chname');
            })
            ->limit(20)
            ->get()
            ->flatMap(function ($item) {
                $list = [];
                if ($item->chname) {
                    $list[] = [
                        'family' => $item->chfamily,
                        'label' => $item->chname,
                        'value' => $item->chname,
                        'hint' => $item->chname.' / '.$item->chfamily,
                        'spcode' => $item->spcode,
                    ];
                }
                return $list;
            });

        // 🔍 2. 包含比對，但排除已出現過的
        $contains = SpInfo::where(function ($query) use ($value) {
                $query->where('chname', 'like', "%{$value}%")->whereNotNull('chname');
            })
            ->limit(50)
            ->get()
            ->flatMap(function ($item) {
                $list = [];
                if ($item->chname) {
                    $list[] = [
                        'family' => $item->chfamily,
                        'label' => $item->chname,
                        'value' => $item->chname,
                        'hint' => $item->chname.' / '.$item->chfamily,
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
                'spinfo.chfamily as chfamily',
                'spinfo.chname as chname',
                'spcode_index.chname_index as chname_index',
                'spcode_index.spcode as spcode'
            )
            ->limit(20)
            ->get()
            ->map(function ($row) {
                return [
                    'family' => $row->chfamily,
                    'label' => $row->chname_index.' / '.$row->chname,
                    'value' => $row->chname_index,
                    'hint' => $row->chname.' / '.$row->chfamily,
                    'spcode' => $row->spcode,
                ];
            });

        // 🔍 4. 學名 比對

        $simnameMatches = SpInfo::where(function ($query) use ($value) {
                $query->where('simname', 'like', "%{$value}%")->whereNotNull('chname');
            })
            ->limit(20)
            ->get()
            ->flatMap(function ($item) {
                $list = [];
                if ($item->chname) {
                    $list[] = [
                        'family' => $item->chfamily,
                        'label' => $item->simname.' / '.$item->chname,
                        'value' => $item->chname,
                        'hint' => $item->chname.' / '.$item->chfamily,
                        'spcode' => $item->spcode,
                    ];
                }
                return $list;
            });

        $subMatches = $indexMatches->concat($simnameMatches);

        // ✅ 4. 合併主名與別名結果
        $merged = $mainMatches
            ->merge($subMatches)
            ->sortByDesc(fn($item) => $item['label'] === $value ? 1 : 0) // 完全符合優先
            ->values();

        return $merged->toArray();
    }
 

}
