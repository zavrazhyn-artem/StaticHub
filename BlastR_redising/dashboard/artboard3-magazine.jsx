// ARTBOARD 3 — MAGAZINE
// Poster hero: huge boss "cover" with editorial typography. Mixed grid below.
// Treats raid as cinema poster.

function ArtboardMagazine() {
  const [syncOpen, setSyncOpen] = React.useState(false);
  const T = window.T;

  return (
    <div style={{
      width:1280, height:900, color:T.textHi, fontFamily:'Inter', position:'relative', overflow:'hidden',
      background:'#08080a',
      padding:'24px 28px'
    }}>
      {/* Top bar */}
      <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between', marginBottom:14 }}>
        <div style={{ display:'flex', alignItems:'baseline', gap:14 }}>
          <span style={{ fontSize:11, color:T.textLow, fontFamily:'JetBrains Mono', letterSpacing:'0.18em' }}>
            ISSUE №207
          </span>
          <span style={{ fontSize:11, color:T.textLow, fontFamily:'JetBrains Mono' }}>·</span>
          <span style={{ fontSize:11, color:T.textLow, fontFamily:'JetBrains Mono' }}>WEEK 17 · APR 2025</span>
        </div>
        <div style={{ display:'flex', gap:8 }}>
          <SyncBadge name="BLZ" mins={56} pct={5} color="#9a9a9a" onClick={()=>setSyncOpen(true)}/>
          <SyncBadge name="R.IO" mins={56} pct={75} color={T.primary} onClick={()=>setSyncOpen(true)}/>
          <SyncBadge name="W.LOGS" mins={56} pct={90} color={T.primary} onClick={()=>setSyncOpen(true)}/>
        </div>
      </div>

      {/* HERO POSTER */}
      <div style={{
        display:'grid', gridTemplateColumns:'1.6fr 1fr', gap:0,
        height:430, marginBottom:18, position:'relative',
        background:T.surface, border:`1px solid ${T.borderStrong}`, borderRadius:14, overflow:'hidden'
      }}>
        {/* Cover with huge type */}
        <div style={{ position:'relative', overflow:'hidden',
          background:`
            radial-gradient(ellipse at 70% 30%, rgba(168,85,247,0.45) 0%, transparent 60%),
            radial-gradient(ellipse at 30% 80%, rgba(255,80,99,0.30) 0%, transparent 55%),
            linear-gradient(135deg, #1a0830 0%, #0a0510 100%)`
        }}>
          {/* placeholder boss silhouette using SVG triangles to suggest crown */}
          <svg width="100%" height="100%" viewBox="0 0 720 430" preserveAspectRatio="xMidYMid slice"
            style={{ position:'absolute', inset:0, opacity:0.5 }}>
            <defs>
              <linearGradient id="bossGrad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stopColor="#a855f7" stopOpacity="0.7"/>
                <stop offset="100%" stopColor="#a855f7" stopOpacity="0"/>
              </linearGradient>
            </defs>
            {/* crown shape */}
            <path d="M 360 80 L 410 180 L 470 90 L 510 200 L 570 100 L 600 220 L 540 240 L 360 250 L 180 240 L 120 220 L 150 100 L 210 200 L 250 90 L 310 180 Z"
              fill="url(#bossGrad)" stroke="#c084fc" strokeWidth="1.5" opacity="0.8"/>
            {/* throne */}
            <rect x="220" y="240" width="280" height="120" fill="url(#bossGrad)" opacity="0.6"/>
            <line x1="240" y1="360" x2="240" y2="430" stroke="#a855f7" strokeWidth="2" opacity="0.5"/>
            <line x1="480" y1="360" x2="480" y2="430" stroke="#a855f7" strokeWidth="2" opacity="0.5"/>
          </svg>

          {/* film grain */}
          <div style={{ position:'absolute', inset:0, mixBlendMode:'overlay', opacity:0.4,
            backgroundImage:'radial-gradient(rgba(255,255,255,0.15) 1px, transparent 1px)',
            backgroundSize:'3px 3px' }}/>

          {/* editorial overlay */}
          <div style={{ position:'absolute', inset:0, padding:'28px 32px', display:'flex',
            flexDirection:'column', justifyContent:'space-between' }}>
            <div>
              <div style={{ display:'inline-block', padding:'4px 10px', borderRadius:20,
                background:'rgba(168,85,247,0.20)', border:'1px solid rgba(168,85,247,0.5)',
                fontSize:9, fontWeight:800, letterSpacing:'0.2em', color:'#c084fc' }}>
                ● MYTHIC · 22% PROGRESS
              </div>
              <div style={{ fontSize:11, color:T.textMid, marginTop:14, fontFamily:'JetBrains Mono', letterSpacing:'0.12em' }}>
                3 / 9 · CURRENT ENCOUNTER
              </div>
            </div>

            <div>
              <div style={{ fontSize:74, fontWeight:900, lineHeight:0.92, letterSpacing:'-0.04em',
                color:T.textHi, textShadow:'0 4px 24px rgba(0,0,0,0.6)' }}>
                Fallen-King<br/><span style={{ color:'#c084fc' }}>Salhadaar</span>
              </div>
              <div style={{ display:'flex', gap:24, marginTop:18, alignItems:'baseline' }}>
                <Stat l="ATTEMPTS" v="21"/>
                <Stat l="BEST" v="14%" c={T.error}/>
                <Stat l="STREAK" v="3 NIGHTS" c={T.tertiary}/>
              </div>
            </div>
          </div>
        </div>

        {/* Right column: countdown, comp, CTA */}
        <div style={{ padding:'28px 28px 22px', display:'flex', flexDirection:'column',
          justifyContent:'space-between', borderLeft:`1px solid ${T.border}`,
          background:'linear-gradient(180deg, #16161a 0%, #0e0e10 100%)' }}>
          <div>
            <Eyebrow color={T.primary}>NEXT RAID NIGHT</Eyebrow>
            <div style={{ fontFamily:'JetBrains Mono', fontWeight:800, fontSize:42,
              color:T.tertiary, marginTop:6, letterSpacing:'-0.02em', lineHeight:1 }}>
              02д 06г<br/>08х 53с
            </div>
            <div style={{ fontSize:11, color:T.textMid, marginTop:8 }}>понеділок · 27 квітня · 21:00 Київ</div>

            {/* Composition mini */}
            <div style={{ marginTop:22, padding:14, borderRadius:8, background:'rgba(0,0,0,0.3)',
              border:`1px solid ${T.border}` }}>
              <div style={{ display:'flex', justifyContent:'space-between', alignItems:'center', marginBottom:10 }}>
                <Eyebrow>СКЛАД · 18 / 24</Eyebrow>
                <span style={{ fontSize:10, color:T.success, fontWeight:700 }}>● ГОТОВО</span>
              </div>
              <div style={{ display:'flex', gap:6 }}>
                <CompBar label="T" have={2} need={2} color={T.tank}/>
                <CompBar label="H" have={4} need={5} color={T.heal}/>
                <CompBar label="DD" have={12} need={17} color={T.dps}/>
              </div>
            </div>
          </div>

          <button style={{
            marginTop:18, padding:'14px 22px', borderRadius:10,
            background:`linear-gradient(135deg, ${T.tertiary}, #ffe066)`, color:'#1a1a00',
            border:'none', fontWeight:800, fontSize:13, letterSpacing:'0.12em', cursor:'pointer',
            boxShadow:'0 8px 24px rgba(252,242,102,0.25)'
          }}>RSVP · ПІДТВЕРДИТИ УЧАСТЬ</button>
        </div>
      </div>

      {/* GRID below: ME / ACHIEVEMENTS / LOGISTICS / BOSSES */}
      <div style={{ display:'grid', gridTemplateColumns:'1fr 1.4fr 1fr', gap:14 }}>
        {/* ME side card */}
        <div style={{ padding:'18px 20px', borderRadius:12, background:T.surface,
          border:`1px solid ${T.border}`, position:'relative', overflow:'hidden' }}>
          <div style={{ position:'absolute', right:-30, top:-30, width:120, height:120,
            background:`radial-gradient(circle, ${T.tertiary}30, transparent 70%)`, pointerEvents:'none' }}/>
          <Eyebrow color={T.tertiary}>STAFF · ZELION</Eyebrow>
          <div style={{ fontSize:22, fontWeight:800, marginTop:6, letterSpacing:'-0.01em' }}>Holy Paladin</div>
          <div style={{ fontSize:11, color:T.textLow, marginTop:2 }}>Ravencrest · Horde</div>
          <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:14, marginTop:16 }}>
            <Big label="ITEM LEVEL" value="639" color={T.tertiary}/>
            <Big label="M+ SCORE" value="2847" color={T.primary}/>
            <Big label="VAULT" value="3/9" color={T.success}/>
            <Big label="BEST KEY" value="+14" color={T.secondary}/>
          </div>
          <div style={{ marginTop:12, fontSize:10, color:T.success, fontWeight:700, letterSpacing:'0.06em' }}>
            ● ГОТОВНІСТЬ 92% · бракує 1 enchant
          </div>
        </div>

        {/* BOSSES list */}
        <div style={{ padding:'18px 20px', borderRadius:12, background:T.surface,
          border:`1px solid ${T.border}` }}>
          <div style={{ display:'flex', justifyContent:'space-between', alignItems:'baseline', marginBottom:12 }}>
            <Eyebrow>LIBERATION OF UNDERMINE</Eyebrow>
            <span style={{ fontSize:10, color:T.textLow, fontFamily:'JetBrains Mono' }}>9N · 7H · 2M / 9</span>
          </div>
          <div style={{ display:'grid', gap:5 }}>
            {RAID_DATA.tiers.flatMap(t=>t.bosses).map((b,i)=>(
              <MagBossRow key={i} {...b} idx={i+1} active={b.n==='Fallen-King Salhadaar'}/>
            ))}
          </div>
        </div>

        {/* RIGHT col: achievements top, logistics bottom */}
        <div style={{ display:'flex', flexDirection:'column', gap:14 }}>
          <div style={{ padding:'14px 16px', borderRadius:12, background:T.surface,
            border:`1px solid ${T.border}` }}>
            <Eyebrow>ДОСЯГНЕННЯ</Eyebrow>
            <div style={{ display:'grid', gridTemplateColumns:'repeat(2,1fr)', gap:6, marginTop:10 }}>
              {ACHIEVEMENTS.map((a,i)=>(
                <div key={i} style={{
                  padding:'8px', borderRadius:6, background:`${a.color}10`,
                  border:`1px solid ${a.color}33`, textAlign:'center'
                }}>
                  <div style={{ fontSize:11, fontWeight:800, color:a.color, fontFamily:'JetBrains Mono' }}>{a.tier}</div>
                  <div style={{ fontSize:9, color:T.textMid, marginTop:3, lineHeight:1.2,
                    whiteSpace:'nowrap', overflow:'hidden', textOverflow:'ellipsis' }}>{a.name.split(':')[0]}</div>
                </div>
              ))}
            </div>
          </div>
          <div style={{ padding:'14px 16px', borderRadius:12, background:T.surface,
            border:`1px solid ${T.border}` }}>
            <Eyebrow>ЛОГІСТИКА</Eyebrow>
            {LOGISTICS.map((l,i)=>(
              <div key={i} style={{ marginTop:8 }}>
                <div style={{ display:'flex', justifyContent:'space-between', fontSize:10, marginBottom:3 }}>
                  <span style={{ color:T.textMid }}>{l.l}</span>
                  <span style={{ fontFamily:'JetBrains Mono', color:l.color, fontWeight:700 }}>{l.v} {l.u}</span>
                </div>
                <div style={{ height:3, background:'rgba(255,255,255,0.06)', borderRadius:2, overflow:'hidden' }}>
                  <div style={{ width:l.pct+'%', height:'100%', background:l.color }}/>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      <SyncModal open={syncOpen} onClose={()=>setSyncOpen(false)}/>
    </div>
  );
}

function Stat({ l, v, c }) {
  const T = window.T;
  return (
    <div>
      <div style={{ fontSize:9, color:T.textLow, letterSpacing:'0.14em', fontWeight:700 }}>{l}</div>
      <div style={{ fontSize:24, fontWeight:800, fontFamily:'JetBrains Mono', color:c||T.textHi }}>{v}</div>
    </div>
  );
}

function Big({ label, value, color }) {
  const T = window.T;
  return (
    <div>
      <div style={{ fontSize:9, color:T.textLow, letterSpacing:'0.12em', fontWeight:700 }}>{label}</div>
      <div style={{ fontSize:24, fontWeight:800, fontFamily:'JetBrains Mono', color, marginTop:2, letterSpacing:'-0.02em' }}>{value}</div>
    </div>
  );
}

function CompBar({ label, have, need, color }) {
  const T = window.T;
  const pct = (have/need)*100;
  return (
    <div style={{ flex:1 }}>
      <div style={{ display:'flex', justifyContent:'space-between', fontSize:9, marginBottom:3 }}>
        <span style={{ color, fontWeight:800 }}>{label}</span>
        <span style={{ fontFamily:'JetBrains Mono', color:T.textHi, fontWeight:700 }}>{have}/{need}</span>
      </div>
      <div style={{ height:24, background:'rgba(255,255,255,0.04)', borderRadius:4, overflow:'hidden', position:'relative' }}>
        <div style={{ width:Math.min(pct,100)+'%', height:'100%', background:`linear-gradient(90deg, ${color}66, ${color})`,
          boxShadow:`inset 0 0 8px ${color}88` }}/>
      </div>
    </div>
  );
}

function MagBossRow({ n, k, tries, idx, active }) {
  const T = window.T;
  const tone = k==='M' ? '#a855f7' : k==='H' ? T.tank : k==='N' ? T.success : T.textLow;
  const isKill = k==='M'||k==='H'||k==='N';
  return (
    <div style={{ display:'flex', alignItems:'center', gap:10,
      padding:'7px 10px', borderRadius:6,
      background: active?'rgba(168,85,247,0.10)':'transparent',
      border: active?'1px solid rgba(168,85,247,0.35)':'1px solid transparent' }}>
      <span style={{ fontSize:10, fontFamily:'JetBrains Mono', color:T.textLow, width:20 }}>0{idx}</span>
      <span style={{ fontSize:9.5, fontFamily:'JetBrains Mono', color:tone, fontWeight:800,
        width:18, textAlign:'center' }}>[{k}]</span>
      <span style={{ flex:1, fontSize:11, color:isKill||active?T.textHi:T.textLow, fontWeight:isKill?600:400 }}>{n}</span>
      {tries>0 && <span style={{ fontSize:9.5, fontFamily:'JetBrains Mono', color:T.textLow }}>{tries} спроб</span>}
      {active && <span style={{ width:6, height:6, borderRadius:'50%', background:'#a855f7',
        boxShadow:'0 0 0 3px rgba(168,85,247,0.25)', animation:'pulse 1.6s infinite' }}/>}
    </div>
  );
}

window.ArtboardMagazine = ArtboardMagazine;
