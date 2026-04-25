<template>
    <div class="relative" ref="rootEl">
        <button
            type="button"
            @click="open = !open"
            class="flex items-center gap-1 px-2 py-1 rounded-2xl bg-white/[0.04] border border-white/[0.06] text-on-surface-variant text-[11px] font-bold hover:bg-white/[0.08] transition"
        >
            <img :src="`/images/flags/${currentCountry}.svg`" :alt="currentCountry" class="w-4 h-auto rounded-sm">
            <span>{{ current.toUpperCase() }}</span>
            <span class="material-symbols-outlined text-sm">arrow_drop_down</span>
        </button>

        <div
            v-if="open"
            class="absolute top-full right-0 mt-1 z-50 bg-surface-container-highest border border-white/10 rounded-md shadow-2xl min-w-[140px] py-1"
        >
            <form :action="switchUrl" method="POST">
                <input type="hidden" name="_token" :value="csrf">
                <button
                    v-for="locale in locales"
                    :key="locale.code"
                    name="locale"
                    :value="locale.code"
                    type="submit"
                    :class="[
                        'flex items-center gap-2 w-full px-3 py-2 text-xs font-semibold text-start hover:bg-white/5 transition',
                        locale.code === current ? 'text-primary bg-primary/5' : 'text-on-surface',
                    ]"
                >
                    <img :src="`/images/flags/${locale.country}.svg`" :alt="locale.country" class="w-4 h-auto rounded-sm">
                    {{ locale.label }}
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
    current:    { type: String, required: true },
    locales:    { type: Array,  required: true },
    switchUrl:  { type: String, required: true },
    csrf:       { type: String, required: true },
})

const open = ref(false)
const rootEl = ref(null)

const currentCountry = computed(() => {
    const found = props.locales.find(l => l.code === props.current)
    return found?.country ?? 'GB'
})

function onClickOutside(e) {
    if (open.value && rootEl.value && !rootEl.value.contains(e.target)) {
        open.value = false
    }
}

onMounted(() => document.addEventListener('click', onClickOutside))
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside))
</script>
