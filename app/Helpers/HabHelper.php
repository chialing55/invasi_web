<?php

// app/Helpers/HabHelper.php

namespace App\Helpers;

use App\Models\HabitatInfo;
use App\Support\HabitatCode;

class HabHelper
{
    /**
     * 根據樣區代碼清單回傳 habitat code + label 的對照表
     */
    public static function habitatOptions(array $plotHabList): array
    {
        $habTypeMap = HabitatInfo::get(['habitat_code', 'habitat'])
            ->mapWithKeys(fn ($item) => [HabitatCode::mapKey($item->habitat_code) => $item->habitat]);

        return collect($plotHabList)
            ->filter(fn ($code) => isset($habTypeMap[HabitatCode::mapKey($code)]))
            ->mapWithKeys(fn ($code) => [HabitatCode::mapKey($code) => $habTypeMap[HabitatCode::mapKey($code)]])
            // ->map(fn($label, $code) => $code . ' ' . $label)
            ->sortKeys()
            ->toArray();
    }
}
