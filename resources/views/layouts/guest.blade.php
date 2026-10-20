<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#065f46">

        <title>Log in · {{ config('app.name') }}</title>

        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col justify-center items-center px-4 py-10 bg-gradient-to-b from-brand-800 to-brand-950">
            <div class="w-full max-w-sm px-6 py-6 bg-white shadow-xl rounded-2xl">
                <div class="flex flex-col items-center mb-6">
                    <x-application-logo class="w-56 max-w-full h-auto" />
                    <h1 class="sr-only">MD Biocoals</h1>
                    <p class="mt-3 text-sm font-medium text-gray-500">Dealer &amp; Distributor Onboarding</p>
                </div>

                {{ $slot }}
            </div>

            <p class="mt-6 text-xs text-brand-200">Accounts are created by your administrator.</p>
        </div>
    </body>
</html>
