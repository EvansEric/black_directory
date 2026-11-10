<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'ROOTS | Local Black Business Directory' }}</title>

    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body
    x-data
    :class="$store.theme.dark ? 'dark' : ''"
    class="font-sans text-slate-800 flex flex-col min-h-screen bg-slate-50"
>

    {{ $slot ?? '' }}

    @livewireScripts
</body>
</html>