import { reactive } from 'vue';

/**
 * Resolves item icons from Wowhead's nether tooltip API.
 * Caches results so each item ID is fetched only once.
 *
 * Usage:
 *   const { getIconUrl } = useWowheadIcons();
 *   // returns reactive URL string or null while loading
 *   const url = getIconUrl(itemId);
 */

const cache = reactive({});
const pending = new Set();

function fetchIcon(itemId) {
    if (pending.has(itemId)) return;
    pending.add(itemId);

    fetch(`https://nether.wowhead.com/tooltip/item/${itemId}?dataEnv=1&locale=0`)
        .then(r => r.json())
        .then(data => {
            if (data.icon) {
                cache[itemId] = `https://wow.zamimg.com/images/wow/icons/medium/${data.icon}.jpg`;
            }
        })
        .catch(() => {})
        .finally(() => pending.delete(itemId));
}

export function useWowheadIcons() {
    const getIconUrl = (itemId) => {
        if (!itemId) return null;
        if (cache[itemId]) return cache[itemId];
        fetchIcon(itemId);
        return null;
    };

    return { getIconUrl };
}
