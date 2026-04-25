// ARTBOARD 2 — TACTICAL HUD
// Game-UI / WoW addon vibe. Concentric rings hero (composition / readiness / sync).
// Diegetic, dense, neon. Hex-grid micro details.

function ArtboardTacticalHUD() {
  const [syncOpen, setSyncOpen] = React.useState(false);
  const T = window.T;

  return (
    <div style={{
      width:1280, height:900, color:T.textHi, fontFamily:'Inter', position:'relative', overflow:'hidden',
      background:`
        radial-gradient(ellipse at 30% 20%, rgba(79,211,247,0.10) 0%, transparent 55%),
        radial-gradient(ellipse at 80% 90%, rgba(168,85,247,0.08) 0%, transparent 55%),
        linear-gradient(180deg, #08080a 0%, #050507 100%)`,
      padding:'24px 28px'
    }}>
      {/* hex grid overlay */}
      <div style={{ position:'absolute', inset:0, opacity:0.06, pointerEvents:'none',
        backgroundImage:`linear-gradient(0deg, rgba(255,255,255,0.4) 1px, transparent 1px),
                         linear-gradient(90deg, rgba(255,255,255,0.4) 1px, transparent 1px)`,
        backgroundSize:'40px 40px' }}/>

      {/* TOP STATUS BAR */}
      <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between',
        padding:'10px 14px', borderRadius:8, background:'rgba(0,0,0,0.4)',
        border:`1px solid ${T.border}`, marginBottom:18,
        fontFamily:'JetBrains Mono', fontSize:10.5, letterSpacing:'0.06em' }}>
        <div style={{ display:'flex', gap:18, color:T.textMid }}>
          <span style={{ color:T.success }}>● ONLINE</span>
          <span>FACTION <span style={{ color:T.textHi }}>HORDE</span></span>
          <span>SERVER <span style={{ color:T.textHi }}>RAVENCREST · EU</span></span>
          <span>TIER <span style={{ color:'#a855f7' }}>S2 · LIBERATION OF UNDERMINE</span></span>
        </div>
        <div style={{ display:'flex', gap:8 }}>
          <SyncBadge name="BLZ" mins={56} pct={5} color="#9a9a9a" onClick={()=>setSyncOpen(true)}/>
          <SyncBadge name="R.IO" mins={56} pct={75} color={T.primary} onClick={()=>setSyncOpen(true)}/>
          <SyncBadge name="W.LOGS" mins={56} pct={90} color={T.primary} onClick={()=>setSyncOpen(true)}/>
        </div>
      </div>

      <div style={{ display:'grid', gridTemplateColumns:'320px 1fr 320px', gap:16 }}>
        {/* LEFT: Я + boss list */}
        <div style={{ display:'flex', flexDirection:'column', gap:14 }}>
          {/* ME PANEL */}
          <PanelHUD title="OPERATIVE" id="V-001">
            <div style={{ display:'flex', alignItems:'center', gap:10, marginBottom:10 }}>
              <div style={{ width:48, height:48, borderRadius:6,
                background:`linear-gradient(135deg, #fcf26666, #fcf26622)`,
                border:`1px solid #fcf26666`, display:'grid', placeItems:'center',
                color:T.tertiary, fontWeight:800, fontSize:20 }}>З</div>
              <div>
                <div style={{ fontSize:13, fontWeight:800 }}>ZELION</div>
                <div style={{ fontSize:10, color:T.tertiary, fontFamily:'JetBrains Mono' }}>HOLY PALADIN · LV80</div>
              </div>
            </div>
            <StatRow label="ITEM LEVEL" value="639" pct={92} color={T.tertiary}/>
            <StatRow label="M+ SCORE"   value="2847" pct={71} color={T.primary}/>
            <StatRow label="VAULT"      value="3 / 9" pct={33} color={T.success}/>
            <StatRow label="READY"      value="92%" pct={92} color={T.success}/>
          </PanelHUD>

          {/* BOSS PROGRESSION as list */}
          <PanelHUD title="TARGETS" id="9 / 9 N · 7 / 9 H · 2 / 9 M">
            <div style={{ display:'grid', gap:4 }}>
              {RAID_DATA.tiers.flatMap(t=>t.bosses).map((b,i)=>(
                <BossRow key={i} {...b} active={b.n==='Fallen-King Salhadaar'}/>
              ))}
            </div>
          </PanelHUD>
        </div>

        {/* CENTER: HERO RINGS */}
        <PanelHUD title="RAID NIGHT" id="2D 06H 08M" big>
          <div style={{ position:'relative', height:520, display:'grid', placeItems:'center' }}>
            {/* concentric rings */}
            <ConcentricHero/>
            {/* corner stats */}
            <div style={{ position:'absolute', top:14, left:14 }}>
              <Eyebrow color={T.primary}>NEXT ENGAGEMENT</Eyebrow>
              <div style={{ fontSize:11, color:T.textMid, marginTop:3, fontFamily:'JetBrains Mono' }}>ПН · 27 КВІТ · 21:00</div>
            </div>
            <div style={{ position:'absolute', top:14, right:14, textAlign:'right' }}>
              <Eyebrow color="#a855f7">CURRENT TARGET</Eyebrow>
              <div style={{ fontSize:11, color:T.textHi, marginTop:3, fontWeight:700 }}>FALLEN-KING SALHADAAR</div>
              <div style={{ fontSize:10, color:T.textLow, marginTop:1, fontFamily:'JetBrains Mono' }}>21 TRIES · BEST 14%</div>
            </div>
            <div style={{ position:'absolute', bottom:14, left:14 }}>
              <Eyebrow>SQUAD COMP</Eyebrow>
              <div style={{ display:'flex', gap:6, marginTop:5 }}>
                <RoleChip label="T" v="2/2" color={T.tank}/>
                <RoleChip label="H" v="4/5" color={T.heal}/>
                <RoleChip label="D" v="12/17" color={T.dps}/>
              </div>
            </div>
            <div style={{ position:'absolute', bottom:14, right:14, textAlign:'right' }}>
              <Eyebrow color={T.success}>READINESS</Eyebrow>
              <div style={{ fontSize:11, color:T.textHi, marginTop:3, fontFamily:'JetBrains Mono' }}>18 / 24 SIGNED</div>
              <div style={{ fontSize:10, color:T.textLow, marginTop:1 }}>2 tentative · 1 absent</div>
            </div>
            {/* CTA */}
            <button style={{ position:'absolute', bottom:'50%', transform:'translateY(245px)',
              padding:'8px 20px', borderRadius:20, background:T.tertiary, color:'#1a1a00',
              border:'none', fontWeight:800, fontSize:11, letterSpacing:'0.12em', cursor:'pointer',
              boxShadow:'0 0 24px rgba(252,242,102,0.35)' }}>RSVP · LOCK IN</button>
          </div>
        </PanelHUD>

        {/* RIGHT: Achievements + Logistics */}
        <div style={{ display:'flex', flexDirection:'column', gap:14 }}>
          <PanelHUD title="ACHIEVEMENTS" id="STATIC HONORS">
            <div style={{ display:'grid', gap:6 }}>
              {ACHIEVEMENTS.map((a,i)=>(
                <div key={i} style={{ display:'flex', alignItems:'center', gap:10,
                  padding:'7px 8px', borderRadius:6,
                  background:`linear-gradient(90deg, ${a.color}14, transparent)`,
                  borderLeft:`2px solid ${a.color}` }}>
                  <span style={{ fontSize:9, fontWeight:800, color:a.color, fontFamily:'JetBrains Mono',
                    width:30 }}>[{a.tier}]</span>
                  <div style={{ flex:1, minWidth:0 }}>
                    <div style={{ fontSize:11, color:T.textHi, fontWeight:700, whiteSpace:'nowrap', overflow:'hidden', textOverflow:'ellipsis' }}>{a.name}</div>
                    <div style={{ fontSize:9, color:T.textLow, fontFamily:'JetBrains Mono' }}>{a.date.toUpperCase()}</div>
                  </div>
                </div>
              ))}
            </div>
          </PanelHUD>

          <PanelHUD title="SUPPLY" id="WEEK CYCLE">
            {LOGISTICS.map((l,i)=>(
              <div key={i} style={{ marginBottom:i<LOGISTICS.length-1?10:0 }}>
                <div style={{ display:'flex', justifyContent:'space-between', marginBottom:4, fontSize:10 }}>
                  <span style={{ display:'flex', alignItems:'center', gap:6 }}>
                    <span className="ms" style={{ fontSize:13, color:l.color }}>{l.i}</span>
                    <span style={{ color:T.textMid, fontWeight:600 }}>{l.l}</span>
                  </span>
                  <span style={{ fontFamily:'JetBrains Mono', color:l.color, fontWeight:700 }}>
                    {l.v}<span style={{ color:T.textLow, marginLeft:3 }}>{l.u}</span>
                  </span>
                </div>
                <div style={{ height:3, borderRadius:2, background:'rgba(255,255,255,0.05)', overflow:'hidden' }}>
                  <div style={{ width:l.pct+'%', height:'100%', background:l.color, boxShadow:`0 0 6px ${l.color}cc` }}/>
                </div>
              </div>
            ))}
          </PanelHUD>
        </div>
      </div>

      <SyncModal open={syncOpen} onClose={()=>setSyncOpen(false)}/>
    </div>
  );
}

function PanelHUD({ title, id, children, big }) {
  const T = window.T;
  return (
    <div style={{ position:'relative',
      background:'linear-gradient(180deg, rgba(255,255,255,0.02), rgba(0,0,0,0.3))',
      border:`1px solid ${T.borderStrong}`,
      clipPath:'polygon(8px 0, 100% 0, 100% calc(100% - 8px), calc(100% - 8px) 100%, 0 100%, 0 8px)',
      padding:big?'14px':'12px' }}>
      {/* corner ticks */}
      <span style={{ position:'absolute', top:0, left:0, width:8, height:1, background:T.primary }}/>
      <span style={{ position:'absolute', top:0, right:0, width:1, height:8, background:T.primary }}/>
      <span style={{ position:'absolute', bottom:0, left:0, width:1, height:8, background:T.primary }}/>
      <span style={{ position:'absolute', bottom:0, right:0, width:8, height:1, background:T.primary }}/>

      <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between',
        marginBottom:10, paddingBottom:8, borderBottom:`1px dashed ${T.border}` }}>
        <span style={{ fontSize:9.5, color:T.primary, fontFamily:'JetBrains Mono',
          letterSpacing:'0.18em', fontWeight:700 }}>▸ {title}</span>
        {id && <span style={{ fontSize:9, color:T.textLow, fontFamily:'JetBrains Mono', letterSpacing:'0.08em' }}>{id}</span>}
      </div>
      {children}
    </div>
  );
}

function StatRow({ label, value, pct, color }) {
  const T = window.T;
  return (
    <div style={{ marginBottom:6 }}>
      <div style={{ display:'flex', justifyContent:'space-between', fontSize:9.5,
        fontFamily:'JetBrains Mono', marginBottom:3 }}>
        <span style={{ color:T.textLow, letterSpacing:'0.1em' }}>{label}</span>
        <span style={{ color, fontWeight:700 }}>{value}</span>
      </div>
      <div style={{ height:2, background:'rgba(255,255,255,0.06)', overflow:'hidden' }}>
        <div style={{ width:pct+'%', height:'100%', background:color, boxShadow:`0 0 4px ${color}` }}/>
      </div>
    </div>
  );
}

function BossRow({ n, k, tries, active }) {
  const T = window.T;
  const tone = k==='M' ? '#a855f7' : k==='H' ? T.tank : k==='N' ? T.success : T.textLow;
  const isKill = k==='M'||k==='H'||k==='N';
  return (
    <div style={{
      display:'flex', alignItems:'center', gap:8, padding:'5px 6px',
      background: active ? 'rgba(168,85,247,0.10)' : 'transparent',
      borderLeft: active ? `2px solid #a855f7` : '2px solid transparent',
      fontFamily:'JetBrains Mono'
    }}>
      <span style={{ fontSize:9, fontWeight:800, color:tone, width:14 }}>{k}</span>
      <span style={{ flex:1, fontSize:10, color:isKill||active?T.textMid:T.textLow,
        whiteSpace:'nowrap', overflow:'hidden', textOverflow:'ellipsis' }}>{n}</span>
      {tries>0 && <span style={{ fontSize:9, color:T.textLow }}>{tries}t</span>}
      {active && <span style={{ width:5, height:5, borderRadius:'50%', background:'#a855f7',
        animation:'pulse 1.6s infinite' }}/>}
    </div>
  );
}

function RoleChip({ label, v, color }) {
  const T = window.T;
  return (
    <div style={{ padding:'3px 8px', borderRadius:4, background:`${color}18`,
      border:`1px solid ${color}55`, display:'flex', gap:6, alignItems:'baseline',
      fontFamily:'JetBrains Mono' }}>
      <span style={{ fontSize:10, color, fontWeight:800 }}>{label}</span>
      <span style={{ fontSize:11, color:T.textHi, fontWeight:700 }}>{v}</span>
    </div>
  );
}

function ConcentricHero() {
  const T = window.T;
  // outer: time, mid: roster, inner: progression
  return (
    <svg width={460} height={460} viewBox="0 0 460 460">
      <defs>
        <radialGradient id="rgCenter" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stopColor="rgba(252,242,102,0.18)"/>
          <stop offset="100%" stopColor="rgba(0,0,0,0)"/>
        </radialGradient>
      </defs>
      <circle cx="230" cy="230" r="220" fill="url(#rgCenter)"/>

      {/* Outer ring — countdown (segmented) */}
      <circle cx="230" cy="230" r="200" fill="none" stroke="rgba(255,255,255,0.06)" strokeWidth="1"/>
      <circle cx="230" cy="230" r="200" fill="none" stroke={T.tertiary} strokeWidth="3"
        strokeDasharray="3 6" opacity="0.45" transform="rotate(-90 230 230)"/>
      {/* progress arc on outer */}
      <circle cx="230" cy="230" r="200" fill="none" stroke={T.tertiary} strokeWidth="3"
        strokeDasharray={`${2*Math.PI*200*0.27} ${2*Math.PI*200}`}
        strokeLinecap="round" transform="rotate(-90 230 230)"
        style={{ filter:`drop-shadow(0 0 6px ${T.tertiary}aa)` }}/>

      {/* Mid ring — roster readiness */}
      <circle cx="230" cy="230" r="160" fill="none" stroke="rgba(255,255,255,0.06)" strokeWidth="6"/>
      <circle cx="230" cy="230" r="160" fill="none" stroke={T.success} strokeWidth="6"
        strokeDasharray={`${2*Math.PI*160*0.75} ${2*Math.PI*160}`} strokeLinecap="round"
        transform="rotate(-90 230 230)"
        style={{ filter:`drop-shadow(0 0 6px ${T.success}aa)` }}/>

      {/* Inner ring — boss progression M */}
      <circle cx="230" cy="230" r="120" fill="none" stroke="rgba(255,255,255,0.06)" strokeWidth="8"/>
      <circle cx="230" cy="230" r="120" fill="none" stroke="#a855f7" strokeWidth="8"
        strokeDasharray={`${2*Math.PI*120*0.22} ${2*Math.PI*120}`} strokeLinecap="round"
        transform="rotate(-90 230 230)"
        style={{ filter:'drop-shadow(0 0 8px #a855f7aa)' }}/>

      {/* Center text */}
      <text x="230" y="200" textAnchor="middle" fill={T.textLow} fontSize="11"
        fontFamily="JetBrains Mono" letterSpacing="3">T-MINUS</text>
      <text x="230" y="252" textAnchor="middle" fill={T.tertiary} fontSize="58"
        fontFamily="JetBrains Mono" fontWeight="800" letterSpacing="-2">2:06:08</text>
      <text x="230" y="278" textAnchor="middle" fill={T.textLow} fontSize="10"
        fontFamily="JetBrains Mono" letterSpacing="2">DAYS · HOURS · MINUTES</text>

      {/* tick marks outer */}
      {Array.from({length:12}).map((_,i)=>{
        const a = (i/12)*Math.PI*2 - Math.PI/2;
        const x1 = 230 + Math.cos(a)*210, y1 = 230 + Math.sin(a)*210;
        const x2 = 230 + Math.cos(a)*220, y2 = 230 + Math.sin(a)*220;
        return <line key={i} x1={x1} y1={y1} x2={x2} y2={y2} stroke={T.textLow} strokeWidth="1"/>;
      })}

      {/* labels around */}
      <text x="230" y="22" textAnchor="middle" fill={T.tertiary} fontSize="10"
        fontFamily="JetBrains Mono" letterSpacing="3" fontWeight="700">⌬ TIME TO RAID</text>
      <text x="230" y="442" textAnchor="middle" fill={T.success} fontSize="10"
        fontFamily="JetBrains Mono" letterSpacing="3" fontWeight="700">⌬ ROSTER READY 75%</text>
      <text x="78" y="234" textAnchor="middle" fill="#a855f7" fontSize="9"
        fontFamily="JetBrains Mono" letterSpacing="2" fontWeight="700"
        transform="rotate(-90 78 234)">M-PROG 22%</text>
      <text x="382" y="234" textAnchor="middle" fill={T.primary} fontSize="9"
        fontFamily="JetBrains Mono" letterSpacing="2" fontWeight="700"
        transform="rotate(90 382 234)">SYNC OK</text>
    </svg>
  );
}

window.ArtboardTacticalHUD = ArtboardTacticalHUD;
