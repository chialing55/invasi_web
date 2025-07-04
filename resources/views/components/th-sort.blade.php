@props([
    'field',
    'sortField',
    'sortDirection',
    'class' => '',
])

<th {{ $attributes->merge([
        'class' => "px-4 py-2 cursor-pointer " . $class,
        'wire:click' => "sortBy('{$field}')"
    ]) }}>
    {{ $slot }}
    @if ($sortField === $field)
        {{ $sortDirection === 'asc' ? '▲' : '▼' }}
    @endif
</th>

