// Tailwind arbitrary-value classes — listed as literals so the JIT scanner picks them up:
// bg-[#C79C6E] bg-[#F58CBA] bg-[#ABD473] bg-[#FFF569] bg-[#FFFFFF] bg-[#C41F3B]
// bg-[#0070DE] bg-[#40C7EB] bg-[#8787ED] bg-[#00FF96] bg-[#FF7D0A] bg-[#A330C9] bg-[#33937F]

const CLASS_BG_COLORS = {
    'Warrior': 'bg-[#C79C6E]',
    'Paladin': 'bg-[#F58CBA]',
    'Hunter': 'bg-[#ABD473]',
    'Rogue': 'bg-[#FFF569]',
    'Priest': 'bg-[#FFFFFF]',
    'Death Knight': 'bg-[#C41F3B]',
    'Shaman': 'bg-[#0070DE]',
    'Mage': 'bg-[#40C7EB]',
    'Warlock': 'bg-[#8787ED]',
    'Monk': 'bg-[#00FF96]',
    'Druid': 'bg-[#FF7D0A]',
    'Demon Hunter': 'bg-[#A330C9]',
    'Evoker': 'bg-[#33937F]',
};

export function useWowClasses() {
    const getClassColor = (playableClass) => CLASS_BG_COLORS[playableClass] || 'bg-white/20';

    const getSpecName = (character) => {
        let displaySpec = character.active_spec;
        if (displaySpec && typeof displaySpec === 'string' && displaySpec.startsWith('{')) {
            try {
                const decoded = JSON.parse(displaySpec);
                displaySpec = decoded.name || displaySpec;
            } catch (e) {}
        }
        return displaySpec || character.playable_class;
    };

    return { getClassColor, getSpecName };
}
