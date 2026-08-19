<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'BK System') — Bimbingan Konseling</title>
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/navbar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/siswaNav.css') }}">
    <link rel="stylesheet" href="{{ asset('css/notifikasiBell.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app-extra.css') }}">
    @stack('styles')
</head>
<body>
@php
    $role = $authRole ?? session('auth_role');
    $user = $authUser ?? session('auth_user', []);
@endphp

@if($role === 'siswa')
    {{-- Halaman History (React) punya sidebar sendiri --}}
    @unless(request()->routeIs('siswa.konseling.index') || request()->routeIs('siswa.konseling.show'))
        @include('partials.siswa-nav')
    @endunless
@elseif($role === 'guru')
    {{-- Guru BK: header khusus di dalam view (guru-bk-page), bukan app-nav generik --}}
@elseif($role === 'admin')
    {{-- Admin: header khusus di dalam view (admin-page), bukan app-nav generik --}}
@elseif($role)
<nav class="app-nav">
    <a href="{{ route($role.'.dashboard') }}" class="app-nav-logo">
        <span class="app-nav-logo-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
        </span>
        BK System
    </a>
    <div class="app-nav-links">
        @if($role === 'guru')
            <a href="{{ route('guru.dashboard') }}" class="{{ request()->routeIs('guru.dashboard') ? 'active' : '' }}">Dashboard</a>
            <a href="{{ route('guru.konseling.index') }}" class="{{ request()->routeIs('guru.konseling.*') ? 'active' : '' }}">Konseling</a>
            <a href="{{ route('guru.siswa.index') }}" class="{{ request()->routeIs('guru.siswa.*') ? 'active' : '' }}">Siswa</a>
            <a href="{{ route('guru.informasi') }}" class="{{ request()->routeIs('guru.informasi*') ? 'active' : '' }}">Informasi</a>
        @elseif($role === 'kepsek')
            <a href="{{ route('kepsek.dashboard') }}" class="{{ request()->routeIs('kepsek.dashboard') ? 'active' : '' }}">Dashboard</a>
            <a href="{{ route('kepsek.konseling') }}" class="{{ request()->routeIs('kepsek.konseling*') ? 'active' : '' }}">Konseling</a>
        @endif
        <form action="{{ route('logout') }}" method="POST" style="display:inline;margin-left:8px">
            @csrf
            <button type="submit" class="btn" style="background:transparent;color:var(--gray-600);padding:8px 12px;font-size:14px">Keluar</button>
        </form>
    </div>
</nav>
@endif

<main class="@yield('main_class', 'main-content')">
    @if(isset($errors) && $errors->any())
        <div class="alert alert-error" style="max-width:1100px;margin:12px auto;padding:12px 16px">
            <ul style="margin:0;padding-left:18px">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif
    @if(session('success'))
        <div class="alert alert-success" style="max-width:1100px;margin:12px auto;padding:12px 16px">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-error" style="max-width:1100px;margin:12px auto;padding:12px 16px">{{ session('error') }}</div>
    @endif
    @yield('content')
</main>
@stack('scripts')
</body>
</html>
