@extends('layouts.admin')

@section('title', 'Contract Report')

@section('content')
@include('admin.reports.partials.shell', [
    'title' => 'Contract Report',
    'icon' => 'fa-file-contract',
    'route' => 'admin.reports.contracts',
    'filtersView' => 'admin.reports.filters.contracts',
])
@endsection
