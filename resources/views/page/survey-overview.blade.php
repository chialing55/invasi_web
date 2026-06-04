{{-- resources/views/page/survey-overview.blade.php --}}
@extends('layouts.app')

@section('content')
    <livewire:survey-overview />
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endpush
