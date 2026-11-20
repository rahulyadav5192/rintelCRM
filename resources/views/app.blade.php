<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @if (!empty($noindex))
            <meta name="robots" content="noindex, nofollow">
        @endif
        <title inertia>{{ config('app.name', 'Rintel Lead CRM') }}</title>
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
