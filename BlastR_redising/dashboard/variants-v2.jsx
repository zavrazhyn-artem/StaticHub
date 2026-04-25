// 5 NEW DASHBOARD VARIANTS — same shell, different logistics treatments
// V1: Inventory Alerts · V2: Supply Lines · V3: Pantry Hero · V4: Burn-Down · V5: Quartermaster Radar

const T2 = () => window.T;

// Logistics dataset — richer than before for these variants
const SUPPLY = [
  { i:'savings',          l:'Банк гільдії', v:140000, u:'G',     pct:78, color:'#fcf266', need:180000, delta:'+12K /тижд', status:'ok' },
  { i:'soup_kitchen',     l:'Котли',        v:3,      u:'/тижд', pct:45, color:'#fa7902', need:6,      delta:'-3 нестача',   status:'low' },
  { i:'local_fire_department', l:'Флакони', v:48,     u:'/тижд', pct:80, color:'#ff5063', need:60,     delta:'-12',          status:'warn' },
  { i:'restaurant',       l:'Їжа',          v:9,      u:'/тижд', pct:95, color:'#39FF14', need:9,      delta:'готово',       status:'ok' },
  { i:'auto_fix_high',    l:'Зачарування',  v:18,     u:'/тижд', pct:60, color:'#4fd3f7', need:24,     delta:'-6',           status:'warn' },
  { i:'inventory_2',      l:'Гем-сокети',   v:22,     u:'/тижд', pct:88, color:'#a855f7', need:24,     delta:'-2',           status:'ok' },
];

// ─────────────────────────────────────────────────────────────
// V1 — INVENTORY ALERTS — список з пріоритетом low-stock зверху + actions
function ArtboardLogisticsAlerts() {
  const [syncOpen, setSyncOpen] = React.useState(false);
  return <DashboardShell
    syncOpen={syncOpen} setSyncOpen={setSyncOpen}
    logisticsTitle="ІНВЕНТАР · СТАТУСИ"
    logisticsHint="6 позицій · 2 потребують уваги"
    accent="#ff5063"
    logisticsRender={()=>{
      const T = T2();
      const sorted = [...SUPPLY].sort((a,b)=>{
        const order = { low:0, warn:1, ok:2 };
        return order[a.status]-order[b.status];
      });
      return (
        <div style={{ display:'grid', gridTemplateColumns:'repeat(3, 1fr)', gap:8 }}>
          {sorted.map((l,i)=>{
            const sColor = l.status==='low'?'#ff5063':l.status==='warn'?'#fa7902':'#39FF14';
            const sLabel = l.status==='low'?'НЕСТАЧА':l.status==='warn'?'УВАГА':'ОК';
            return (
              <div key={i} style={{
                padding:'12px 14px', borderRadius:10, background:T.surfaceLow,
                border:`1px solid ${l.status==='ok'?T.border:sColor+'55'}`,
                display:'flex', alignItems:'center', gap:12, position:'relative'
              }}>
                <div style={{ width:38, height:38, borderRadius:8,
                  background:`${l.color}18`, border:`1px solid ${l.color}55`,
                  display:'grid', placeItems:'center', flexShrink:0 }}>
                  <span className="ms" style={{ fontSize:20, color:l.color }}>{l.i}</span>
                </div>
                <div style={{ flex:1, minWidth:0 }}>
                  <div style={{ display:'flex', alignItems:'baseline', justifyContent:'space-between' }}>
                    <span style={{ fontSize:11, color:T.textMid, fontWeight:600 }}>{l.l}</span>
                    <span style={{ fontSize:8, fontWeight:800, color:sColor, letterSpacing:'0.08em' }}>● {sLabel}</span>
                  </div>
                  <div style={{ display:'flex', alignItems:'baseline', gap:4, marginTop:2 }}>
                    <span style={{ fontSize:16, fontWeight:800, color:T.textHi, fontFamily:'JetBrains Mono' }}>{l.v.toLocaleString()}</span>
                    <span style={{ fontSize:9, color:T.textLow }}>/ {l.need.toLocaleString()} {l.u}</span>
                  </div>
                  <div style={{ height:3, marginTop:5, background:'rgba(255,255,255,0.06)', borderRadius:2, overflow:'hidden' }}>
                    <div style={{ width:l.pct+'%', height:'100%', background:l.color }}/>
                  </div>
                  <div style={{ fontSize:9, color:l.status==='ok'?T.textLow:sColor, marginTop:4, fontFamily:'JetBrains Mono' }}>{l.delta}</div>
                </div>
                {l.status!=='ok' && (
                  <button style={{
                    position:'absolute', top:10, right:10,
                    padding:'4px 8px', borderRadius:6, background:sColor+'22',
                    border:`1px solid ${sColor}66`, color:sColor, fontSize:9, fontWeight:700,
                    cursor:'pointer', letterSpacing:'0.06em'
                  }}>{l.status==='low'?'ЗАМОВИТИ':'ПОПОВНИТИ'}</button>
                )}
              </div>
            );
          })}
        </div>
      );
    }}
  />;
}

// ─────────────────────────────────────────────────────────────
// V2 — SUPPLY LINES — flow chart: Бюджет → Виробництво → Готовність до рейду
function ArtboardLogisticsFlow() {
  const [syncOpen, setSyncOpen] = React.useState(false);
  return <DashboardShell
    syncOpen={syncOpen} setSyncOpen={setSyncOpen}
    logisticsTitle="ЛАНЦЮГ ПОСТАЧАННЯ"
    logisticsHint="бюджет → виробництво → рейд"
    accent="#4fd3f7"
    logisticsRender={()=>{
      const T = T2();
      const stages = [
        { label:'БЮДЖЕТ', items:[
          { i:'savings', n:'Банк', v:'140K G', sub:'+12K тижд', c:'#fcf266' },
        ]},
        { label:'ВИРОБНИЦТВО', items:[
          { i:'soup_kitchen', n:'Котли', v:'3 / 6', sub:'нестача 3', c:'#fa7902', warn:true },
          { i:'local_fire_department', n:'Флакони', v:'48 / 60', sub:'нестача 12', c:'#ff5063', warn:true },
          { i:'restaurant', n:'Їжа', v:'9 / 9', sub:'готово', c:'#39FF14' },
        ]},
        { label:'РЕЙД (ПН 21:00)', items:[
          { i:'inventory_2', n:'Готовність', v:'68%', sub:'критично', c:'#fa7902', warn:true },
        ]},
      ];
      return (
        <div style={{ display:'grid', gridTemplateColumns:'1fr auto 2fr auto 1fr', gap:14, alignItems:'stretch' }}>
          {stages.map((stage, si) => (
            <React.Fragment key={si}>
              {si>0 && (
                <div style={{ display:'grid', placeItems:'center' }}>
                  <span className="ms" style={{ fontSize:24, color:T.primary, opacity:0.6 }}>arrow_forward</span>
                </div>
              )}
              <div>
                <div style={{ fontSize:9, color:T.textLow, letterSpacing:'0.18em', fontWeight:700, marginBottom:8 }}>{stage.label}</div>
                <div style={{ display:'grid', gap:6 }}>
                  {stage.items.map((it,i)=>(
                    <div key={i} style={{
                      padding:'10px 12px', borderRadius:8,
                      background:T.surfaceLow,
                      border:`1px solid ${it.warn?it.c+'55':T.border}`,
                      display:'flex', alignItems:'center', gap:10
                    }}>
                      <span className="ms" style={{ fontSize:18, color:it.c }}>{it.i}</span>
                      <div style={{ flex:1, minWidth:0 }}>
                        <div style={{ fontSize:10, color:T.textMid, fontWeight:600 }}>{it.n}</div>
                        <div style={{ fontSize:14, fontWeight:800, color:T.textHi, fontFamily:'JetBrains Mono', lineHeight:1 }}>{it.v}</div>
                        <div style={{ fontSize:9, color:it.warn?it.c:T.textLow, marginTop:1 }}>{it.sub}</div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </React.Fragment>
          ))}
        </div>
      );
    }}
  />;
}

// ─────────────────────────────────────────────────────────────
// V3 — PANTRY HERO — велика картка Банк, малі типи запасів навколо
function ArtboardLogisticsPantry() {
  const [syncOpen, setSyncOpen] = React.useState(false);
  return <DashboardShell
    syncOpen={syncOpen} setSyncOpen={setSyncOpen}
    logisticsTitle="СКАРБНИЦЯ"
    logisticsHint="асиметричний огляд"
    accent="#fcf266"
    logisticsRender={()=>{
      const T = T2();
      const others = SUPPLY.slice(1);
      return (
        <div style={{ display:'grid', gridTemplateColumns:'1.2fr 2fr', gap:10 }}>
          {/* Hero — bank */}
          <div style={{
            padding:'20px 22px', borderRadius:10,
            background:`linear-gradient(135deg, rgba(252,242,102,0.16) 0%, rgba(252,242,102,0.03) 100%), ${T.surfaceLow}`,
            border:`1px solid rgba(252,242,102,0.30)`,
            position:'relative', overflow:'hidden'
          }}>
            <div style={{ position:'absolute', right:-30, top:-30, width:160, height:160,
              background:`radial-gradient(circle, rgba(252,242,102,0.20), transparent 70%)`, pointerEvents:'none' }}/>
            <div style={{ display:'flex', alignItems:'center', gap:8, marginBottom:6 }}>
              <span className="ms" style={{ fontSize:20, color:'#fcf266' }}>savings</span>
              <span style={{ fontSize:9.5, color:T.textLow, letterSpacing:'0.14em', fontWeight:700 }}>БАНК ГІЛЬДІЇ</span>
            </div>
            <div style={{ fontSize:36, fontWeight:800, color:'#fcf266', fontFamily:'JetBrains Mono', letterSpacing:'-0.02em', lineHeight:1 }}>
              140 000 <span style={{ fontSize:18, color:T.textMid }}>G</span>
            </div>
            <div style={{ fontSize:10, color:T.success, marginTop:6, fontWeight:600 }}>↑ +12 000 G цей тиждень</div>
            {/* week sparkline */}
            <svg width="100%" height="42" viewBox="0 0 220 42" preserveAspectRatio="none" style={{ marginTop:10 }}>
              <polyline points="0,32 30,28 60,30 90,22 120,18 150,14 180,10 220,8"
                fill="none" stroke="#fcf266" strokeWidth="2" style={{ filter:'drop-shadow(0 0 4px #fcf266aa)' }}/>
              <polyline points="0,32 30,28 60,30 90,22 120,18 150,14 180,10 220,8 220,42 0,42"
                fill="rgba(252,242,102,0.10)"/>
            </svg>
            <div style={{ display:'flex', justifyContent:'space-between', fontSize:9, color:T.textLow, fontFamily:'JetBrains Mono', marginTop:2 }}>
              <span>пн</span><span>вт</span><span>ср</span><span>чт</span><span>пт</span><span>сб</span><span>нд</span>
            </div>
          </div>

          {/* Mini cards */}
          <div style={{ display:'grid', gridTemplateColumns:'repeat(5, 1fr)', gap:6 }}>
            {others.map((l,i)=>(
              <div key={i} style={{
                padding:'12px 10px', borderRadius:8, background:T.surfaceLow,
                border:`1px solid ${T.border}`, textAlign:'center'
              }}>
                <span className="ms" style={{ fontSize:18, color:l.color }}>{l.i}</span>
                <div style={{ fontSize:9, color:T.textLow, marginTop:5, letterSpacing:'0.06em', fontWeight:600 }}>{l.l.toUpperCase()}</div>
                <div style={{ fontSize:18, fontWeight:800, color:T.textHi, fontFamily:'JetBrains Mono', marginTop:2, letterSpacing:'-0.02em' }}>{l.v}</div>
                <div style={{ fontSize:9, color:T.textLow, marginTop:1, fontFamily:'JetBrains Mono' }}>/ {l.need} {l.u}</div>
                <div style={{ height:3, marginTop:6, background:'rgba(255,255,255,0.06)', borderRadius:2, overflow:'hidden' }}>
                  <div style={{ width:l.pct+'%', height:'100%', background:l.color, boxShadow:`0 0 4px ${l.color}88` }}/>
                </div>
              </div>
            ))}
          </div>
        </div>
      );
    }}
  />;
}

// ─────────────────────────────────────────────────────────────
// V4 — BURN-DOWN — великий графік (запаси vs витрати), знизу легенда
function ArtboardLogisticsBurnDown() {
  const [syncOpen, setSyncOpen] = React.useState(false);
  return <DashboardShell
    syncOpen={syncOpen} setSyncOpen={setSyncOpen}
    logisticsTitle="ВИТРАТИ ТИЖНЯ"
    logisticsHint="прогноз до наступного рейду"
    accent="#a855f7"
    logisticsRender={()=>{
      const T = T2();
      // 7-day series. Stock decreases as raid burns through it.
      const days = ['ПН','ВТ','СР','ЧТ','ПТ','СБ','НД'];
      const series = [
        { name:'Котли', color:'#fa7902', data:[6,6,5,4,3,3,3], target:6 },
        { name:'Флакони', color:'#ff5063', data:[60,58,55,50,48,48,48], target:60 },
        { name:'Зачар.', color:'#4fd3f7', data:[24,24,22,20,18,18,18], target:24 },
      ];
      const W = 560, H = 130, padL = 30, padR = 8, padT = 8, padB = 18;
      return (
        <div style={{ display:'grid', gridTemplateColumns:'1fr 200px', gap:16 }}>
          {/* Chart */}
          <div style={{ background:T.surfaceLow, borderRadius:8, border:`1px solid ${T.border}`, padding:'10px 12px' }}>
            <svg width="100%" height={H} viewBox={`0 0 ${W} ${H}`} preserveAspectRatio="none">
              {/* gridlines */}
              {[0,0.25,0.5,0.75,1].map((p,i)=>(
                <line key={i} x1={padL} x2={W-padR} y1={padT+(H-padT-padB)*p} y2={padT+(H-padT-padB)*p}
                  stroke="rgba(255,255,255,0.05)" strokeDasharray="2 3"/>
              ))}
              {series.map((s, si) => {
                const max = s.target;
                const pts = s.data.map((v,i)=>{
                  const x = padL + (i/(s.data.length-1))*(W-padL-padR);
                  const y = padT + (1 - v/max)*(H-padT-padB);
                  return `${x},${y}`;
                }).join(' ');
                return (
                  <g key={si}>
                    <polyline points={pts} fill="none" stroke={s.color} strokeWidth="2"
                      style={{ filter:`drop-shadow(0 0 3px ${s.color}aa)` }}/>
                    {s.data.map((v,i)=>{
                      const x = padL + (i/(s.data.length-1))*(W-padL-padR);
                      const y = padT + (1 - v/s.target)*(H-padT-padB);
                      return <circle key={i} cx={x} cy={y} r="2.5" fill={s.color}/>;
                    })}
                  </g>
                );
              })}
              {/* x labels */}
              {days.map((d,i)=>{
                const x = padL + (i/(days.length-1))*(W-padL-padR);
                return <text key={i} x={x} y={H-4} textAnchor="middle" fill={T.textLow}
                  fontSize="9" fontFamily="JetBrains Mono">{d}</text>;
              })}
              {/* y labels */}
              <text x="6" y="14" fill={T.textLow} fontSize="9" fontFamily="JetBrains Mono">100%</text>
              <text x="6" y={padT+(H-padT-padB)*0.5+3} fill={T.textLow} fontSize="9" fontFamily="JetBrains Mono">50%</text>
              <text x="6" y={H-padB} fill={T.textLow} fontSize="9" fontFamily="JetBrains Mono">0%</text>

              {/* raid marker */}
              <line x1={padL} x2={W-padR} y1={padT+(H-padT-padB)*0.7} y2={padT+(H-padT-padB)*0.7}
                stroke="#fcf266" strokeWidth="1" strokeDasharray="4 4" opacity="0.5"/>
              <text x={W-padR-4} y={padT+(H-padT-padB)*0.7-3} fill="#fcf266" fontSize="8"
                fontFamily="JetBrains Mono" textAnchor="end">МІНІМУМ ДЛЯ РЕЙДУ</text>
            </svg>
          </div>

          {/* Legend & forecast */}
          <div>
            {series.map((s,i)=>(
              <div key={i} style={{
                padding:'8px 10px', borderRadius:6, background:T.surfaceLow,
                border:`1px solid ${T.border}`, marginBottom:6
              }}>
                <div style={{ display:'flex', alignItems:'center', gap:8 }}>
                  <span style={{ width:10, height:2, background:s.color, boxShadow:`0 0 4px ${s.color}` }}/>
                  <span style={{ fontSize:10, color:T.textMid, fontWeight:600, flex:1 }}>{s.name}</span>
                  <span style={{ fontSize:11, fontFamily:'JetBrains Mono', fontWeight:700, color:s.color }}>
                    {s.data[s.data.length-1]}/{s.target}
                  </span>
                </div>
                <div style={{ fontSize:9, color:T.textLow, marginTop:3, fontFamily:'JetBrains Mono' }}>
                  {s.data[s.data.length-1] >= s.target*0.7 ? '↑ ОК на рейд' : '↓ нестача до рейду'}
                </div>
              </div>
            ))}
          </div>
        </div>
      );
    }}
  />;
}

// ─────────────────────────────────────────────────────────────
// V5 — RADAR + ALERTS — компактна радар-діаграма + список тільки того що треба робити
function ArtboardLogisticsRadar() {
  const [syncOpen, setSyncOpen] = React.useState(false);
  return <DashboardShell
    syncOpen={syncOpen} setSyncOpen={setSyncOpen}
    logisticsTitle="ГОТОВНІСТЬ ПОСТАЧАННЯ"
    logisticsHint="радар покриття + тільки дії"
    accent="#39FF14"
    logisticsRender={()=>{
      const T = T2();
      // Radar: 6 axes of supply (банк, котли, флакони, їжа, зачар., геми)
      const axes = SUPPLY.map(s=>({ label:s.l, pct:s.pct, color:s.color }));
      const N = axes.length;
      const cx = 110, cy = 110, R = 90;
      // ring grid
      const rings = [0.25, 0.5, 0.75, 1.0];
      // poly points
      const polyPts = axes.map((a,i)=>{
        const angle = (i/N)*Math.PI*2 - Math.PI/2;
        const r = R * (a.pct/100);
        return `${cx + Math.cos(angle)*r},${cy + Math.sin(angle)*r}`;
      }).join(' ');
      const axisEnds = axes.map((a,i)=>{
        const angle = (i/N)*Math.PI*2 - Math.PI/2;
        return { x: cx+Math.cos(angle)*R, y: cy+Math.sin(angle)*R, lx: cx+Math.cos(angle)*(R+18), ly: cy+Math.sin(angle)*(R+18), label:a.label };
      });

      const actions = SUPPLY.filter(s => s.status !== 'ok');

      return (
        <div style={{ display:'grid', gridTemplateColumns:'240px 1fr', gap:18 }}>
          {/* Radar */}
          <div style={{ position:'relative', display:'grid', placeItems:'center' }}>
            <svg width="240" height="240" viewBox="0 0 240 240">
              {/* grid rings */}
              {rings.map((p,i)=>{
                const pts = axes.map((_,j)=>{
                  const angle = (j/N)*Math.PI*2 - Math.PI/2;
                  return `${cx + Math.cos(angle)*R*p},${cy + Math.sin(angle)*R*p}`;
                }).join(' ');
                return <polygon key={i} points={pts} fill="none" stroke="rgba(255,255,255,0.06)" strokeWidth="1"/>;
              })}
              {/* axes */}
              {axisEnds.map((a,i)=>(
                <line key={i} x1={cx} y1={cy} x2={a.x} y2={a.y} stroke="rgba(255,255,255,0.06)"/>
              ))}
              {/* coverage polygon */}
              <polygon points={polyPts} fill="rgba(57,255,20,0.18)" stroke="#39FF14" strokeWidth="2"
                style={{ filter:'drop-shadow(0 0 6px #39FF1488)' }}/>
              {/* dots */}
              {axes.map((a,i)=>{
                const angle = (i/N)*Math.PI*2 - Math.PI/2;
                const r = R*(a.pct/100);
                return <circle key={i} cx={cx+Math.cos(angle)*r} cy={cy+Math.sin(angle)*r} r="3" fill={a.color}/>;
              })}
              {/* axis labels */}
              {axisEnds.map((a,i)=>(
                <text key={i} x={a.lx} y={a.ly} fill={T.textMid} fontSize="9" fontWeight="700"
                  textAnchor="middle" dominantBaseline="middle" fontFamily="Inter">{a.label}</text>
              ))}
              {/* center label */}
              <text x={cx} y={cy-4} fill={T.textLow} fontSize="9" textAnchor="middle" fontFamily="JetBrains Mono">ГОТОВНІСТЬ</text>
              <text x={cx} y={cy+12} fill="#39FF14" fontSize="20" fontWeight="800" textAnchor="middle"
                fontFamily="JetBrains Mono">74%</text>
            </svg>
          </div>

          {/* Action list — only items needing attention */}
          <div>
            <div style={{ fontSize:10, color:T.textLow, letterSpacing:'0.14em', fontWeight:700, marginBottom:8 }}>
              ▸ ПОТРЕБУЄ ДІЇ · {actions.length}
            </div>
            <div style={{ display:'grid', gap:5 }}>
              {actions.map((a,i)=>{
                const sColor = a.status==='low'?'#ff5063':'#fa7902';
                return (
                  <div key={i} style={{
                    padding:'9px 12px', borderRadius:8, background:T.surfaceLow,
                    border:`1px solid ${sColor}40`, display:'flex', alignItems:'center', gap:12
                  }}>
                    <span className="ms" style={{ fontSize:16, color:a.color }}>{a.i}</span>
                    <div style={{ flex:1 }}>
                      <div style={{ display:'flex', alignItems:'baseline', gap:8 }}>
                        <span style={{ fontSize:11, color:T.textHi, fontWeight:700 }}>{a.l}</span>
                        <span style={{ fontSize:10, color:sColor, fontFamily:'JetBrains Mono', fontWeight:700 }}>
                          {a.v} / {a.need}
                        </span>
                      </div>
                      <div style={{ fontSize:9.5, color:T.textMid, marginTop:1 }}>{a.delta}</div>
                    </div>
                    <button style={{
                      padding:'5px 10px', borderRadius:6, background:sColor+'22',
                      border:`1px solid ${sColor}66`, color:sColor, fontSize:9.5, fontWeight:700,
                      letterSpacing:'0.06em', cursor:'pointer'
                    }}>{a.status==='low'?'ЗАМОВИТИ':'ПОПОВНИТИ'}</button>
                  </div>
                );
              })}
              <div style={{
                padding:'9px 12px', borderRadius:8,
                background:'rgba(57,255,20,0.05)', border:'1px dashed rgba(57,255,20,0.2)',
                display:'flex', alignItems:'center', gap:10, color:T.success, fontSize:10, fontWeight:600
              }}>
                <span className="ms" style={{ fontSize:14 }}>check_circle</span>
                {SUPPLY.length - actions.length} позиції в нормі — нічого робити
              </div>
            </div>
          </div>
        </div>
      );
    }}
  />;
}

window.ArtboardLogisticsAlerts = ArtboardLogisticsAlerts;
window.ArtboardLogisticsFlow = ArtboardLogisticsFlow;
window.ArtboardLogisticsPantry = ArtboardLogisticsPantry;
window.ArtboardLogisticsBurnDown = ArtboardLogisticsBurnDown;
window.ArtboardLogisticsRadar = ArtboardLogisticsRadar;
