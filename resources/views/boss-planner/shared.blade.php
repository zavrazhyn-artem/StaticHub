<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $bossName }} — Raid Plan</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
</head>
<body class="bg-[#0d0d0f] text-white min-h-screen">
    <div id="app">
        <shared-plan-view
            :plan="{{ json_encode($plan) }}"
            boss-name="{{ $bossName }}"
            :maps="{{ json_encode($maps) }}"
            portrait="{{ $portrait ?? '' }}"
            static-name="{{ $staticName }}"
        ></shared-plan-view>
    </div>
</body>
</html>
