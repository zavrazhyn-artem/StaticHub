// Shared design tokens — reuse from sidebar
window.T = {
  bg:'#0e0e10', surface:'#19191c', surfaceHigh:'#222226', surfaceLow:'#131315',
  primary:'#4fd3f7', secondary:'#fa7902', tertiary:'#fcf266',
  error:'#ff6e84', success:'#39FF14',
  textHi:'#f9f5f8', textMid:'#adaaad', textLow:'#767577',
  border:'rgba(255,255,255,0.06)', borderStrong:'rgba(255,255,255,0.12)',
  // role colors
  tank:'#3a8dff', heal:'#39ff14', dps:'#ff5063',
  // raid difficulty
  diffN:'#7a8a9a', diffH:'#3a8dff', diffM:'#a855f7',
};

// Wordmark
function Wordmark({ size=15 }) {
  return (
    <span style={{
      fontWeight:800, letterSpacing:'0.04em', fontSize:size,
      color:T.textHi, fontFamily:'Inter', display:'inline-flex', alignItems:'baseline'
    }}>
      <span>BLAST</span>
      <span style={{ color:T.primary, textShadow:`0 0 14px rgba(79,211,247,0.55)`, fontWeight:900 }}>R</span>
    </span>
  );
}

// Sync ring (mini, used inline + in modal)
function SyncRing({ size=44, pct=70, color, label, sub }) {
  const r = (size-6)/2, c = 2*Math.PI*r, off = c*(1-pct/100);
  color = color || T.primary;
  return (
    <div style={{ position:'relative', width:size, height:size, display:'inline-block' }}>
      <svg width={size} height={size} style={{ transform:'rotate(-90deg)' }}>
        <circle cx={size/2} cy={size/2} r={r} stroke="rgba(255,255,255,0.08)" strokeWidth="3" fill="none"/>
        <circle cx={size/2} cy={size/2} r={r} stroke={color} strokeWidth="3" fill="none"
          strokeDasharray={c} strokeDashoffset={off} strokeLinecap="round"
          style={{ filter:`drop-shadow(0 0 4px ${color}88)` }}/>
      </svg>
      {(label || sub) && (
        <div style={{ position:'absolute', inset:0, display:'grid', placeItems:'center', textAlign:'center', lineHeight:1.1 }}>
          {label && <div style={{ fontSize: size>50?10:8, fontWeight:800, color:T.textHi }}>{label}</div>}
          {sub && <div style={{ fontSize: size>50?9:7, color:color, fontFamily:'JetBrains Mono', fontWeight:700 }}>{sub}</div>}
        </div>
      )}
    </div>
  );
}

// Sync badge — small pill that opens modal on click
function SyncBadge({ name, mins, pct, color, onClick }) {
  return (
    <button onClick={onClick} style={{
      display:'flex', alignItems:'center', gap:8, padding:'6px 10px 6px 6px',
      borderRadius:20, background:'rgba(255,255,255,0.03)',
      border:`1px solid ${T.border}`, cursor:'pointer', color:T.textMid,
      fontSize:11, fontWeight:600, fontFamily:'Inter', transition:'all .15s'
    }}
    onMouseEnter={e=>{ e.currentTarget.style.background='rgba(255,255,255,0.06)'; e.currentTarget.style.borderColor=color+'55'; }}
    onMouseLeave={e=>{ e.currentTarget.style.background='rgba(255,255,255,0.03)'; e.currentTarget.style.borderColor=T.border; }}>
      <SyncRing size={26} pct={pct} color={color}/>
      <span style={{ display:'flex', flexDirection:'column', alignItems:'flex-start', lineHeight:1.1 }}>
        <span style={{ fontSize:10, color:T.textLow, letterSpacing:'0.08em', fontWeight:700, textTransform:'uppercase' }}>{name}</span>
        <span style={{ fontSize:10, color:T.textMid, fontFamily:'JetBrains Mono' }}>{mins}хв тому</span>
      </span>
    </button>
  );
}

// Sync modal — opens from any sync element
function SyncModal({ open, onClose }) {
  if (!open) return null;
  const services = [
    { n:'BLIZZARD',     pct:5,  mins:'56х', sub:'3х 37с', color:'#9a9a9a' },
    { n:'RAIDER.IO',    pct:75, mins:'56х', sub:'423х 37с', color:T.primary },
    { n:'WARCRAFT LOGS',pct:90, mins:'56х', sub:'1383х 37с', color:T.primary },
  ];
  return (
    <div onClick={onClose} style={{
      position:'absolute', inset:0, background:'rgba(8,8,10,0.72)', backdropFilter:'blur(6px)',
      display:'grid', placeItems:'center', zIndex:50, animation:'fade .15s'
    }}>
      <div onClick={e=>e.stopPropagation()} style={{
        width:540, padding:24, borderRadius:16,
        background:`linear-gradient(180deg, ${T.surface} 0%, ${T.surfaceLow} 100%)`,
        border:`1px solid ${T.borderStrong}`,
        boxShadow:'0 30px 80px rgba(0,0,0,0.6), 0 0 0 1px rgba(79,211,247,0.08)'
      }}>
        <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between', marginBottom:18 }}>
          <div style={{ fontSize:14, fontWeight:700, color:T.textHi }}>Статус синхронізації</div>
          <button onClick={onClose} style={{ background:'none', border:'none', color:T.textMid, cursor:'pointer', fontSize:18, padding:4 }}>✕</button>
        </div>
        <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr 1fr', gap:12 }}>
          {services.map(s=>(
            <div key={s.n} style={{
              padding:'18px 12px', borderRadius:12, background:T.surfaceLow,
              border:`1px solid ${T.border}`, textAlign:'center'
            }}>
              <div style={{ fontSize:9, letterSpacing:'0.14em', color:T.textLow, fontWeight:700, marginBottom:14 }}>{s.n}</div>
              <SyncRing size={92} pct={s.pct} color={s.color} label={s.mins+' тому'} sub={s.sub}/>
            </div>
          ))}
        </div>
        <div style={{ marginTop:16, display:'flex', justifyContent:'flex-end', gap:8 }}>
          <button style={{ padding:'8px 14px', borderRadius:8, background:T.surfaceHigh,
            border:`1px solid ${T.border}`, color:T.textMid, fontSize:12, fontWeight:600, cursor:'pointer' }}>Налаштувати</button>
          <button style={{ padding:'8px 14px', borderRadius:8, background:T.primary,
            border:'none', color:'#003040', fontSize:12, fontWeight:700, cursor:'pointer' }}>Синхронізувати все</button>
        </div>
      </div>
    </div>
  );
}

// Section label
function Eyebrow({ children, color }) {
  return <div style={{
    fontSize:9, letterSpacing:'0.18em', color:color||T.textLow, fontWeight:700, textTransform:'uppercase'
  }}>{children}</div>;
}

// Bosses dataset
window.RAID_DATA = {
  name: "Liberation of Undermine",
  tiers: [
    { name:'THE VOIDSPIRE', bosses:[
      { n:'Imperator Aversion',  k:'M', tries:14 },
      { n:'Vorashus',            k:'M', tries:8 },
      { n:'Fallen-King Salhadaar', k:'M', tries:21 },
    ]},
    { name:'THE DREAMRIFT', bosses:[
      { n:'Vaeloor & Ezzomar',   k:'H', tries:3 },
      { n:'Lightlinked Vanguard',k:'H', tries:1 },
      { n:'Crown of the Cosmos', k:'H', tries:0 },
    ]},
    { name:'MARCH ON GUEL\'DANAS', bosses:[
      { n:'Chimaerus the Undreamt One', k:'H', tries:0 },
      { n:'Belothren, Child of Al\'ar', k:'-', tries:0 },
      { n:'Midnight Falls',     k:'-', tries:0 },
    ]},
  ],
  totals: { N:'9/9', H:'7/9', M:'2/9' },
  current: { name:'Fallen-King Salhadaar', pct:28, attempts:21, best:'14%' },
};

window.ROSTER = {
  total:24, signed:18, tanks:{have:2, need:2}, heals:{have:4, need:5}, dps:{have:12, need:17},
  bench:3, absent:1, tentative:2,
};

window.LOGISTICS = [
  { i:'savings', l:'Банк гільдії', v:'140 000', u:'G', pct:78, color:'#fcf266' },
  { i:'soup_kitchen', l:'Котли', v:3, u:'/тижд', pct:45, color:T.secondary },
  { i:'local_fire_department', l:'Флакони', v:48, u:'/тижд', pct:80, color:T.error },
  { i:'restaurant', l:'Їжа', v:9, u:'/тижд', pct:95, color:T.success },
];

window.ACHIEVEMENTS = [
  { name:"Cutting Edge: Ovi'nax", date:'15 жов 2024', tier:'CE', color:'#a855f7' },
  { name:"Hall of Fame: Top 200", date:'12 жов 2024', tier:'HOF', color:T.tertiary },
  { name:"Keystone Master S2", date:'01 лис 2024', tier:'KSM', color:T.primary },
  { name:"Glory of the Raider", date:'22 вер 2024', tier:'AOTC', color:T.success },
];

Object.assign(window, { Wordmark, SyncRing, SyncBadge, SyncModal, Eyebrow });
