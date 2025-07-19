  <div class="md:flex md:flex-col gap-2 text-sm">
    <!-- 調查基本資訊 -->
    <div class="md:flex gap-2 items-center">
      <label for="date" class="w-24 text-right">調查日期</label>
      <input id="date" name="date" type="date" wire:model.defer="subPlotEnvForm.date" class="border border-gray-300 px-2 py-1 w-48">

      <label for="investigator" class="w-20 text-right">調查者</label>
      <input id="investigator" name="investigator" type="text" wire:model.defer="subPlotEnvForm.investigator" class="border border-gray-300 px-2 py-1 w-40" placeholder="請填本名">

      <label for="recorder" class="w-20 text-right">紀錄者</label>
      <input id="recorder" name="recorder" type="text" wire:model.defer="subPlotEnvForm.recorder" class="border border-gray-300 px-2 py-1 w-40" placeholder="請填本名">
    </div>

    <!-- 座標與編號 -->
    <div class="md:flex gap-2 items-center">
      <label for="dd97_x" class="w-24 text-right">經度</label>
      <input id="dd97_x" name="dd97_x" type="number" step="any" wire:model.defer="subPlotEnvForm.dd97_x" class="border border-gray-300 px-2 py-1 w-32" placeholder="118 - 123">

      <label for="dd97_y" class="w-10 text-right">緯度</label>
      <input id="dd97_y" name="dd97_y" type="number" step="any" wire:model.defer="subPlotEnvForm.dd97_y" class="border border-gray-300 px-2 py-1 w-32" placeholder="20 - 27">

      <label for="gps_error" class="w-24 text-right">座標誤差</label>
      <input id="gps_error" name="gps_error" type="number" step="any" wire:model.defer="subPlotEnvForm.gps_error" class="border border-gray-300 px-2 py-1 w-24" placeholder="0 - 10">
    </div>

    <div class="md:flex gap-2 items-center">
      <label for="plot" class="w-24 text-right">樣區編號</label>
      <input id="plot" name="plot" type="text" wire:model.defer="subPlotEnvForm.plot" class="border border-gray-300 px-2 py-1 w-40 bg-gray-100 text-gray-600" readonly>
     @php 
     if($subPlotEnvForm['habitat_code']=='88' || $subPlotEnvForm['habitat_code']=='99') {
        $readonly = 'readonly';
        $addclass = 'bg-gray-100 text-gray-600';
     } else {
        $readonly = '';
        $addclass = '';
     }
     @endphp
      <label for="habitat_code" class="w-24 text-right">生育地類型</label>
      <input id="habitat_code" name="habitat_code" type="text" wire:model.defer="subPlotEnvForm.habitat_code" class="border border-gray-300 px-2 py-1 w-28 {{$addclass}}" maxlength="2" placeholder="01-20(除了19)" {{$readonly}}>

      <label for="subplot_id" class="w-32 text-right">小樣方流水號</label>
      <input id="subplot_id" name="subplot_id" type="text" wire:model.defer="subPlotEnvForm.subplot_id" class="border border-gray-300 px-2 py-1 w-24 {{$addclass}}" maxlength="2" placeholder="01-50" {{$readonly}}>
    </div>

    <!-- 取樣面積 -->
    <div class="md:flex gap-4 items-center">
      <label class="w-24 text-right">取樣面積</label>
      <label for="area_1x10"><input id="area_1x10" type="radio" wire:model.defer="subPlotEnvForm.subplot_area" name="subplot_area" value="1x10"> 1 x 10</label>
      <label for="area_2x5"><input id="area_2x5" type="radio" wire:model.defer="subPlotEnvForm.subplot_area" name="subplot_area" value="2x5"> 2 x 5</label>
      <label for="area_5x5"><input id="area_5x5" type="radio" wire:model.defer="subPlotEnvForm.subplot_area" name="subplot_area" value="5x5"> 5 x 5</label>
    </div>

    <!-- 樣區所屬與類型
    <div class="md:flex gap-4 items-center">
      <label class="w-24 text-right">樣區所屬</label>
      <label for="island_main"><input id="island_main" type="radio" wire:model.defer="subPlotEnvForm.island_category" name="island_category" value="本島"> 本島</label>
      <label for="island_off"><input id="island_off" type="radio" wire:model.defer="subPlotEnvForm.island_category" name="island_category" value="離島"> 離島</label>
    </div>

    <div class="md:flex gap-4 items-center">
      <label class="w-24 text-right">樣區類型</label>
      <label for="type_flat"><input id="type_flat" type="radio" wire:model.defer="subPlotEnvForm.plot_env" name="plot_env" value="平地"> 平地</label>
      <label for="type_city"><input id="type_city" type="radio" wire:model.defer="subPlotEnvForm.plot_env" name="plot_env" value="都會"> 都會</label>
      <label for="type_coast"><input id="type_coast" type="radio" wire:model.defer="subPlotEnvForm.plot_env" name="plot_env" value="海岸"> 海岸</label>
      <label for="type_protected"><input id="type_protected" type="radio" wire:model.defer="subPlotEnvForm.plot_env" name="plot_env" value="保護區"> 保護區</label>
      <label for="type_recreation"><input id="type_recreation" type="radio" wire:model.defer="subPlotEnvForm.plot_env" name="plot_env" value="森林遊樂區"> 森林遊樂區</label>
    </div> -->

    <!-- 地形 -->
    <div class="md:flex gap-2 items-center">
      <label for="elevation" class="w-24 text-right">海拔(m)</label>
      <input id="elevation" name="elevation" type="number" step="any" wire:model.defer="subPlotEnvForm.elevation" class="border border-gray-300 px-2 py-1 w-24" placeholder="0-5000">

      <label for="slope" class="w-24 text-right">坡度</label>
      <input id="slope" name="slope" type="number" step="1" wire:model.defer="subPlotEnvForm.slope" class="border border-gray-300 px-2 py-1 w-24" placeholder="-1(未調查), 0-90">

      <label for="aspect" class="w-24 text-right">坡向</label>
      <input id="aspect" name="aspect" type="number" step="1" wire:model.defer="subPlotEnvForm.aspect" class="border border-gray-300 px-2 py-1 w-24" placeholder="-1(未調查), 0-359">
    </div>

   

    <!-- 照片與環境描述 -->
    <div class="md:flex gap-2 items-center">
      <label for="photo_id" class="w-24 text-right">照片編號</label>
      <input id="photo_id" name="photo_id" type="text" wire:model.defer="subPlotEnvForm.photo_id" class="border border-gray-300 px-2 py-1 w-64">

      <label for="env_description" class="w-24 mb-1 text-right">環境描述</label>
      <textarea id="env_description" name="env_description" wire:model.defer="subPlotEnvForm.env_description" rows="1" class="w-64 border border-gray-300"></textarea>
    </div>
  <!-- 舊樣區編號 -->
    <div class="md:flex gap-2 items-center bg-orange-100 pl-8 p-2">
      <label for="original_plot_id" class="text-right">當生育地類型改變，請輸入原小樣方編號</label>
      <input id="original_plot_id" name="original_plot_id" type="text" wire:model.defer="subPlotEnvForm.original_plot_id" class="border border-gray-300 px-2 py-1 w-64" placeholder="生育地類型小樣方流水號(ex: 0101)">
    </div>
  </div>
