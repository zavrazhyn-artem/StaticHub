<script setup>
import { computed, ref } from 'vue';
import { useTranslation } from '@/composables/useTranslation';
import SectionHeader from '../UI/SectionHeader.vue';

const { __ } = useTranslation();

const props = defineProps({
    payload: { type: Object, required: true },
});

const showSmartScreenHelp = ref(false);

const manifest = computed(() => props.payload?.manifest ?? {});
const connection = computed(() => props.payload?.connection ?? { paired: false });
const onboardingSteps = computed(() => props.payload?.onboarding_steps ?? []);

const downloadAvailable = computed(() => {
    const m = manifest.value ?? {};
    return Boolean(m.download_url);
});

function relativeTime(iso) {
    if (!iso) return null;
    const ts = new Date(iso).getTime();
    if (Number.isNaN(ts)) return null;
    const diff = Math.max(0, Math.floor((Date.now() - ts) / 1000));
    if (diff < 60) return __(':n s ago', { n: diff });
    if (diff < 3600) return __(':n m ago', { n: Math.floor(diff / 60) });
    if (diff < 86400) return __(':n h ago', { n: Math.floor(diff / 3600) });
    return __(':n d ago', { n: Math.floor(diff / 86400) });
}

function formatBytes(n) {
    if (!n || n <= 0) return null;
    const mb = n / (1024 * 1024);
    if (mb >= 1) return `${mb.toFixed(1)} MB`;
    return `${Math.round(n / 1024)} KB`;
}

const installerSize = computed(() => formatBytes(manifest.value?.size_bytes));
const sha256Short = computed(() => {
    const s = manifest.value?.sha256;
    return s ? `${s.slice(0, 8)}…${s.slice(-8)}` : null;
});
</script>

<template>
    <div class="max-w-4xl mx-auto px-6 py-8 space-y-8">
        <SectionHeader
            icon="download"
            :title="__('BlastR Desktop')"
            :subtitle="__('Bridge between blastr.pro and World of Warcraft')"
        />

        <!-- Hero -->
        <section
            class="rounded-2xl border border-white/10 bg-gradient-to-br from-surface-container/80 to-surface-container-low/40 backdrop-blur-md overflow-hidden"
        >
            <div class="p-8 md:p-10 grid md:grid-cols-[1fr_auto] gap-8 items-center">
                <div>
                    <h1 class="font-slogan text-3xl md:text-4xl font-black uppercase tracking-tight italic">
                        <span class="text-white">Blast</span>
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-cyan-300">R</span>
                        <span class="text-white"> Desktop</span>
                    </h1>
                    <p class="mt-3 text-on-surface-variant leading-relaxed max-w-xl">
                        {{ __('The bridge keeps wishlists synced into your addons and pushes RCLootCouncil awards back to blastr.pro automatically — no copy-paste, no manual exports.') }}
                    </p>
                    <div
                        v-if="manifest.latest_version || installerSize"
                        class="mt-3 flex items-center gap-3 font-mono text-[10px] text-on-surface-variant/70 tabular"
                    >
                        <span v-if="manifest.latest_version">v{{ manifest.latest_version }}</span>
                        <span v-if="installerSize" class="opacity-60">· {{ installerSize }}</span>
                        <span v-if="sha256Short" class="opacity-60" :title="manifest.sha256">· sha256 {{ sha256Short }}</span>
                    </div>
                </div>
                <div class="flex flex-col items-stretch md:items-end gap-3 min-w-0 md:min-w-[280px]">
                    <a
                        v-if="downloadAvailable"
                        :href="manifest.download_url"
                        class="px-6 py-3 rounded-xl bg-primary hover:bg-primary/90 text-on-primary font-headline font-bold uppercase tracking-widest text-sm transition flex items-center justify-center gap-2 shadow-lg shadow-primary/20"
                    >
                        <span class="material-symbols-outlined">download</span>
                        {{ __('Download for Windows') }}
                    </a>
                    <button
                        v-else
                        type="button"
                        disabled
                        class="px-6 py-3 rounded-xl bg-white/5 border border-white/10 text-on-surface-variant/60 font-headline font-bold uppercase tracking-widest text-sm flex items-center justify-center gap-2 cursor-not-allowed"
                    >
                        <span class="material-symbols-outlined">hourglass_empty</span>
                        {{ __('Coming soon') }}
                    </button>
                    <p class="text-[10px] text-on-surface-variant/60 text-center md:text-right">
                        {{ __('Windows 10/11 · per-user install · no admin required') }}
                    </p>
                    <a
                        v-if="manifest.changelog_url"
                        :href="manifest.changelog_url"
                        target="_blank"
                        rel="noopener"
                        class="text-[11px] font-headline font-bold uppercase tracking-widest text-on-surface-variant hover:text-white text-center md:text-right"
                    >
                        {{ __("See what's new") }}
                    </a>
                </div>
            </div>

            <!-- Connection status (only when paired) -->
            <div
                v-if="connection.paired"
                class="border-t border-white/5 px-6 py-3 bg-success-neon/5 flex items-center gap-3"
            >
                <span class="material-symbols-outlined text-success-neon">check_circle</span>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-bold text-on-surface">
                        {{ __('Bridge connected') }}
                    </div>
                    <div class="text-[11px] text-on-surface-variant">
                        <template v-if="connection.last_used_at">
                            {{ __('Last seen :time', { time: relativeTime(connection.last_used_at) }) }}
                        </template>
                        <template v-else>
                            {{ __("Authorized but hasn't synced yet — start the bridge.") }}
                        </template>
                    </div>
                </div>
            </div>
        </section>

        <!-- SmartScreen warning -->
        <section
            class="rounded-xl border border-secondary/30 bg-secondary/5 overflow-hidden"
        >
            <button
                type="button"
                class="w-full px-5 py-4 flex items-center gap-3 text-left hover:bg-secondary/10 transition"
                @click="showSmartScreenHelp = !showSmartScreenHelp"
            >
                <span class="material-symbols-outlined text-secondary">shield</span>
                <div class="flex-1 min-w-0">
                    <h3 class="font-headline text-sm font-bold uppercase tracking-widest text-on-surface">
                        {{ __('Windows will warn you about the installer') }}
                    </h3>
                    <p class="text-[11px] text-on-surface-variant mt-1 leading-snug">
                        {{ __('BlastR Desktop is unsigned for now — the warning is normal and harmless. Click to see how to bypass it.') }}
                    </p>
                </div>
                <span
                    class="material-symbols-outlined text-on-surface-variant/60 transition"
                    :class="{ 'rotate-180': showSmartScreenHelp }"
                >
                    expand_more
                </span>
            </button>
            <div v-if="showSmartScreenHelp" class="px-5 pb-5 pt-1 space-y-3 text-sm text-on-surface-variant leading-relaxed">
                <p>
                    {{ __("Windows SmartScreen blocks new files from publishers it doesn't recognize yet. Two clicks let it through:") }}
                </p>
                <ol class="list-decimal pl-5 space-y-2">
                    <li>
                        {{ __('When you double-click') }} <code class="font-mono bg-white/5 px-1.5 py-0.5 rounded">BlastR-Desktop-Setup.exe</code>,
                        {{ __('a blue dialog says') }} <em>{{ __('"Windows protected your PC"') }}</em>.
                    </li>
                    <li>
                        {{ __('Click the small') }} <strong class="text-on-surface">{{ __('More info') }}</strong> {{ __('link, then') }}
                        <strong class="text-on-surface">{{ __('Run anyway') }}</strong>.
                    </li>
                </ol>
                <p>
                    {{ __("The bridge runs from then on without further prompts. We're working on a Microsoft Store release that skips this step entirely.") }}
                </p>
                <p
                    v-if="manifest.sha256"
                    class="rounded-md border border-white/5 bg-white/[0.03] px-3 py-2 font-mono text-[10px] text-on-surface-variant/70 break-all"
                >
                    {{ __('Verify the file hash if you want extra confidence:') }}
                    <br />
                    sha256: {{ manifest.sha256 }}
                </p>
            </div>
        </section>

        <!-- Onboarding steps -->
        <section v-if="onboardingSteps.length">
            <SectionHeader icon="rocket_launch" :title="__('Getting started')" :subtitle="__(`Four steps, then it's hands-off`)" />
            <div class="grid md:grid-cols-2 gap-4">
                <div
                    v-for="(step, idx) in onboardingSteps"
                    :key="idx"
                    class="rounded-xl border border-white/10 bg-surface-container/40 p-5 flex gap-4"
                >
                    <div class="shrink-0 w-10 h-10 rounded-lg bg-primary/10 ring-1 ring-primary/30 flex items-center justify-center">
                        <span class="material-symbols-outlined text-primary">{{ step.icon }}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-baseline gap-2">
                            <span class="font-mono text-[11px] tabular text-on-surface-variant/60">
                                {{ String(idx + 1).padStart(2, '0') }}
                            </span>
                            <h4 class="font-headline text-sm font-bold uppercase tracking-widest text-on-surface">
                                {{ step.title }}
                            </h4>
                        </div>
                        <p class="text-[12px] text-on-surface-variant mt-2 leading-relaxed">
                            {{ step.body }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- What gets installed -->
        <section class="rounded-xl border border-white/10 bg-surface-container/40 p-6">
            <h3 class="font-headline text-sm font-bold uppercase tracking-widest text-on-surface mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">folder_managed</span>
                {{ __('What the installer does') }}
            </h3>
            <ul class="space-y-3 text-[12px] text-on-surface-variant leading-relaxed">
                <li class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-primary text-[18px] mt-0.5">install_desktop</span>
                    <div>
                        <strong class="text-on-surface">{{ __('Per-user install — no admin prompt.') }}</strong>
                        {{ __('Lands under') }}
                        <code class="font-mono bg-white/5 px-1.5 py-0.5 rounded text-[11px]">%LOCALAPPDATA%\Programs\BlastR</code>.
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-primary text-[18px] mt-0.5">extension</span>
                    <div>
                        <strong class="text-on-surface">{{ __('Pulls the BlastR addon from blastr.pro after you sign in') }}</strong>
                        — {{ __('drops it straight into your WoW') }}
                        <code class="font-mono bg-white/5 px-1.5 py-0.5 rounded text-[11px]">Interface\AddOns\</code>
                        {{ __('folder. New addon releases install themselves silently in the background.') }}
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-primary text-[18px] mt-0.5">power_settings_new</span>
                    <div>
                        <strong class="text-on-surface">{{ __('Auto-launches with Windows, hidden in the system tray.') }}</strong>
                        {{ __('You can turn this off in Settings any time.') }}
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-primary text-[18px] mt-0.5">system_update</span>
                    <div>
                        <strong class="text-on-surface">{{ __('Self-updates from blastr.pro.') }}</strong>
                        {{ __('Bridge releases install in the background without admin rights or a re-download by you. Toggle off in Settings if you prefer manual.') }}
                    </div>
                </li>
            </ul>
        </section>

        <!-- Tech footnote -->
        <section
            class="rounded-xl border border-white/5 bg-white/[0.02] px-5 py-4 text-[11px] text-on-surface-variant/70 leading-snug flex items-start gap-3"
        >
            <span class="material-symbols-outlined text-on-surface-variant/60">info</span>
            <div>
                {{ __("BlastR Desktop is Windows-only for now. Mac and Linux support are tracked but unscheduled — let us know if you'd use them.") }}
            </div>
        </section>
    </div>
</template>
