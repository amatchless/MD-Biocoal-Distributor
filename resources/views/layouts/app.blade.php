<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#065f46">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">

        <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-100 text-gray-900">
        <div class="min-h-screen pb-20 md:pb-0">
            @include('layouts.navigation')

            @isset($header)
                <header class="bg-white border-b border-gray-200">
                    <div class="max-w-6xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6">
                @if (session('status') && ! in_array(session('status'), ['profile-updated', 'password-updated']))
                    <div class="mb-4 rounded-lg bg-brand-50 border border-brand-200 text-brand-800 px-4 py-3 text-sm" role="status">
                        {{ session('status') }}
                    </div>
                @endif
                @if (session('clear_draft'))
                    <script>try { Object.keys(localStorage).filter(k => k.startsWith('mdb-draft')).forEach(k => localStorage.removeItem(k)); } catch (e) {}</script>
                @endif

                {{ $slot }}
            </main>
        </div>
    </body>
</html>
