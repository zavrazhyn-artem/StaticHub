<template>
    <div class="space-y-6 pb-12">
        <div v-if="loading && !payload" class="bg-surface-container border border-white/5 rounded-xl p-12 text-center">
            <span class="material-symbols-outlined animate-spin text-on-surface-variant text-3xl">progress_activity</span>
            <div class="mt-3 text-3xs uppercase tracking-widest text-on-surface-variant font-semibold">
                {{ __('Loading loot history...') }}
            </div>
        </div>

        <div v-else-if="error" class="bg-error/10 border border-error/30 rounded-xl p-6 text-error text-sm">{{ error }}</div>

        <template v-else-if="payload">
            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="bg-surface-container border border-white/5 rounded-xl p-4">
                    <div class="text-3xs uppercase tracking-widest text-on-surface-variant font-semibold">{{ __('Total awards') }}</div>
                    <div class="text-3xl font-black text-white font-headline mt-1">{{ payload.totals.total }}</div>
                </div>
                <div v-for="(label, code) in payload.difficulties" :key="`d-${code}`"
                     class="bg-surface-container border border-white/5 rounded-xl p-4">
                    <div class="text-3xs uppercase tracking-widest text-on-surface-variant font-semibold">{{ label }}</div>
                    <div class="text-3xl font-black font-headline mt-1" :class="diffColor(code)">
                        {{ payload.totals.by_difficulty[code] || 0 }}
                    </div>
                </div>
            </div>

            <div v-if="payload.totals.by_response.length > 0"
                 class="bg-surface-container border border-white/5 rounded-xl p-4">
                <div class="text-3xs uppercase tracking-widest text-on-surface-variant font-semibold mb-3">
                    {{ __('Response breakdown') }}
                </div>
                <div class="flex w-full h-3 rounded-sm overflow-hidden bg-surface-container-highest">
                    <div v-for="r in payload.totals.by_response" :key="r.text"
                         class="h-full"
                         :style="{ width: pct(r.count) + '%', backgroundColor: r.color || '#8b8b8b' }"
                         :title="`${r.text}: ${r.count}`"></div>
                </div>
                <div class="flex flex-wrap gap-x-4 gap-y-1 mt-3 text-3xs">
                    <div v-for="r in payload.totals.by_response" :key="`l-${r.text}`" class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-sm inline-block" :style="{ backgroundColor: r.color || '#8b8b8b' }"></span>
                        <span class="text-on-surface-variant">{{ r.text }}</span>
                        <span class="text-white font-semibold">{{ r.count }}</span>
                    </div>
                </div>
            </div>

            <LootHistoryCharacterRoll
                v-if="payload.perCharacter.length > 0"
                :rows="payload.perCharacter"
                :methods="payload.methods"
            />

            <!-- Reset bar above the table. Always renders (fixed h-6) but
                 hides via `invisible` when no filter is active — so the
                 table doesn't jump up/down each time a filter toggles. -->
            <div class="h-6 flex items-center" :class="{ 'invisible': !hasActiveFilters }">
                <button @click="resetFilters" type="button"
                        class="inline-flex items-center gap-1.5 text-3xs uppercase tracking-widest text-on-surface-variant font-semibold hover:text-white transition">
                    <span class="material-symbols-outlined text-sm">filter_alt_off</span>
                    {{ __('Reset filters') }}
                </button>
            </div>

            <!-- Main table — inline per-column filters live in the second header row -->
            <div class="bg-surface-container border border-white/5 rounded-xl shadow-2xl backdrop-blur-sm min-h-[400px]">
                <!-- overflow-visible (not -x-auto) so SelectUserWithMain /
                     StyledSelect dropdowns aren't clipped by the wrapper.
                     min-h-[400px] keeps the layout from collapsing when
                     a filter narrows results down to one or two rows. -->
                <div class="overflow-visible">
                    <table class="w-full text-left border-collapse" style="table-layout: fixed;">
                        <!-- Fixed column widths so adding/removing rows
                             doesn't snap the layout — Date trimmed to
                             ~half, Boss inherits the freed pixels, every
                             other column gets an explicit width. Item
                             stays auto so long item names still breathe. -->
                        <colgroup>
                            <col style="width: 13%;" />   <!-- Date -->
                            <col style="width: 16%;" />  <!-- Recipient -->
                            <col />                      <!-- Item — fluid, takes the remaining ~28% -->
                            <col style="width: 7%;" />   <!-- Slot -->
                            <col style="width: 5%;" />   <!-- iLvl -->
                            <col style="width: 17%;" />  <!-- Boss -->
                            <col style="width: 6%;" />   <!-- Diff -->
                            <col style="width: 9%;" />   <!-- Response -->
                            <col style="width: 5%;" />   <!-- Votes -->
                        </colgroup>
                        <thead>
                            <tr class="bg-surface-container-highest border-b border-white/5">
                                <SortableTh field="awarded_at"      :sort="payload.filters.sort" :dir="payload.filters.dir" @sort="onSort">{{ __('Date') }}</SortableTh>
                                <SortableTh field="recipient_name"  :sort="payload.filters.sort" :dir="payload.filters.dir" @sort="onSort">{{ __('Recipient') }}</SortableTh>
                                <SortableTh field="item_id"         :sort="payload.filters.sort" :dir="payload.filters.dir" @sort="onSort">{{ __('Item') }}</SortableTh>
                                <SortableTh field="item_slot"       :sort="payload.filters.sort" :dir="payload.filters.dir" @sort="onSort">{{ __('Slot') }}</SortableTh>
                                <SortableTh field="item_level"      :sort="payload.filters.sort" :dir="payload.filters.dir" @sort="onSort">{{ __('iLvl') }}</SortableTh>
                                <SortableTh field="boss_name"       :sort="payload.filters.sort" :dir="payload.filters.dir" @sort="onSort">{{ __('Boss') }}</SortableTh>
                                <SortableTh field="raid_difficulty" :sort="payload.filters.sort" :dir="payload.filters.dir" @sort="onSort">{{ __('Diff') }}</SortableTh>
                                <SortableTh field="method"          :sort="payload.filters.sort" :dir="payload.filters.dir" @sort="onSort">{{ __('Response') }}</SortableTh>
                                <th class="px-4 py-3 text-3xs font-semibold text-on-surface-variant uppercase tracking-wider text-right">{{ __('Votes') }}</th>
                            </tr>
                            <tr class="loot-filter-row bg-surface-container border-b border-white/5">
                                <!-- Date -->
                                <th class="px-2 py-2 align-top">
                                    <div class="relative h-8">
                                        <span class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                                            <span class="material-symbols-outlined text-on-surface-variant text-sm">calendar_month</span>
                                        </span>
                                        <input ref="dateInputRef" type="text"
                                               :placeholder="__('Date range')"
                                               class="w-full h-full pl-7 pr-6 bg-surface-container-highest text-3xs rounded-md border border-white/5 text-white placeholder:text-on-surface-variant/50 cursor-pointer focus:border-primary outline-none" />
                                        <button v-if="filterState.from || filterState.to" @click="clearDate" type="button"
                                                class="absolute inset-y-0 right-0 pr-1.5 flex items-center text-on-surface-variant hover:text-white">
                                            <span class="material-symbols-outlined text-xs">close</span>
                                        </button>
                                    </div>
                                </th>
                                <!-- Recipient -->
                                <th class="px-2 py-2 align-top">
                                    <div class="relative">
                                        <SelectUserWithMain
                                            v-model="filterState.character_id"
                                            :members="recipientMembers"
                                            :placeholder="__('All recipients')"
                                            :search-placeholder="__('Search character...')"
                                            accent-color="#a78bfa"
                                            @update:model-value="commitNow"
                                        />
                                        <button v-if="filterState.character_id"
                                                @click.stop="clearRecipient" type="button"
                                                class="absolute right-7 top-1/2 -translate-y-1/2 z-[55] text-on-surface-variant hover:text-white"
                                                :title="__('Clear recipient')">
                                            <span class="material-symbols-outlined text-xs">close</span>
                                        </button>
                                    </div>
                                </th>
                                <!-- Item -->
                                <th class="px-2 py-2 align-top">
                                    <input type="text" v-model="filterState.item" @input="commitDebounced"
                                           :placeholder="__('Item name or id')"
                                           class="w-full h-8 px-2 bg-surface-container-highest text-3xs rounded-md border border-white/5 text-white placeholder:text-on-surface-variant/50 focus:border-primary outline-none" />
                                </th>
                                <!-- Slot -->
                                <th class="px-2 py-2 align-top">
                                    <StyledSelect v-model="filterState.slot" :options="slotOptions" :placeholder="__('All')" min-width="80px" @update:model-value="commitNow" />
                                </th>
                                <!-- iLvl (no filter) -->
                                <th></th>
                                <!-- Boss (encounter_id from season catalogue) -->
                                <th class="px-2 py-2 align-top">
                                    <SearchableSelect
                                        v-model="filterState.encounter_id"
                                        :options="bossOptions"
                                        :placeholder="__('All bosses')"
                                        :search-placeholder="__('Search boss...')"
                                        icon="skull"
                                        accent-color="#ef4444"
                                        @update:model-value="commitNow"
                                    />
                                </th>
                                <!-- Difficulty -->
                                <th class="px-2 py-2 align-top">
                                    <StyledSelect v-model="filterState.difficulty" :options="difficultyOptions" :placeholder="__('All')" min-width="70px" @update:model-value="commitNow" />
                                </th>
                                <!-- Method -->
                                <th class="px-2 py-2 align-top">
                                    <StyledSelect v-model="filterState.method" :options="methodOptions" :placeholder="__('All')" min-width="100px" @update:model-value="commitNow" />
                                </th>
                                <!-- Votes (no filter) -->
                                <th></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="row in payload.rows" :key="row.id" class="hover:bg-white/5 transition-colors">
                                <td class="px-4 py-3 text-3xs text-on-surface-variant font-medium whitespace-nowrap">
                                    {{ formatDate(row.awarded_at) }}
                                </td>
                                <td class="px-4 py-3 min-w-0">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <img v-if="row.spec_icon" :src="row.spec_icon" :alt="row.spec_name || ''"
                                             class="w-7 h-7 rounded border border-white/10 shrink-0" />
                                        <div class="flex flex-col leading-tight min-w-0">
                                            <span class="text-xs font-bold truncate"
                                                  :style="{ color: getClassTextColor(row.recipient_class) }">
                                                {{ row.recipient_display }}
                                            </span>
                                            <span v-if="row.spec_name" class="text-[10px] text-on-surface-variant/70 truncate">
                                                {{ row.spec_name }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 min-w-0">
                                    <a :href="wowheadUrl(row)" target="_blank" rel="noopener"
                                       class="flex items-center gap-2 text-xs font-medium hover:underline min-w-0"
                                       :style="{ color: itemColorByDifficulty(row.raid_difficulty) }">
                                        <img v-if="row.item_icon" :src="iconUrl(row.item_icon)" class="w-5 h-5 rounded-sm shrink-0" :alt="row.item_name || ''" />
                                        <span class="truncate">{{ row.item_name || `#${row.item_id}` }}</span>
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-3xs text-on-surface-variant">{{ row.item_slot || '—' }}</td>
                                <td class="px-4 py-3 text-3xs text-white font-semibold">{{ row.item_level || '—' }}</td>
                                <td class="px-4 py-3 text-3xs min-w-0">
                                    <div class="text-on-surface truncate">{{ row.boss_display || '—' }}</div>
                                    <div v-if="row.raid_name" class="text-on-surface-variant/60 truncate">{{ row.raid_name }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-3xs font-bold uppercase" :class="diffColor(row.raid_difficulty)">{{ row.raid_difficulty }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-3xs font-semibold uppercase tracking-wider"
                                          :style="{ color: row.response_color || '#aaa' }">
                                        {{ row.response_text || payload.methods[row.method] || row.method }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-3xs text-right text-on-surface-variant font-semibold">
                                    {{ row.council_same_vote ?? '—' }}
                                </td>
                            </tr>
                            <tr v-if="payload.rows.length === 0">
                                <td colspan="9" class="px-6 py-12 text-center text-on-surface-variant italic text-sm">
                                    {{ __('No loot awards match the current filters.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination bar: per-page selector + numbered page picker
                 + total count. Numbered picker shows up to 7 buttons
                 with ellipses, current always centered when possible. -->
            <div class="flex flex-col md:flex-row items-center justify-between gap-3 px-1">
                <div class="flex items-center gap-2 text-3xs uppercase tracking-widest text-on-surface-variant font-semibold">
                    <span>{{ __('Per page') }}</span>
                    <StyledSelect
                        :model-value="payload.filters.per_page"
                        :options="perPageSelectOptions"
                        min-width="64px"
                        accent-color="#a78bfa"
                        @update:model-value="changePerPage"
                    />
                </div>

                <div v-if="payload.pagination.last_page > 1" class="flex items-center gap-1">
                    <button @click="loadPage(1)" :disabled="page === 1" :title="__('First')"
                            class="w-8 h-8 grid place-items-center text-xs bg-surface-container border border-white/5 rounded hover:bg-white/5 disabled:opacity-30 disabled:cursor-not-allowed">
                        <span class="material-symbols-outlined text-sm">first_page</span>
                    </button>
                    <button @click="loadPage(page - 1)" :disabled="page === 1" :title="__('Previous')"
                            class="w-8 h-8 grid place-items-center text-xs bg-surface-container border border-white/5 rounded hover:bg-white/5 disabled:opacity-30 disabled:cursor-not-allowed">
                        <span class="material-symbols-outlined text-sm">chevron_left</span>
                    </button>
                    <template v-for="(p, idx) in pageNumbers" :key="`pg-${idx}-${p}`">
                        <span v-if="p === '...'" class="px-1 text-xs text-on-surface-variant/60">…</span>
                        <button v-else @click="loadPage(p)"
                                :class="[
                                    'w-8 h-8 grid place-items-center text-xs rounded border transition',
                                    p === page
                                        ? 'bg-primary text-on-primary border-primary font-bold'
                                        : 'bg-surface-container border-white/5 text-on-surface-variant hover:bg-white/5 hover:text-white'
                                ]">
                            {{ p }}
                        </button>
                    </template>
                    <button @click="loadPage(page + 1)" :disabled="page === lastPage" :title="__('Next')"
                            class="w-8 h-8 grid place-items-center text-xs bg-surface-container border border-white/5 rounded hover:bg-white/5 disabled:opacity-30 disabled:cursor-not-allowed">
                        <span class="material-symbols-outlined text-sm">chevron_right</span>
                    </button>
                    <button @click="loadPage(lastPage)" :disabled="page === lastPage" :title="__('Last')"
                            class="w-8 h-8 grid place-items-center text-xs bg-surface-container border border-white/5 rounded hover:bg-white/5 disabled:opacity-30 disabled:cursor-not-allowed">
                        <span class="material-symbols-outlined text-sm">last_page</span>
                    </button>
                </div>

                <div class="text-3xs uppercase tracking-widest text-on-surface-variant font-semibold">
                    {{ __('Total') }}: <span class="text-white normal-case tracking-normal">{{ payload.pagination.total }}</span>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick, h } from 'vue';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/themes/dark.css';
import { Ukrainian } from 'flatpickr/dist/l10n/uk.js';
import { useTranslation } from '@/composables/useTranslation';
import { useWowClasses } from '@/composables/useWowClasses';
import SelectUserWithMain from '@/Components/UI/SelectUserWithMain.vue';
import StyledSelect from '@/Components/UI/StyledSelect.vue';
import SearchableSelect from '@/Components/UI/SearchableSelect.vue';
import LootHistoryCharacterRoll from './LootHistoryCharacterRoll.vue';

const { __ } = useTranslation();
const { getClassTextColor } = useWowClasses();

const props = defineProps({
    payloadUrl: { type: String, required: true },
});

const payload = ref(null);
const loading = ref(false);
const error = ref(null);
const dateInputRef = ref(null);
let dateFp = null;
let searchTimer = null;

// Mutable filter state — pushes through commit() to the server.
const filterState = reactive({
    difficulty: null,
    slot: null,
    method: null,
    character_id: null,
    from: null,
    to: null,
    item: '',
    encounter_id: null,
});

const SortableTh = {
    props: ['field', 'sort', 'dir'],
    emits: ['sort'],
    setup(p, { emit, slots }) {
        return () => {
            const active = p.sort === p.field;
            const arrow = active ? (p.dir === 'asc' ? 'arrow_upward' : 'arrow_downward') : 'unfold_more';
            return h('th', {
                class: 'px-4 py-3 text-3xs font-semibold uppercase tracking-wider cursor-pointer select-none ' +
                       (active ? 'text-white' : 'text-on-surface-variant hover:text-white'),
                onClick: () => emit('sort', p.field),
            }, [
                h('span', { class: 'inline-flex items-center gap-1' }, [
                    h('span', null, slots.default ? slots.default() : ''),
                    h('span', { class: 'material-symbols-outlined text-xs', style: 'font-size: 14px' }, arrow),
                ]),
            ]);
        };
    },
};

const fetchPayload = async (params = {}) => {
    loading.value = true;
    error.value = null;
    try {
        const url = new URL(props.payloadUrl, window.location.origin);
        Object.entries(params).forEach(([k, v]) => {
            if (v !== null && v !== undefined && v !== '') url.searchParams.set(k, v);
        });
        const r = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        payload.value = await r.json();
        // Sync local state with canonical filters from server (handles
        // first load + reset).
        const f = payload.value.filters;
        ['difficulty', 'slot', 'method', 'character_id', 'from', 'to', 'item', 'encounter_id']
            .forEach(k => { filterState[k] = f[k] ?? (k === 'item' ? '' : null); });
        // Re-sync flatpickr with canonical from/to.
        if (dateFp) {
            const dates = [];
            if (f.from) dates.push(f.from);
            if (f.to) dates.push(f.to);
            dateFp.setDate(dates, false);
        }
    } catch (e) {
        error.value = String(e.message || e);
    } finally {
        loading.value = false;
    }
};

const buildParams = () => ({
    sort: payload.value?.filters.sort,
    dir:  payload.value?.filters.dir,
    per_page: payload.value?.filters.per_page,
    difficulty:   filterState.difficulty,
    slot:         filterState.slot,
    method:       filterState.method,
    character_id: filterState.character_id,
    from:         filterState.from,
    to:           filterState.to,
    item:         filterState.item,
    encounter_id: filterState.encounter_id,
    page: 1,
});

const commitNow = () => fetchPayload(buildParams());
const commitDebounced = () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(commitNow, 300);
};
const resetFilters = () => fetchPayload({ sort: payload.value?.filters.sort, dir: payload.value?.filters.dir, per_page: payload.value?.filters.per_page });
const loadPage = (page) => fetchPayload({ ...buildParams(), page });
const onSort = (field) => {
    const cur = payload.value?.filters || {};
    const dir = (cur.sort === field && cur.dir === 'desc') ? 'asc' : 'desc';
    fetchPayload({ ...buildParams(), sort: field, dir });
};
const changePerPage = (n) => fetchPayload({ ...buildParams(), per_page: Number(n) });
const clearDate = () => {
    filterState.from = null;
    filterState.to = null;
    if (dateFp) dateFp.clear();
    commitNow();
};

const clearRecipient = () => {
    filterState.character_id = null;
    commitNow();
};

const hasActiveFilters = computed(() => {
    const f = filterState;
    return Boolean(f.difficulty || f.slot || f.method || f.character_id
        || f.from || f.to || (f.item && f.item.length) || f.encounter_id);
});

const bossOptions = computed(() =>
    (payload.value?.availableBosses ?? []).map(b => ({
        id: b.encounter_id,
        name: b.boss_name,
    }))
);

const perPageSelectOptions = computed(() =>
    (payload.value?.perPageOptions ?? []).map(n => ({ value: n, label: String(n) }))
);

const page = computed(() => payload.value?.pagination.current_page ?? 1);
const lastPage = computed(() => payload.value?.pagination.last_page ?? 1);

// Compact numbered pagination — show first, last, current ± 1, with
// `…` separators when the gap is wider than one page. Caps at ~7 visible
// buttons so the bar stays narrow on small screens.
const pageNumbers = computed(() => {
    const c = page.value;
    const last = lastPage.value;
    if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);

    const set = new Set([1, last, c - 1, c, c + 1]);
    const sorted = [...set].filter(n => n >= 1 && n <= last).sort((a, b) => a - b);
    const out = [];
    let prev = 0;
    for (const n of sorted) {
        if (prev && n - prev > 1) out.push('...');
        out.push(n);
        prev = n;
    }
    return out;
});

// SelectUserWithMain wants `[{id, name, character: {name, playable_class}}]`.
// We fold mains + alts into one list and stamp role into the rendered
// label so the picker shows "Zaavrik (alt)" inline. Same component the
// rest of the app uses.
const recipientMembers = computed(() => {
    const list = payload.value?.rosterCharacters ?? [];
    return list.map(c => ({
        id: c.id,
        name: c.name + (c.role === 'alt' ? ' (alt)' : ''),
        character: { name: c.name, playable_class: c.class, avatar_url: c.avatar_url },
    }));
});

const slotOptions = computed(() => [
    { value: null, label: __('All') },
    ...((payload.value?.availableSlots ?? []).map(s => ({ value: s, label: s }))),
]);

const difficultyOptions = computed(() => [
    { value: null, label: __('All') },
    ...Object.entries(payload.value?.difficulties ?? {}).map(([code, label]) => ({ value: code, label })),
]);

const methodOptions = computed(() => [
    { value: null, label: __('All') },
    ...Object.entries(payload.value?.methods ?? {}).map(([code, label]) => ({ value: code, label })),
]);

const pct = (count) => {
    if (!payload.value?.totals.total) return 0;
    return (count / payload.value.totals.total) * 100;
};

// Stays in lockstep with itemColorByDifficulty so the difficulty cell,
// the M/H/N stat cards and the item-name colour all share one palette.
const diffColor = (code) => ({
    M: 'text-orange-500',
    H: 'text-purple-400',
    N: 'text-blue-400',
    R: 'text-on-surface-variant',
}[code] || 'text-white');

const formatDate = (raw) => {
    if (!raw) return '—';
    const d = new Date(raw);
    return d.toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: '2-digit',
        hour: '2-digit', minute: '2-digit', hour12: false,
    }).replace(',', '');
};

const wowheadUrl = (row) => {
    const params = [];
    if (row.bonus_ids?.length) params.push('bonus=' + row.bonus_ids.join(':'));
    if (row.item_level) params.push('ilvl=' + row.item_level);
    return `https://www.wowhead.com/item=${row.item_id}` + (params.length ? '?' + params.join('&') : '');
};

const iconUrl = (icon) => {
    if (!icon) return '';
    if (icon.startsWith('http')) return icon;
    return `https://wow.zamimg.com/images/wow/icons/medium/${icon.toLowerCase()}.jpg`;
};

// Item colour by RAID difficulty — app-wide convention (matches the
// difficulty cell colour and the stat-card numbers): Mythic=orange,
// Heroic=purple, Normal=blue, LFR=grey. We use difficulty rather than
// item quality so that two drops of the same item from M vs H clearly
// differ in the table at a glance.
const DIFF_HEX = {
    M: '#ff8000', // orange
    H: '#a335ee', // purple
    N: '#0070dd', // blue
    R: '#9d9d9d', // grey
};
const itemColorByDifficulty = (code) => DIFF_HEX[code] ?? '#ffffff';

// Init flatpickr after the input is in the DOM. We reuse the same range
// + Ukrainian-locale config as the Logs page so date filtering feels
// identical across the app.
watch(payload, async (v) => {
    if (!v) return;
    await nextTick();
    if (dateInputRef.value && !dateFp) {
        const dates = [];
        if (v.filters.from) dates.push(v.filters.from);
        if (v.filters.to) dates.push(v.filters.to);
        dateFp = flatpickr(dateInputRef.value, {
            mode: 'range',
            dateFormat: 'Y-m-d',
            defaultDate: dates,
            locale: Ukrainian,
            onChange: (selected) => {
                if (selected.length === 2) {
                    const adj = (d) => {
                        const offset = d.getTimezoneOffset() * 60000;
                        return new Date(d.getTime() - offset).toISOString().split('T')[0];
                    };
                    filterState.from = adj(selected[0]);
                    filterState.to = adj(selected[1]);
                    commitNow();
                } else if (selected.length === 0) {
                    filterState.from = null;
                    filterState.to = null;
                    commitNow();
                }
            },
        });
    }
}, { flush: 'post' });

onMounted(() => fetchPayload({}));
</script>

<style>
/* GLOBAL (not scoped) — :deep() against scoped data-attrs loses the
   specificity battle vs SelectUserWithMain's `min-h-10` and
   SearchableSelect's `min-h-12 py-3` defaults. Restricted to
   .loot-filter-row so this never bleeds into other pages. */
.loot-filter-row button,
.loot-filter-row input,
.loot-filter-row .relative > div[class*="cursor-pointer"] {
    height: 2rem !important;
    min-height: 2rem !important;
    max-height: 2rem !important;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
    box-sizing: border-box;
}
.loot-filter-row button { line-height: 1; }

/* Inner trigger content (avatar + name + chevron) ships with its own
   padding and gap — squeeze them so 32px doesn't burst. */
.loot-filter-row .w-6.h-6,
.loot-filter-row .w-8.h-8 {
    width: 1.25rem !important;
    height: 1.25rem !important;
}
.loot-filter-row .gap-3 { gap: 0.5rem !important; }
.loot-filter-row .px-3 { padding-left: 0.5rem !important; padding-right: 0.5rem !important; }

/* SearchableSelect (boss picker) defaults to text-sm + pl-10 + uppercase
   font-headline + bold + tracking-widest — visually huge for our 32px
   shell. Strip every loud utility from anything inside .loot-filter-row
   so the trigger reads the same as the options dropdown. */
.loot-filter-row .font-headline { font-size: 0.6875rem !important; letter-spacing: 0 !important; }
.loot-filter-row .font-bold     { font-weight: 500 !important; }
.loot-filter-row .uppercase     { text-transform: none !important; }
.loot-filter-row .text-sm       { font-size: 0.75rem !important; }
.loot-filter-row .tracking-widest,
.loot-filter-row .tracking-wider { letter-spacing: 0 !important; }
.loot-filter-row button[class*="pl-10"] { padding-left: 1.75rem !important; }
.loot-filter-row button[class*="pr-8"]  { padding-right: 1.5rem !important; }

/* Long labels — ellipsis instead of growing the cell. */
.loot-filter-row button > span,
.loot-filter-row button > div {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    min-width: 0;
}
</style>
