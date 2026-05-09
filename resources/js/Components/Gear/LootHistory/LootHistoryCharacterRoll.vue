<template>
    <div class="bg-surface-container border border-white/5 rounded-xl overflow-hidden">
        <div class="px-4 py-3 border-b border-white/5">
            <div class="text-3xs uppercase tracking-widest text-on-surface-variant font-semibold">
                {{ __('Per-character roll-up') }}
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-container-highest border-b border-white/5">
                        <th class="px-4 py-2 text-3xs font-semibold text-on-surface-variant uppercase tracking-wider">{{ __('Character') }}</th>
                        <th class="px-4 py-2 text-3xs font-semibold text-on-surface-variant uppercase tracking-wider text-right">{{ __('Total') }}</th>
                        <th v-for="(label, code) in methods" :key="code"
                            class="px-3 py-2 text-3xs font-semibold text-on-surface-variant uppercase tracking-wider text-right">
                            {{ label }}
                        </th>
                        <th class="px-4 py-2 text-3xs font-semibold text-on-surface-variant uppercase tracking-wider whitespace-nowrap">{{ __('Last loot') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <tr v-for="r in rows" :key="r.character_id || r.name" class="hover:bg-white/5">
                        <td class="px-4 py-2">
                            <span class="text-xs font-bold cursor-default" :style="{ color: getClassTextColor(r.class) }">
                                {{ r.name }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right text-xs text-white font-bold font-headline">{{ r.total }}</td>
                        <td v-for="(_, code) in methods" :key="`m-${code}`"
                            class="px-3 py-2 text-right text-3xs"
                            :class="(r.by_method[code] || 0) > 0 ? 'text-white font-semibold' : 'text-on-surface-variant'">
                            {{ r.by_method[code] || 0 }}
                        </td>
                        <td class="px-4 py-2 text-3xs text-on-surface-variant whitespace-nowrap">
                            {{ formatDate(r.last_awarded_at) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { useTranslation } from '@/composables/useTranslation';
import { useWowClasses } from '@/composables/useWowClasses';

const { __ } = useTranslation();
const { getClassTextColor } = useWowClasses();

defineProps({
    rows: { type: Array, required: true },
    methods: { type: Object, required: true },
});

const formatDate = (raw) => {
    if (!raw) return '—';
    const d = new Date(raw);
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: '2-digit' }).replace(',', '');
};
</script>
