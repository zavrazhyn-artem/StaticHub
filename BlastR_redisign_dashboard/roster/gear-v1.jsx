// V1 — Annotated Grid. Близько до оригіналу: 16 слотів × 30 чарів,
// ItemCell з проблем-бейджами (енчант/гем/апгрейд/ілвл), колонки Аудит + Пропущені апгрейди.
// Перша колонка — точно як у roster-v3a (220px, avatar+nick+ClassMark+spec+BT).

function GearV1({ onTabChange }) {
  const T = window.T;
  const [filter, setFilter] = React.useState('all');
  const [auditOpen, setAuditOpen] = React.useState(null);

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
        <table style={{ borderCollapse:'separate', borderSpacing:0, fontSize:11, tableLayout:'fixed' }}>
          <colgroup>
            <col style={{ width:220 }}/>
            <col style={{ width:74 }}/>
            <col style={{ width:90 }}/>
            {window.GEAR_SLOTS.map(s => <col key={s.id} style={{ width:62 }}/>)}
          </colgroup>
          <thead>
            <tr>
              <th style={hG(220, true)}>ПЕРСОНАЖ</th>
              <th style={hG(74)}>АУДИТ</th>
              <th style={hG(90)} title="К-сть слотів які можна апгрейднути">ПРОПУЩЕНІ АПГРЕЙДИ</th>
              {window.GEAR_SLOTS.map(s => (
                <th key={s.id} style={hG(62)}>{s.l.toUpperCase()}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {['tank','heal','dps'].map(role => (
              <React.Fragment key={role}>
                <GroupHeader role={role} count={grouped[role].length}/>
                {grouped[role].map(c => (
                  <RowG1 key={c.id} c={c} onAudit={()=>setAuditOpen(c)}/>
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

const hG = (w, left=false) => ({
  position:'sticky', top:0, zIndex:2,
  padding:'10px 6px', fontSize:9, fontWeight:800, color:window.T.textLow,
  letterSpacing:'0.1em', textAlign: left?'left':'center', width:w, minWidth:w,
  background:window.T.surfaceLow,
  borderBottom:`1px solid ${window.T.border}`
});

function RowG1({ c, onAudit }) {
  const T = window.T;
  const cc = window.CLASS_COLORS[c.cls] || '#888';
  const g = window.GEAR_DATA[c.id];
  return (
    <tr style={{ borderBottom:`1px solid ${T.border}`, height:74 }}>
      {/* Char — same shape as roster-v3a first column */}
      <td style={{ padding:'6px 8px', borderBottom:`1px solid ${T.border}`, background:T.bg, position:'sticky', left:0, zIndex:1 }}>
        <div style={{ display:'flex', alignItems:'center', gap:10 }}>
          <RosterAvatar char={c} size={34}/>
          <div style={{ minWidth:0 }}>
            <div style={{ display:'flex', alignItems:'center', gap:6 }}>
              <span style={{ fontSize:14, fontWeight:800, color:cc, letterSpacing:'-0.01em' }}>{c.nick}</span>
              <span style={{ fontSize:9, color:T.textLow, fontWeight:700, letterSpacing:'0.08em' }}>{c.spec.slice(0,4).toUpperCase()}</span>
              <ClassMark cls={c.cls} size={12}/>
            </div>
            <div style={{ fontSize:9, color:T.textLow, fontFamily:'JetBrains Mono', letterSpacing:'0.04em', marginTop:1 }}>{c.bt}</div>
          </div>
        </div>
      </td>
      {/* Audit */}
      <td style={{ textAlign:'center', borderBottom:`1px solid ${T.border}` }}>
        <AuditChip count={g.audit.total} onClick={onAudit}/>
      </td>
      {/* Missed Upgrades */}
      <td style={{ textAlign:'center', borderBottom:`1px solid ${T.border}` }}>
        {g.missedUpgrades > 0 ? (
          <span style={{
            display:'inline-flex', alignItems:'center', gap:5,
            padding:'4px 10px', borderRadius:6, fontFamily:'JetBrains Mono',
            background:'rgba(252,242,102,0.08)', border:`1px solid ${T.tertiary}44`,
            color:T.tertiary, fontSize:12, fontWeight:800
          }}>
            <span className="ms" style={{ fontSize:13 }}>arrow_upward</span>
            {g.missedUpgrades}
          </span>
        ) : (
          <span style={{ fontSize:9, color:T.textLow, fontFamily:'JetBrains Mono' }}>—</span>
        )}
      </td>
      {/* Slots */}
      {window.GEAR_SLOTS.map(slot => (
        <td key={slot.id} style={{ padding:'8px 4px', textAlign:'center', verticalAlign:'middle', borderBottom:`1px solid ${T.border}` }}>
          <div style={{ display:'inline-flex', justifyContent:'center' }}>
            <window.ItemCell slot={slot} item={g.slots[slot.id]}/>
          </div>
        </td>
      ))}
    </tr>
  );
}

window.GearV1 = GearV1;
