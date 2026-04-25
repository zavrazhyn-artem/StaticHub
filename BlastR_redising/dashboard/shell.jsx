// Shared dashboard shell — все що сподобалось user'у з v1/v4
// Header + Next Raid (v4) + ME card (v4) + Progression strip (v4 style)
// Logistics slot is RENDERED BY CHILD via {logisticsRender} prop.

function DashboardShell({ logisticsTitle, logisticsHint, logisticsRender, syncOpen, setSyncOpen, accent }) {
  const T = window.T;
  accent = accent || T.primary;

  return (
    <div style={{
      width:1280, height:900, color:T.textHi, fontFamily:'Inter', position:'relative', overflow:'hidden',
      background:'#0e0e10', padding:'24px 28px'
    }}>
      {/* Topbar */}
      <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between', marginBottom:18 }}>
        <div>
          <div style={{ fontSize:11, color:T.textLow, letterSpacing:'0.16em', fontWeight:700 }}>ПАНЕЛЬ КЕРУВАННЯ</div>
          <div style={{ fontSize:22, fontWeight:800, marginTop:4, display:'flex', alignItems:'center', gap:10 }}>
            <span style={{ width:8, height:8, borderRadius:'50%', background:T.success,
              boxShadow:`0 0 0 4px rgba(57,255,20,0.18)` }}/>
            Характерники
            <span style={{ fontSize:11, color:T.textLow, fontWeight:600, marginLeft:8 }}>· Mythic Progression 2/9</span>
          </div>
        </div>
        <div style={{ display:'flex', gap:8 }}>
          <SyncBadge name="BLZ" mins={56} pct={5} color="#9a9a9a" onClick={()=>setSyncOpen(true)}/>
          <SyncBadge name="R.IO" mins={56} pct={75} color={T.primary} onClick={()=>setSyncOpen(true)}/>
          <SyncBadge name="W.LOGS" mins={56} pct={90} color={T.primary} onClick={()=>setSyncOpen(true)}/>
        </div>
      </div>

      {/* Row 1: Next Raid + ME (v4) */}
      <div style={{ display:'grid', gridTemplateColumns:'1.7fr 1fr', gap:14, marginBottom:14 }}>
        {/* NEXT RAID */}
        <div style={{
          padding:'22px 26px', borderRadius:14,
          background:`linear-gradient(135deg, rgba(79,211,247,0.10) 0%, rgba(79,211,247,0.02) 60%), ${T.surface}`,
          border:`1px solid rgba(79,211,247,0.22)`, position:'relative', overflow:'hidden'
        }}>
          <div style={{ position:'absolute', right:-50, top:-50, width:240, height:240,
            background:`radial-gradient(circle, rgba(79,211,247,0.12), transparent 70%)`, pointerEvents:'none' }}/>
          <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:24, position:'relative' }}>
            <div>
              <Eyebrow color={T.primary}>НАЙБЛИЖЧИЙ РЕЙД</Eyebrow>
              <div style={{ fontSize:32, fontWeight:800, fontFamily:'JetBrains Mono', color:T.tertiary,
                marginTop:6, letterSpacing:'-0.02em', lineHeight:1 }}>2д 06г 08х</div>
              <div style={{ fontSize:12, color:T.textMid, marginTop:6 }}>понеділок · 27 квіт · 21:00 Київ</div>
              <div style={{ marginTop:14, display:'flex', gap:8 }}>
                <button style={{ padding:'8px 16px', borderRadius:8, background:T.tertiary,
                  border:'none', color:'#1a1a00', fontWeight:800, fontSize:12, letterSpacing:'0.06em', cursor:'pointer' }}>RSVP</button>
                <button style={{ padding:'8px 14px', borderRadius:8, background:'transparent',
                  border:`1px solid ${T.borderStrong}`, color:T.textMid, fontWeight:600, fontSize:12, cursor:'pointer' }}>
                  Деталі події
                </button>
              </div>
            </div>
            <div style={{ borderLeft:`1px solid ${T.border}`, paddingLeft:24 }}>
              <Eyebrow>СКЛАД · 18 / 24</Eyebrow>
              <div style={{ display:'grid', gap:10, marginTop:10 }}>
                <ShellCompRow l="Танки" have={2} need={2} color={T.tank}/>
                <ShellCompRow l="Хіли" have={4} need={5} color={T.heal}/>
                <ShellCompRow l="ДД" have={12} need={17} color={T.dps}/>
              </div>
              <div style={{ display:'flex', gap:12, marginTop:12, fontSize:10, color:T.textLow, fontFamily:'JetBrains Mono' }}>
                <span style={{ color:T.success }}>● 18 GO</span>
                <span style={{ color:T.tertiary }}>● 2 ?</span>
                <span>● 1 NO</span>
                <span>● 3 BENCH</span>
              </div>
            </div>
          </div>
        </div>

        {/* ME card */}
        <div style={{ padding:'18px 20px', borderRadius:14, background:T.surface,
          border:`1px solid ${T.border}`, position:'relative', overflow:'hidden' }}>
          <div style={{ display:'flex', alignItems:'center', gap:12, marginBottom:12 }}>
            <div style={{ width:42, height:42, borderRadius:8,
              background:`linear-gradient(135deg, ${T.tertiary}66, ${T.tertiary}22)`,
              border:`1px solid ${T.tertiary}55`, display:'grid', placeItems:'center',
              fontWeight:800, fontSize:18, color:T.tertiary }}>З</div>
            <div>
              <Eyebrow color={T.tertiary}>ВИ · ZELION</Eyebrow>
              <div style={{ fontSize:14, fontWeight:800, marginTop:2 }}>Holy Paladin</div>
            </div>
          </div>
          <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:8 }}>
            <ShellTile l="ITEM LEVEL" v="639" c={T.tertiary}/>
            <ShellTile l="M+ SCORE"   v="2847" c={T.primary}/>
            <ShellTile l="VAULT"      v="3 / 9" c={T.success}/>
            <ShellTile l="BEST KEY"   v="+14"  c={T.secondary}/>
          </div>
          <div style={{ marginTop:10, padding:'8px 10px', borderRadius:6,
            background:'rgba(57,255,20,0.08)', border:'1px solid rgba(57,255,20,0.2)',
            fontSize:10, color:T.success, fontWeight:700 }}>
            ● ГОТОВНІСТЬ 92% — бракує 1 enchant
          </div>
        </div>
      </div>

      {/* Row 2: Progression strip */}
      <div style={{
        padding:'16px 20px', borderRadius:14, background:T.surface,
        border:`1px solid ${T.border}`, marginBottom:14
      }}>
        <div style={{ display:'flex', alignItems:'baseline', justifyContent:'space-between', marginBottom:12 }}>
          <Eyebrow>LIBERATION OF UNDERMINE · ПРОГРЕСІЯ</Eyebrow>
          <div style={{ display:'flex', gap:14, fontSize:10, fontFamily:'JetBrains Mono', color:T.textLow }}>
            <span><b style={{ color:T.success }}>9/9</b> N</span>
            <span><b style={{ color:T.tank }}>7/9</b> H</span>
            <span><b style={{ color:'#a855f7' }}>2/9</b> M</span>
          </div>
        </div>
        <div style={{ display:'grid', gridTemplateColumns:'repeat(9, 1fr)', gap:6 }}>
          {RAID_DATA.tiers.flatMap(t=>t.bosses).map((b,i)=>(
            <ShellBossPip key={i} {...b} active={b.n==='Fallen-King Salhadaar'}/>
          ))}
        </div>
      </div>

      {/* Row 3: Logistics — full width, swappable */}
      <div style={{
        padding:'18px 22px', borderRadius:14, background:T.surface, border:`1px solid ${T.border}`,
        position:'relative', overflow:'hidden', minHeight:206
      }}>
        <div style={{ display:'flex', justifyContent:'space-between', alignItems:'baseline', marginBottom:14 }}>
          <Eyebrow color={accent}>ЛОГІСТИКА · {logisticsTitle}</Eyebrow>
          {logisticsHint && <span style={{ fontSize:10, color:T.textLow, fontFamily:'JetBrains Mono' }}>{logisticsHint}</span>}
        </div>
        {logisticsRender()}
      </div>

      <SyncModal open={syncOpen} onClose={()=>setSyncOpen(false)}/>
    </div>
  );
}

function ShellCompRow({ l, have, need, color }) {
  const T = window.T;
  const pct = Math.min((have/need)*100, 100);
  return (
    <div>
      <div style={{ display:'flex', justifyContent:'space-between', marginBottom:4 }}>
        <span style={{ fontSize:11, color:T.textMid, fontWeight:600 }}>{l}</span>
        <span style={{ fontSize:11, fontFamily:'JetBrains Mono', color, fontWeight:700 }}>{have} / {need}</span>
      </div>
      <div style={{ height:6, background:'rgba(255,255,255,0.06)', borderRadius:3, overflow:'hidden' }}>
        <div style={{ width:pct+'%', height:'100%',
          background:`linear-gradient(90deg, ${color}88, ${color})`, boxShadow:`0 0 6px ${color}aa` }}/>
      </div>
    </div>
  );
}

function ShellTile({ l, v, c }) {
  const T = window.T;
  return (
    <div style={{ padding:'10px 12px', borderRadius:8, background:T.surfaceLow, border:`1px solid ${T.border}` }}>
      <div style={{ fontSize:9, color:T.textLow, letterSpacing:'0.12em', fontWeight:700 }}>{l}</div>
      <div style={{ fontSize:18, fontWeight:800, fontFamily:'JetBrains Mono', color:c, marginTop:2 }}>{v}</div>
    </div>
  );
}

function ShellBossPip({ n, k, tries, active }) {
  const T = window.T;
  const tone = k==='M'?'#a855f7':k==='H'?T.tank:k==='N'?T.success:T.textLow;
  const isKill = k==='M'||k==='H'||k==='N';
  return (
    <div style={{
      padding:'10px', borderRadius:8,
      background: active?'linear-gradient(180deg, rgba(168,85,247,0.18), rgba(168,85,247,0.04))':
        isKill?`${tone}10`:'rgba(255,255,255,0.02)',
      border:`1px solid ${active?'rgba(168,85,247,0.55)':isKill?tone+'33':T.border}`,
      position:'relative', overflow:'hidden', minHeight:62
    }}>
      {active && <div style={{ position:'absolute', top:6, right:6, width:6, height:6, borderRadius:'50%',
        background:'#a855f7', boxShadow:'0 0 0 3px rgba(168,85,247,0.25)', animation:'pulse 1.6s infinite' }}/>}
      <div style={{ fontSize:10, color:isKill||active?T.textHi:T.textLow, fontWeight:600, lineHeight:1.2,
        height:24, overflow:'hidden' }}>{n}</div>
      <div style={{ display:'flex', alignItems:'baseline', justifyContent:'space-between', marginTop:6 }}>
        <span style={{ fontSize:13, fontWeight:800, color:tone, fontFamily:'JetBrains Mono' }}>{k}</span>
        {tries>0 && <span style={{ fontSize:9, color:T.textLow, fontFamily:'JetBrains Mono' }}>{tries} спроб</span>}
      </div>
    </div>
  );
}

window.DashboardShell = DashboardShell;
