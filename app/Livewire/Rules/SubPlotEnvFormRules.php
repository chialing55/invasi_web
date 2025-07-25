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
            'subPlotEnvForm.dd97_x' => 'required|numeric|between:118,123',
            'subPlotEnvForm.dd97_y' => 'required|numeric|between:20,27',
            'subPlotEnvForm.gps_error' => 'required|numeric|between:0,10',
            'subPlotEnvForm.habitat_code' => 'required|string|max:2|in:01,02,03,04,05,06,07,08,09,10,11,12,13,14,15,16,17,18,20,88,99',
            'subPlotEnvForm.subplot_id' => [
                'required',
                'string',
                'regex:/^(0[1-9]|[1-4][0-9]|50)$/',
            ],
            'subPlotEnvForm.subplot_area' => 'required|in:1,2,3',
            // 'subPlotEnvForm.island_category' => 'required|in:本島,離島',
            // 'subPlotEnvForm.plot_env' => 'required|in:平地,都會,海岸,保護區,森林遊樂區',
            'subPlotEnvForm.elevation' => 'required|numeric|min:0|between:0,5000',
            'subPlotEnvForm.slope' => 'required|integer|between:-1,90',
            'subPlotEnvForm.aspect' => 'required|integer|between:-1,359',
            // 'subPlotEnvForm.light_0' => 'required|integer|between:0,90',
            // 'subPlotEnvForm.light_45' => 'required|integer|between:0,90',
            // 'subPlotEnvForm.light_90' => 'required|integer|between:0,90',
            // 'subPlotEnvForm.light_135' => 'required|integer|between:0,90',
            // 'subPlotEnvForm.light_180' => 'required|integer|between:0,90',
            // 'subPlotEnvForm.light_225' => 'required|integer|between:0,90',
            // 'subPlotEnvForm.light_270' => 'required|integer|between:0,90',
            // 'subPlotEnvForm.light_315' => 'required|integer|between:0,90',
            'subPlotEnvForm.photo_id' => 'nullable|string|max:255',
            'subPlotEnvForm.env_description' => 'nullable|string|max:1000',
            'subPlotEnvForm.original_plot_id' => 'nullable|string|max:1000',
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

            'subPlotEnvForm.dd97_x.required' => '請輸入經度',
            'subPlotEnvForm.dd97_x.numeric' => '經度必須是數字',
            'subPlotEnvForm.dd97_y.required' => '請輸入緯度',
            'subPlotEnvForm.dd97_y.numeric' => '緯度必須是數字',
            'subPlotEnvForm.dd97_x.between' => '經度必須介於 118 到 123 之間。',
            'subPlotEnvForm.dd97_y.between' => '緯度必須介於 20 到 27 之間。',
            'subPlotEnvForm.gps_error.numeric' => 'GPS 誤差需為數字',
            'subPlotEnvForm.gps_error.between' => 'GPS 誤差必須介於 0 到 10 之間。',

            'subPlotEnvForm.habitat_code.required' => '請輸入生育地類型',
            'subPlotEnvForm.habitat_code.string' => '生育地類型格式錯誤',
            'subPlotEnvForm.habitat_code.max' => '生育地類型最多 2 碼',
            'subPlotEnvForm.habitat_code.in' => '生育地類型必須是 01 至 20 之間，或 88 和 99，但不可為 19，',

            'subPlotEnvForm.subplot_id.required' => '請輸入小樣方流水號',
            'subPlotEnvForm.subplot_id.string' => '流水號格式錯誤',
            'subPlotEnvForm.subplot_id.regex' => '小樣方代碼必須是 01 至 50 的兩位數字。',

            'subPlotEnvForm.subplot_area.required' => '請選擇取樣面積',
            'subPlotEnvForm.subplot_area.in' => '取樣面積格式錯誤',

            // 'subPlotEnvForm.island_category.required' => '請選擇樣區所屬地區',
            // 'subPlotEnvForm.island_category.in' => '樣區所屬地區格式錯誤',

            // 'subPlotEnvForm.plot_env.required' => '請選擇樣區類型',
            // 'subPlotEnvForm.plot_env.in' => '樣區類型格式錯誤',

            'subPlotEnvForm.elevation.numeric' => '海拔需為數字',
            'subPlotEnvForm.elevation.min' => '海拔不能小於 0',
            'subPlotEnvForm.elevation.between' => '海拔必須介於 0 到 5000',

            'subPlotEnvForm.slope.integer' => '坡度需為整數',
            'subPlotEnvForm.slope.between' => '坡度必須介於 -1 到 90',

            'subPlotEnvForm.aspect.integer' => '坡向需為整數',
            'subPlotEnvForm.aspect.between' => '坡向必須介於 -1 到 359',

            // 全天光
            // 'subPlotEnvForm.light_0.integer' => '全天光(0°)需為整數',
            // 'subPlotEnvForm.light_0.between' => '全天光(0°)需介於 0–90',

            // 'subPlotEnvForm.light_45.integer' => '全天光(45°)需為整數',
            // 'subPlotEnvForm.light_45.between' => '全天光(45°)需介於 0–90',

            // 'subPlotEnvForm.light_90.integer' => '全天光(90°)需為整數',
            // 'subPlotEnvForm.light_90.between' => '全天光(90°)需介於 0–90',

            // 'subPlotEnvForm.light_135.integer' => '全天光(135°)需為整數',
            // 'subPlotEnvForm.light_135.between' => '全天光(135°)需介於 0–90',

            // 'subPlotEnvForm.light_180.integer' => '全天光(180°)需為整數',
            // 'subPlotEnvForm.light_180.between' => '全天光(180°)需介於 0–90',

            // 'subPlotEnvForm.light_225.integer' => '全天光(225°)需為整數',
            // 'subPlotEnvForm.light_225.between' => '全天光(225°)需介於 0–90',

            // 'subPlotEnvForm.light_270.integer' => '全天光(270°)需為整數',
            // 'subPlotEnvForm.light_270.between' => '全天光(270°)需介於 0–90',

            // 'subPlotEnvForm.light_315.integer' => '全天光(315°)需為整數',
            // 'subPlotEnvForm.light_315.between' => '全天光(315°)需介於 0–90',

            // 照片與描述
            'subPlotEnvForm.photo_id.string' => '照片編號格式錯誤',
            'subPlotEnvForm.photo_id.max' => '照片編號長度不可超過 255 字元',

            'subPlotEnvForm.env_description.string' => '環境描述格式錯誤',
            'subPlotEnvForm.env_description.max' => '環境描述不可超過 1000 字元',

            'subPlotEnvForm.original_plot_id.string' => '舊樣區編號格式錯誤',
        ];

    }
}
