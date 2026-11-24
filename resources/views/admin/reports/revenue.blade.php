@extends('layouts.admin')

@section('title', 'Revenue Report')

@section('content')
@include('admin.reports.partials.shell', [
    'title' => 'Revenue Report',
    'icon' => 'fa-rupee-sign',
    'route' => 'admin.reports.revenue',
    'filtersView' => 'admin.reports.filters.revenue',
])
@endsection
