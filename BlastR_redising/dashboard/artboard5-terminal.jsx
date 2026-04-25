// ARTBOARD 5 — DATA TERMINAL
// Bloomberg-style. Info-dense. Sparklines, list-view, compact tables.
// For officer/raid-lead power users.

function ArtboardDataTerminal() {
  const [syncOpen, setSyncOpen] = React.useState(false);
  const T = window.T;

  return (
    <div style={{
      width:1280, height:900, color:T.textHi, fontFamily:'JetBrains Mono',
      background:'#0a0a0c', position:'relative', overflow:'hidden', padding:'18px 22px',
      fontSize:11
    }}>
      {/* Top status bar */}
      <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between',
        padding:'6px 10px', borderRadius:6, background:'#000',
        border:`1px solid ${T.border}`, marginBottom:12, fontSize:10, color:T.textLow,
        letterSpacing:'0.06em' }}>
        <div style={{ display:'flex', gap:18 }}>
          <span style={{ color:T.success }}>● LIVE</span>
          <span>STATIC <span style={{ color:T.textHi }}>ХАРАКТЕРНИКИ</span></span>
          <span>TIER <span style={{ color:'#a855f7' }}>S2 LOU</span></span>
          <span>UPDATE <span style={{ color:T.textHi }}>56m AGO</span></span>
        </div>
        <div style={{ display:'flex', gap:6 }}>
          <SyncBadge name="BLZ" mins={56} pct={5} color="#9a9a9a" onClick={()=>setSyncOpen(true)}/>
          <SyncBadge name="R.IO" mins={56} pct={75} color={T.primary} onClick={()=>setSyncOpen(true)}/>
          <SyncBadge name="W.LOGS" mins={56} pct={90} color={T.primary} onClick={()=>setSyncOpen(true)}/>
        </div>
      </div>

      {/* MAIN GRID 4-COL */}
      <div style={{ display:'grid', gridTemplateColumns:'1fr 1fr 1fr 1fr', gap:10 }}>
        {/* CELL 1: KPI strip — full width */}
        <div style={{ gridColumn:'1 / -1', display:'grid',
          gridTemplateColumns:'repeat(6, 1fr)', gap:8, marginBottom:2 }}>
          <KPI l="NEXT RAID"   v="2D 06:08" c={T.tertiary} sub="MON 21:00" delta="↑"/>
          <KPI l="SIGNED"      v="18 / 24"  c={T.success} sub="75% READY" delta="↑3"/>
          <KPI l="MYTHIC"      v="2 / 9"    c="#a855f7" sub="22% PROG" delta="↑1w"/>
          <KPI l="HEROIC"      v="7 / 9"    c={T.tank} sub="78% PROG" delta="="/>
          <KPI l="GUILD BANK"  v="140K G"   c={T.tertiary} sub="↑12K /w" delta="↑"/>
          <KPI l="ACTIVITY"    v="86%"      c={T.primary} sub="14d AVG" delta="↑4"/>
        </div>

        {/* CELL 2: BOSS TABLE — col 1-2 row */}
        <div style={{ gridColumn:'1 / 3' }}>
          <Section title="BOSS PROGRESSION" right="9N · 7H · 2M / 9">
            <table style={{ width:'100%', borderCollapse:'collapse', fontSize:10.5 }}>
              <thead>
                <tr style={{ color:T.textLow, fontWeight:600 }}>
                  <th style={Th()}>#</th>
                  <th style={Th()}>BOSS</th>
                  <th style={Th()}>N</th>
                  <th style={Th()}>H</th>
                  <th style={Th()}>M</th>
                  <th style={Th(true)}>TRIES</th>
                  <th style={Th(true)}>BEST</th>
                </tr>
              </thead>
              <tbody>
                {RAID_DATA.tiers.flatMap(t=>t.bosses).map((b,i)=>{
                  const active = b.n==='Fallen-King Salhadaar';
                  const cellN = b.k==='N'||b.k==='H'||b.k==='M';
                  const cellH = b.k==='H'||b.k==='M';
                  const cellM = b.k==='M';
                  return (
                    <tr key={i} style={{
                      background:active?'rgba(168,85,247,0.10)':'transparent',
                      borderLeft:active?'2px solid #a855f7':'2px solid transparent'
                    }}>
                      <td style={Td()}>{String(i+1).padStart(2,'0')}</td>
                      <td style={{ ...Td(), color:active?T.textHi:T.textMid, fontWeight:active?700:400, fontFamily:'Inter' }}>{b.n}</td>
                      <td style={Td()}><Mark on={cellN} c={T.success}/></td>
                      <td style={Td()}><Mark on={cellH} c={T.tank}/></td>
                      <td style={Td()}><Mark on={cellM} c="#a855f7"/></td>
                      <td style={{ ...Td(true), color:b.tries>0?T.textHi:T.textLow }}>{b.tries||'—'}</td>
                      <td style={{ ...Td(true), color:active?T.error:T.textLow, fontWeight:active?700:400 }}>
                        {active?'14%':'—'}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </Section>
        </div>

        {/* CELL 3: ROSTER COMPOSITION — col 3 */}
        <div>
          <Section title="ROSTER" right="18 / 24 SIGNED">
            <RoleBlock label="TANKS" have={2} need={2} color={T.tank} chars={['DZ','HG']}/>
            <RoleBlock label="HEALS" have={4} need={5} color={T.heal} chars={['ZL','MK','VR','AS']} miss={1}/>
            <RoleBlock label="DPS"   have={12} need={17} color={T.dps}
              chars={['AL','BT','CY','DM','EV','FR','GB','HW','IC','JK','LP','NM']} miss={5}/>
            <div style={{ marginTop:10, paddingTop:10, borderTop:`1px dashed ${T.border}`,
              display:'grid', gridTemplateColumns:'repeat(2,1fr)', gap:6, fontSize:10 }}>
              <Mini l="GO"        v={18} c={T.success}/>
              <Mini l="TENTATIVE" v={2}  c={T.tertiary}/>
              <Mini l="ABSENT"    v={1}  c={T.error}/>
              <Mini l="BENCH"     v={3}  c={T.textMid}/>
            </div>
          </Section>
        </div>

        {/* CELL 4: ME — col 4 */}
        <div>
          <Section title="OPERATIVE: ZELION" right="LV 80">
            <div style={{ fontSize:11, color:T.tertiary, fontWeight:700, marginBottom:8, fontFamily:'Inter' }}>
              Holy Paladin · Ravencrest
            </div>
            <Sparkline data={[610,612,615,620,624,628,632,635,639]} color={T.tertiary} label="ILVL ↑29" cur="639"/>
            <Sparkline data={[2400,2480,2520,2580,2620,2700,2750,2810,2847]} color={T.primary} label="M+ SCORE ↑447" cur="2847"/>
            <Sparkline data={[60,68,72,78,82,85,88,90,92]} color={T.success} label="READINESS ↑32%" cur="92%"/>
            <div style={{ marginTop:10, padding:'6px 8px', borderRadius:4, background:T.surfaceLow,
              border:`1px solid ${T.border}`, display:'grid', gridTemplateColumns:'1fr 1fr', gap:6, fontSize:10 }}>
              <Mini l="VAULT"    v="3/9"  c={T.success}/>
              <Mini l="BEST KEY" v="+14"  c={T.secondary}/>
              <Mini l="WEEK Q"   v="3/8"  c={T.primary}/>
              <Mini l="GEAR ENH" v="7/8"  c={T.error}/>
            </div>
          </Section>
        </div>

        {/* CELL 5: CURRENT TARGET — col 1 */}
        <div>
          <Section title="CURRENT TARGET" right="MYTHIC">
            <div style={{ fontFamily:'Inter', fontSize:14, fontWeight:800, color:T.textHi, lineHeight:1.15 }}>
              Fallen-King Salhadaar
            </div>
            <div style={{ fontSize:10, color:T.textLow, marginTop:3 }}>encounter 3 / 9</div>
            <div style={{ marginTop:10, height:4, background:'rgba(255,255,255,0.05)', borderRadius:2, overflow:'hidden' }}>
              <div style={{ width:'14%', height:'100%', background:T.error, boxShadow:`0 0 6px ${T.error}` }}/>
            </div>
            <div style={{ fontSize:10, color:T.error, marginTop:4 }}>BEST: 14% · Phase 3</div>
            <div style={{ marginTop:12, display:'grid', gridTemplateColumns:'1fr 1fr 1fr', gap:6, fontSize:10 }}>
              <Mini l="TRIES"     v={21} c={T.textHi}/>
              <Mini l="STREAK"    v="3n" c={T.tertiary}/>
              <Mini l="ELAPSED"   v="6h" c={T.primary}/>
            </div>
          </Section>
        </div>

        {/* CELL 6: ACHIEVEMENTS — col 2 */}
        <div>
          <Section title="STATIC ACHIEVEMENTS" right="ALL TIME">
            {ACHIEVEMENTS.map((a,i)=>(
              <div key={i} style={{ display:'flex', alignItems:'center', gap:8, padding:'4px 0',
                borderBottom: i<ACHIEVEMENTS.length-1 ? `1px dashed ${T.border}` : 'none' }}>
                <span style={{ fontSize:9, color:a.color, fontWeight:800, width:36 }}>[{a.tier}]</span>
                <span style={{ flex:1, fontSize:10, color:T.textHi, fontFamily:'Inter', whiteSpace:'nowrap', overflow:'hidden', textOverflow:'ellipsis' }}>{a.name}</span>
                <span style={{ fontSize:9, color:T.textLow }}>{a.date.replace(' 2024','')}</span>
              </div>
            ))}
          </Section>
        </div>

        {/* CELL 7: LOGISTICS — col 3 */}
        <div>
          <Section title="SUPPLY" right="WEEK CYCLE">
            {LOGISTICS.map((l,i)=>(
              <div key={i} style={{ marginBottom:i<LOGISTICS.length-1?8:0 }}>
                <div style={{ display:'flex', justifyContent:'space-between', fontSize:10, marginBottom:3 }}>
                  <span style={{ color:T.textMid, fontFamily:'Inter' }}>{l.l.toUpperCase()}</span>
                  <span style={{ color:l.color, fontWeight:700 }}>{l.v}{l.u}</span>
                </div>
                <div style={{ height:3, background:'rgba(255,255,255,0.05)', borderRadius:2, overflow:'hidden' }}>
                  <div style={{ width:l.pct+'%', height:'100%', background:l.color, boxShadow:`0 0 4px ${l.color}` }}/>
                </div>
                <div style={{ fontSize:9, color:T.textLow, marginTop:2 }}>
                  {l.pct}% &middot; {l.pct>70?'OK':l.pct>40?'WARN':'LOW'}
                </div>
              </div>
            ))}
          </Section>
        </div>

        {/* CELL 8: ACTIVITY HEATMAP — col 4 */}
        <div>
          <Section title="ACTIVITY · 4 WEEKS" right="14 / 16 NIGHTS">
            <Heatmap/>
            <div style={{ display:'flex', gap:10, marginTop:8, fontSize:9, color:T.textLow }}>
              <span>● 0</span><span>● 25%</span><span>● 50%</span><span>● 75%</span><span>● 100%</span>
            </div>
            <div style={{ marginTop:10, fontSize:10, color:T.textMid, lineHeight:1.4 }}>
              <span style={{ color:T.success }}>↑ 86%</span> attendance · <span style={{ color:T.error }}>2 cancels</span>
            </div>
          </Section>
        </div>

      </div>

      <SyncModal open={syncOpen} onClose={()=>setSyncOpen(false)}/>
    </div>
  );
}

function Section({ title, right, children }) {
  const T = window.T;
  return (
    <div style={{ background:'#000', border:`1px solid ${T.border}`, borderRadius:6, padding:10, height:'100%' }}>
      <div style={{ display:'flex', justifyContent:'space-between', alignItems:'baseline',
        marginBottom:8, paddingBottom:6, borderBottom:`1px solid ${T.border}` }}>
        <span style={{ fontSize:9.5, color:T.primary, letterSpacing:'0.16em', fontWeight:700 }}>{title}</span>
        <span style={{ fontSize:9, color:T.textLow }}>{right}</span>
      </div>
      {children}
    </div>
  );
}

function KPI({ l, v, c, sub, delta }) {
  const T = window.T;
  return (
    <div style={{ background:'#000', border:`1px solid ${T.border}`, borderRadius:6, padding:'10px 12px' }}>
      <div style={{ display:'flex', justifyContent:'space-between', alignItems:'baseline' }}>
        <span style={{ fontSize:9, color:T.textLow, letterSpacing:'0.12em' }}>{l}</span>
        <span style={{ fontSize:9, color:delta==='='?T.textLow:T.success }}>{delta}</span>
      </div>
      <div style={{ fontSize:18, fontWeight:800, color:c, marginTop:3, letterSpacing:'-0.02em' }}>{v}</div>
      <div style={{ fontSize:9, color:T.textLow, marginTop:1 }}>{sub}</div>
    </div>
  );
}

function Th(right) {
  return { textAlign: right?'right':'left', fontSize:9, color:'#767577', letterSpacing:'0.1em',
    padding:'4px 6px', borderBottom:'1px solid rgba(255,255,255,0.06)', fontWeight:700 };
}
function Td(right) {
  return { textAlign: right?'right':'left', fontSize:10, padding:'5px 6px',
    borderBottom:'1px solid rgba(255,255,255,0.04)' };
}

function Mark({ on, c }) {
  return on
    ? <span style={{ display:'inline-block', width:10, height:10, borderRadius:2, background:c, boxShadow:`0 0 4px ${c}` }}/>
    : <span style={{ display:'inline-block', width:10, height:10, borderRadius:2, background:'rgba(255,255,255,0.05)' }}/>;
}

function RoleBlock({ label, have, need, color, chars, miss }) {
  const T = window.T;
  return (
    <div style={{ marginBottom:10 }}>
      <div style={{ display:'flex', justifyContent:'space-between', marginBottom:4 }}>
        <span style={{ fontSize:9.5, color, letterSpacing:'0.1em', fontWeight:700 }}>{label}</span>
        <span style={{ fontSize:10, color:T.textHi, fontWeight:700 }}>{have}/{need}</span>
      </div>
      <div style={{ display:'flex', gap:2, flexWrap:'wrap' }}>
        {chars.map((c,i)=>(
          <span key={i} style={{ fontSize:8, fontWeight:700, color, padding:'2px 4px',
            background:`${color}18`, border:`1px solid ${color}55`, borderRadius:2 }}>{c}</span>
        ))}
        {Array.from({length:miss||0}).map((_,i)=>(
          <span key={'m'+i} style={{ fontSize:8, color:T.textLow, padding:'2px 4px',
            background:'rgba(255,255,255,0.02)', border:`1px dashed ${T.border}`, borderRadius:2 }}>—</span>
        ))}
      </div>
    </div>
  );
}

function Mini({ l, v, c }) {
  const T = window.T;
  return (
    <div>
      <div style={{ fontSize:8, color:T.textLow, letterSpacing:'0.1em' }}>{l}</div>
      <div style={{ fontSize:11, color:c, fontWeight:700 }}>{v}</div>
    </div>
  );
}

function Sparkline({ data, color, label, cur }) {
  const T = window.T;
  const w = 240, h = 24;
  const min = Math.min(...data), max = Math.max(...data);
  const pts = data.map((d,i)=>{
    const x = (i/(data.length-1))*w;
    const y = h - ((d-min)/(max-min||1))*h;
    return `${x},${y}`;
  }).join(' ');
  return (
    <div style={{ marginBottom:8 }}>
      <div style={{ display:'flex', justifyContent:'space-between', fontSize:9, marginBottom:2 }}>
        <span style={{ color:T.textLow, letterSpacing:'0.08em' }}>{label}</span>
        <span style={{ color, fontWeight:700 }}>{cur}</span>
      </div>
      <svg width="100%" height={h} viewBox={`0 0 ${w} ${h}`} preserveAspectRatio="none">
        <polyline points={pts} fill="none" stroke={color} strokeWidth="1.5"
          style={{ filter:`drop-shadow(0 0 2px ${color})` }}/>
      </svg>
    </div>
  );
}

function Heatmap() {
  const T = window.T;
  // 4 weeks × 7 days
  const data = [
    [0,0,0.5,0,1,0.75,0],   // wk1
    [0,0,1,0,0.75,1,0.25],  // wk2
    [0,0,1,0,1,0.5,0],      // wk3
    [0,0,0.75,0,1,1,0],     // wk4
  ];
  const days = ['M','T','W','T','F','S','S'];
  return (
    <div>
      <div style={{ display:'grid', gridTemplateColumns:'auto repeat(7, 1fr)', gap:3, fontSize:8 }}>
        <span/>
        {days.map((d,i)=>(<span key={i} style={{ color:T.textLow, textAlign:'center' }}>{d}</span>))}
        {data.map((week, wi)=>(
          <React.Fragment key={wi}>
            <span style={{ color:T.textLow, fontSize:8 }}>W{wi+1}</span>
            {week.map((v,di)=>{
              const c = T.primary;
              return <div key={di} style={{ aspectRatio:'1', borderRadius:2,
                background: v===0 ? 'rgba(255,255,255,0.04)' : c,
                opacity: v===0 ? 1 : 0.25 + v*0.75,
                boxShadow: v>0 ? `0 0 4px ${c}88` : 'none'
              }}/>;
            })}
          </React.Fragment>
        ))}
      </div>
    </div>
  );
}

window.ArtboardDataTerminal = ArtboardDataTerminal;
