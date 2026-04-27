@php use App\Policies\StaticGroupPolicy; @endphp
    <!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="gridinsoft-key" content="3nb7xludk7tjbqh2nuc0g466h5zwzzilqiwftrf42jj2df7znies5sc6qnsthh6z" />

    <title>{{ config('app.name', 'Laravel') }}</title>
    <link rel="icon" href="/images/logo.svg" type="image/svg+xml">

    <!-- All fonts (Inter/Space Grotesk/Orbitron/Material Symbols) bundled via app.css -->

    <script>
        // Читаємо JSON з перекладами і записуємо в глобальну змінну вікна
        window.translations = {!! file_exists(base_path('lang/' . app()->getLocale() . '.json'))
            ? file_get_contents(base_path('lang/' . app()->getLocale() . '.json'))
            : '{}' !!};
        window.appLocale = @json(app()->getLocale());

        // Sidebar collapsed state — applied before paint to prevent layout flash.
        (function () {
            try {
                if (localStorage.getItem('sidebar_collapsed') === '1') {
                    document.documentElement.style.setProperty('--sidebar-w', '72px');
                    document.documentElement.classList.add('sidebar-collapsed');
                }
            } catch (e) {}
        })();
    </script>
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Wowhead Tooltips -->
    <script>const whTooltips = {colorLinks: true, iconizeLinks: false, renameLinks: false};</script>
    <script src="https://wow.zamimg.com/js/tooltips.js"></script>
</head>
<body class="bg-background text-on-background min-h-screen arcane-bg subpixel-antialiased" x-data="{ sidebarOpen: false }">
@php
    $ghostService = app(\App\Services\Ghost\GhostModeService::class);
    $ghostActive = $ghostService->isActive();

    if ($ghostActive) {
        $static = \App\Models\StaticGroup::withoutGlobalScopes()->find($ghostService->currentStaticId());
    } else {
        $static = Auth::user() ? Auth::user()->statics->first() : null;
    }
    $isOnboarding = $onboarding ?? false;
    $isBare = $bare ?? false;
    // "Back to Blastr" (on bare pages like /feedback) is only meaningful for
    // fully-onboarded users: logged in + has a static + picked a main character.
    // Anyone less than that would be bounced to onboarding, which is the opposite
    // of what "back to the app" should feel like.
    $canReturnToBlastr = Auth::check()
        && $static
        && \App\Models\User::query()->hasMainCharacter(Auth::id());

    $layoutShellPayload = app(\App\Services\StaticGroup\SidebarPayloadService::class)
        ->build(Auth::user(), $static, $isOnboarding, $isBare);
@endphp

{{-- Vue mounts on #app at the body level so it sees both the new
     <layout-shell> (sidebar + top-right controls + notifications) and
     page-level components inside <main>. The legacy <header>/<aside> below
     stay temporarily for visual side-by-side comparison and will be removed
     in a follow-up commit. --}}
<div id="app">
    <layout-shell
        :sidebar='@json($layoutShellPayload['sidebar'])'
        :lang='@json($layoutShellPayload['lang'])'
        :auth='@json($layoutShellPayload['auth'])'
        :ghost='@json($layoutShellPayload['ghost'])'
        :csrf="'{{ $layoutShellPayload['csrf'] }}'"
        :initial-notifications='@json($layoutShellPayload['initialNotifications'])'
    ></layout-shell>

<!-- Main Content Canvas -->
<main class="{{ ($isOnboarding || $isBare) ? '' : 'app-main' }} pt-6 px-8 min-h-screen">
    <div class="max-w-8xl mx-auto">
        @if($isBare && Auth::check() && !$canReturnToBlastr)
            <div id="blastr-onboarding-banner"
                 class="hidden w-full lg:w-[85%] mx-auto mb-6 items-center gap-4 p-4 rounded-2xl bg-primary/10 border border-primary/30 backdrop-blur-sm">
                <span class="material-symbols-outlined text-2xl text-primary shrink-0">auto_awesome</span>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold text-on-surface">
                        {{ __('Welcome to Blastr!') }} 👋
                    </div>
                    <div class="text-xs text-on-surface-variant mt-1">
                        {{ __('You can leave feedback without a full setup, but create or join a static to unlock rosters, raid planner, AI log analysis and more.') }}
                    </div>
                </div>
                <a href="{{ route('onboarding.index') }}"
                   class="px-4 py-2 rounded-lg text-3xs font-semibold uppercase tracking-wider bg-primary text-on-primary hover:bg-primary/90 active:scale-95 transition shrink-0">
                    {{ __('Complete setup') }}
                </a>
                <button type="button"
                        onclick="dismissBlastrOnboardingBanner()"
                        class="p-1 rounded hover:bg-white/10 text-on-surface-variant hover:text-on-surface transition shrink-0">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>
            <script>
                (function () {
                    if (!localStorage.getItem('alert_dismissed_blastr_onboarding')) {
                        const el = document.getElementById('blastr-onboarding-banner');
                        if (el) {
                            el.classList.remove('hidden');
                            el.classList.add('flex');
                        }
                    }
                })();
                function dismissBlastrOnboardingBanner() {
                    localStorage.setItem('alert_dismissed_blastr_onboarding', '1');
                    const el = document.getElementById('blastr-onboarding-banner');
                    if (el) { el.classList.add('hidden'); el.classList.remove('flex'); }
                }
            </script>
        @endif
        {{ $slot }}
    </div>
</main>
</div>{{-- /#app --}}
</body>
</html>
