<?php

namespace App\Livewire\Rules;

trait SubPlotEnvFormRules
{
    protected function subPlotEnvRules(): array
    {
        return [
            'subPlotEnvForm.plot' => 'required',
            'subPlotEnvForm.date' => 'required|date',
            'subPlotEnvForm.investigator' => 'required|string|max:255',
            'subPlotEnvForm.recorder' => 'required|string|max:255',
            'subPlotEnvForm.tm2_x' => 'required|numeric',
            'subPlotEnvForm.tm2_y' => 'required|numeric',
            // 'subPlotEnvForm.gps_error' => 'nullable|numeric',
            'subPlotEnvForm.habitat_code' => 'required|string|max:2|in:01,02,03,04,05,06,07,08,09,10,11,12,13,14,15,16,17,18,20',
            'subPlotEnvForm.subplot_id' => [
                'required',
                'string',
                'regex:/^(0[1-9]|[1-4][0-9]|50)$/',
            ],
            'subPlotEnvForm.subplot_area' => 'required|in:1x10,2x5,5x5',
            'subPlotEnvForm.island_category' => 'required|in:本島,離島',
            'subPlotEnvForm.plot_env' => 'required|in:平地,都會,海岸,保護區,森林遊樂區',
            'subPlotEnvForm.elevation' => 'nullable|numeric|min:0|between:0,4000',
            'subPlotEnvForm.slope' => 'nullable|numeric|between:0,90',
            'subPlotEnvForm.aspect' => 'nullable|numeric|between:0,359',
            'subPlotEnvForm.light_0' => 'nullable|numeric|between:0,10',
            'subPlotEnvForm.light_45' => 'nullable|numeric|between:0,10',
            'subPlotEnvForm.light_90' => 'nullable|numeric|between:0,10',
            'subPlotEnvForm.light_135' => 'nullable|numeric|between:0,10',
            'subPlotEnvForm.light_180' => 'nullable|numeric|between:0,10',
            'subPlotEnvForm.light_225' => 'nullable|numeric|between:0,10',
            'subPlotEnvForm.light_270' => 'nullable|numeric|between:0,10',
            'subPlotEnvForm.light_315' => 'nullable|numeric|between:0,10',
            'subPlotEnvForm.photo_id' => 'nullable|string|max:255',
            'subPlotEnvForm.env_description' => 'nullable|string|max:1000',
        ];
    }

    protected function subPlotEnvMessages(): array
    {
        return [
            'subPlotEnvForm.plot.required' => '請選擇樣區編號',
            'subPlotEnvForm.date.required' => '請填寫日期',
            'subPlotEnvForm.date.date' => '請輸入正確的日期格式',

            'subPlotEnvForm.investigator.required' => '請填寫調查者',
            'subPlotEnvForm.investigator.string' => '調查者姓名格式錯誤',
            'subPlotEnvForm.investigator.max' => '調查者姓名長度過長',

            'subPlotEnvForm.recorder.required' => '請填寫紀錄者',
            'subPlotEnvForm.recorder.string' => '紀錄者姓名格式錯誤',
            'subPlotEnvForm.recorder.max' => '紀錄者姓名長度過長',

            'subPlotEnvForm.tm2_x.required' => '請輸入座標 X',
            'subPlotEnvForm.tm2_x.numeric' => '座標 X 必須是數字',
            'subPlotEnvForm.tm2_y.required' => '請輸入座標 Y',
            'subPlotEnvForm.tm2_y.numeric' => '座標 Y 必須是數字',

            // 'subPlotEnvForm.gps_error.numeric' => 'GPS 誤差需為數字',

            'subPlotEnvForm.habitat_code.required' => '請輸入生育地類型',
            'subPlotEnvForm.habitat_code.string' => '生育地類型格式錯誤',
            'subPlotEnvForm.habitat_code.max' => '生育地類型最多 2 碼',
            'subPlotEnvForm.habitat_code.in' => '生育地類型必須是 01 至 20 之間，且不可為 19',

            'subPlotEnvForm.subplot_id.required' => '請輸入小樣方流水號',
            'subPlotEnvForm.subplot_id.string' => '流水號格式錯誤',
            'subPlotEnvForm.subplot_id.regex' => '小樣區代碼必須是 01 至 50 的兩位數字。',

            'subPlotEnvForm.subplot_area.required' => '請選擇取樣面積',
            'subPlotEnvForm.subplot_area.in' => '取樣面積格式錯誤',

            'subPlotEnvForm.island_category.required' => '請選擇樣區所屬地區',
            'subPlotEnvForm.island_category.in' => '樣區所屬地區格式錯誤',

            'subPlotEnvForm.plot_env.required' => '請選擇樣區類型',
            'subPlotEnvForm.plot_env.in' => '樣區類型格式錯誤',

            'subPlotEnvForm.elevation.numeric' => '海拔需為數字',
            'subPlotEnvForm.elevation.min' => '海拔不能小於 0',
            'subPlotEnvForm.elevation.between' => '海拔必須介於 0 到 4000',

            'subPlotEnvForm.slope.numeric' => '坡度需為數字',
            'subPlotEnvForm.slope.between' => '坡度必須介於 0 到 90',

            'subPlotEnvForm.aspect.numeric' => '坡向需為數字',
            'subPlotEnvForm.aspect.between' => '坡向必須介於 0 到 359',

            // 全天光
            'subPlotEnvForm.light_0.numeric' => '全天光(0°)需為數字',
            'subPlotEnvForm.light_0.between' => '全天光(0°)需介於 0–10',

            'subPlotEnvForm.light_45.numeric' => '全天光(45°)需為數字',
            'subPlotEnvForm.light_45.between' => '全天光(45°)需介於 0–10',

            'subPlotEnvForm.light_90.numeric' => '全天光(90°)需為數字',
            'subPlotEnvForm.light_90.between' => '全天光(90°)需介於 0–10',

            'subPlotEnvForm.light_135.numeric' => '全天光(135°)需為數字',
            'subPlotEnvForm.light_135.between' => '全天光(135°)需介於 0–10',

            'subPlotEnvForm.light_180.numeric' => '全天光(180°)需為數字',
            'subPlotEnvForm.light_180.between' => '全天光(180°)需介於 0–10',

            'subPlotEnvForm.light_225.numeric' => '全天光(225°)需為數字',
            'subPlotEnvForm.light_225.between' => '全天光(225°)需介於 0–10',

            'subPlotEnvForm.light_270.numeric' => '全天光(270°)需為數字',
            'subPlotEnvForm.light_270.between' => '全天光(270°)需介於 0–10',

            'subPlotEnvForm.light_315.numeric' => '全天光(315°)需為數字',
            'subPlotEnvForm.light_315.between' => '全天光(315°)需介於 0–10',

            // 照片與描述
            'subPlotEnvForm.photo_id.string' => '照片編號格式錯誤',
            'subPlotEnvForm.photo_id.max' => '照片編號長度不可超過 255 字元',

            'subPlotEnvForm.env_description.string' => '環境描述格式錯誤',
            'subPlotEnvForm.env_description.max' => '環境描述不可超過 1000 字元',
        ];

    }
}
