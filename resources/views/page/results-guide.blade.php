@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-xl font-bold">調查成果資料說明</h2>
            <p class="mt-2 text-sm text-gray-700">本頁說明「物種數」、「成果圖表」及「資料下載」的資料來源與計算方式。</p>
        </div>
        <a href="{{ route('results.charts') }}" class="font-semibold text-forest underline hover:text-forest-dark">返回調查成果</a>
    </div>

    <div class="rounded border-l-4 border-amber-500 bg-amber-50 p-4 text-sm text-amber-950">
        <h3 class="font-bold">共用資料原則</h3>
        <ul class="mt-2 list-disc space-y-1 pl-5">
            <li>統計範圍依使用者在「調查成果」已套用的樣區而定。</li>
            <li>本次調查指 2025 年起使用的本期調查資料；前次調查指 2010 年調查資料。</li>
            <li>物種以臺灣植物名錄目前有效的物種代碼辨識，同一物種在同一統計範圍內不重複計數。</li>
            <li>名錄比對不到，或來源屬性為空白、unknown、uncertain 的物種，不納入統計。</li>
            <li>「歸化物種」不含栽培種；已刪除的調查記錄不納入。</li>
        </ul>
    </div>

    <nav class="gray-card text-sm" aria-label="本頁目錄">
        <span class="font-semibold">快速前往：</span>
        <a href="#species" class="ml-3 text-forest underline">物種數</a>
        <a href="#charts" class="ml-3 text-forest underline">成果圖表</a>
        <a href="#downloads" class="ml-3 text-forest underline">資料下載</a>
        <a href="#terms" class="ml-3 text-forest underline">公式說明</a>
    </nav>

    <section id="species" class="scroll-mt-4">
        <h3 class="mb-3 text-lg font-bold text-forest">一、物種數</h3>
        <div class="overflow-x-auto">
            <table class="w-full border border-gray-300 bg-white text-sm">
                <thead class="bg-[#F9E7AC]">
                    <tr><th class="border p-3 text-left">項目</th><th class="border p-3 text-left">資料來源</th><th class="border p-3 text-left">計算方式</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <th class="border p-3 text-left align-top">物種數與物種名錄</th>
                        <td class="border p-3 align-top">本次調查的植物記錄、小樣方生育地資料及臺灣植物名錄。可再依生育地類型篩選。</td>
                        <td class="border p-3 align-top">
                            以有效物種代碼去除重複後，計算科、屬、種數，並統計原生、特有、歸化及各生長型的物種數。<br>
                            <span class="mt-1 inline-block">歸化物種比例（%）＝歸化種數 ÷（原生種數＋歸化種數＋栽培種數）×100。</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section id="charts" class="scroll-mt-4">
        <h3 class="mb-3 text-lg font-bold text-forest">二、成果圖表</h3>
        <div class="overflow-x-auto">
            <table class="w-full border border-gray-300 bg-white text-sm">
                <thead class="bg-[#F9E7AC]">
                    <tr><th class="w-1/5 border p-3 text-left">圖表</th><th class="w-1/3 border p-3 text-left">資料來源</th><th class="border p-3 text-left">計算方式</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <th class="border p-3 text-left align-top">表 1<br>全部植物習性統計</th>
                        <td class="border p-3 align-top">本次調查植物記錄與臺灣植物名錄。</td>
                        <td class="border p-3 align-top">物種去重後，依植物類群統計科、屬、種數，並分別統計特有、原生、歸化、栽培及生長習性。</td>
                    </tr>
                    <tr class="bg-gray-50">
                        <th class="border p-3 text-left align-top">表 2<br>歸化物種習性統計</th>
                        <td class="border p-3 align-top">與表 1 相同。</td>
                        <td class="border p-3 align-top">與表 1 相同，但只納入歸化物種，不含栽培種。</td>
                    </tr>
                    <tr>
                        <th class="border p-3 text-left align-top">表 3<br>各生育地物種統計與多樣性</th>
                        <td class="border p-3 align-top">本次調查植物記錄、小樣方生育地與覆蓋度資料。成對的木本上層與林下層以主生育地合併統計。</td>
                        <td class="border p-3 align-top">
                            各生育地的原生、歸化、栽培物種數均以物種去重後計算。<br>
                            歸化種數比例＝歸化種數 ÷（原生＋歸化＋栽培種數）×100。<br>
                            歸化物種平均覆蓋度與 Shannon index 請見本頁下方「公式說明」。
                        </td>
                    </tr>
                    <tr class="bg-gray-50">
                        <th class="border p-3 text-left align-top">表 4<br>各生育地歸化物種 IV</th>
                        <td class="border p-3 align-top">與表 3 相同。</td>
                        <td class="border p-3 align-top">先在各生育地中計算歸化物種的相對覆蓋度與相對頻度，IV＝相對覆蓋度＋相對頻度，再依 IV 由高至低列出前 10 名。公式與表 5 相同。</td>
                    </tr>
                    <tr>
                        <th class="border p-3 text-left align-top">表 5<br>草本小樣方歸化物種 IVI</th>
                        <td class="border p-3 align-top">本次調查中，除木本生育地以外的植物記錄與覆蓋度資料。</td>
                        <td class="border p-3 align-top">列出歸化物種，依 IVI 由高至低排序。平均覆蓋度、相對覆蓋度、相對頻度與 IVI 請見「公式說明」；分母使用同一範圍的全部已分類物種。</td>
                    </tr>
                    <tr class="bg-gray-50">
                        <th class="border p-3 text-left align-top">表 6<br>木本小樣方歸化物種 IVI</th>
                        <td class="border p-3 align-top">本次調查的木本生育地植物記錄與覆蓋度資料，依木本生育地分組。</td>
                        <td class="border p-3 align-top">與表 5 相同，但各木本生育地分別計算。</td>
                    </tr>
                    <tr>
                        <th class="border p-3 text-left align-top">表 7<br>低海拔 IVI 比較</th>
                        <td class="border p-3 align-top">所選樣區中，樣區最高海拔不超過 500 m 者的前次與本次調查植物及覆蓋度資料。</td>
                        <td class="border p-3 align-top">兩期各自依表 5 的相對覆蓋度、相對頻度及 IVI 公式計算並排名。表中以本次調查的歸化物種為主，依物種代碼對應前次調查結果。</td>
                    </tr>
                    <tr class="bg-gray-50">
                        <th class="border p-3 text-left align-top">圖 1<br>歸化物種優勢科前十名</th>
                        <td class="border p-3 align-top">本次調查植物記錄與臺灣植物名錄。</td>
                        <td class="border p-3 align-top">歸化物種依科別分組，計算每科不重複物種數，顯示物種數最多的前 10 科。</td>
                    </tr>
                    <tr>
                        <th class="border p-3 text-left align-top">圖 2<br>低海拔外來植物優勢科比較</th>
                        <td class="border p-3 align-top">海拔條件與表 7 相同，使用前次與本次調查植物記錄。</td>
                        <td class="border p-3 align-top">兩期分別計算各科的歸化物種數，以同一科別並列比較，最多顯示 15 科。</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section id="downloads" class="scroll-mt-4">
        <h3 class="mb-3 text-lg font-bold text-forest">三、資料下載</h3>
        <div class="overflow-x-auto">
            <table class="w-full border border-gray-300 bg-white text-sm">
                <thead class="bg-[#F9E7AC]">
                    <tr><th class="border p-3 text-left">下載項目</th><th class="border p-3 text-left">資料內容與來源</th><th class="border p-3 text-left">處理方式</th></tr>
                </thead>
                <tbody>
                    <tr><th class="border p-3 text-left align-top">前次／本次環境資料</th><td class="border p-3 align-top">所選樣區的小樣方環境調查原始欄位。</td><td class="border p-3 align-top">依所選樣區匯出，不進行統計計算。</td></tr>
                    <tr class="bg-gray-50"><th class="border p-3 text-left align-top">前次／本次植物資料</th><td class="border p-3 align-top">所選樣區的小樣方植物調查原始記錄，包含物種與覆蓋度等欄位。</td><td class="border p-3 align-top">依所選樣區匯出，不將同物種的多筆調查記錄合併。</td></tr>
                    <tr><th class="border p-3 text-left align-top">前次／本次植物名錄</th><td class="border p-3 align-top">所選樣區的植物記錄，對應臺灣植物名錄的科名、學名、中文名、來源屬性與生長型等資訊。</td><td class="border p-3 align-top">以目前有效物種代碼整理並去除重複。</td></tr>
                    <tr class="bg-gray-50"><th class="border p-3 text-left align-top">小樣方未調查原因</th><td class="border p-3 align-top">本次調查中，所選樣區的未調查小樣方與原因記錄。</td><td class="border p-3 align-top">將原因代碼轉為「上層原因／下層原因」的完整文字。</td></tr>
                    <tr><th class="border p-3 text-left align-top">全部植物名錄</th><td class="border p-3 align-top">本次調查植物記錄與臺灣植物名錄。</td><td class="border p-3 align-top">以 Excel 工作表整理類群、科別與生育地出現情形；生育地工作表為全體名錄架構，不受所選樣區限制。</td></tr>
                    <tr class="bg-gray-50"><th class="border p-3 text-left align-top">統計表 xlsx／docx<br>統計圖 PDF</th><td class="border p-3 align-top">「成果圖表」的表 1–7 與圖 1–2。</td><td class="border p-3 align-top">資料範圍與算法與本頁上方各表、各圖說明相同。</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section id="terms" class="scroll-mt-4">
        <h3 class="mb-3 text-lg font-bold text-forest">四、公式說明</h3>
        <div class="gray-card space-y-4 text-sm leading-7">
            <div><h4 class="font-bold">頻度</h4><p>某物種的頻度＝該物種出現的不重複小樣方數。</p></div>
            <div><h4 class="font-bold">歸化物種平均覆蓋度（表 3）</h4><p>先在每個小樣方計算「歸化物種總覆蓋度 ÷ 全部已分類物種總覆蓋度×100」，再將同一生育地的小樣方比例取平均。</p></div>
            <div><h4 class="font-bold">Shannon index（表 3）</h4><p>以各物種在該生育地的覆蓋度加總作為豐度，p為該物種覆蓋度占總覆蓋度的比例，Shannon index＝−Σ（p×ln p）。數值越高，表示物種組成通常越多樣且均勻。</p></div>
            <div><h4 class="font-bold">平均覆蓋度（表 5、6）</h4><p>某物種總覆蓋度 ÷ 統計範圍的小樣方數。</p></div>
            <div><h4 class="font-bold">相對覆蓋度</h4><p>某物種總覆蓋度 ÷ 同一統計範圍全部已分類物種總覆蓋度×100。</p></div>
            <div><h4 class="font-bold">相對頻度</h4><p>某物種頻度 ÷ 同一統計範圍所有已分類物種頻度總和×100。</p></div>
            <div><h4 class="font-bold">IV／IVI</h4><p>IV（表 4）與 IVI（表 5–7）皆以「相對覆蓋度＋相對頻度」計算。數值越高，表示該物種在統計範圍內的優勢程度越高。</p></div>
        </div>
    </section>
</div>
@endsection
