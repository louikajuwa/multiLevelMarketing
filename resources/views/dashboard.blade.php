@extends('layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
    <div class="card card-pad">
        <h2 class="text-lg font-semibold mb-1">Welcome, {{ $user->name }}</h2>
        <p class="text-sm" style="color: var(--text-muted)">
            You're signed in. Role-specific dashboards (Admin / Partner / Customer) with KPIs and charts
            are built in Phase 13.
        </p>
    </div>
@endsection
