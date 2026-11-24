@extends('layouts.admin')

@section('title', 'Employee Report')

@section('content')
@include('admin.reports.partials.shell', [
    'title' => 'Employee Report',
    'icon' => 'fa-user-nurse',
    'route' => 'admin.reports.employees',
    'filtersView' => 'admin.reports.filters.employees',
])
@endsection
