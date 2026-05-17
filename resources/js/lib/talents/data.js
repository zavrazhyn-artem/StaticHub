/**
 * Loads talent tree data from our static JSON snapshot.
 *
 * Data files live under `/vendor/talent-calculator/json/`:
 *   - classes_basic_info.json — maps classId/specId → file slug
 *   - <class_slug>.json       — one per class, contains 4 specs each (classNodes,
 *                               specNodes, hero { left, right }, apexNode)
 *
 * Cached in memory after first fetch — talent definitions don't change mid-session.
 */

const JSON_PATH = '/vendor/talent-calculator/json';

let classesIndexCache = null;
const classDataCache = {};

export async function loadClassesIndex() {
    if (classesIndexCache) return classesIndexCache;
    const res = await fetch(`${JSON_PATH}/classes_basic_info.json`);
    if (!res.ok) throw new Error('Failed to load classes_basic_info.json');
    classesIndexCache = await res.json();
    return classesIndexCache;
}

/**
 * Look up class metadata by class id or spec id.
 * @returns {{ classData: object, specData: object } | null}
 */
export async function findClassAndSpecBySpecId(specId) {
    const classes = await loadClassesIndex();
    for (const cls of classes) {
        const spec = cls.specializations.find((s) => Number(s.id) === Number(specId));
        if (spec) return { classData: cls, specData: spec };
    }
    return null;
}

/**
 * Load the full per-class JSON (druid.json, etc.) given a class slug.
 */
export async function loadClassData(classSlug) {
    if (classDataCache[classSlug]) return classDataCache[classSlug];
    const res = await fetch(`${JSON_PATH}/${classSlug}.json`);
    if (!res.ok) throw new Error(`Failed to load ${classSlug}.json`);
    const data = await res.json();
    classDataCache[classSlug] = data;
    return data;
}

/**
 * One-shot: from a specId, return everything a renderer needs.
 * @returns {Promise<{ classData, specData, classJson, spec }>}
 */
export async function loadSpecBundle(specId) {
    const match = await findClassAndSpecBySpecId(specId);
    if (!match) throw new Error(`Unknown specId: ${specId}`);

    const classJson = await loadClassData(match.classData.name);
    const spec = classJson.specs[match.specData.name];
    if (!spec) throw new Error(`Spec ${match.specData.name} not found in ${match.classData.name}.json`);

    return { classData: match.classData, specData: match.specData, classJson, spec };
}

/**
 * Wowhead's CDN serves WoW icons keyed by icon slug. No hotlinking issues.
 * Names must be lowercase — `Elunes_Chosen` 404s, `elunes_chosen` works.
 */
export function iconUrl(iconName, size = 'large') {
    if (!iconName) return FALLBACK_ICON_URL;
    return `https://wow.zamimg.com/images/wow/icons/${size}/${iconName.toLowerCase()}.jpg`;
}

/** Generic placeholder used when an icon fails to load (matches Wowhead's own fallback). */
export const FALLBACK_ICON_URL = 'https://wow.zamimg.com/images/wow/icons/large/inv_misc_questionmark.jpg';

/** Hero tree names → Wowhead-compatible icon slugs. Hero tree icons aren't
 *  consistent with the in-game UI names, so this is curated. Missing entries
 *  fall through to the questionmark placeholder via the @error handler. */
const HERO_TREE_ICON_OVERRIDES = {
    // Druid
    'Druid_of_the_Claw':   'inv_ability_druidoftheclawdruid_thrash',
    'Wildstalker':         'inv_ability_wildstalkerdruid_thrivinggrowth',
    "Elune_s_Chosen":      'inv_ability_eluneschosendruid_lunarinsight',
    'Keeper_of_the_Grove': 'inv_ability_keeperofthegrovedruid_dreamsurge',
    // Shaman
    'Stormbringer':        'inv_ability_stormbringershaman_stormstrike',
    'Farseer':             'inv_ability_farseershaman_ancestralguidance',
    'Totemic':             'inv_ability_totemicshaman_supportivetotems',
    // Add more as needed; missing slugs fall back to questionmark via <img @error>.
};

export function heroIconUrl(heroIconName) {
    if (!heroIconName) return FALLBACK_ICON_URL;
    const mapped = HERO_TREE_ICON_OVERRIDES[heroIconName];
    if (mapped) return iconUrl(mapped);
    return iconUrl(heroIconName); // best-effort, browser falls back via @error
}

/**
 * Find which sub-spell (and within-spell rank) corresponds to a given total
 * invested rank. For single-spell talents this is trivial; for apex talents
 * with 3 stacked spells (e.g. maxRanks 1+2+1=4 total), we walk cumulatively.
 *
 * @param {Array} spells          — node.spells array
 * @param {number} totalInvested  — total points invested in the node
 * @returns {{spell: object, subRank: number}} the current sub-spell and rank within it (1-based)
 */
export function resolveCurrentSpell(spells, totalInvested) {
    if (!spells?.length) return null;
    if (totalInvested <= 0) return { spell: spells[0], subRank: 0 };
    let cumulative = 0;
    for (const spell of spells) {
        const max = spell.maxRanks ?? 1;
        if (totalInvested <= cumulative + max) {
            return { spell, subRank: totalInvested - cumulative };
        }
        cumulative += max;
    }
    return { spell: spells[spells.length - 1], subRank: spells[spells.length - 1].maxRanks ?? 1 };
}

/**
 * Build a Wowhead talent URL that produces a rank-aware tooltip.
 *
 * Format (matches what Wowhead's own talent-calc emits):
 *   - single-rank focus spell:  https://www.wowhead.com/spell=<id>/<slug>
 *   - multi-rank focus spell:   https://www.wowhead.com/spell=<id>/<slug>?def=<defId>&rank=<subRank>
 *
 * Critical: `rank=` is the WITHIN-SPELL rank, not total invested. And for
 * single-rank focus spells we omit the params entirely (Wowhead doesn't
 * disambiguate them). This matches the exact URLs emitted by Wowhead's own
 * talent calc page across rank 0..4 of a multi-spell apex talent.
 */
export function wowheadTalentUrl(talentName, spells, totalInvested) {
    const current = resolveCurrentSpell(spells, totalInvested);
    if (!current?.spell?.spellId) return null;

    const slug = (talentName || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    const params = new URLSearchParams();

    const maxRanks = current.spell.maxRanks ?? 1;
    if (maxRanks > 1 && current.subRank > 0) {
        if (current.spell.definitionId) params.set('def', current.spell.definitionId);
        params.set('rank', current.subRank);
    }

    const qs = params.toString();
    return `https://www.wowhead.com/spell=${current.spell.spellId}/${slug}${qs ? '?' + qs : ''}`;
}
