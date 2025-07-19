{{-- livewire/entry-entry.note.php --}}
<div>
    <h2 class="text-xl font-bold mb-4">資料輸入注意事項</h2>

    <div class="mb-4">
        <ul class="list-disc pl-5 space-y-1">
            <li>只能選擇 <b>輸入 / 檢視 / 修改</b> 各自團隊負責之樣區。</li>
            <li>選擇縣市與樣區編號後，方可進行資料操作。</li>
            <li>生育地類型之勾選僅供核對使用，可隨時新增或修改。</li>
            <li>欲檢視或修改既有資料時，請選擇小樣方編號。</li>
            <li>此樣區尚無資料時，請點選「新增小樣方」。</li>
            <li>填寫完「小樣方環境資料」後，下方將自動顯示「小樣方植物調查資料」表單。</li>
            <li><b>請務必按下儲存鈕，資料才會儲存。</b></li>
            <li>所有修改動作皆會被紀錄，作為後續追蹤依據。</li>
        </ul>
    </div>
    <div class='md:flex md:flex-row gap-2 mb-4 items-start'>
        <div class="gray-card">
            <h3 class="text-lg font-semibold mb-2">樣區生育地類型</h3>
            <ul class="list-disc pl-5 space-y-1">
                <li>淺綠色表示上次調查曾包含的生育地類型，提供參考。</li>
                <li>此欄位將用於樣區完成度檢驗，請確實勾選實際涵蓋之生育地類型。</li>
                <li><b>請按 <button class="btn-submit">儲存生育地類型</button> 才能完成儲存。</b></li>
            </ul>
        </div>
        <div class="flex-1 gray-card">
            <h3 class="text-lg font-semibold mb-2">樣區調查資料上傳</h3>
            <ul class="list-disc pl-5 space-y-1">
                <li>調查資料輸入並確認無誤後，請將該樣區的所有紙本資料掃描為電子檔，合併成單一檔案（pdf 檔），並透過此處上傳至主機。</li>
                <li>系統將自動以 <b>樣區編號</b> 作為檔案名稱。</li>
                <li>若需更新檔案，請直接重新上傳，新檔案將自動覆蓋舊檔。</li>
                <li>請在資料輸入完成、物種鑑定完成、小樣區照片上傳，並確認紙本資料無誤後，再上傳樣區調查資料。</li>
                <li>如之後有再更動紙本資料，務必重新掃描(或修圖更改)，並上傳最新版本。</li>
                <li>系統將以樣區調查資料是否已上傳，作為判定該樣區是否完成調查的依據。</li>

            </ul>
        </div>
    </div>
    <div class='md:flex md:flex-row gap-2 mb-4 items-start'>
        <div class="flex-1 gray-card mb-4">
            <h3 class="text-lg font-semibold mb-2">小樣方環境資料</h3>
            <ul class="list-disc pl-5 space-y-1">
                <li>樣區編號為系統預設，無法修改。</li>
                <li>調查者與紀錄者請填寫完整姓名。</li>
                <ul class="list-['📝'] list-outside pl-5 mt-1 space-y-1">
                    <li>若有多位調查者或紀錄者，請以「、」作為分隔符號。</li>
                </ul>
                <li>橘色框線欄位限定只能輸入數字。其中<b>坡度、坡向、全天光需為整數</b>。</li>
                <li>各欄位的輸入範圍已於欄位提示中說明。</li>
                <li>坡度小於 5 度而未測量者，請填入 <b>-1</b>。</li>
                <li>坡向無法測量者，請填入 <b>-1</b>。</li>
                <li>
                    當生育地類型為「08（天然林）」或「09（人工林）」時，系統會自動新增對應的地被樣區「88（天然林-地被）」與「99（人工林-地被）」，並共用相同的小樣方編號。
                    <ul class="list-['✳️'] list-outside pl-5 mt-1 space-y-1">
                        <li><b>請務必先輸入「08」或「09」的資料</b>，系統才會產生對應的草本樣區。</li>
                        <li>「08」與「09」的取樣面積固定為 5×5；「88」與「99」的取樣面積固定為 2×5。</li>
                        <li>為確保地被樣區與森林樣區的小樣方編號能正確對應，當生育地類型為「88」或「99」時，系統將鎖定「生育地類型」與「小樣方編號」欄位，不可進行修改。</li>
                        <li>若舊資料中，地被樣區若有獨立於森林樣區的小樣方編號，則將該原始編號填入變更原始編號的欄位中。</li>
                    </ul>
                </li>
                <li>若因生育地類型改變而需重新編碼樣區，請輸入原樣區編號，格式為「生育地編號-小樣區編號」，例如：01-01。</li>

                <li><b>請按 <button class="btn-submit">儲存環境資料</button> 才能完成儲存。</b></li>
                <li>如欄位有錯誤，將會顯示提示訊息，須更正所有錯誤後方可儲存。</li>
                <li>完成儲存後，將出現植物調查的輸入表單。</li>
            </ul>
        </div>


        <div class="flex-1 gray-card">
            <h3 class="text-lg font-semibold mb-2">小樣方植物調查資料</h3>
            <ul class="list-disc pl-5 space-y-1">
                <li>初始表單提供 15 筆空白資料，可一次儲存 15 筆。完成儲存後，系統將自動新增 15 筆空白列供繼續填寫。</li>
                <li>在表單欄位上按右鍵，可選擇「新增一列」或「刪除此列」。</li>
                <li>表單操作方式：可使用方向鍵上下左右移動；按 Enter 鍵向下移動；按 Tab 鍵或 Shift + Tab 鍵可左右移動，操作方式已盡量貼近 Excel。</li>
                <li>資料需包含「中名」欄位，否則不會被儲存。</li>
                <li>關於植物名稱輸入，請參考以下說明：
                    <ul class="list-decimal pl-6 space-y-1 mt-1">
                        <li>中名選單來源為「台灣植物資訊整合查詢系統」之植物名錄。</li>
                        <li>以「中名 / 科名」形式顯示。若輸入為中文別名，則顯示為「中文別名 / 中名 / 科名」。</li>
                        <li>若植物名稱已存在於名錄中，<b>請務必從下拉選單中選擇</b>。</li>
                        <li>選擇植物名稱後，表單後方將同步顯示「中名 / 科名」作為物種提示，該欄位無法修改。</li>
                        <li>若植物名稱不在名錄中（包含各種未鑑定物種），請直接手動輸入；儲存後會以紅字顯示，表示為未鑑定物種。</li>
                        <li>若不確定中文名稱，建議至「植物查詢」頁面使用<b>學名</b>查詢；亦可自行新增植物中文別名，以利日後輸入。</li>
                        <li>若名錄中確實無該植物，且<b>確定需新增</b>，請填寫<a
                                href='https://docs.google.com/spreadsheets/d/13GUOo_I5fhUBh2IeGb1TJpQeIPN0GqSQKfsMwulSTHE/edit?usp=sharing'
                                target="_blank">「外來植物調查計畫-需新增的植物」</a>資料表。</li>
                        <li>同一植物若重複輸入，該列將以紅色底色標示，請特別留意並進行修正。</li>
                    </ul>
                </li>
                <li>覆蓋度請輸入介於 0 至 100 之間的數值。超出範圍的數值將無法輸入，並以紅字標示；若未填寫，系統將自動填入 0，並以紅字提示，視為資料錯誤。</li>
                <li>「開花」與「結果」欄位：點選儲存格即可勾選 ✔️。</li>
                <li>「標本號」與「備註」欄位為純文字，可自由填寫。</li>
                <li><b>請按 <button class="btn-submit">儲存植物調查資料</button> 才能完成儲存。</b></li>
                <li>⚠️ 特別提醒：如有刪除資料，請務必儲存後才能正確執行刪除。</li>
                <li>⚠️ 若未儲存即切換樣區或離開頁面，變更內容將不會保留。</li>
            </ul>
        </div>

    </div>
    <div class="gray-card mb-4">
        <h3 class="text-lg font-semibold mb-2">小樣方照片上傳</h3>
        <ul class="list-disc pl-5 space-y-1">
            <li>系統會自動將檔名更名為小樣方編號，並以 .jpg 儲存，檔案大小不得超過 12MB。</li>
            <li>每個小樣方僅可上傳一張照片。</li>
            <li>若需更換照片，請直接重新上傳，系統會自動覆蓋原有檔案。</li>
            <li>生育地編號為 88 或 99 者，無需上傳小樣方照片。</li>
        </ul>
    </div>
</div>
