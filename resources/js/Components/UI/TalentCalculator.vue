<script setup>
import { ref, watch, onMounted, onUnmounted, computed } from 'vue';

const props = defineProps({
    talentCode: { type: String, default: '' },
    readonly:   { type: Boolean, default: true },
    updateUrl:  { type: String, default: null },
    csrfToken:  { type: String, default: null },
});

const emit = defineEmits(['saved']);

// ── Unique container ID per instance ────────────────────────────────────────
const containerId = 'talent-calc-' + Math.random().toString(36).slice(2, 8);

// ── State ────────────────────────────────────────────────────────────────────
const loading  = ref(true);
const saving   = ref(false);
const error    = ref(null);

let calculator = null;

// ── CSS + JS loader helpers ──────────────────────────────────────────────────
const CSS_HREF = '/vendor/talent-calculator/midnight-talent-calculator.css';
const JS_SRC   = '/vendor/talent-calculator/midnight-talent-calculator.js';

function ensureCSS() {
    const existing = document.querySelector(`link[href="${CSS_HREF}"]`);
    if (existing) return Promise.resolve();
    return new Promise((resolve) => {
        const link = document.createElement('link');
        link.rel  = 'stylesheet';
        link.href = CSS_HREF;
        link.onload = resolve;
        link.onerror = resolve; // don't block init on CSS failure
        document.head.appendChild(link);
    });
}

function ensureJS() {
    if (window.MidnightTalentCalculator) return Promise.resolve();
    return new Promise((resolve, reject) => {
        const existing = document.querySelector(`script[src="${JS_SRC}"]`);
        if (existing) {
            // Script tag already in DOM but window global not ready yet — poll briefly
            const interval = setInterval(() => {
                if (window.MidnightTalentCalculator) {
                    clearInterval(interval);
                    resolve();
                }
            }, 50);
            // Bail after 5 s
            setTimeout(() => {
                clearInterval(interval);
                if (window.MidnightTalentCalculator) resolve();
                else reject(new Error('MidnightTalentCalculator failed to load'));
            }, 5000);
            return;
        }
        const script = document.createElement('script');
        script.src = JS_SRC;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Failed to load talent-calculator script'));
        document.head.appendChild(script);
    });
}

// ── Init calculator ──────────────────────────────────────────────────────────
async function initCalculator() {
    try {
        loading.value = true;
        error.value   = null;

        await Promise.all([ensureCSS(), ensureJS()]);

        const container = document.getElementById(containerId);
        if (!container) return;

        calculator = new window.MidnightTalentCalculator(
            containerId,
            null,
            props.talentCode ?? '',
            {}
        );
        calculator.run();

    } catch (err) {
        error.value = err.message ?? 'Failed to load talent calculator';
    } finally {
        loading.value = false;
    }
}

// ── Watch talentCode prop ────────────────────────────────────────────────────
watch(
    () => props.talentCode,
    (newCode) => {
        if (calculator && newCode != null) {
            calculator.importString(newCode);
        }
    }
);

// ── Save build ───────────────────────────────────────────────────────────────
async function saveBuild() {
    if (!calculator || !props.updateUrl) return;

    const code = calculator.exportString();
    saving.value = true;
    error.value  = null;

    try {
        const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
        if (props.csrfToken) headers['X-CSRF-TOKEN'] = props.csrfToken;

        const response = await fetch(props.updateUrl, {
            method: 'PATCH',
            headers,
            body: JSON.stringify({ talent_loadout_code: code }),
        });

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            throw new Error(data.message ?? `Server error ${response.status}`);
        }

        emit('saved', code);
    } catch (err) {
        error.value = err.message ?? 'Failed to save build';
    } finally {
        saving.value = false;
    }
}

// ── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(initCalculator);

onUnmounted(() => {
    calculator = null;
});

// ── Computed wrapper class ───────────────────────────────────────────────────
const wrapperClass = computed(() => ({
    'readonly-calc': props.readonly,
}));
</script>

<template>
    <div class="talent-calculator-wrapper">

        <!-- Loading state -->
        <div
            v-if="loading"
            class="flex items-center justify-center py-8 text-on-surface-variant text-sm"
        >
            <span class="animate-pulse">Loading talent calculator…</span>
        </div>

        <!-- Error state -->
        <div
            v-if="error && !loading"
            class="py-6 text-center text-error text-sm"
        >
            {{ error }}
        </div>

        <!-- Calculator container -->
        <div
            :id="containerId"
            :class="[wrapperClass, { 'calc-readonly': readonly, 'min-h-[200px]': loading }]"
        />

        <!-- Save area (editable mode only) -->
        <div
            v-if="!readonly && updateUrl"
            class="save-area mt-4 flex items-center gap-3"
        >
            <button
                :disabled="saving || loading"
                class="px-4 py-2 rounded-lg bg-cyan-500/20 border border-cyan-400/40 text-cyan-100
                       text-xs font-bold uppercase tracking-widest
                       hover:bg-cyan-500/30 transition-colors
                       disabled:opacity-40 disabled:cursor-not-allowed"
                @click="saveBuild"
            >
                <span v-if="saving">Saving…</span>
                <span v-else>Save Build</span>
            </button>

            <span v-if="error && !loading" class="text-error text-xs">{{ error }}</span>
        </div>

    </div>
</template>

<style scoped>
/* Read-only: block all mouse interaction on the calculator itself */
.calc-readonly {
    pointer-events: none;
    user-select: none;
}

/* Hide interactive UI controls when in read-only mode */
.readonly-calc :deep(.control-buttons-container),
.readonly-calc :deep(.embed-export-open),
.readonly-calc :deep(.embed-share-build-toggle) {
    display: none !important;
}

/* Smooth reveal once the lib has injected its DOM */
:deep(.midnight-skill-builder) {
    animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to   { opacity: 1; }
}
</style>
