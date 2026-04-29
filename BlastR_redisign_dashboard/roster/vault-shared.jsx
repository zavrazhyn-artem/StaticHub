// Vault data — per-char × per-week × 9 slots (raid 1-3, m+ 1-3, world 1-3).
// Each slot is either null (—) or { ilvl, track, level, max }.
// Tracks: M (Myth), H (Hero), C (Champion), V (Veteran).
// Same color palette as Gear.

window.VAULT_TRACKS = {
  M: { color:'#FB923C', max:6 },
  H: { color:'#C084FC', max:6 },
  C: { color:'#60A5FA', max:8 },
  V: { color:'#4ADE80', max:8 },
};

window.VAULT_WEEKS = [
  { id:'cur', label:'Поточний тиждень' },
  { id:'w7',  label:'Тиждень 7' },
  { id:'w6',  label:'Тиждень 6' },
  { id:'w5',  label:'Тиждень 5' },
  { id:'w4',  label:'Тиждень 4' },
  { id:'w3',  label:'Тиждень 3' },
  { id:'w2',  label:'Тиждень 2' },
];

// Per-row: array of 9 entries — null for empty, otherwise reward.
// 0..2 = raid, 3..5 = m+, 6..8 = world.
window.VAULT_DATA = (() => {
  const out = {};
  const chars = window.ROSTER_CHARS;
  // Per-char activity profile: how strong they are in each category 0..1
  chars.forEach((c, idx) => {
    const seed = (idx * 31 + 13) % 100;
    out[c.id] = {};
    window.VAULT_WEEKS.forEach((wk, wi) => {
      const slots = [];
      // Raid bias: tanks/heals raid more often
      const raidBias  = (c.role==='tank' || c.role==='heal' ? 0.92 : 0.78) - (c.parentOf?0.4:0) - (wi*0.05);
      const mpBias    = 0.85 - (c.parentOf?0.45:0) - (wi*0.04);
      const worldBias = 0.75 - (c.parentOf?0.4:0) - (wi*0.03);

      // RAID: ilvl 285 (M 1-3) for endbosses, 272 (H) for mid, 285 (M) high
      [0,1,2].forEach(i => {
        const r = ((seed + wi*7 + i*11) % 100) / 100;
        if (r > raidBias) { slots.push(null); return; }
        // Track depends on slot level: 1 → maybe Champion or Hero, 3 → Hero or Myth
        const tier = i; // 0=easy 1=mid 2=hard
        let track, level, ilvl;
        const mythChance = tier===2 ? 0.55 : tier===1 ? 0.18 : 0.04;
        if (r < mythChance) { track='M'; level=Math.min(6, 1+Math.floor(r*60)); ilvl=285+level; }
        else if (r < mythChance+0.55) { track='H'; level=Math.min(6, 2+Math.floor(r*60)%5); ilvl=272+level; }
        else { track='C'; level=Math.min(8, 3+Math.floor(r*60)%5); ilvl=259+level; }
        slots.push({ ilvl, track, level, max:window.VAULT_TRACKS[track].max });
      });
      // M+: ilvl heavily depends on key level done
      [0,1,2].forEach(i => {
        const r = ((seed + wi*13 + i*17 + 5) % 100) / 100;
        if (r > mpBias) { slots.push(null); return; }
        const mythChance = i===2 ? 0.45 : i===1 ? 0.30 : 0.18;
        let track, level, ilvl;
        if (r < mythChance) { track='M'; level=Math.min(6, 1+Math.floor(r*70)%5); ilvl=285+level; }
        else if (r < mythChance+0.55) { track='H'; level=Math.min(6, 3+Math.floor(r*70)%4); ilvl=272+level; }
        else { track='C'; level=Math.min(8, 4+Math.floor(r*70)%5); ilvl=259+level; }
        slots.push({ ilvl, track, level, max:window.VAULT_TRACKS[track].max });
      });
      // WORLD: usually Veteran/Champion, rarely Hero
      [0,1,2].forEach(i => {
        const r = ((seed + wi*19 + i*23 + 11) % 100) / 100;
        if (r > worldBias) { slots.push(null); return; }
        const heroChance = i===2 ? 0.30 : 0.10;
        let track, level, ilvl;
        if (r < heroChance) { track='H'; level=Math.min(6, 1+Math.floor(r*70)%4); ilvl=272+level; }
        else if (r < heroChance+0.45) { track='C'; level=Math.min(8, 2+Math.floor(r*70)%5); ilvl=259+level; }
        else { track='V'; level=Math.min(8, 3+Math.floor(r*70)%6); ilvl=246+level; }
        slots.push({ ilvl, track, level, max:window.VAULT_TRACKS[track].max });
      });
      out[c.id][wk.id] = slots;
    });
  });
  return out;
})();

// Week selector — purple chip with caret, matches user's reference screenshot.
function WeekSelector({ value, onChange }) {
  const T = window.T;
  const [open, setOpen] = React.useState(false);
  const cur = window.VAULT_WEEKS.find(w => w.id === value) || window.VAULT_WEEKS[0];
  const purple = '#C084FC';
  return (
    <div style={{ position:'relative' }}>
      <button onClick={()=>setOpen(o=>!o)} style={{
        display:'inline-flex', alignItems:'center', gap:8,
        padding:'8px 12px 8px 12px', minWidth:240,
        borderRadius:8,
        background: open ? purple+'22' : purple+'14',
        border:`1px solid ${purple}66`,
        color: purple,
        fontSize:11, fontWeight:800, letterSpacing:'0.1em',
        cursor:'pointer', textTransform:'uppercase'
      }}>
        <span className="ms" style={{ fontSize:14 }}>calendar_month</span>
        <span style={{ flex:1, textAlign:'left' }}>{cur.label}</span>
        <span className="ms" style={{ fontSize:16, transform: open?'rotate(180deg)':'none', transition:'transform .15s' }}>expand_more</span>
      </button>
      {open && (
        <div style={{
          position:'absolute', top:'calc(100% + 6px)', right:0, width:280, zIndex:50,
          background:'#1a1a1d', border:`1px solid ${T.border}`, borderRadius:8,
          padding:6, maxHeight:340, overflow:'auto',
          boxShadow:'0 12px 40px rgba(0,0,0,0.45)'
        }}>
          {window.VAULT_WEEKS.map(w => (
            <button key={w.id} onClick={()=>{ onChange(w.id); setOpen(false); }} style={{
              display:'flex', alignItems:'center', justifyContent:'space-between', width:'100%',
              padding:'10px 12px', borderRadius:6,
              background: w.id===value ? purple+'18' : 'transparent',
              border:'none', color: w.id===value ? purple : T.textHi,
              fontSize:13, fontWeight: w.id===value?800:600, cursor:'pointer', textAlign:'left',
              fontFamily:'Inter'
            }}
              onMouseEnter={(e)=>{ if(w.id!==value) e.currentTarget.style.background='rgba(255,255,255,0.04)'; }}
              onMouseLeave={(e)=>{ if(w.id!==value) e.currentTarget.style.background='transparent'; }}
            >
              <span>{w.label}</span>
              {w.id===value && <span className="ms" style={{ fontSize:16 }}>check</span>}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

window.WeekSelector = WeekSelector;

// VaultIlvl — single ilvl reward, colored by track.
function VaultIlvl({ slot, size = 'md' }) {
  const T = window.T;
  if (!slot) {
    return <span style={{ fontSize:14, color:T.textLow, opacity:0.4 }}>—</span>;
  }
  const c = window.VAULT_TRACKS[slot.track].color;
  const fontSize = size === 'lg' ? 18 : size === 'sm' ? 13 : 16;
  return (
    <span style={{
      fontSize, fontWeight:800, color:c, fontFamily:'JetBrains Mono', letterSpacing:'-0.02em'
    }}>{slot.ilvl}</span>
  );
}
window.VaultIlvl = VaultIlvl;

// VaultCard — full card with ilvl + track badge + upgrade level.
// Used in V2/V3 variants.
function VaultCard({ slot }) {
  const T = window.T;
  if (!slot) {
    return (
      <div style={{
        display:'inline-flex', alignItems:'center', justifyContent:'center',
        width:60, height:42, borderRadius:6,
        border:`1px dashed ${T.border}`,
        color:T.textLow, fontSize:14, opacity:0.5
      }}>—</div>
    );
  }
  const cfg = window.VAULT_TRACKS[slot.track];
  return (
    <div style={{
      display:'inline-flex', flexDirection:'column', alignItems:'center', justifyContent:'center',
      minWidth:60, padding:'4px 8px', borderRadius:6,
      background:`linear-gradient(180deg, ${cfg.color}18 0%, ${cfg.color}06 100%)`,
      border:`1px solid ${cfg.color}44`
    }}>
      <span style={{ fontSize:14, fontWeight:800, fontFamily:'JetBrains Mono', color:cfg.color, lineHeight:1, letterSpacing:'-0.02em' }}>
        {slot.ilvl}
      </span>
      <span style={{ fontSize:8, fontWeight:800, color:cfg.color+'cc', letterSpacing:'0.06em', fontFamily:'JetBrains Mono', marginTop:2 }}>
        {slot.track} {slot.level}/{slot.max}
      </span>
    </div>
  );
}
window.VaultCard = VaultCard;
