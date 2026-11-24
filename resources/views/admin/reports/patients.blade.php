@extends('layouts.admin')

@section('title', 'Patient Report')

@section('content')
@include('admin.reports.partials.shell', [
    'title' => 'Patient Report',
    'icon' => 'fa-users',
    'route' => 'admin.reports.patients',
    'filtersView' => 'admin.reports.filters.patients',
])
@endsection
