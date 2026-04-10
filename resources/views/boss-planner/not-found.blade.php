<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Plan Not Found</title>
    @vite(['resources/css/app.css'])
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
</head>
<body class="bg-[#0d0d0f] text-white min-h-screen flex items-center justify-center">
    <div class="text-center space-y-4 max-w-md px-6">
        <span class="material-symbols-outlined text-6xl text-on-surface-variant/20">link_off</span>
        <h1 class="text-2xl font-black uppercase tracking-tight">Plan Not Found</h1>
        <p class="text-sm text-on-surface-variant">
            This raid plan doesn't exist or sharing has been revoked by the owner.
        </p>
        <a href="/" class="inline-block mt-4 px-6 py-3 rounded-xl bg-primary/10 border border-primary/30 text-primary text-xs font-black uppercase tracking-widest hover:bg-primary/20 transition-all">
            Go Home
        </a>
    </div>
</body>
</html>
