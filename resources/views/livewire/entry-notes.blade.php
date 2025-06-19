{{-- livewire/entry-entry.note.php --}}
<div>
    <h2 class="text-xl font-bold mb-4">資料輸入注意事項</h2>

    <div class="mb-4">
        <ul class="list-disc pl-5 space-y-1">
            <li>只能選擇 <b>輸入 / 檢視 / 修改</b> 各自團隊負責之樣區。</li>
            <li>選擇縣市與樣區編號後，方可進行資料操作。</li>
            <li>尚無資料時請點選「新增小樣方」。</li>
            <li>欲檢視或修改既有資料時，請選擇小樣方編號。</li>
            <li>上方為「小樣方環境資料」，下方為「小樣方植物調查資料」。</li>
            <li><b>請務必按下儲存鈕，資料才會儲存。</b></li>
            <li>所有修改動作皆會被紀錄，作為後續追蹤依據。</li>
        </ul>
    </div>
    <div class="gray-card mb-4">
        <h3 class="text-lg font-semibold mb-2">樣區生育地類型</h3>
        <ul class="list-disc pl-5 space-y-1">
            <li>淺綠色表示上次調查曾包含的生育地類型，提供參考。</li>
            <li>此欄位將用於樣區完成度檢驗，請確實勾選實際涵蓋之生育地類型。</li>
        </ul>
    </div>
<div class='md:flex md:flex-row gap-2 mb-4'>
    <div class="gray-card mb-4">
        <h3 class="text-lg font-semibold mb-2">樣區環境資料</h3>
        <ul class="list-disc pl-5 space-y-1">
            <li>樣區編號為系統預設，無法修改。</li>
            <li>調查者與紀錄者請填寫完整姓名。</li>
            <li>橘色框線欄位限定只能輸入數字。</li>
            <li>各欄位如有特殊格式要求，皆會於欄位提示中說明。</li>
            <li>
                當生育地類型為「08（天然林）」或「09（人工林）」時，系統會自動產生對應的草地類型資料（「88（天然林-草地）」或「99（人工林-草地）」）。
                <ul class="list-['✳️'] list-outside pl-5 mt-1 space-y-1">
                    <li>「08（天然林）」與「09（人工林）」的取樣面積固定為 5×5。</li>
                    <li>「88（天然林-草地）」與「99（人工林-草地）」的取樣面積固定為 2×5。</li>
                </ul>
            </li>

            <li><b>請按 <button class="btn-submit">儲存環境資料</button> 才能完成儲存。</b></li>
            <li>如欄位有錯誤，將會顯示提示訊息，須更正所有錯誤後方可儲存。</li>
            <li>完成儲存後，將出現植物調查的輸入表單。</li>
        </ul>
    </div>

    <div class="gray-card">
        <h3 class="text-lg font-semibold mb-2">植物調查資料</h3>
        <ul class="list-disc pl-5 space-y-1">
            <li>資料需包含「中文名」欄位，否則不會被儲存。</li>
            <li>在表單欄位上按右鍵，可選擇「新增一列」或「刪除此列」。</li>
            <li>關於植物名稱輸入，請參考以下說明：
                <ul class="list-decimal pl-6 space-y-1 mt-1">
                    <li>中文名選單來源包括：
                        <span class="inline-block ml-1">① 上次調查資料、② 本次輸入資料、③ 植物別名清單。</span>
                    </li>
                    <li>若名稱未列於下拉選單中，仍可手動輸入。</li>
                    <li>若輸入的中文名不在植物名錄中，儲存後會以紅字顯示，表示為未鑑定物種。</li>
                    
                    <li>若不確定對應的中文名稱，建議至「植物查詢」頁面查詢；亦可自行新增植物別名，以利未來輸入。</li>
                    <li>若查無此植物，並確定需新增至名錄中，請聯繫資料管理員。</li>
                    <li>若同種植物重複輸入，該列將以紅色底色標示，請特別留意此狀況並進行相應修改。</li>
                </ul>
            </li>
            <li>覆蓋度請輸入介於 0–100 之間的數值；超出範圍會以紅字標示，仍可儲存，但視為資料尚未完成。</li>
            <li>「開花」與「結果」欄位：點選儲存格即可勾選 ✔️。</li>
            <li>「標本號」與「備註」欄位為純文字，可自由填寫。</li>
            <li><b>請按 <button class="btn-submit">儲存植物調查資料</button> 才能完成儲存。</b></li>
            <li>⚠️ 特別提醒：如有刪除資料，請務必儲存後才能正確執行刪除。</li>
            <li>⚠️ 若未儲存即切換樣區或離開頁面，變更內容將不會保留。</li>
        </ul>
    </div>
</div>
</div>
