/**
 * Build a MethodRaidTools / NorthernSkyRaidTools note string from a
 * Cooldown-Manager plan.
 *
 * Output shape:
 *   EncounterID:<id>;Difficulty:<name>;Name:<boss>
 *   <decorative cooldown header>
 *   time:<secs>;ph:<n>;tag:<char>;spellid:<id>;text:<note>   -- one per assignment
 *
 * NSRT parses the `time:/ph:/tag:/spellid:` lines and fires in-fight reminders;
 * MRT's own note window also renders any decorative `{time:M:SS} {spell:id}`
 * tokens included above for raid leaders to eyeball.
 */

const DIFFICULTY_LABELS = {
    mythic: 'Mythic',
    heroic: 'Heroic',
    normal: 'Normal',
    raid_finder: 'LFR',
};

/**
 * Find the phase segment containing an absolute time.
 * Returns { segmentIndex, phaseNumber, offset } where phaseNumber is 1-based
 * (matches NSRT's `ph:` parameter) and offset is seconds from phase start.
 */
function resolvePhase(absTime, segments) {
    if (!segments?.length) return { segmentIndex: 0, phaseNumber: 1, offset: Math.max(0, Math.round(absTime)) };
    for (let i = 0; i < segments.length; i++) {
        const s = segments[i];
        const end = s.start + s.duration;
        if (absTime >= s.start && absTime < end) {
            return {
                segmentIndex: i,
                phaseNumber: i + 1,
                offset: Math.max(0, Math.round(absTime - s.start)),
            };
        }
    }
    const last = segments[segments.length - 1];
    return {
        segmentIndex: segments.length - 1,
        phaseNumber: segments.length,
        offset: Math.max(0, Math.round(absTime - last.start)),
    };
}

function escapeNoteText(str) {
    if (!str) return '';
    // Strip characters that would break token parsing (`;` separates fields
    // inside a line; `\n` starts a new line).
    return String(str).replace(/[;\n\r]/g, ' ').trim();
}

function formatMinSec(sec) {
    const m = Math.floor(sec / 60);
    const s = Math.floor(sec) % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
}

/**
 * Generate the full note string.
 *
 * @param {Object} opts
 * @param {{ encounter_id, name, slug }} opts.encounter
 * @param {string} opts.difficulty
 * @param {Array<{ id,start,duration,name,is_intermission }>} opts.segments
 * @param {Array<{ time, character_id, spell_id, spell_name, character_name, class, note }>} opts.assignments
 * @param {Array} opts.bossAbilities (optional — for boss spell lookup near each assignment)
 * @returns {string}
 */
export function buildMrtNote(opts) {
    const { encounter, difficulty, segments = [], assignments = [], bossAbilities = [] } = opts;
    if (!encounter) return '';

    const lines = [];

    // ─── Header ───────────────────────────────────────────────────────────
    const headerParts = [];
    if (encounter.encounter_id) headerParts.push(`EncounterID:${encounter.encounter_id}`);
    headerParts.push(`Difficulty:${DIFFICULTY_LABELS[difficulty] || difficulty || 'Mythic'}`);
    headerParts.push(`Name:${encounter.name || encounter.slug || 'Unknown'}`);
    lines.push(headerParts.join(';'));

    if (!assignments.length) {
        lines.push('', '|cffc41f3bNo cooldown assignments saved yet.|r');
        return lines.join('\n');
    }

    // ─── Decorative overview (visible in the MRT note window) ─────────────
    // Section headers use MRT's |cffRRGGBB…|r colour codes so they render
    // as styled text in-game instead of raw "# ---" comments.
    lines.push('');
    lines.push('|cffc41f3b=== Cooldowns ===|r');
    const sorted = [...assignments].sort((a, b) => a.time - b.time);
    for (const a of sorted) {
        const t = formatMinSec(a.time);
        const spell = a.spell_id ? `{spell:${a.spell_id}}` : '';
        const char = a.character_name || '';
        const note = a.note ? ` — ${escapeNoteText(a.note)}` : '';
        lines.push(`{time:${t}}${spell}${char}${note}`);
    }

    // Pre-compute absolute times of every boss cast so each assignment can
    // attach a `bossSpell:` to the closest cast (within 5s). Lets NSRT show
    // the incoming boss icon alongside the player cooldown prompt.
    const bossCastsAbs = [];
    for (const ab of bossAbilities) {
        if (!ab?.default_casts?.length || !ab.spell_id) continue;
        for (const c of ab.default_casts) {
            const abs = typeof c === 'number'
                ? c
                : (segments.find(s => s.id === c.segment_id)?.start ?? 0) + (c.offset || 0);
            bossCastsAbs.push({ time: abs, spell_id: ab.spell_id });
        }
    }

    // ─── NSRT machine-readable assignment lines ──────────────────────────
    lines.push('');
    lines.push('|cff69ccf0=== NSRT triggers ===|r');
    for (const a of sorted) {
        const { phaseNumber, offset } = resolvePhase(a.time, segments);
        const tokens = [`time:${offset}`, `ph:${phaseNumber}`];
        if (a.character_name) tokens.push(`tag:${a.character_name}`);
        if (a.spell_id) tokens.push(`spellid:${a.spell_id}`);
        // Boss-cast context: nearest boss cast within 5s (either direction).
        const nearBoss = bossCastsAbs
            .filter(b => Math.abs(b.time - a.time) <= 5)
            .sort((x, y) => Math.abs(x.time - a.time) - Math.abs(y.time - a.time))[0];
        if (nearBoss) tokens.push(`bossSpell:${nearBoss.spell_id}`);
        if (a.note) tokens.push(`text:${escapeNoteText(a.note)}`);
        lines.push(tokens.join(';'));
    }

    return lines.join('\n');
}
