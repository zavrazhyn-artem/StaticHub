// ARTBOARD 1 — OPERATIONS BRIEF
// Editorial / military briefing tone.
// Hero: "RAID NIGHT IN 2D 06H" with composition + roster readiness.
// Bosses progression as compact horizontal strip below hero.
// "Я" personal block + Achievements + Logistics cards.

function ArtboardOperationsBrief() {
  const [syncOpen, setSyncOpen] = React.useState(false);
  const T = window.T;

  return (
    <div style={{
      width:1280, height:900, background:`linear-gradient(180deg, #0e0e10 0%, #0a0a0c 100%)`,
      color:T.textHi, fontFamily:'Inter', position:'relative', overflow:'hidden',
      padding:'28px 32px'
    }}>
      {/* Topbar */}
      <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between', marginBottom:18 }}>
        <div>
          <Eyebrow>OPERATIONS BRIEF · ХАРАКТЕРНИКИ</Eyebrow>
          <div style={{ fontFamily:'JetBrains Mono', fontSize:11, color:T.textLow, marginTop:4, letterSpacing:'0.05em' }}>
            BRIEF #207 · ВТ 22:14 КИЇВ · OFFICER VIEW
          </div>
        </div>
        <div style={{ display:'flex', gap:8 }}>
          <SyncBadge name="BLIZZARD" mins={56} pct={5} color="#9a9a9a" onClick={()=>setSyncOpen(true)}/>
          <SyncBadge name="RAIDER.IO" mins={56} pct={75} color={T.primary} onClick={()=>setSyncOpen(true)}/>
          <SyncBadge name="W.LOGS" mins={56} pct={90} color={T.primary} onClick={()=>setSyncOpen(true)}/>
        </div>
      </div>

      {/* HERO — current boss progression card with roster readiness */}
      <div style={{
        position:'relative', borderRadius:16, padding:'24px 28px',
        background:`linear-gradient(135deg, rgba(168,85,247,0.16) 0%, rgba(79,211,247,0.06) 50%, transparent 100%), ${T.surface}`,
        border:`1px solid ${T.borderStrong}`,
        display:'grid', gridTemplateColumns:'1.4fr 1fr', gap:32, marginBottom:18,
        overflow:'hidden'
      }}>
        {/* Diagonal accent */}
        <div style={{ position:'absolute', right:-80, top:-80, width:300, height:300,
          background:'radial-gradient(circle, rgba(168,85,247,0.18) 0%, transparent 65%)', pointerEvents:'none' }}/>

        {/* Left: progress headline */}
        <div style={{ position:'relative' }}>
          <Eyebrow color="#a855f7">CURRENT TARGET · MYTHIC</Eyebrow>
          <div style={{ fontSize:42, fontWeight:800, lineHeight:1.05, marginTop:8, letterSpacing:'-0.02em' }}>
            Fallen-King<br/>Salhadaar
          </div>
          <div style={{ display:'flex', alignItems:'baseline', gap:24, marginTop:18 }}>
            <div>
              <div style={{ fontSize:9, color:T.textLow, letterSpacing:'0.14em', fontWeight:700 }}>СПРОБ</div>
              <div style={{ fontSize:26, fontWeight:800, fontFamily:'JetBrains Mono', color:T.textHi }}>21</div>
            </div>
            <div>
              <div style={{ fontSize:9, color:T.textLow, letterSpacing:'0.14em', fontWeight:700 }}>BEST PULL</div>
              <div style={{ fontSize:26, fontWeight:800, fontFamily:'JetBrains Mono', color:T.error }}>14%</div>
            </div>
            <div>
              <div style={{ fontSize:9, color:T.textLow, letterSpacing:'0.14em', fontWeight:700 }}>STREAK</div>
              <div style={{ fontSize:26, fontWeight:800, fontFamily:'JetBrains Mono', color:T.tertiary }}>3 ноч.</div>
            </div>
          </div>
          {/* progression bar */}
          <div style={{ marginTop:22 }}>
            <div style={{ display:'flex', justifyContent:'space-between', fontSize:10, color:T.textMid, marginBottom:6, letterSpacing:'0.06em', fontWeight:600 }}>
              <span>2 / 9 MYTHIC</span><span>22%</span>
            </div>
            <div style={{ height:6, borderRadius:3, background:'rgba(255,255,255,0.06)', overflow:'hidden' }}>
              <div style={{ width:'22%', height:'100%', background:`linear-gradient(90deg, #a855f7, #c084fc)`, boxShadow:'0 0 8px rgba(168,85,247,0.5)' }}/>
            </div>
            <div style={{ display:'flex', justifyContent:'space-between', fontSize:10, color:T.textLow, marginTop:10, fontFamily:'JetBrains Mono' }}>
              <span>7/9 H</span><span>9/9 N</span>
            </div>
          </div>
        </div>

        {/* Right: next raid + readiness */}
        <div style={{ position:'relative', borderLeft:`1px solid ${T.border}`, paddingLeft:28 }}>
          <Eyebrow color={T.primary}>NEXT RAID NIGHT IN</Eyebrow>
          <div style={{ fontSize:46, fontWeight:800, fontFamily:'JetBrains Mono', color:T.tertiary,
            lineHeight:1, marginTop:6, letterSpacing:'-0.02em',
            textShadow:'0 0 24px rgba(252,242,102,0.2)' }}>
            2д 06г 08х
          </div>
          <div style={{ fontSize:11, color:T.textMid, marginTop:6 }}>понеділок, 27 квіт · 21:00 Київ</div>

          {/* Roster readiness */}
          <div style={{ marginTop:18, padding:'14px 16px', borderRadius:10, background:T.surfaceLow,
            border:`1px solid ${T.border}` }}>
            <div style={{ display:'flex', justifyContent:'space-between', alignItems:'center', marginBottom:10 }}>
              <Eyebrow>SIGNED · 18 / 24</Eyebrow>
              <span style={{ fontSize:10, color:T.success, fontWeight:700 }}>● ГОТОВО ДО PULL</span>
            </div>
            <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr 1fr', gap:8 }}>
              <RoleSlot label="ТАНКИ" have={2} need={2} color={T.tank}/>
              <RoleSlot label="ХІЛИ" have={4} need={5} color={T.heal}/>
              <RoleSlot label="ДД" have={12} need={17} color={T.dps}/>
            </div>
            <div style={{ display:'flex', gap:14, marginTop:10, fontSize:10, color:T.textLow, fontFamily:'JetBrains Mono' }}>
              <span>● 18 GO</span>
              <span>● 2 TENTATIVE</span>
              <span>● 1 ABSENT</span>
              <span>● 3 BENCH</span>
            </div>
          </div>
        </div>
      </div>

      {/* Boss progression strip */}
      <div style={{ marginBottom:18 }}>
        <div style={{ display:'flex', alignItems:'baseline', justifyContent:'space-between', marginBottom:8 }}>
          <Eyebrow>LIBERATION OF UNDERMINE · PROGRESSION</Eyebrow>
          <div style={{ fontSize:10, color:T.textLow, fontFamily:'JetBrains Mono' }}>9/9 N · 7/9 H · 2/9 M</div>
        </div>
        <div style={{ display:'grid', gridTemplateColumns:'repeat(9, 1fr)', gap:6 }}>
          {RAID_DATA.tiers.flatMap(t=>t.bosses).map((b,i)=>(
            <BossPip key={i} name={b.n} kill={b.k} tries={b.tries} active={b.n==='Fallen-King Salhadaar'}/>
          ))}
        </div>
      </div>

      {/* Bottom 3-col: ME · ACHIEVEMENTS · LOGISTICS */}
      <div style={{ display:'grid', gridTemplateColumns:'1fr 1.2fr 1.2fr', gap:14 }}>
        {/* ME */}
        <div style={{ padding:'16px 18px', borderRadius:12, background:T.surface,
          border:`1px solid ${T.border}`, position:'relative', overflow:'hidden' }}>
          <Eyebrow color={T.primary}>Я · VIKTOR</Eyebrow>
          <div style={{ fontSize:16, fontWeight:800, marginTop:6 }}>Зеліон <span style={{ color:T.textLow, fontWeight:600, fontSize:13 }}>· Holy Paladin</span></div>
          <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:10, marginTop:12 }}>
            <Metric label="ITEM LEVEL" value="639" color={T.tertiary}/>
            <Metric label="M+ SCORE"   value="2847" color={T.primary}/>
            <Metric label="VAULT"      value="3 / 9" color={T.success}/>
            <Metric label="BEST KEY"   value="+14"  color={T.secondary}/>
          </div>
          <div style={{ marginTop:12, padding:'10px 12px', borderRadius:8,
            background:'rgba(57,255,20,0.08)', border:`1px solid rgba(57,255,20,0.20)` }}>
            <div style={{ fontSize:10, color:T.success, fontWeight:700, letterSpacing:'0.06em' }}>● ГОТОВНІСТЬ 92%</div>
            <div style={{ fontSize:10, color:T.textMid, marginTop:3 }}>Усе ок. Бракує: 1 enchant + flask cap</div>
          </div>
        </div>

        {/* ACHIEVEMENTS */}
        <div style={{ padding:'16px 18px', borderRadius:12, background:T.surface,
          border:`1px solid ${T.border}` }}>
          <div style={{ display:'flex', justifyContent:'space-between', alignItems:'center', marginBottom:12 }}>
            <Eyebrow>ДОСЯГНЕННЯ СТАТІКУ</Eyebrow>
            <span style={{ fontSize:10, color:T.textLow }}>4 за рік</span>
          </div>
          <div style={{ display:'grid', gap:8 }}>
            {ACHIEVEMENTS.map((a,i)=>(
              <div key={i} style={{ display:'flex', alignItems:'center', gap:12,
                padding:'8px 10px', borderRadius:8, background:T.surfaceLow,
                border:`1px solid ${T.border}` }}>
                <div style={{ width:34, height:34, borderRadius:6, display:'grid', placeItems:'center',
                  background:`${a.color}18`, border:`1px solid ${a.color}55`,
                  color:a.color, fontSize:9, fontWeight:800, letterSpacing:'0.06em' }}>{a.tier}</div>
                <div style={{ flex:1, minWidth:0 }}>
                  <div style={{ fontSize:12, fontWeight:700, color:T.textHi, whiteSpace:'nowrap', overflow:'hidden', textOverflow:'ellipsis' }}>{a.name}</div>
                  <div style={{ fontSize:10, color:T.textLow, fontFamily:'JetBrains Mono' }}>{a.date}</div>
                </div>
                <span className="ms" style={{ fontSize:18, color:a.color, opacity:.7 }}>military_tech</span>
              </div>
            ))}
          </div>
        </div>

        {/* LOGISTICS */}
        <div style={{ padding:'16px 18px', borderRadius:12, background:T.surface,
          border:`1px solid ${T.border}` }}>
          <div style={{ display:'flex', justifyContent:'space-between', alignItems:'center', marginBottom:12 }}>
            <Eyebrow>ЛОГІСТИКА</Eyebrow>
            <span style={{ fontSize:10, color:T.textLow }}>тиждень</span>
          </div>
          <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr', gap:8 }}>
            {LOGISTICS.map((l,i)=>(
              <div key={i} style={{
                padding:'10px 12px', borderRadius:8, background:T.surfaceLow,
                border:`1px solid ${T.border}`, position:'relative', overflow:'hidden'
              }}>
                <div style={{ display:'flex', alignItems:'center', gap:8, marginBottom:6 }}>
                  <span className="ms" style={{ fontSize:14, color:l.color }}>{l.i}</span>
                  <span style={{ fontSize:10, color:T.textMid, fontWeight:600 }}>{l.l}</span>
                </div>
                <div style={{ display:'flex', alignItems:'baseline', gap:4, marginBottom:6 }}>
                  <span style={{ fontSize:18, fontWeight:800, color:T.textHi, fontFamily:'JetBrains Mono' }}>{l.v}</span>
                  <span style={{ fontSize:9, color:T.textLow }}>{l.u}</span>
                </div>
                <div style={{ height:3, borderRadius:2, background:'rgba(255,255,255,0.06)', overflow:'hidden' }}>
                  <div style={{ width:l.pct+'%', height:'100%', background:l.color, boxShadow:`0 0 6px ${l.color}88` }}/>
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

function RoleSlot({ label, have, need, color }) {
  const T = window.T;
  const pct = (have/need)*100;
  const ok = have>=need;
  return (
    <div>
      <div style={{ display:'flex', justifyContent:'space-between', marginBottom:4 }}>
        <span style={{ fontSize:9, color:T.textLow, letterSpacing:'0.1em', fontWeight:700 }}>{label}</span>
        <span style={{ fontSize:10, fontFamily:'JetBrains Mono', fontWeight:700, color:ok?T.success:color }}>{have}/{need}</span>
      </div>
      <div style={{ height:4, borderRadius:2, background:'rgba(255,255,255,0.06)', overflow:'hidden' }}>
        <div style={{ width:Math.min(pct,100)+'%', height:'100%', background:color, boxShadow:`0 0 6px ${color}aa` }}/>
      </div>
    </div>
  );
}

function BossPip({ name, kill, tries, active }) {
  const T = window.T;
  const isKilled = kill==='M' || kill==='H' || kill==='N';
  const tone = kill==='M' ? '#a855f7' : kill==='H' ? T.tank : kill==='N' ? T.textLow : T.textLow;
  return (
    <div style={{
      padding:'10px 8px', borderRadius:8,
      background:active ? `linear-gradient(180deg, rgba(168,85,247,0.18), rgba(168,85,247,0.04))` :
        isKilled ? `${tone}10` : 'rgba(255,255,255,0.02)',
      border:`1px solid ${active ? '#a855f7aa' : isKilled ? tone+'33' : T.border}`,
      position:'relative', overflow:'hidden'
    }}>
      {active && <div style={{ position:'absolute', top:6, right:6, width:6, height:6, borderRadius:'50%',
        background:'#a855f7', boxShadow:'0 0 0 3px rgba(168,85,247,0.25)', animation:'pulse 1.6s infinite' }}/>}
      <div style={{ fontSize:9.5, color:isKilled||active?T.textHi:T.textLow, fontWeight:700, lineHeight:1.2,
        height:24, overflow:'hidden' }}>{name}</div>
      <div style={{ display:'flex', alignItems:'baseline', justifyContent:'space-between', marginTop:6 }}>
        <span style={{ fontSize:14, fontWeight:800, color:tone, fontFamily:'JetBrains Mono' }}>{kill}</span>
        {tries>0 && <span style={{ fontSize:9, color:T.textLow, fontFamily:'JetBrains Mono' }}>{tries}t</span>}
      </div>
    </div>
  );
}

function Metric({ label, value, color }) {
  const T = window.T;
  return (
    <div>
      <div style={{ fontSize:9, color:T.textLow, letterSpacing:'0.12em', fontWeight:700 }}>{label}</div>
      <div style={{ fontSize:18, fontWeight:800, fontFamily:'JetBrains Mono', color:color||T.textHi, marginTop:2 }}>{value}</div>
    </div>
  );
}

window.ArtboardOperationsBrief = ArtboardOperationsBrief;
