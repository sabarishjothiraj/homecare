@extends('layouts.admin')

@section('title', 'Assignment Report')

@section('content')
@include('admin.reports.partials.shell', [
    'title' => 'Assignment Report',
    'icon' => 'fa-clipboard-list',
    'route' => 'admin.reports.assignments',
    'filtersView' => 'admin.reports.filters.assignments',
])
@endsection
