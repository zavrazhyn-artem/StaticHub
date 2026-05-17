/**
 * Decoder for Blizzard's WoW talent_loadout_code (Midnight format).
 *
 * Format (little-endian bitstream over base64):
 *   8 bits  : serialization version (currently 1)
 *  16 bits  : spec ID
 * 128 bits  : reserved / unused
 *  per node (in ascending nodeId order across the full class file):
 *     1 bit : isSelected
 *     if 1:
 *       1 bit : isPurchased (vs auto-granted)
 *       if 1:
 *         1 bit : isPartiallyRanked
 *         if 1: 6 bits — points invested
 *         else: -1 (means max points for this node)
 *         1 bit : isChoiceNode
 *         if 1: 2 bits — entry chosen (0 = left, 1 = right)
 *
 * The decoder needs the canonical nodeId ordering for that class. We get
 * this from the class JSON via `buildAllNodeIds(classJson)`.
 */

const BASE64 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
const BASE64_INDEX = (() => {
    const map = {};
    for (let i = 0; i < BASE64.length; i++) map[BASE64[i]] = i;
    return map;
})();

class BitReader {
    constructor(str) {
        this.bits = [];
        for (const ch of str) {
            const v = BASE64_INDEX[ch];
            if (v === undefined) continue;
            for (let j = 0; j < 6; j++) this.bits.push((v >> j) & 1);
        }
        this.pos = 0;
    }
    read(n) {
        if (this.pos + n > this.bits.length) return -1;
        let r = 0;
        for (let i = 0; i < n; i++) r += this.bits[this.pos + i] << i;
        this.pos += n;
        return r;
    }
    remaining() { return Math.max(0, this.bits.length - this.pos); }
}

/**
 * Build the canonical nodeId order Blizzard uses for the bitstream.
 * Mirrors Icy Veins's `tree.allNodeIds` initialisation in midnight-talent-calculator.js.
 *
 * @param {object} classJson — the full class JSON (e.g. druid.json).
 * @returns {number[]} sorted ascending node IDs across all specs of the class.
 */
export function buildAllNodeIds(classJson) {
    const set = new Set();
    (classJson.unusedNodeIds || []).forEach((id) => set.add(Number(id)));

    for (const specKey of Object.keys(classJson.specs || {})) {
        const spec = classJson.specs[specKey];
        Object.keys(spec.classNodes || {}).forEach((id) => set.add(Number(id)));
        Object.keys(spec.specNodes || {}).forEach((id) => set.add(Number(id)));
        Object.keys(spec.hero?.left?.nodes || {}).forEach((id) => set.add(Number(id)));
        Object.keys(spec.hero?.right?.nodes || {}).forEach((id) => set.add(Number(id)));
        if (spec.hero?.metaNodeId) set.add(Number(spec.hero.metaNodeId));
        if (spec.apexNode?.id) set.add(Number(spec.apexNode.id));
    }
    return Array.from(set).sort((a, b) => a - b);
}

/**
 * Decode a Blizzard talent loadout code into selected nodes.
 *
 * @param {string} code — base64 talent_loadout_code from Blizzard API.
 * @param {number[]} allNodeIds — output of buildAllNodeIds() for the matching class.
 * @returns {{ specId: number, version: number, nodes: Object<number, {rank: number, choice: ?number}> }}
 *   `rank`  = points invested (1..maxRanks). `-1` means "max" (fully purchased without partial flag).
 *   `choice` = 0 or 1 for choice nodes (null otherwise).
 */
export function decodeTalentCode(code, allNodeIds) {
    if (!code) return { specId: 0, version: 0, nodes: {} };

    const reader = new BitReader(code);
    const version = reader.read(8);
    const specId  = reader.read(16);
    reader.read(64);   // skip 128 reserved bits in two reads (max 53-bit JS int safety)
    reader.read(64);

    const nodes = {};

    for (const nodeId of allNodeIds) {
        const isSelected = reader.read(1);
        if (isSelected !== 1) continue;

        const isPurchased = reader.read(1);
        if (isPurchased !== 1) {
            // Auto-granted (free) node — mark as present but no rank
            nodes[nodeId] = { rank: 0, choice: null, granted: true };
            continue;
        }

        const isPartial = reader.read(1);
        let rank;
        if (isPartial === 1) {
            rank = reader.read(6);
        } else {
            rank = -1; // sentinel: use maxRanks from spec definition
        }

        const isChoice = reader.read(1);
        let choice = null;
        if (isChoice === 1) {
            choice = reader.read(2);
        }

        nodes[nodeId] = { rank, choice, granted: false };
    }

    return { specId, version, nodes };
}

/**
 * Convenience: resolve a node's effective rank using maxRanks from the spec definition.
 */
export function resolveRank(decoded, nodeId, maxRanks) {
    const entry = decoded.nodes[nodeId];
    if (!entry) return 0;
    if (entry.rank === -1) return maxRanks;
    return entry.rank;
}

// ── Encoder ──────────────────────────────────────────────────────────────────

class BitWriter {
    constructor() { this.bits = []; }
    write(value, width) {
        for (let i = 0; i < width; i++) this.bits.push((value >> i) & 1);
    }
    toBase64() {
        while (this.bits.length % 6 !== 0) this.bits.push(0);
        let out = '';
        for (let i = 0; i < this.bits.length; i += 6) {
            let v = 0;
            for (let j = 0; j < 6; j++) v += this.bits[i + j] << j;
            out += BASE64[v];
        }
        return out;
    }
}

/**
 * Re-encode a node selection map back into a Blizzard talent_loadout_code.
 *
 * @param {number} specId
 * @param {number[]} allNodeIds — canonical order from buildAllNodeIds(classJson)
 * @param {Object<number, {rank, choice, granted}>} nodes
 * @returns {string} base64 talent code
 */
export function encodeTalentCode(specId, allNodeIds, nodes) {
    const w = new BitWriter();
    w.write(2, 8);          // serialization version (matches Blizzard's format v2)
    w.write(specId, 16);
    w.write(0, 64); w.write(0, 64);  // 128 reserved bits

    for (const nodeId of allNodeIds) {
        const e = nodes[nodeId];
        const isSelected = !!e && (e.granted || e.rank === -1 || (e.rank ?? 0) > 0 || e.choice != null);

        if (!isSelected) { w.write(0, 1); continue; }
        w.write(1, 1);                                          // isSelected
        if (e.granted) { w.write(0, 1); continue; }             // not purchased = auto-granted, done
        w.write(1, 1);                                          // isPurchased

        if (e.rank === -1) {
            w.write(0, 1);                                      // not partial → max
        } else {
            w.write(1, 1);                                      // partial
            w.write(Math.max(0, e.rank ?? 0), 6);
        }

        if (e.choice != null) {
            w.write(1, 1);                                      // isChoice
            w.write(e.choice, 2);
        } else {
            w.write(0, 1);                                      // not choice
        }
    }
    return w.toBase64();
}
