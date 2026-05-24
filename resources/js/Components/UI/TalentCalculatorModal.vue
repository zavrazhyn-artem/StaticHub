<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { decodeTalentCode, buildAllNodeIds, encodeTalentCode } from '@/lib/talents/decoder.js';
import { loadSpecBundle, iconUrl } from '@/lib/talents/data.js';
import TalentTreeColumn from '@/lib/talents/TalentTreeColumn.vue';
import GlassModal from '@/Components/UI/GlassModal.vue';

const props = defineProps({
    show:       { type: Boolean, default: false },
    title:      { type: String, default: 'Talent Build' },
    subtitle:   { type: String, default: null },
    talentCode: { type: String, default: '' },
    specId:     { type: Number, default: null },
    readonly:   { type: Boolean, default: true },
    updateUrl:  { type: String, default: null },
    csrfToken:  { type: String, default: null },
});

const emit = defineEmits(['saved', 'close']);

const loading   = ref(true);
const error     = ref(null);
const saving    = ref(false);
const draftCode = ref(props.talentCode ?? '');

const bundle  = ref(null);     // { classData, specData, classJson, spec }
const decoded = ref(null);     // { specId, version, nodes }
const editedNodes = ref({});   // editable local state; mirrors decoded.nodes plus user edits
const allNodeIds  = ref([]);   // canonical bitstream order, set after bundle loads

// What we show in trees: edits in editable mode, decoded state otherwise.
const effectiveNodes = computed(() => props.readonly ? (decoded.value?.nodes ?? {}) : editedNodes.value);
const dirty          = computed(() => !props.readonly && draftCode.value !== (props.talentCode ?? ''));

// Resolve which hero tree side (left/right) the build picked. The decoded
// `choice` on the spec's metaNodeId tells us: 0=left, 1=right.
const activeHero = computed(() => {
    if (!bundle.value || !decoded.value) return null;
    const meta = bundle.value.spec.hero?.metaNodeId;
    if (!meta) return null;
    const choice = decoded.value.nodes[meta]?.choice;
    if (choice === 1) return bundle.value.spec.hero.right;
    return bundle.value.spec.hero.left;  // default to left if no selection
});

// Hero meta = the central "select your hero tree" pylon. We synthesize a
// renderable node from the active hero tree's metadata (icon/name come from
// the chosen subtree, spellId from the metaNode entry if available).
const heroMetaNode = computed(() => {
    if (!bundle.value || !activeHero.value) return null;
    const metaId = bundle.value.spec.hero?.metaNodeId;
    const allHeroNodes = {
        ...bundle.value.spec.hero.left.nodes,
        ...bundle.value.spec.hero.right.nodes,
    };
    const metaNode = allHeroNodes[metaId];
    return {
        icon:    activeHero.value.icon || metaNode?.spells?.[0]?.icon,
        name:    activeHero.value.name || 'Hero Talents',
        spellId: metaNode?.spells?.[0]?.spellId ?? null,
    };
});

// Max talent point pool per tree — Midnight expansion grants 34/34/13.
// These are fixed by level grants, not derivable from node maxRanks sums.
const classTotal = computed(() => 34);
const specTotal  = computed(() => 34);
const heroTotal  = computed(() => 13);

// ── Load + decode whenever talentCode changes ────────────────────────────────
async function loadFromCode(code) {
    loading.value = true;
    error.value = null;
    bundle.value = null;
    decoded.value = null;

    try {
        // Resolve spec id — either from the talent code header or from the prop
        // (used when starting from a blank build).
        let specIdToLoad = null;
        if (code) {
            const peek = decodeTalentCode(code, []);
            specIdToLoad = peek.specId || null;
        }
        if (!specIdToLoad && props.specId) specIdToLoad = Number(props.specId);

        if (!specIdToLoad) {
            loading.value = false;
            return;  // no code, no spec hint → show the empty state
        }

        const b      = await loadSpecBundle(specIdToLoad);
        const allIds = buildAllNodeIds(b.classJson);
        const full   = code
            ? decodeTalentCode(code, allIds)
            : { specId: specIdToLoad, version: 2, nodes: {} };

        // Seed auto-granted defaults — `alreadyMaxedOut` talents are free of cost
        // (don't count toward the 34/13/34 budget) but must be present in the
        // bitstream as isSelected=1, isPurchased=0. We always force them to be
        // granted, even if a saved code omitted them, so an older code can never
        // remove a class default.
        for (const node of [
            ...Object.values(b.spec.classNodes || {}),
            ...Object.values(b.spec.specNodes  || {}),
        ]) {
            if (node.alreadyMaxedOut) {
                full.nodes[node.id] = { rank: 0, choice: null, granted: true };
            }
        }
        // Hero root: the first node of the active hero subtree is auto-granted
        // once the player picks that hero (via the meta-choice node). Without
        // an explicit hero choice we leave the tree unselected so the player
        // can still pick left or right.
        const heroChoice = full.nodes[b.spec.hero?.metaNodeId]?.choice;
        if (heroChoice != null) {
            const heroSide = heroChoice === 1 ? b.spec.hero?.right : b.spec.hero?.left;
            if (heroSide?.rootNodeId) {
                full.nodes[heroSide.rootNodeId] = { rank: 0, choice: null, granted: true };
            }
        }

        bundle.value     = b;
        decoded.value    = full;
        allNodeIds.value = allIds;
        editedNodes.value = { ...full.nodes };
        draftCode.value   = code ?? '';
    } catch (err) {
        error.value = err.message ?? 'Failed to load talent build';
    } finally {
        loading.value = false;
    }
}

onMounted(() => loadFromCode(props.talentCode));
watch(() => props.talentCode, (val) => {
    draftCode.value = val ?? '';
    loadFromCode(val);
});
watch(() => props.specId, () => {
    // Spec switched (e.g. user picked a different gear list) — reload tree if blank code.
    if (!props.talentCode) loadFromCode('');
});

// ── Editable: click cycles ranks, re-encodes draftCode on every change ─────
function onNodeChanged({ nodeId, entry }) {
    const next = { ...editedNodes.value };
    if (entry == null) delete next[nodeId];
    else               next[nodeId] = entry;
    editedNodes.value = next;

    if (bundle.value) {
        draftCode.value = encodeTalentCode(bundle.value.specData.id, allNodeIds.value, next);
    }
}

// ── Save (editable) ─────────────────────────────────────────────────────────
async function saveBuild() {
    if (!props.updateUrl) return;
    const code = draftCode.value.trim();
    saving.value = true;
    error.value = null;
    try {
        const headers = { 'Content-Type': 'application/json', Accept: 'application/json' };
        if (props.csrfToken) headers['X-CSRF-TOKEN'] = props.csrfToken;
        const res = await fetch(props.updateUrl, {
            method: 'PATCH', headers,
            body: JSON.stringify({ talent_loadout_code: code }),
        });
        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            throw new Error(data.message ?? `Server error ${res.status}`);
        }
        await loadFromCode(code);
        emit('saved', code);
    } catch (err) {
        error.value = err.message ?? 'Failed to save build';
    } finally {
        saving.value = false;
    }
}

async function copyDraftCode() {
    try { await navigator.clipboard.writeText(draftCode.value); } catch {}
}

// ── Spec/class header info ──────────────────────────────────────────────────
const classDisplay = computed(() => bundle.value?.classData?.displayName);
const specDisplay  = computed(() => bundle.value?.specData?.displayName);
const heroTitle    = computed(() => activeHero.value?.name ?? 'Hero Talents');
</script>

<template>
    <GlassModal :show="show" @close="emit('close')" max-width="max-w-[85rem]">
        <header class="flex items-center justify-between px-6 py-4 border-b border-white/10 bg-black/40">
            <h3 class="font-headline text-sm font-black text-white uppercase tracking-widest flex items-center gap-2">
                <span class="material-symbols-outlined text-purple-300 text-base">account_tree</span>
                {{ title }}
                <span v-if="subtitle" class="text-on-surface-variant font-normal normal-case tracking-normal text-xs ml-1">
                    {{ subtitle }}
                </span>
                <span v-if="readonly" class="text-[10px] font-normal normal-case tracking-normal text-on-surface-variant border border-white/10 rounded px-1.5 py-0.5 ml-2">
                    read-only
                </span>
            </h3>
            <button type="button" @click="emit('close')" class="text-on-surface-variant hover:text-white transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </header>

        <div class="p-6 overflow-auto max-h-[85vh] custom-scroll">
            <div v-if="loading" class="py-12 text-center text-on-surface-variant text-sm">
                <span class="animate-pulse">Loading talent build…</span>
            </div>

        <div v-else-if="error" class="py-6 text-center text-error text-sm">
            {{ error }}
        </div>

        <div v-else-if="!bundle"
             class="py-12 text-center text-on-surface-variant text-sm">
            <span class="material-symbols-outlined text-4xl opacity-30 block mb-2">account_tree</span>
            No talent build set.<br>
            <span class="text-xs opacity-60">Sync the character via Battle.net to populate.</span>
        </div>

        <!-- 3-column tree layout: Class | Hero | Spec -->
        <div v-else class="talent-trees-row">
            <TalentTreeColumn
                :title="classDisplay"
                :nodes="bundle.spec.classNodes"
                :selected-map="effectiveNodes"
                :checkpoints="bundle.spec.classCheckpoints"
                :total-points="classTotal"
                :editable="!readonly"
                @node-changed="onNodeChanged"
            />
            <TalentTreeColumn
                v-if="activeHero"
                title="Hero"
                :subtitle="heroTitle"
                :nodes="activeHero.nodes"
                :selected-map="effectiveNodes"
                :meta-node="heroMetaNode"
                :total-points="heroTotal"
                :editable="!readonly"
                @node-changed="onNodeChanged"
            />
            <TalentTreeColumn
                :title="specDisplay"
                :nodes="bundle.spec.specNodes"
                :selected-map="effectiveNodes"
                :apex-node="bundle.spec.apexNode"
                :checkpoints="bundle.spec.specCheckpoints"
                checkpoints-side="right"
                :total-points="specTotal"
                :editable="!readonly"
                @node-changed="onNodeChanged"
            />
        </div>

            <!-- Actions Footer -->
            <div v-if="!loading && bundle" class="mt-6 pt-4 border-t border-white/10 flex flex-col sm:flex-row items-center justify-between gap-4">

                <!-- Readonly Mode -->
                <div v-if="readonly" class="w-full flex items-center gap-2">
                    <input
                        type="text"
                        :value="draftCode"
                        readonly
                        class="flex-1 px-3 py-2 text-xs font-mono rounded-md bg-black/40 border border-white/10 text-on-surface-variant focus:outline-none"
                        placeholder="No talent code"
                    />
                    <button type="button"
                        @click="copyDraftCode"
                        :disabled="!draftCode"
                        class="px-4 py-2 rounded-md text-xs font-bold uppercase tracking-widest
                               bg-white/5 border border-white/10 text-white
                               hover:bg-white/10 transition-colors flex items-center gap-2
                               disabled:opacity-30 disabled:cursor-not-allowed">
                        <span class="material-symbols-outlined text-[16px]">content_copy</span>
                        Copy
                    </button>
                </div>

                <!-- Editable Mode -->
                <div v-else class="w-full flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-normal text-on-surface-variant/70">
                            Left-click talents to add a rank · Right-click to remove
                        </span>
                        <span v-if="dirty" class="text-[10px] text-amber-400 font-bold uppercase tracking-widest bg-amber-400/10 px-2 py-0.5 rounded border border-amber-400/20">
                            Unsaved Changes
                        </span>
                    </div>
                    <div class="flex flex-col sm:flex-row items-center gap-2">
                        <input
                            type="text"
                            v-model="draftCode"
                            class="flex-1 w-full px-3 py-2 text-xs font-mono rounded-md bg-black/40 border border-white/10 text-white focus:outline-none focus:border-cyan-500/50 transition-colors"
                            placeholder="Paste a Blizzard talent code here..."
                        />
                        <div class="flex items-center gap-2 w-full sm:w-auto shrink-0">
                            <button type="button"
                                @click="copyDraftCode"
                                :disabled="!draftCode.trim()"
                                class="flex-1 sm:flex-none px-3 py-2 rounded-md text-xs font-bold uppercase tracking-widest
                                       bg-white/5 border border-white/10 text-on-surface-variant
                                       hover:bg-white/10 hover:text-white transition-colors flex items-center justify-center gap-1.5
                                       disabled:opacity-30 disabled:cursor-not-allowed"
                                title="Copy current build string">
                                <span class="material-symbols-outlined text-[16px]">content_copy</span>
                                Export
                            </button>
                            <button type="button"
                                @click="loadFromCode(props.talentCode)"
                                :disabled="!dirty"
                                class="flex-1 sm:flex-none px-3 py-2 rounded-md text-xs font-bold uppercase tracking-widest
                                       bg-white/5 border border-white/10 text-on-surface-variant
                                       hover:bg-white/10 hover:text-white transition-colors flex items-center justify-center gap-1.5
                                       disabled:opacity-30 disabled:cursor-not-allowed">
                                <span class="material-symbols-outlined text-[16px]">undo</span>
                                Reset
                            </button>
                            <button type="button"
                                :disabled="saving || !draftCode.trim() || !dirty"
                                @click="saveBuild"
                                class="flex-1 sm:flex-none px-4 py-2 rounded-md text-xs font-bold uppercase tracking-widest
                                       bg-cyan-500/20 border border-cyan-400/40 text-cyan-100
                                       hover:bg-cyan-500/30 transition-colors flex items-center justify-center gap-1.5
                                       disabled:opacity-30 disabled:cursor-not-allowed">
                                <span v-if="saving" class="material-symbols-outlined text-[16px] animate-spin">sync</span>
                                <span v-else class="material-symbols-outlined text-[16px]">save</span>
                                {{ saving ? 'Saving…' : 'Save' }}
                            </button>
                        </div>
                    </div>
                    <span v-if="error" class="text-error text-xs">{{ error }}</span>
                </div>
            </div>
        </div>
    </GlassModal>
</template>

<style scoped>
.talent-trees-row {
    display: flex;
    gap: 16px;
    justify-content: center;
    align-items: flex-start;
    overflow-x: auto;
    padding: 16px 8px;
}
</style>
