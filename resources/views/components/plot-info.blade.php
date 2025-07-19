<div class="text-sm bg-white rounded-lg p-4 shadow leading-6">

    <div class="flex flex-wrap gap-x-8 gap-y-1 border-b pb-1">
        <div class="flex gap-1">
            <span class="text-gray-500">調查日期：</span><span class="font-semibold">{{ $subPlotEnvForm['date'] }}</span>
        </div>
        <div class="flex gap-1">
            <span class="text-gray-500">調查者：</span><span>{{ $subPlotEnvForm['investigator'] }}</span>
        </div>
        <div class="flex gap-1">
            <span class="text-gray-500">紀錄者：</span><span>{{ $subPlotEnvForm['recorder'] }}</span>
        </div>
    </div>

    <div class="flex flex-wrap gap-x-8 gap-y-1 border-b py-1">
        <div class="flex gap-1">
            <span class="text-gray-500">經度：</span><span>{{ $subPlotEnvForm['dd97_x'] }}</span>
        </div>
        <div class="flex gap-1">
            <span class="text-gray-500">緯度：</span><span>{{ $subPlotEnvForm['dd97_y'] }}</span>
        </div>
        <div class="flex gap-1">
            <span class="text-gray-500">座標誤差：</span><span>{{ $subPlotEnvForm['gps_error'] }}</span>
        </div>
    </div>

    <div class="flex flex-wrap gap-x-8 gap-y-1 border-b py-1">
        <div class="flex gap-1">
            <span class="text-gray-500">樣區編號：</span>
            <span>{{ $subPlotEnvForm['plot'] }}</span>
        </div>
        <div class="flex gap-1">
            <span class="text-gray-500">生育地類型：</span>
            <span>
                {{ $subPlotEnvForm['habitat_code'] }}-{{ $subPlotEnvForm['hab_type'] }}
            </span>
        </div>
        <div class="flex gap-1">
            <span class="text-gray-500">小樣方流水號：</span>
            <span>
                {{ $subPlotEnvForm['subplot_id'] }}
            </span>
        </div>
        <div class="flex gap-1">
            <span class="text-gray-500">取樣面積：</span><span>{{ $subPlotEnvForm['subplot_area_data'] }}</span>
        </div>
    </div>

    <div class="flex flex-wrap gap-x-8 gap-y-1 border-b py-1">
        <div class="flex gap-1">
            <span class="text-gray-500">海拔(m)：</span><span>{{ $subPlotEnvForm['elevation'] }}</span>
        </div>
        <div class="flex gap-1">
            <span class="text-gray-500">坡度：</span><span>{{ $subPlotEnvForm['slope'] }}</span>
        </div>
        <div class="flex gap-1">
            <span class="text-gray-500">坡向：</span><span>{{ $subPlotEnvForm['aspect'] }}</span>
        </div>
    </div>

    <div class="flex flex-wrap gap-x-8 gap-y-1 pt-1">
        <div class="flex gap-1">
            <span class="text-gray-500">照片編號：</span><span>{{ $subPlotEnvForm['photo_id'] }}</span>
        </div>
        <div class="flex gap-1">
            <span class="text-gray-500">環境描述：</span><span
                class="whitespace-pre-line">{{ $subPlotEnvForm['env_description'] }}</span>
        </div>

        <div class="flex gap-1">
            <span class="text-gray-500">舊樣區編號：</span><span>{{ $subPlotEnvForm['original_plot_id'] }}</span>
        </div>
    </div>
</div>
