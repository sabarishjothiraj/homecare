@extends('layouts.admin')

@section('title', 'Payout Report')

@section('content')
@include('admin.reports.partials.shell', [
    'title' => 'Payout Report',
    'icon' => 'fa-hand-holding-usd',
    'route' => 'admin.reports.payouts',
    'filtersView' => 'admin.reports.filters.payouts',
])
@endsection
