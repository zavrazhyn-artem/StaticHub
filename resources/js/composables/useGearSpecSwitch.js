import { reactive } from 'vue';

/**
 * Composable for per-character gear spec switching in the roster gear tab.
 *
 * Tracks which spec each character is currently viewing and provides
 * helpers to overlay the selected spec's gear data.
 */
export function useGearSpecSwitch(refreshTooltips) {
    /** Tracks which spec is currently viewed per character id. */
    const selectedSpec = reactive({});

    /**
     * Returns the effective character data for the currently selected spec.
     * If no spec is selected or it matches the default, returns as-is.
     */
    const getSpecData = (char) => {
        if (!char) return char;
        const chosen = selectedSpec[char.id];

        if (!chosen || chosen === (char.main_spec?.name ?? null)) {
            return char;
        }

        const equipBySpec = char.equipment_by_spec ?? {};
        const auditBySpec = char.gear_audit_by_spec ?? {};
        const ilvlBySpec  = char.ilvl_by_spec ?? {};

        if (!equipBySpec[chosen]) return char;

        const audit = auditBySpec[chosen] ?? {};
        return {
            ...char,
            equipment:                equipBySpec[chosen],
            equipped_ilvl:            ilvlBySpec[chosen] ?? char.equipped_ilvl,
            missing_enchants_slots:   audit.missing_enchants_slots ?? char.missing_enchants_slots,
            low_quality_enchants_slots: audit.low_quality_enchants_slots ?? char.low_quality_enchants_slots,
            empty_sockets_count:      audit.empty_sockets_count ?? char.empty_sockets_count,
            upgrades_missing:         audit.upgrades_missing ?? char.upgrades_missing,
            sparks_equipped:          audit.sparks_equipped ?? char.sparks_equipped,
            tier_pieces:              audit.tier_pieces ?? char.tier_pieces,
            tier_ilvls:               audit.tier_ilvls ?? char.tier_ilvls,
            embellished_items:        audit.embellished_items ?? char.embellished_items,
            spark_gear:               audit.spark_gear ?? char.spark_gear,
        };
    };

    const selectSpec = (charId, specName) => {
        selectedSpec[charId] = specName;
        if (refreshTooltips) {
            setTimeout(refreshTooltips, 50);
        }
    };

    /**
     * Builds the list of spec objects for the switcher from available_gear_specs.
     * Uses all_specs for icon URLs when available.
     */
    const getSpecOptions = (char) => {
        const available = char?.available_gear_specs ?? [];
        if (available.length <= 1) return [];

        const allSpecs = char.all_specs ?? [];
        return available.map(name => {
            const match = allSpecs.find(s => s.name === name);
            return {
                name,
                icon_url: match?.icon_url ?? null,
            };
        });
    };

    const getActiveSpec = (char) => {
        if (!char) return null;
        return selectedSpec[char.id] ?? char.main_spec?.name ?? null;
    };

    return { getSpecData, selectSpec, getSpecOptions, getActiveSpec };
}
