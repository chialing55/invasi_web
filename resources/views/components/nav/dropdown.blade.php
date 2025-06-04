<div x-data="{ open: false }" class="relative" @mouseenter="open = true" @mouseleave="open = false">
    <div class="btn-navigation-div">
        <button class="btn-navigation @navActive($active)">
            {{ $label }}
        </button>
    </div>

    <div x-show="open"
        class="btn-navigation-2"
        x-cloak
        @click.away="open = false">
        @foreach ($routes as $item)
            <a href="{{ route($item['route']) }}">{{ $item['label'] }}</a>
        @endforeach
    </div>
</div>
