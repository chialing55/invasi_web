<div class="gray-card w-full max-w-3xl mt-8">
    <h3>匯入台灣植物名錄</h3>

    <form wire:submit.prevent="import" class="space-y-4">
        <div>
            <label class="block font-semibold mb-2" for="taiwan-checklist-csv">CSV 檔案</label>
            <input id="taiwan-checklist-csv" type="file" wire:model="csvFile" accept=".csv,text/csv,text/plain">
            @error('csvFile')
                <div class="text-red-700 text-sm mt-2">{{ $message }}</div>
            @enderror
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn-submit" wire:loading.attr="disabled" wire:target="csvFile,import">
                匯入
            </button>
            <span class="text-sm text-gray-600" wire:loading wire:target="csvFile">上傳中...</span>
            <span class="text-sm text-gray-600" wire:loading wire:target="import">匯入中...</span>
        </div>
    </form>

    @if ($message)
        <div class="{{ $messageType === 'success' ? 'alert-info' : 'alert-error' }} mt-4">
            {{ $message }}
        </div>
    @endif
</div>
