// Gear/Audit shared: slots, generated item data, helpers, ItemCell renderer.

window.GEAR_SLOTS = [
  { id:'head',     l:'Голова',       short:'голова',  tier:true  },
  { id:'neck',     l:'Шия',          short:'шия' },
  { id:'shoulders',l:'Плече',        short:'плече',   tier:true  },
  { id:'cloak',    l:'Плащ',         short:'плащ' },
  { id:'chest',    l:'Нагрудник',    short:'нагрудник', tier:true },
  { id:'wrists',   l:'Запʼястя',     short:'запʼястя', tier:true },
  { id:'hands',    l:'Руки',         short:'руки',    tier:true  },
  { id:'waist',    l:'Пояс',         short:'пояс' },
  { id:'legs',     l:'Ноги',         short:'ноги',    tier:true  },
  { id:'feet',     l:'Ступні',       short:'ступні' },
  { id:'ring1',    l:'Кільце 1',     short:'кільце'   },
  { id:'ring2',    l:'Кільце 2',     short:'кільце'   },
  { id:'trink1',   l:'Аксесуар 1',   short:'аксесуар' },
  { id:'trink2',   l:'Аксесуар 2',   short:'аксесуар' },
  { id:'mh',       l:'Основна рука', short:'осн.рука' },
  { id:'oh',       l:'Друга рука',   short:'друга рука' },
];

// Track config — equipment upgrade tracks (NOT raid difficulty)
window.TRACKS = {
  M:     { l:'Myth',     short:'M',     color:'#FB923C', baseIlvl:285, levels:6 },
  H:     { l:'Hero',     short:'H',     color:'#C084FC', baseIlvl:272, levels:6 },
  C:     { l:'Champion', short:'C',     color:'#60A5FA', baseIlvl:259, levels:8 },
  V:     { l:'Veteran',  short:'V',     color:'#4ADE80', baseIlvl:246, levels:8 },
  KRAFT: { l:'Crafted',  short:'КРАФТ', color:'#94A3B8', baseIlvl:272, levels:1 },
};

// Hash a string deterministically
function hashStr(s) {
  let h = 0;
  for (let i=0; i<s.length; i++) h = ((h<<5) - h) + s.charCodeAt(i), h |= 0;
  return Math.abs(h);
}

// Generate gear for a character — deterministic pseudo-random
window.GEAR_DATA = (function buildGear() {
  const out = {};
  const chars = window.ROSTER_CHARS;
  // Track distribution by character "tier" (main = better, bench/alt = worse)
  chars.forEach(c => {
    const seed = hashStr(c.id);
    const isMain = c.status === 'main';
    const isBench = c.status === 'bench';
    const isAlt = !!c.parentOf;
    const slots = {};
    window.GEAR_SLOTS.forEach((slot, si) => {
      const r = (seed + si*97) % 1000 / 1000;
      let track;
      if (isMain) {
        if (r < 0.55) track = 'M';
        else if (r < 0.85) track = 'H';
        else if (r < 0.95) track = 'C';
        else track = 'KRAFT';
      } else if (isBench || isAlt) {
        if (r < 0.05) track = 'M';
        else if (r < 0.45) track = 'H';
        else if (r < 0.85) track = 'C';
        else if (r < 0.95) track = 'V';
        else track = 'KRAFT';
      } else {
        if (r < 0.30) track = 'M';
        else if (r < 0.75) track = 'H';
        else if (r < 0.95) track = 'C';
        else track = 'KRAFT';
      }
      const cfg = window.TRACKS[track];
      // Upgrade level (e.g. 5/6)
      let level;
      if (track === 'KRAFT') level = 1;
      else {
        const r2 = ((seed + si*43) % 100) / 100;
        if (isMain) level = Math.min(cfg.levels, 4 + Math.floor(r2*3)); // 4..6
        else if (isBench || isAlt) level = Math.max(1, Math.floor(r2 * cfg.levels));
        else level = Math.min(cfg.levels, 3 + Math.floor(r2*4));
      }
      const ilvl = cfg.baseIlvl + (level - 1);
      // Problems
      const r3 = ((seed + si*61) % 100) / 100;
      const r4 = ((seed + si*113 + 7) % 100) / 100;
      const r5 = ((seed + si*157 + 11) % 100) / 100;
      const slotInfo = window.GEAR_SLOTS[si];
      const enchantable = ['cloak','chest','wrists','hands','waist','legs','feet','ring1','ring2','mh','oh'].includes(slotInfo.id);
      const socketable  = ['neck','ring1','ring2','wrists','waist'].includes(slotInfo.id);
      const slotProbs = {
        upgradeAvail: track !== 'KRAFT' && level < cfg.levels && r3 < 0.55,
        missingEnchant: enchantable && r4 < 0.18,
        missingGem: socketable && r5 < 0.30,
        lowIlvl: false, // computed later
        tierMissing: false, // computed later
      };
      slots[slotInfo.id] = { track, level, ilvl, ...slotProbs };
    });
    // Compute avg ilvl, mark low slots (>=10 below avg)
    const ilvls = Object.values(slots).map(s => s.ilvl);
    const avg = ilvls.reduce((a,b)=>a+b,0) / ilvls.length;
    Object.values(slots).forEach(s => { if (avg - s.ilvl >= 10) s.lowIlvl = true; });
    // Tier pieces: count how many tier slots have a track in {M, H} (we say if Champion piece in tier slot, "tier missing" = true if better available somewhere)
    const tierSlots = window.GEAR_SLOTS.filter(s => s.tier).map(s => s.id);
    const tierWorn = tierSlots.filter(sid => ['M','H'].includes(slots[sid].track)).length;
    // Audit profile (separate from gear): enchants/gems/talents (for "Audit" col)
    const auditSeed = (seed + 13) % 100;
    const audit = {
      enchantsMissing: Math.max(0, Math.floor(((seed+1)%17) - 8)),
      gemsMissing:     Math.max(0, Math.floor(((seed+5)%11) - 4)),
      talentsBad:      ((seed+7) % 10) === 0,
    };
    audit.total = audit.enchantsMissing + audit.gemsMissing + (audit.talentsBad ? 1 : 0);
    // Problems summary: missed upgrades = # slots with upgradeAvail
    const missedUpgrades = Object.values(slots).filter(s => s.upgradeAvail).length;
    const problemsCount =
      Object.values(slots).filter(s => s.lowIlvl || s.missingEnchant || s.missingGem).length;
    out[c.id] = {
      slots,
      avgIlvl: Math.round(avg),
      tierWorn,
      tierTotal: 6,
      audit,
      missedUpgrades,
      problemsCount,
    };
  });
  return out;
})();

// Item card — used in V1 Annotated Grid
window.ItemCell = function ItemCell({ slot, item, dense=false }) {
  const T = window.T;
  const cfg = window.TRACKS[item.track];
  const slotHash = hashStr(slot.id);
  const hue = slotHash % 360;
  const hue2 = (hue + 30) % 360;
  const w = dense ? 44 : 56;
  const h = dense ? 44 : 56;
  // Problem badges
  const probs = [];
  if (item.missingEnchant) probs.push({ icon:'bolt',     c:'#ff6e84', t:'Без енчанта' });
  if (item.missingGem)     probs.push({ icon:'diamond',  c:'#fbbf24', t:'Порожній сокет' });
  if (item.lowIlvl)        probs.push({ icon:'arrow_downward', c:'#ff6e84', t:'Низький ілвл' });
  if (item.upgradeAvail)   probs.push({ icon:'expand_less',    c:cfg.color, t:'Можна апгрейднути' });

  const tierBadge = slot.tier && ['M','H'].includes(item.track);

  return (
    <div title={`${slot.l} · ${cfg.l} · ${item.ilvl}`} style={{
      position:'relative', width:w, height:h, borderRadius:8,
      background: `linear-gradient(135deg, hsl(${hue} 40% 22%) 0%, hsl(${hue2} 35% 14%) 100%)`,
      border: `2px solid ${cfg.color}aa`,
      boxShadow: `inset 0 1px 0 rgba(255,255,255,0.06), 0 0 0 1px rgba(0,0,0,0.4)`,
      overflow:'visible', cursor:'pointer'
    }}>
      {/* Slot glyph */}
      <div style={{
        position:'absolute', inset:0, display:'flex', alignItems:'center', justifyContent:'center',
        color:'rgba(255,255,255,0.4)', fontSize: dense?10:11, fontWeight:800,
        letterSpacing:'0.04em', textTransform:'uppercase', textShadow:'0 1px 2px rgba(0,0,0,0.6)'
      }}>{slot.short.slice(0,4)}</div>

      {/* ilvl top-left */}
      <div style={{
        position:'absolute', top:-6, left:-2, fontSize:10, fontWeight:800,
        fontFamily:'JetBrains Mono', color:T.textHi,
        background:'#0e0e10', padding:'1px 4px', borderRadius:3,
        border:`1px solid ${cfg.color}55`
      }}>{item.ilvl}</div>

      {/* track badge bottom */}
      <div style={{
        position:'absolute', bottom:-7, left:'50%', transform:'translateX(-50%)',
        fontSize:8.5, fontWeight:900, fontFamily: item.track==='KRAFT'?'Inter':'JetBrains Mono',
        color: cfg.color,
        background:'#0e0e10', padding:'1px 5px', borderRadius:3,
        border:`1px solid ${cfg.color}55`,
        whiteSpace:'nowrap', letterSpacing: item.track==='KRAFT'?'0.08em':0
      }}>{item.track==='KRAFT' ? 'КРАФТ' : `${item.track} ${item.level}/${cfg.levels}`}</div>

      {/* Tier badge top-right */}
      {tierBadge && (
        <div style={{
          position:'absolute', top:-6, right:-4,
          width:16, height:16, borderRadius:4,
          background:'#fbbf24', color:'#0e0e10', fontSize:9, fontWeight:900,
          display:'flex', alignItems:'center', justifyContent:'center',
          border:'1px solid #0e0e10', fontFamily:'JetBrains Mono'
        }} title="Тирова частина">T</div>
      )}

      {/* Problem dots, top-right stacked when no tier badge; otherwise below */}
      {probs.length > 0 && (
        <div style={{
          position:'absolute', top: tierBadge ? 12 : -5, right:-5,
          display:'flex', flexDirection:'column', gap:2
        }}>
          {probs.slice(0,3).map((p,i) => (
            <div key={i} title={p.t} style={{
              width:13, height:13, borderRadius:'50%',
              background:'#0e0e10', border:`1.5px solid ${p.c}`,
              display:'flex', alignItems:'center', justifyContent:'center'
            }}>
              <span className="ms" style={{ fontSize:9, color:p.c, lineHeight:1 }}>{p.icon}</span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

// Compact dot for V2 heatmap — just track color block + tiny problem indicator
window.GearDot = function GearDot({ slot, item, size=22 }) {
  const cfg = window.TRACKS[item.track];
  const hasProb = item.missingEnchant || item.missingGem || item.lowIlvl;
  const hasUpgrade = item.upgradeAvail;
  return (
    <div title={`${slot.l} · ${cfg.l} ${item.level}/${cfg.levels} · ilvl ${item.ilvl}${hasProb?' · ⚠':''}`} style={{
      width:size, height:size, borderRadius:4,
      background: cfg.color + (item.track==='KRAFT'?'33':'cc'),
      border: `1px solid ${cfg.color}`,
      position:'relative', cursor:'pointer'
    }}>
      {/* upgrade bar at bottom */}
      {item.track !== 'KRAFT' && (
        <div style={{
          position:'absolute', bottom:1, left:1, right:1, height:2,
          background: 'rgba(0,0,0,0.45)', borderRadius:1, overflow:'hidden'
        }}>
          <div style={{
            width:`${(item.level/cfg.levels)*100}%`, height:'100%',
            background:'#fff', opacity:0.8
          }}/>
        </div>
      )}
      {hasProb && (
        <div style={{
          position:'absolute', top:-2, right:-2, width:7, height:7, borderRadius:'50%',
          background:'#ff6e84', border:'1px solid #0e0e10'
        }}/>
      )}
      {hasUpgrade && !hasProb && (
        <div style={{
          position:'absolute', top:-2, right:-2, width:7, height:7, borderRadius:'50%',
          background:'#fbbf24', border:'1px solid #0e0e10'
        }}/>
      )}
    </div>
  );
};

// Track legend (for tweaks/info)
window.TrackLegend = function TrackLegend() {
  const T = window.T;
  return (
    <div style={{ display:'flex', gap:14, alignItems:'center', fontSize:10, color:T.textMid, fontWeight:700, letterSpacing:'0.06em' }}>
      <span>ТРЕК ЕКІПУ:</span>
      {['M','H','C','V','KRAFT'].map(k => {
        const cfg = window.TRACKS[k];
        return (
          <span key={k} style={{ display:'inline-flex', alignItems:'center', gap:5 }}>
            <span style={{ width:10, height:10, borderRadius:2, background:cfg.color, border:`1px solid ${cfg.color}` }}/>
            <span style={{ color:cfg.color, fontFamily:'JetBrains Mono', fontWeight:800 }}>{cfg.short}</span>
            <span style={{ color:T.textLow, textTransform:'uppercase' }}>{cfg.l}</span>
          </span>
        );
      })}
    </div>
  );
};
