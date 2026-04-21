<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useDodgePanel } from '@/composables/useDodgePanel';
import { useTranslation } from '@/composables/useTranslation';
const { __ } = useTranslation();

const props = defineProps({
    ability: { type: Object, required: true },
});
const emit = defineEmits(['close']);

const position = ref({ x: window.innerWidth - 400, y: 140 });
const isDragging = ref(false);
const dragStart = ref({ x: 0, y: 0 });
const panelRef = ref(null);

const startDrag = (e) => {
    isDragging.value = true;
    dragStart.value = { x: e.clientX - position.value.x, y: e.clientY - position.value.y };
    e.preventDefault();
};
const onMouseMove = (e) => {
    if (!isDragging.value) return;
    position.value = { x: e.clientX - dragStart.value.x, y: e.clientY - dragStart.value.y };
};
const onMouseUp = () => { isDragging.value = false; };
const onKeyDown = (e) => { if (e.key === 'Escape') emit('close'); };

onMounted(() => {
    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('mouseup', onMouseUp);
    window.addEventListener('keydown', onKeyDown);
});
onUnmounted(() => {
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('mouseup', onMouseUp);
    window.removeEventListener('keydown', onKeyDown);
});

useDodgePanel({
    elementRef: panelRef,
    position,
    enabled: () => !isDragging.value,
});

const iconUrl = computed(() => props.ability.icon_filename
    ? `/images/cooldowns/${props.ability.icon_filename}`
    : null);

const wowheadUrl = computed(() => props.ability.spell_id
    ? `https://www.wowhead.com/spell=${props.ability.spell_id}`
    : null);

const formatTime = (sec) => {
    if (sec == null) return '';
    const m = Math.floor(sec / 60);
    const s = Math.floor(sec) % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
};

const priorityTone = computed(() => ({
    high: 'bg-red-500/20 text-red-300 border-red-500/40',
    medium: 'bg-amber-500/20 text-amber-300 border-amber-500/40',
    low: 'bg-white/10 text-white/60 border-white/20',
}[props.ability.priority] || 'bg-white/10 text-white/60 border-white/20'));

// User-facing label for a recommended_response enum token. Falls back to a
// space-separated version of the raw key when no translation exists.
const prettyResponse = (r) => __('response_' + r) === 'response_' + r
    ? r.replace(/_/g, ' ')
    : __('response_' + r);

const priorityLabel = computed(() => props.ability.priority
    ? __('priority_' + props.ability.priority)
    : '');
const schoolLabel = computed(() => props.ability.school
    ? __('school_' + props.ability.school)
    : '');
const notesLabel = computed(() => {
    const a = props.ability;
    // Locale-specific notes take precedence when present (YAML may carry
    // notes_uk / notes_ru alongside the English notes).
    const locale = (window.appLocale || 'en').toLowerCase();
    return a['notes_' + locale] || a.notes || '';
});

</script>

<template>
    <div ref="panelRef"
        class="fixed z-50 w-[360px] rounded-xl shadow-2xl bg-[#0f0f12] border border-white/10 overflow-hidden select-none"
        :style="{ left: `${position.x}px`, top: `${position.y}px` }">
        <!-- Header (drag handle) -->
        <div class="flex items-center gap-3 px-4 py-3 bg-gradient-to-b from-white/5 to-transparent cursor-grab"
            :class="{ 'cursor-grabbing': isDragging }"
            @mousedown="startDrag">
            <div class="shrink-0 w-10 h-10 rounded overflow-hidden relative"
                :style="{ boxShadow: `0 0 0 2px ${ability.color || '#A78BFA'}` }">
                <img v-if="iconUrl" :src="iconUrl" :alt="ability.name" class="w-full h-full object-cover" />
                <div v-else class="w-full h-full" :style="{ background: ability.color || '#A78BFA' }"></div>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-sm font-bold text-white leading-tight truncate">{{ ability.name }}</div>
                <div class="text-[10px] text-on-surface-variant/60 mt-0.5 flex items-center gap-2 flex-wrap">
                    <span v-if="ability.priority"
                        class="text-[8px] font-black uppercase tracking-widest px-1.5 py-0.5 rounded border"
                        :class="priorityTone">
                        {{ priorityLabel }}
                    </span>
                    <span v-if="ability.school">{{ schoolLabel }}</span>
                    <span v-if="ability.time != null">· {{ formatTime(ability.time) }}</span>
                    <span v-if="ability.duration_sec">· {{ ability.duration_sec }}{{ __('s channel') }}</span>
                </div>
            </div>
            <button @click="emit('close')"
                class="shrink-0 w-7 h-7 flex items-center justify-center rounded hover:bg-white/10 text-white/50 hover:text-white transition-colors">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <!-- Body -->
        <div class="px-4 py-3 space-y-3">
            <!-- Notes -->
            <div v-if="notesLabel" class="text-[11px] text-on-surface-variant leading-relaxed whitespace-pre-wrap">
                {{ notesLabel }}
            </div>
            <div v-else class="text-[10px] italic text-on-surface-variant/40">
                {{ __('No description available.') }}
            </div>

            <!-- Recommended response -->
            <div v-if="ability.recommended_response?.length">
                <div class="text-[8px] font-black uppercase tracking-widest text-on-surface-variant/40 mb-1.5">
                    {{ __('Recommended Response') }}
                </div>
                <div class="flex flex-wrap gap-1">
                    <span v-for="r in ability.recommended_response" :key="r"
                        class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-primary/15 text-primary border border-primary/25">
                        {{ prettyResponse(r) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-4 py-2 border-t border-white/5 bg-white/[0.02] flex items-center justify-between text-[10px] text-on-surface-variant/50">
            <span v-if="ability.spell_id">{{ __('Spell') }} {{ ability.spell_id }}</span>
            <span v-else></span>
            <a v-if="wowheadUrl" :href="wowheadUrl" target="_blank" rel="noopener"
                class="text-primary/80 hover:text-primary font-medium flex items-center gap-1">
                {{ __('Wowhead') }}
                <span class="material-symbols-outlined text-[12px]">open_in_new</span>
            </a>
        </div>
    </div>
</template>
