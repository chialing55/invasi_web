@props(['name', 'model', 'options' => [], 'class' => 'flex items-center'])
<div {{ $attributes->merge(['class' => $class]) }}>
    @foreach ($options as $opt)
        <label class="inline-flex items-center mr-4 cursor-pointer">
            <input type="radio" name="{{ $name }}" wire:model="{{ $model }}" value="{{ $opt['value'] }}"
                class="form-radio text-forest focus:ring-forest">
            <span class="ml-1">{{ $opt['label'] }}</span>
        </label>
    @endforeach
</div>
