// Shared data + components for Roster v3 variants
// Reused across A/B/C — only the row presentation differs.

window.ROSTER_CHARS = [
  // tanks
  { id:'zavrikk',  nick:'Zavrikk',  bt:'ZAVRIKK#2796',     role:'tank', cls:'paladin',  spec:'Protection',     ilvl:281.8, tier:['M','M','-','H','M'], keys:9,  rio:2979, audit:0,  acc:'officer', status:'main',  alt:true,  isOfficer:true },
  { id:'raghaar',  nick:'Raghaar',  bt:'RAG#2415',         role:'tank', cls:'shaman',   spec:'Enhancement',    ilvl:252.8, tier:['-','-','-','-','-'], keys:0,  rio:793,  audit:11, acc:'member',  status:'main',  alt:false, isOfficer:false, parentOf:'zavrikk' },
  { id:'chong',    nick:'Chong',    bt:'ZHUKK#21636',      role:'tank', cls:'druid',    spec:'Guardian',       ilvl:278.8, tier:['H','H','M','H','-'], keys:8,  rio:3415, audit:0,  acc:'member',  status:'main',  alt:true,  isOfficer:false },
  { id:'magnezium',nick:'Magnezium',cls:'shaman',          role:'tank', spec:'Restoration',                    ilvl:233.1, tier:['-','C','C','C','C'], keys:8,  rio:0,    audit:2,  acc:'member',  status:'main',  alt:false, parentOf:'chong' },
  { id:'paulvoker',nick:'Paulvoker',cls:'evoker',          role:'tank', spec:'Preservation',                   ilvl:265.8, tier:['V','H','H','-','H'], keys:5,  rio:1966, audit:1,  acc:'member',  status:'main',  alt:false, parentOf:'chong' },
  // heals
  { id:'yazhneloh',nick:'Yazhneloh',cls:'druid',           role:'heal', spec:'Restoration',                    ilvl:280.7, tier:['M','H','M','M','H'], keys:7,  rio:3163, audit:0,  acc:'member',  status:'main',  alt:true },
  { id:'mcmorrio', nick:'Mcmorrio', cls:'priest',          role:'heal', spec:'Holy',                            ilvl:280.7, tier:['H','H','H','-','M'], keys:9,  rio:2989, audit:0,  acc:'member',  status:'bench', alt:false },
  { id:'pristula', nick:'Pristula', cls:'paladin',         role:'heal', spec:'Holy',                            ilvl:279.9, tier:['H','H','-','H','H'], keys:4,  rio:3285, audit:5,  acc:'member',  status:'bench', alt:false },
  { id:'lumiral',  nick:'Lumiral',  cls:'monk',            role:'heal', spec:'Mistweaver',                      ilvl:280.7, tier:['H','M','M','M','-'], keys:8,  rio:3853, audit:0,  acc:'member',  status:'main',  alt:false },
  { id:'ahrni',    nick:'Ahrni',    cls:'shaman',          role:'heal', spec:'Restoration',                     ilvl:277.8, tier:['M','H','-','M','H'], keys:10, rio:3254, audit:2,  acc:'member',  status:'main',  alt:true },
  { id:'soluna',   nick:'Soluna',   cls:'priest',          role:'heal', spec:'Discipline',                      ilvl:278.2, tier:['H','H','H','H','-'], keys:6,  rio:2840, audit:1,  acc:'member',  status:'main',  alt:false },
  { id:'velara',   nick:'Velara',   cls:'evoker',          role:'heal', spec:'Preservation',                    ilvl:276.4, tier:['H','-','H','H','-'], keys:3,  rio:2110, audit:3,  acc:'member',  status:'bench', alt:false },
  // dps  (showing first 13)
  { id:'kaeris',   nick:'Kaeris',   cls:'mage',            role:'dps',  spec:'Frost',                           ilvl:281.4, tier:['M','M','H','M','H'], keys:11, rio:3340, audit:0,  acc:'member',  status:'main',  alt:false },
  { id:'thalor',   nick:'Thalor',   cls:'rogue',           role:'dps',  spec:'Assassination',                   ilvl:280.9, tier:['H','M','M','H','H'], keys:10, rio:3402, audit:0,  acc:'member',  status:'main',  alt:true },
  { id:'morrigan', nick:'Morrigan', cls:'warlock',         role:'dps',  spec:'Destruction',                     ilvl:279.6, tier:['H','H','H','H','H'], keys:9,  rio:3120, audit:1,  acc:'member',  status:'main',  alt:false },
  { id:'syndra',   nick:'Syndra',   cls:'demonhunter',     role:'dps',  spec:'Havoc',                           ilvl:281.0, tier:['M','M','M','H','H'], keys:12, rio:3460, audit:0,  acc:'member',  status:'main',  alt:false },
  { id:'rhysand',  nick:'Rhysand',  cls:'hunter',          role:'dps',  spec:'Beast Mastery',                   ilvl:278.5, tier:['H','H','-','H','H'], keys:8,  rio:2940, audit:0,  acc:'member',  status:'main',  alt:false },
  { id:'lyssara',  nick:'Lyssara',  cls:'mage',            role:'dps',  spec:'Arcane',                          ilvl:280.2, tier:['M','H','H','H','-'], keys:9,  rio:3015, audit:2,  acc:'member',  status:'bench', alt:false },
  { id:'darius',   nick:'Darius',   cls:'warrior',         role:'dps',  spec:'Fury',                            ilvl:277.0, tier:['H','-','H','H','-'], keys:5,  rio:2620, audit:4,  acc:'member',  status:'bench', alt:false },
  { id:'nyrael',   nick:'Nyrael',   cls:'deathknight',     role:'dps',  spec:'Frost',                           ilvl:279.3, tier:['H','H','H','M','-'], keys:7,  rio:2890, audit:0,  acc:'member',  status:'main',  alt:false },
  { id:'sera',     nick:'Sera',     cls:'rogue',           role:'dps',  spec:'Outlaw',                          ilvl:280.1, tier:['M','H','H','M','H'], keys:11, rio:3280, audit:0,  acc:'member',  status:'main',  alt:false },
  { id:'thane',    nick:'Thane',    cls:'paladin',         role:'dps',  spec:'Retribution',                     ilvl:276.8, tier:['H','H','-','H','-'], keys:4,  rio:2380, audit:3,  acc:'member',  status:'main',  alt:false },
  { id:'fellfire', nick:'Fellfire', cls:'demonhunter',     role:'dps',  spec:'Vengeance',                       ilvl:278.9, tier:['H','M','H','-','H'], keys:8,  rio:2810, audit:0,  acc:'member',  status:'main',  alt:false },
  { id:'orlan',    nick:'Orlan',    cls:'warlock',         role:'dps',  spec:'Affliction',                      ilvl:275.4, tier:['-','-','H','-','H'], keys:2,  rio:1840, audit:6,  acc:'member',  status:'bench', alt:false },
  { id:'iva',      nick:'Iva',      cls:'monk',            role:'dps',  spec:'Windwalker',                      ilvl:279.8, tier:['H','H','M','H','H'], keys:9,  rio:3050, audit:0,  acc:'member',  status:'main',  alt:false },
];

// WoW class colors (canonical)
window.CLASS_COLORS = {
  paladin:'#F58CBA', shaman:'#0070DD', druid:'#FF7D0A', evoker:'#33937F',
  priest:'#FFFFFF', monk:'#00FF96', mage:'#3FC7EB', rogue:'#FFF569',
  warlock:'#8788EE', demonhunter:'#A330C9', hunter:'#ABD473',
  warrior:'#C79C6E', deathknight:'#C41F3B',
};

// Class spec icon (simple emoji-style rendered as colored circle with letter)
function ClassMark({ cls, size=14 }) {
  const c = window.CLASS_COLORS[cls] || '#888';
  const letter = (cls||'?').slice(0,1).toUpperCase();
  return (
    <span style={{
      display:'inline-grid', placeItems:'center',
      width:size, height:size, borderRadius:3,
      background:`${c}33`, border:`1px solid ${c}88`,
      color:c, fontSize:size*0.6, fontWeight:800, fontFamily:'JetBrains Mono'
    }}>{letter}</span>
  );
}

// Tier pips — 5 segments. h=height. Solid = present (color by difficulty), empty = missing.
function TierPips({ tier, size='md' }) {
  const T = window.T;
  const cfg = size==='sm' ? { w:7, h:11, gap:2, fs:0 } :
              size==='md' ? { w:11, h:14, gap:2, fs:8 } :
                            { w:14, h:18, gap:3, fs:9 };
  const colorOf = (k) => k==='M' ? '#a855f7' :
                          k==='H' ? T.tank :
                          k==='N' ? T.success :
                          k==='C' ? '#fcf266' :
                          k==='V' ? '#9a9a9a' : null;
  return (
    <div style={{ display:'inline-flex', gap:cfg.gap, alignItems:'center' }}>
      {tier.map((k,i) => {
        const c = colorOf(k);
        return (
          <div key={i} style={{
            width:cfg.w, height:cfg.h, borderRadius:2,
            background: c ? `linear-gradient(180deg, ${c}, ${c}cc)` : 'transparent',
            border: c ? `1px solid ${c}` : `1px dashed rgba(255,255,255,0.12)`,
            display:'grid', placeItems:'center',
            color: c ? '#0e0e10' : T.textLow,
            fontSize:cfg.fs, fontWeight:800, fontFamily:'JetBrains Mono', lineHeight:1,
            boxShadow: c ? `0 0 4px ${c}66` : 'none',
          }}>
            {cfg.fs > 0 && c ? k : ''}
          </div>
        );
      })}
    </div>
  );
}

// M+ key bar — 0-12 visualised, 4/8 thresholds colored
function KeyBar({ keys }) {
  const T = window.T;
  const c = keys >= 8 ? T.success : keys >= 4 ? T.tertiary : T.error;
  const pct = Math.min((keys/8)*100, 100);
  return (
    <div style={{ display:'flex', alignItems:'center', gap:8, minWidth:84 }}>
      <span style={{ fontSize:13, fontWeight:800, fontFamily:'JetBrains Mono', color:c, minWidth:18 }}>{keys}</span>
      <div style={{ flex:1, height:5, background:'rgba(255,255,255,0.06)', borderRadius:3, overflow:'hidden', position:'relative' }}>
        <div style={{ width:pct+'%', height:'100%', background:c, boxShadow:`0 0 4px ${c}88` }}/>
        {/* 8-mark */}
        <div style={{ position:'absolute', left:'100%', top:-1, bottom:-1, width:1, background:'rgba(255,255,255,0.15)', transform:'translateX(-1px)' }}/>
      </div>
    </div>
  );
}

// Audit chip — clickable, opens modal. Compact.
function AuditChip({ count, onClick }) {
  const T = window.T;
  if (count === 0) {
    return (
      <span style={{
        display:'inline-flex', alignItems:'center', gap:4,
        fontSize:10, color:T.success+'aa', fontWeight:700, letterSpacing:'0.06em',
      }}>
        <span className="ms" style={{ fontSize:14 }}>check_circle</span>
        ALL CLEAR
      </span>
    );
  }
  return (
    <button onClick={onClick} style={{
      display:'inline-flex', alignItems:'center', gap:5,
      padding:'3px 8px', borderRadius:6,
      background:'rgba(255,110,132,0.10)', border:'1px solid rgba(255,110,132,0.4)',
      color:T.error, fontSize:11, fontWeight:800, fontFamily:'JetBrains Mono',
      cursor:'pointer', transition:'all .12s'
    }}
      onMouseEnter={e=>{ e.currentTarget.style.background='rgba(255,110,132,0.18)'; }}
      onMouseLeave={e=>{ e.currentTarget.style.background='rgba(255,110,132,0.10)'; }}
    >
      <span className="ms" style={{ fontSize:13 }}>warning</span>
      {count}
    </button>
  );
}

// Avatar with role-color spec ring + bench/officer ribbons
function RosterAvatar({ char, size=32 }) {
  const T = window.T;
  const c = window.CLASS_COLORS[char.cls] || '#888';
  const roleColor = char.role==='tank' ? T.tank : char.role==='heal' ? T.heal : T.dps;
  return (
    <div style={{ position:'relative', width:size, height:size, flexShrink:0 }}>
      <div style={{
        width:size, height:size, borderRadius:6,
        background:`linear-gradient(135deg, ${c}55, ${c}11)`,
        border:`1.5px solid ${c}aa`,
        display:'grid', placeItems:'center',
        color:c, fontWeight:800, fontFamily:'Inter', fontSize:size*0.42
      }}>{char.nick.slice(0,1)}</div>
      {char.status === 'bench' && (
        <div style={{
          position:'absolute', top:-4, left:-2, padding:'1px 4px', borderRadius:3,
          background:T.error, color:'#fff', fontSize:7, fontWeight:800, letterSpacing:'0.08em',
          fontFamily:'Inter', lineHeight:1.1, transform:'rotate(-6deg)'
        }}>БЕНЧ</div>
      )}
      {char.isOfficer && (
        <div style={{
          position:'absolute', bottom:-3, right:-3, width:14, height:14, borderRadius:'50%',
          background:T.tertiary, border:'2px solid #0e0e10',
          display:'grid', placeItems:'center', color:'#1a1a00', fontSize:8, fontWeight:800
        }}>★</div>
      )}
    </div>
  );
}

// Status pill — Officer/Member, Main/Bench.
// Editable values render as a button with chevron (officer, main, bench).
// Non-editable / default values (member) render as plain text in the same
// visual rhythm — no border, no chevron, no padding-as-button.
function StatusPill({ kind, value, onChange }) {
  const T = window.T;
  const labels = {
    acc: { officer:'ОФІЦЕР', member:'УЧАСНИК' },
    status: { main:'ОСНОВНИЙ', bench:'БЕНЧ' }
  };
  const icons = {
    acc: { officer:'shield_person', member:'person' },
    status: { main:'check', bench:'pause' }
  };
  const colors = {
    officer:T.tertiary, member:T.textLow,
    main:T.success, bench:T.error
  };
  const isPlain = (kind === 'acc' && value === 'member');
  const label = labels[kind][value];
  const color = colors[value];

  if (isPlain) {
    return (
      <span style={{
        display:'inline-flex', alignItems:'center', gap:6,
        padding:'4px 8px',
        color: color, fontSize:9, fontWeight:800, letterSpacing:'0.08em',
        fontFamily:'Inter', lineHeight:1.2
      }}>
        <span className="ms" style={{ fontSize:11, opacity:0.7 }}>{icons[kind][value]}</span>
        {label}
      </span>
    );
  }

  return (
    <button style={{
      display:'inline-flex', alignItems:'center', gap:6,
      padding:'4px 8px', borderRadius:6,
      background:'transparent', border:`1px solid ${color}55`,
      color: color, fontSize:9, fontWeight:800, letterSpacing:'0.08em',
      cursor:'pointer', fontFamily:'Inter', lineHeight:1.2
    }}>
      <span className="ms" style={{ fontSize:11 }}>{icons[kind][value]}</span>
      {label}
      <span className="ms" style={{ fontSize:11, opacity:0.5 }}>expand_more</span>
    </button>
  );
}

// Filter pill row — replaces the chunky header counters
function FilterPills({ value, onChange, counts }) {
  const T = window.T;
  const items = [
    { k:'all',  l:'Усі',   n: counts.all },
    { k:'tank', l:'Танки', n: counts.tank, c:T.tank },
    { k:'heal', l:'Хіли',  n: counts.heal, c:T.heal },
    { k:'dps',  l:'ДД',    n: counts.dps,  c:T.dps },
    { k:'main', l:'Основа',n: counts.main, c:T.success },
    { k:'bench',l:'Бенч',  n: counts.bench, c:T.error },
  ];
  return (
    <div style={{ display:'flex', gap:6, alignItems:'center' }}>
      {items.map(it => (
        <button key={it.k} onClick={()=>onChange(it.k)} style={{
          display:'inline-flex', alignItems:'center', gap:6, padding:'5px 10px',
          borderRadius:6,
          background: value===it.k ? (it.c||T.primary)+'22' : 'transparent',
          border: `1px solid ${value===it.k ? (it.c||T.primary)+'66' : T.border}`,
          color: value===it.k ? (it.c||T.primary) : T.textMid,
          fontSize:11, fontWeight:700, cursor:'pointer', fontFamily:'Inter'
        }}>
          {it.l}
          <span style={{
            fontSize:9, fontFamily:'JetBrains Mono', fontWeight:800,
            padding:'1px 5px', borderRadius:3,
            background: value===it.k ? (it.c||T.primary)+'33' : 'rgba(255,255,255,0.04)',
            color: value===it.k ? (it.c||T.primary) : T.textLow
          }}>{it.n}</span>
        </button>
      ))}
    </div>
  );
}

// Top tabs — clickable
function RosterTabs({ active, onChange }) {
  const T = window.T;
  const tabs = [
    { k:'general', l:'Загальний',   icon:'view_list' },
    { k:'raids',   l:'Рейди',       icon:'swords' },
    { k:'gear',    l:'Спорядження', icon:'shield' },
    { k:'vault',   l:'Сейф',        icon:'inventory_2' },
  ];
  return (
    <div style={{ display:'flex', gap:6 }}>
      {tabs.map(t => {
        const isActive = active===t.k;
        const isDisabled = !isActive && !onChange;
        return (
          <button key={t.k} onClick={()=>onChange && onChange(t.k)} style={{
            display:'inline-flex', alignItems:'center', gap:6, padding:'8px 14px',
            borderRadius:8,
            background: isActive ? T.success+'22' : 'transparent',
            border: `1px solid ${isActive ? T.success+'88' : T.border}`,
            color: isActive ? T.success : T.textMid,
            fontSize:11, fontWeight:800, letterSpacing:'0.06em', cursor:'pointer',
            opacity: isDisabled && !['general','raids'].includes(t.k) ? 0.45 : 1,
            fontFamily:'Inter'
          }}>
            <span className="ms" style={{ fontSize:14 }}>{t.icon}</span>
            {t.l.toUpperCase()}
          </button>
        );
      })}
    </div>
  );
}

// Compact week-picker chip — delegates to global WeekSelector when available
function WeekChip({ value, onChange }) {
  if (window.WeekSelector) {
    return <window.WeekSelector value={value || 'cur'} onChange={onChange || (()=>{})}/>;
  }
  const T = window.T;
  return (
    <button style={{
      display:'inline-flex', alignItems:'center', gap:6, padding:'6px 12px',
      borderRadius:6, background:'transparent', border:`1px solid ${T.border}`,
      color:T.textMid, fontSize:11, fontWeight:700, cursor:'pointer'
    }}>
      <span className="ms" style={{ fontSize:14 }}>calendar_month</span>
      Поточний тиждень
      <span className="ms" style={{ fontSize:14, opacity:0.5 }}>expand_more</span>
    </button>
  );
}

// Live status pill
function LivePill() {
  const T = window.T;
  return (
    <span style={{
      display:'inline-flex', alignItems:'center', gap:6, padding:'4px 10px',
      borderRadius:20, background:T.success+'15', border:`1px solid ${T.success}55`,
      color:T.success, fontSize:10, fontWeight:800, letterSpacing:'0.1em'
    }}>
      <span style={{ width:6, height:6, borderRadius:'50%', background:T.success,
        boxShadow:`0 0 0 3px ${T.success}33`, animation:'pulse 1.6s infinite' }}/>
      НАЖИВО
    </span>
  );
}

// Group header
function GroupHeader({ role, count }) {
  const T = window.T;
  const cfg = role==='tank' ? { c:T.tank, l:'ТАНКИ' } :
              role==='heal' ? { c:T.heal, l:'ХІЛИ' } :
                              { c:T.dps,  l:'ДД' };
  return (
    <tr>
      <td colSpan={99} style={{
        padding:'10px 14px',
        background:`linear-gradient(90deg, ${cfg.c}22 0%, ${cfg.c}05 60%, transparent 100%)`,
        borderLeft:`3px solid ${cfg.c}`,
        borderTop:`1px solid ${cfg.c}33`,
      }}>
        <span style={{ display:'inline-flex', alignItems:'center', gap:8 }}>
          <span className="ms" style={{ fontSize:12, color:cfg.c }}>expand_less</span>
          <span style={{ fontSize:10, fontWeight:800, color:cfg.c, letterSpacing:'0.16em' }}>{cfg.l}</span>
          <span style={{ fontSize:10, fontFamily:'JetBrains Mono', color:T.textLow, fontWeight:700 }}>({count})</span>
        </span>
      </td>
    </tr>
  );
}

// Selection bar — floating bottom action bar when multi-select active
function SelectionBar({ count, onCompare, onIsolate, onClear, mode='general', compareMode=false }) {
  const T = window.T;
  if (count === 0) return null;
  return (
    <div style={{
      position:'absolute', bottom:24, left:'50%', transform:'translateX(-50%)',
      padding:'10px 14px', borderRadius:10,
      background:'rgba(20,20,24,0.95)', backdropFilter:'blur(8px)',
      border:`1px solid ${T.borderStrong}`,
      boxShadow:'0 8px 32px rgba(0,0,0,0.45)',
      display:'flex', alignItems:'center', gap:12,
      zIndex:20
    }}>
      <span style={{ display:'inline-flex', alignItems:'center', gap:6, fontSize:11, color:T.textMid, fontWeight:700 }}>
        <span className="ms" style={{ fontSize:14, color:T.success }}>check_circle</span>
        ВИБРАНО: <b style={{ color:T.textHi, fontFamily:'JetBrains Mono' }}>{count}</b>
      </span>
      <div style={{ width:1, height:18, background:T.border }}/>
      {!compareMode && (
        <button onClick={onCompare} style={{
          display:'inline-flex', alignItems:'center', gap:5, padding:'5px 10px', borderRadius:6,
          background:T.primary+'22', border:`1px solid ${T.primary}66`, color:T.primary,
          fontSize:10, fontWeight:800, letterSpacing:'0.08em', cursor:'pointer'
        }}>
          <span className="ms" style={{ fontSize:13 }}>compare_arrows</span>
          ПОРІВНЯТИ
        </button>
      )}
      <button onClick={onClear} style={{
        display:'inline-flex', alignItems:'center', gap:4, padding:'5px 8px', borderRadius:6,
        background:'transparent', border:'none', color:T.textLow,
        fontSize:10, fontWeight:700, cursor:'pointer'
      }}>
        <span className="ms" style={{ fontSize:13 }}>close</span>
        СКИНУТИ
      </button>
    </div>
  );
}

// Page chrome — top stripe with НАЖИВО + lifecycle counters + week picker
function PageChrome({ mainCount=20, totalCount=22, week, onWeekChange }) {
  const T = window.T;
  return (
    <div style={{
      padding:'10px 16px',
      borderBottom:`1px solid ${T.border}`,
      display:'flex', alignItems:'center', justifyContent:'space-between',
      background:'rgba(20,20,24,0.5)'
    }}>
      <div style={{ display:'flex', alignItems:'center', gap:14 }}>
        <LivePill/>
        <div style={{ display:'flex', alignItems:'center', gap:10, fontSize:10, color:T.textLow, fontFamily:'JetBrains Mono', fontWeight:700 }}>
          <span style={{ display:'inline-flex', alignItems:'center', gap:4 }}>
            <span style={{ width:8, height:8, borderRadius:'50%', background:T.tank }}/> 2/2
          </span>
          <span style={{ display:'inline-flex', alignItems:'center', gap:4 }}>
            <span style={{ width:8, height:8, borderRadius:'50%', background:T.heal }}/> 4/7
          </span>
          <span style={{ display:'inline-flex', alignItems:'center', gap:4 }}>
            <span style={{ width:8, height:8, borderRadius:'50%', background:T.dps }}/> 13/14
          </span>
          <span style={{ color:T.textLow, marginLeft:6 }}>· В ОСНОВІ {mainCount}/{totalCount}</span>
        </div>
      </div>
      <WeekChip value={week} onChange={onWeekChange}/>
    </div>
  );
}

// Audit modal (show on demand)
function AuditModal({ char, onClose }) {
  const T = window.T;
  if (!char) return null;
  return (
    <div onClick={onClose} style={{
      position:'absolute', inset:0, background:'rgba(8,8,10,0.72)', backdropFilter:'blur(6px)',
      display:'grid', placeItems:'center', zIndex:50
    }}>
      <div onClick={e=>e.stopPropagation()} style={{
        width:480, padding:'22px 26px', borderRadius:14, background:T.surface,
        border:`1px solid ${T.borderStrong}`
      }}>
        <div style={{ display:'flex', alignItems:'center', gap:12, marginBottom:18 }}>
          <div style={{ width:38, height:38, borderRadius:8, background:T.error+'22',
            border:`1px solid ${T.error}55`, display:'grid', placeItems:'center' }}>
            <span className="ms" style={{ fontSize:20, color:T.error }}>warning</span>
          </div>
          <div style={{ flex:1 }}>
            <div style={{ fontSize:18, fontWeight:800 }}>Проблеми аудиту</div>
            <div style={{ fontSize:10, color:T.textLow, fontFamily:'JetBrains Mono', letterSpacing:'0.08em' }}>{char.nick.toUpperCase()}</div>
          </div>
          <button onClick={onClose} style={{ background:'none', border:'none', color:T.textMid, fontSize:18, cursor:'pointer' }}>×</button>
        </div>
        <div style={{ padding:'12px 14px', borderRadius:8, border:`1px solid ${T.border}`, background:T.surfaceLow, marginBottom:10 }}>
          <div style={{ fontSize:10, color:T.error, letterSpacing:'0.1em', fontWeight:800, marginBottom:8 }}>
            <span className="ms" style={{ fontSize:14, marginRight:4, verticalAlign:'-3px' }}>auto_fix_high</span>
            ВІДСУТНІ ЧАРИ
          </div>
          <div style={{ display:'flex', flexWrap:'wrap', gap:6 }}>
            {['HEAD','SHOULDER','CHEST','LEGS','FEET','FINGER_1','FINGER_2','MAIN_HAND'].map(s=>(
              <span key={s} style={{
                padding:'3px 8px', borderRadius:5, fontSize:9, fontWeight:700, fontFamily:'JetBrains Mono',
                background:'rgba(255,110,132,0.08)', border:`1px solid ${T.error}33`, color:T.error
              }}>{s}</span>
            ))}
          </div>
        </div>
        <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:10 }}>
          <div style={{ padding:'12px 14px', borderRadius:8, border:`1px solid ${T.border}`, background:T.surfaceLow }}>
            <div style={{ fontSize:10, color:T.tertiary, letterSpacing:'0.1em', fontWeight:800, marginBottom:6 }}>
              <span className="ms" style={{ fontSize:14, marginRight:4, verticalAlign:'-3px' }}>diamond</span>
              ПОРОЖНІ СЛОТИ
            </div>
            <div style={{ fontSize:28, fontWeight:800, fontFamily:'JetBrains Mono', color:T.tertiary }}>3</div>
          </div>
          <div style={{ padding:'12px 14px', borderRadius:8, border:`1px solid ${T.border}`, background:T.surfaceLow }}>
            <div style={{ fontSize:10, color:T.primary, letterSpacing:'0.1em', fontWeight:800, marginBottom:6 }}>
              <span className="ms" style={{ fontSize:14, marginRight:4, verticalAlign:'-3px' }}>trending_up</span>
              ПРОПУЩЕНІ АПГРЕЙДИ
            </div>
            <div style={{ fontSize:28, fontWeight:800, fontFamily:'JetBrains Mono', color:T.primary }}>{char.audit*3}</div>
          </div>
        </div>
        <div style={{ marginTop:16, display:'flex', justifyContent:'flex-end' }}>
          <button onClick={onClose} style={{ padding:'8px 16px', borderRadius:8,
            background:T.surfaceHigh, border:`1px solid ${T.border}`, color:T.textHi,
            fontSize:11, fontWeight:700, cursor:'pointer' }}>Закрити</button>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, {
  ClassMark, TierPips, KeyBar, AuditChip, RosterAvatar, StatusPill,
  FilterPills, RosterTabs, WeekChip, LivePill, GroupHeader,
  SelectionBar, PageChrome, AuditModal
});
