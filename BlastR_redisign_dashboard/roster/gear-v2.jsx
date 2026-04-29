// V2 — Compact Heatmap. Маленькі кольорові клітинки замість великих ItemCell.
// Усі 30 чарів видно одразу. Колір = трек, прогрес-бар знизу = upgrade level,
// крапка зверху = проблема (червона) або апгрейд доступний (жовта).
// Ширші колонки Аудит / Проблеми / Апгрейди з міні-візуалізаціями.

function GearV2({ onTabChange }) {
  const T = window.T;
  const [filter, setFilter] = React.useState('all');
  const [auditOpen, setAuditOpen] = React.useState(null);
  const [hoverChar, setHoverChar] = React.useState(null);

  const chars = window.ROSTER_CHARS;
  const counts = {
    all: chars.filter(c=>!c.parentOf).length,
    tank: chars.filter(c=>c.role==='tank' && !c.parentOf).length,
    heal: chars.filter(c=>c.role==='heal' && !c.parentOf).length,
    dps:  chars.filter(c=>c.role==='dps'  && !c.parentOf).length,
    main: chars.filter(c=>c.status==='main' && !c.parentOf).length,
    bench:chars.filter(c=>c.status==='bench' && !c.parentOf).length,
  };

  const filtered = chars.filter(c => {
    if (c.parentOf) return false;
    if (filter==='all') return true;
    if (['tank','heal','dps'].includes(filter)) return c.role===filter;
    if (filter==='main')  return c.status==='main';
    if (filter==='bench') return c.status==='bench';
    return true;
  });

  const grouped = { tank:[], heal:[], dps:[] };
  filtered.forEach(c => grouped[c.role].push(c));

  return (
    <div style={{ width:1280, height:900, color:T.textHi, fontFamily:'Inter', background:'#0e0e10', position:'relative', overflow:'hidden' }}>
      <PageChrome mainCount={counts.main} totalCount={counts.all}/>
      <div style={{ padding:'14px 16px 0', display:'flex', alignItems:'center', justifyContent:'space-between', gap:12 }}>
        <RosterTabs active="gear" onChange={onTabChange}/>
      </div>
      <div style={{ padding:'10px 16px', display:'flex', alignItems:'center', justifyContent:'space-between', gap:12, borderBottom:`1px solid ${T.border}` }}>
        <FilterPills value={filter} onChange={setFilter} counts={counts}/>
        <window.TrackLegend/>
      </div>

      <div style={{ padding:'0 16px', overflow:'auto', maxHeight:760 }}>
        <table style={{ borderCollapse:'separate', borderSpacing:0, fontSize:11, tableLayout:'fixed', width:'100%' }}>
          <colgroup>
            <col style={{ width:220 }}/>
            <col style={{ width:64 }}/>
            <col style={{ width:90 }}/>
            <col style={{ width:80 }}/>
            <col style={{ width:80 }}/>
            {window.GEAR_SLOTS.map(s => <col key={s.id} style={{ width:32 }}/>)}
          </colgroup>
          <thead>
            <tr>
              <th style={hG2(220, true)}>ПЕРСОНАЖ</th>
              <th style={hG2(64)}>ILVL</th>
              <th style={hG2(90)}>АУДИТ</th>
              <th style={hG2(80)}>ПРОБЛ.</th>
              <th style={hG2(80)}>АПГР.</th>
              {window.GEAR_SLOTS.map(s => (
                <th key={s.id} style={hG2(32)} title={s.l}>
                  <div style={{
                    writingMode:'vertical-rl', transform:'rotate(180deg)',
                    fontSize:8, letterSpacing:'0.06em', whiteSpace:'nowrap',
                    height:54, display:'flex', alignItems:'center', justifyContent:'center'
                  }}>{s.l.toUpperCase()}</div>
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {['tank','heal','dps'].map(role => (
              <React.Fragment key={role}>
                <GroupHeader role={role} count={grouped[role].length}/>
                {grouped[role].map(c => (
                  <RowG2 key={c.id} c={c}
                    onAudit={()=>setAuditOpen(c)}
                    hover={hoverChar===c.id}
                    onHover={()=>setHoverChar(c.id)}
                    onLeave={()=>setHoverChar(null)}/>
                ))}
              </React.Fragment>
            ))}
          </tbody>
        </table>
      </div>
      {auditOpen && <AuditModal char={auditOpen} onClose={()=>setAuditOpen(null)}/>}
    </div>
  );
}

const hG2 = (w, left=false) => ({
  position:'sticky', top:0, zIndex:2,
  padding:'8px 4px', fontSize:9, fontWeight:800, color:window.T.textLow,
  letterSpacing:'0.1em', textAlign: left?'left':'center', width:w, minWidth:w,
  background:window.T.surfaceLow,
  borderBottom:`1px solid ${window.T.border}`
});

function MiniBar({ value, max, color, w=60 }) {
  const pct = Math.min((value/max)*100, 100);
  return (
    <div style={{ display:'flex', alignItems:'center', gap:6 }}>
      <span style={{ fontSize:10, fontWeight:800, fontFamily:'JetBrains Mono', color, minWidth:14, textAlign:'right' }}>{value}</span>
      <div style={{ width:w, height:4, background:'rgba(255,255,255,0.06)', borderRadius:2, overflow:'hidden' }}>
        <div style={{ width:pct+'%', height:'100%', background:color, boxShadow:`0 0 4px ${color}66` }}/>
      </div>
    </div>
  );
}

function RowG2({ c, onAudit, hover, onHover, onLeave }) {
  const T = window.T;
  const cc = window.CLASS_COLORS[c.cls] || '#888';
  const g = window.GEAR_DATA[c.id];
  const ilvlColor = g.avgIlvl >= 285 ? '#FB923C' : g.avgIlvl >= 275 ? '#C084FC' : g.avgIlvl >= 265 ? '#60A5FA' : '#4ADE80';
  return (
    <tr onMouseEnter={onHover} onMouseLeave={onLeave} style={{
      borderBottom:`1px solid ${T.border}`, height:38,
      background: hover ? 'rgba(79,211,247,0.04)' : 'transparent'
    }}>
      <td style={{ padding:'4px 8px', borderBottom:`1px solid ${T.border}`, background:hover?'rgba(79,211,247,0.04)':T.bg, position:'sticky', left:0, zIndex:1 }}>
        <div style={{ display:'flex', alignItems:'center', gap:8 }}>
          <RosterAvatar char={c} size={26}/>
          <div style={{ minWidth:0 }}>
            <div style={{ display:'flex', alignItems:'center', gap:5 }}>
              <span style={{ fontSize:12, fontWeight:800, color:cc }}>{c.nick}</span>
              <ClassMark cls={c.cls} size={10}/>
            </div>
            <div style={{ fontSize:8.5, color:T.textLow, fontFamily:'JetBrains Mono', letterSpacing:'0.04em' }}>
              {c.spec.toUpperCase()} · тир {g.tierWorn}/{g.tierTotal}
            </div>
          </div>
        </div>
      </td>
      <td style={{ textAlign:'center', borderBottom:`1px solid ${T.border}` }}>
        <span style={{ fontSize:13, fontWeight:800, fontFamily:'JetBrains Mono', color: ilvlColor }}>
          {g.avgIlvl}
        </span>
      </td>
      <td style={{ padding:'4px 6px', borderBottom:`1px solid ${T.border}` }}>
        {g.audit.total > 0
          ? <button onClick={onAudit} style={{ all:'unset', cursor:'pointer', width:'100%' }}>
              <MiniBar value={g.audit.total} max={20} color={T.error}/>
            </button>
          : <span style={{ fontSize:9, color:T.success+'aa', fontWeight:800, letterSpacing:'0.08em' }}>
              <span className="ms" style={{ fontSize:12, verticalAlign:'-2px', marginRight:3 }}>check_circle</span>
              ОК
            </span>
        }
      </td>
      <td style={{ padding:'4px 6px', borderBottom:`1px solid ${T.border}` }}>
        {g.problemsCount > 0
          ? <MiniBar value={g.problemsCount} max={16} color={T.error} w={50}/>
          : <span style={{ fontSize:9, color:T.success+'aa', fontWeight:800 }}>—</span>
        }
      </td>
      <td style={{ padding:'4px 6px', borderBottom:`1px solid ${T.border}` }}>
        {g.missedUpgrades > 0
          ? <MiniBar value={g.missedUpgrades} max={16} color={T.tertiary} w={50}/>
          : <span style={{ fontSize:9, color:T.textLow }}>—</span>
        }
      </td>
      {window.GEAR_SLOTS.map(slot => (
        <td key={slot.id} style={{ padding:'2px 0', textAlign:'center', verticalAlign:'middle', borderBottom:`1px solid ${T.border}` }}>
          <div style={{ display:'inline-flex' }}>
            <window.GearDot slot={slot} item={g.slots[slot.id]} size={26}/>
          </div>
        </td>
      ))}
    </tr>
  );
}

window.GearV2 = GearV2;
