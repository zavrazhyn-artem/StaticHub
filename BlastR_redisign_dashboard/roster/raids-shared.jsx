// Raids — shared boss list + per-character kill matrix.
// 3 raids, 9 bosses, binary kill state per (char × boss × difficulty).

window.RAIDS = [
  {
    id:'voidspire', name:'VOIDSPIRE', short:'VS', tier:1,
    bosses: [
      { id:'vs1', name:'Voidwarden',         short:'VWD' },
      { id:'vs2', name:"Echo of Sargeras",   short:'ECS' },
      { id:'vs3', name:'Twin Spires',        short:'TSP' },
      { id:'vs4', name:'Shadowmaw',          short:'SHM' },
      { id:'vs5', name:'Korrak the Bound',   short:'KOR' },
      { id:'vs6', name:'Nyssara Voidqueen',  short:'NYS' },
    ]
  },
  {
    id:'dreamrift', name:'DREAMRIFT', short:'DR', tier:2,
    bosses: [
      { id:'dr1', name:'Riftspawn Tyrannos', short:'RFT' },
    ]
  },
  {
    id:'guel', name:"GUEL'DANAS", short:'GD', tier:3,
    bosses: [
      { id:'gd1', name:"Gul'dan Reborn",     short:'GUL' },
      { id:'gd2', name:'Sargeras Avatar',    short:'SAR' },
    ]
  },
];

// Flat list of all bosses with raid context
window.ALL_BOSSES = window.RAIDS.flatMap(r =>
  r.bosses.map(b => ({ ...b, raidId:r.id, raidShort:r.short, raidTier:r.tier }))
);

// Generate kills matrix — { [charId]: { [bossId]: { M:bool, H:bool, N:bool } } }
// Realistic distribution: M = early bosses common, last bosses rare.
// Some chars have full clear, some partial, some only N.
window.RAID_KILLS = (() => {
  const out = {};
  const chars = window.ROSTER_CHARS;
  // Per-boss kill probability on each difficulty
  const probM = [0.92, 0.86, 0.78, 0.62, 0.40, 0.10,   // VS — last 2 are progression
                 0.55,                                  // DR — single boss
                 0.28, 0.04];                           // GD — last is end-boss
  const probH = [0.96, 0.94, 0.90, 0.84, 0.78, 0.72,
                 0.85,
                 0.70, 0.40];
  const probN = [0.40, 0.36, 0.32, 0.28, 0.20, 0.18,   // N is mostly alts
                 0.25,
                 0.18, 0.10];

  chars.forEach((c, idx) => {
    const cMatrix = {};
    // Some chars are weaker performers — bench / parentOf alts
    const bias = c.parentOf ? 0.3 : c.status==='bench' ? 0.6 : 1.0;
    // Add per-char rng seed so it's stable across renders
    const seed = (idx * 17 + 31) % 100;
    window.ALL_BOSSES.forEach((b, bi) => {
      const rA = ((seed + bi*13) % 100) / 100;
      const rB = ((seed + bi*7+5) % 100) / 100;
      const rC = ((seed + bi*23+11) % 100) / 100;
      const rD = ((seed + bi*31+19) % 100) / 100;
      cMatrix[b.id] = {
        M: rA < probM[bi] * bias,
        H: rB < probH[bi] * bias,
        N: rC < probN[bi] * bias,
        LFR: rD < (probN[bi]*0.6) * bias,
      };
    });
    out[c.id] = cMatrix;
  });
  return out;
})();

// Difficulty config — colors per spec
window.DIFFS = {
  M:   { label:'Міфік',  short:'M',   color:'#FB923C' }, // orange
  H:   { label:'Героїк', short:'H',  color:'#C084FC' }, // purple
  N:   { label:'Нормал', short:'N',  color:'#60A5FA' }, // blue
  LFR: { label:'ЛФР',     short:'LFR', color:'#4ADE80' }, // green
};

// Difficulty segmented toggle
function DiffToggle({ value, onChange }) {
  const T = window.T;
  const items = [
    { k:'M',   l:'МІФІК',  c:'#FB923C' },
    { k:'H',   l:'ГЕРОЇК', c:'#C084FC' },
    { k:'N',   l:'НОРМАЛ', c:'#60A5FA' },
    { k:'LFR', l:'ЛФР',     c:'#4ADE80' },
  ];
  return (
    <div style={{ display:'inline-flex', padding:2, borderRadius:6, background:T.surfaceLow, border:`1px solid ${T.border}` }}>
      {items.map(it => (
        <button key={it.k} onClick={()=>onChange(it.k)} style={{
          padding:'5px 12px', borderRadius:5,
          background: value===it.k ? it.c+'22' : 'transparent',
          border: `1px solid ${value===it.k ? it.c+'66' : 'transparent'}`,
          color: value===it.k ? it.c : window.T.textLow,
          fontSize:10, fontWeight:800, letterSpacing:'0.08em', cursor:'pointer',
          fontFamily:'Inter'
        }}>{it.l}</button>
      ))}
    </div>
  );
}

// Boss column header — full name, raid color stripe, sortable
function BossHeader({ boss, color, sortDir, onSort }) {
  const T = window.T;
  return (
    <th onClick={onSort} style={{
      padding:'10px 4px 8px', fontSize:9, fontWeight:800,
      letterSpacing:'0.04em', textAlign:'center',
      borderBottom:`1px solid ${T.border}`,
      borderTop:`2px solid ${color}88`,
      background:`linear-gradient(180deg, ${color}12 0%, transparent 60%)`,
      color: T.textHi,
      cursor: onSort?'pointer':'default', position:'relative', userSelect:'none',
      verticalAlign:'bottom'
    }}
      title={boss.name}
    >
      <div style={{
        fontFamily:'Inter', fontWeight:800, color:T.textHi,
        lineHeight:1.2, textTransform:'uppercase', letterSpacing:'0.02em',
        padding:'0 4px',
        whiteSpace:'nowrap', overflow:'hidden', textOverflow:'ellipsis'
      }}>{boss.name}</div>
      {sortDir && (
        <span className="ms" style={{
          fontSize:11, color:color, position:'absolute', top:2, right:4
        }}>{sortDir==='desc'?'arrow_downward':'arrow_upward'}</span>
      )}
    </th>
  );
}

// Cell: ✓ killed / × not killed
function KillCell({ killed, color, isAlt }) {
  const T = window.T;
  if (killed) {
    return (
      <td style={{
        padding:0, textAlign:'center', verticalAlign:'middle',
        background: color+'14',
      }}>
        <span className="ms" style={{
          fontSize: isAlt?14:16, color: color, fontWeight:900
        }}>check</span>
      </td>
    );
  }
  return (
    <td style={{
      padding:0, textAlign:'center', verticalAlign:'middle',
    }}>
      <span style={{
        display:'inline-block', width:6, height:6, borderRadius:'50%',
        background: 'rgba(255,255,255,0.06)',
      }}/>
    </td>
  );
}

// Heatmap variant cell — solid colored block
function KillCellHeat({ killed, color }) {
  if (killed) {
    return (
      <td style={{ padding:2 }}>
        <div style={{
          height:24, borderRadius:4,
          background: `linear-gradient(180deg, ${color}, ${color}cc)`,
          boxShadow:`0 0 6px ${color}55`,
          display:'grid', placeItems:'center'
        }}>
          <span className="ms" style={{ fontSize:14, color:'#0e0e10', fontWeight:900 }}>check</span>
        </div>
      </td>
    );
  }
  return (
    <td style={{ padding:2 }}>
      <div style={{
        height:24, borderRadius:4,
        background: 'rgba(255,255,255,0.03)',
        border: '1px dashed rgba(255,255,255,0.07)',
      }}/>
    </td>
  );
}

// Compute attendance stats for a char on current diff
function killStats(c, diff) {
  const m = window.RAID_KILLS[c.id];
  if (!m) return { killed:0, total:0, pct:0 };
  const total = window.ALL_BOSSES.length;
  const killed = window.ALL_BOSSES.filter(b => m[b.id]?.[diff]).length;
  return { killed, total, pct: Math.round(killed/total*100) };
}

// Per-raid stats
function killStatsByRaid(c, diff, raidId) {
  const m = window.RAID_KILLS[c.id];
  if (!m) return { killed:0, total:0 };
  const bosses = window.ALL_BOSSES.filter(b => b.raidId===raidId);
  const killed = bosses.filter(b => m[b.id]?.[diff]).length;
  return { killed, total: bosses.length };
}

// Mini progress bar
function MiniProgress({ killed, total, color, w=64 }) {
  const T = window.T;
  const pct = total ? (killed/total*100) : 0;
  const c = color || (pct>=80 ? T.success : pct>=50 ? T.tertiary : T.error);
  return (
    <div style={{ display:'flex', alignItems:'center', gap:6 }}>
      <span style={{ fontSize:11, fontWeight:800, fontFamily:'JetBrains Mono', color:c, minWidth:30, textAlign:'right' }}>
        {killed}/{total}
      </span>
      <div style={{ width:w, height:5, background:'rgba(255,255,255,0.06)', borderRadius:3, overflow:'hidden' }}>
        <div style={{ width:pct+'%', height:'100%', background:c, boxShadow:`0 0 4px ${c}66` }}/>
      </div>
    </div>
  );
}

Object.assign(window, { DiffToggle, BossHeader, KillCell, KillCellHeat, killStats, killStatsByRaid, MiniProgress });
