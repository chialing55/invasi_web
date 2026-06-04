{{-- resources/views/page/entry-entry.blade.php --}}
@extends('layouts.app')

@section('content')
    <livewire:entry-entry />
@endsection

@push('styles')
    <link href="https://unpkg.com/tabulator-tables@5.5.0/dist/css/tabulator.min.css" rel="stylesheet">
@endpush

@push('scripts')
    <script src="https://unpkg.com/tabulator-tables@5.5.0/dist/js/tabulator.min.js"></script>
@endpush
