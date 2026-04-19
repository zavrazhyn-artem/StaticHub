import { computed } from 'vue';

/**
 * Build a regex + replacement function that wraps known ability names with
 * WoWHead-linkable anchors. Wowhead's powered-by script auto-attaches tooltips
 * when it sees `https://www.wowhead.com/spell=NNN` URLs.
 *
 * @param {Object<string, number>} abilityMap  lowercase name → spell ID
 */
export function useAbilityLinker(abilityMap) {
    const sortedKeys = computed(() => {
        if (!abilityMap || typeof abilityMap !== 'object') return [];
        // Sort by length DESC so longer matches win ("Shield Block" before "Block")
        return Object.keys(abilityMap).sort((a, b) => b.length - a.length);
    });

    const pattern = computed(() => {
        const keys = sortedKeys.value;
        if (!keys.length) return null;
        // Escape regex special chars + word boundary
        const escaped = keys.map(k => k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
        return new RegExp(`\\b(${escaped.join('|')})\\b`, 'gi');
    });

    /**
     * Linkify a plain text string, returning HTML string with anchor tags.
     * If pattern is empty or no matches, returns escaped HTML of the original text.
     */
    function linkify(text) {
        if (typeof text !== 'string' || !text) return '';
        const escaped = escapeHtml(text);
        const pat = pattern.value;
        if (!pat) return escaped;

        return escaped.replace(pat, (match) => {
            const id = abilityMap[match.toLowerCase()];
            if (!id) return match;
            // Class "ability-link" gets richer styling in app.css. wowhead's powered-by
            // script will color-tint by quality (epic/rare/etc.) — we keep that and just
            // layer a dotted underline + hover effect on top so users notice the link.
            return `<a href="https://www.wowhead.com/spell=${id}" target="_blank" rel="noopener noreferrer" data-wh-rename-link="false" class="ability-link">${match}</a>`;
        });
    }

    return { linkify };
}

function escapeHtml(s) {
    return s
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
