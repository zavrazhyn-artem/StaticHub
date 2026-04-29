// V3 — Slot Insights. Перевернутий підхід.
// Зверху: топ-смуга з гільдською статистикою по слотах (де гільдія слабка).
// Знизу: компактна таблиця чарів (ItemCell в скороченому форматі), фокус на слотах-проблемах.

function GearV3({ onTabChange }) {
  const T = window.T;
  const [filter, setFilter] = React.useState('all');
  const [hoverSlot, setHoverSlot] = React.useState(null);
  const [auditOpen, setAuditOpen] = React.useState(null);

  const chars = window.ROSTER_CHARS.filter(c => !c.parentOf);
  const counts = {
    all: chars.length,
    tank: chars.filter(c=>c.role==='tank').length,
    heal: chars.filter(c=>c.role==='heal').length,
    dps:  chars.filter(c=>c.role==='dps').length,
    main: chars.filter(c=>c.status==='main').length,
    bench:chars.filter(c=>c.status==='bench').length,
  };

  const filtered = chars.filter(c => {
    if (filter==='all') return true;
    if (['tank','heal','dps'].includes(filter)) return c.role===filter;
    if (filter==='main')  return c.status==='main';
    if (filter==='bench') return c.status==='bench';
    return true;
  });

  // Per-slot guild stats
  const slotStats = window.GEAR_SLOTS.map(slot => {
    const items = filtered.map(c => window.GEAR_DATA[c.id].slots[slot.id]);
    const avgIlvl = Math.round(items.reduce((a,b)=>a+b.ilvl,0) / items.length);
    const lowCount = items.filter(i => i.lowIlvl).length;
    const enchMiss = items.filter(i => i.missingEnchant).length;
    const gemMiss = items.filter(i => i.missingGem).length;
    const upgAvail = items.filter(i => i.upgradeAvail).length;
    const totalProb = lowCount + enchMiss + gemMiss;
    return { slot, avgIlvl, lowCount, enchMiss, gemMiss, upgAvail, totalProb };
  });
  // Sort by problem severity (descending)
  const sortedSlots = [...slotStats].sort((a,b) => b.totalProb - a.totalProb);

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

      {/* Insight strip */}
      <div style={{ padding:'12px 16px 14px', borderBottom:`1px solid ${T.border}`, background:'rgba(20,20,24,0.35)' }}>
        <div style={{ display:'flex', alignItems:'baseline', justifyContent:'space-between', marginBottom:10 }}>
          <div style={{ display:'flex', alignItems:'center', gap:10 }}>
            <span className="ms" style={{ fontSize:16, color:T.primary }}>insights</span>
            <span style={{ fontSize:11, fontWeight:800, letterSpacing:'0.14em', color:T.textHi }}>
              СЛАБКІ СЛОТИ ГІЛЬДІЇ
            </span>
            <span style={{ fontSize:9, color:T.textLow, letterSpacing:'0.08em', fontWeight:700 }}>
              · СОРТУВАННЯ ПО К-СТІ ПРОБЛЕМ
            </span>
          </div>
          <div style={{ fontSize:10, color:T.textLow, fontFamily:'JetBrains Mono' }}>
            {filtered.length} ЧАРІВ
          </div>
        </div>
        <div style={{ display:'grid', gridTemplateColumns:'repeat(8, 1fr)', gap:8 }}>
          {sortedSlots.slice(0,8).map(s => {
            const sev = s.totalProb >= 6 ? T.error : s.totalProb >= 3 ? T.tertiary : T.success;
            return (
              <button key={s.slot.id} onMouseEnter={()=>setHoverSlot(s.slot.id)} onMouseLeave={()=>setHoverSlot(null)} style={{
                padding:'10px 10px 8px', borderRadius:8, textAlign:'left',
                background: hoverSlot===s.slot.id ? sev+'15' : 'rgba(255,255,255,0.025)',
                border: `1px solid ${hoverSlot===s.slot.id ? sev+'66' : T.border}`,
                cursor:'pointer', transition:'all .12s'
              }}>
                <div style={{ display:'flex', alignItems:'center', justifyContent:'space-between', marginBottom:4 }}>
                  <span style={{ fontSize:9, fontWeight:800, color:T.textHi, letterSpacing:'0.08em', textTransform:'uppercase' }}>
                    {s.slot.l}
                  </span>
                  {s.slot.tier && (
                    <span style={{
                      fontSize:7.5, fontFamily:'JetBrains Mono', fontWeight:800, color:'#fbbf24',
                      padding:'1px 4px', borderRadius:3, background:'rgba(251,191,36,0.12)'
                    }}>TIER</span>
                  )}
                </div>
                <div style={{ display:'flex', alignItems:'baseline', gap:6, marginBottom:6 }}>
                  <span style={{ fontSize:18, fontWeight:800, fontFamily:'JetBrains Mono', color:sev, letterSpacing:'-0.02em' }}>
                    {s.totalProb}
                  </span>
                  <span style={{ fontSize:9, color:T.textLow, letterSpacing:'0.06em', fontWeight:700 }}>
                    ПРОБЛЕМ
                  </span>
                </div>
                <div style={{ display:'flex', gap:5, fontSize:8.5, color:T.textMid, fontFamily:'JetBrains Mono', fontWeight:700 }}>
                  {s.lowCount > 0 && <span title="низький ілвл" style={{ color:T.error }}>↓{s.lowCount}</span>}
                  {s.enchMiss > 0 && <span title="без енчанта" style={{ color:T.error }}>⚡{s.enchMiss}</span>}
                  {s.gemMiss > 0 && <span title="порожні сокети" style={{ color:'#fbbf24' }}>◇{s.gemMiss}</span>}
                  <span style={{ marginLeft:'auto', color:T.textLow }}>avg {s.avgIlvl}</span>
                </div>
              </button>
            );
          })}
        </div>
      </div>

      <div style={{ padding:'0 16px', overflow:'auto', maxHeight:520 }}>
        <table style={{ borderCollapse:'separate', borderSpacing:0, fontSize:11, tableLayout:'fixed' }}>
          <colgroup>
            <col style={{ width:200 }}/>
            <col style={{ width:60 }}/>
            <col style={{ width:60 }}/>
            <col style={{ width:60 }}/>
            {window.GEAR_SLOTS.map(s => <col key={s.id} style={{ width:54 }}/>)}
          </colgroup>
          <thead>
            <tr>
              <th style={hG3(200, true)}>ПЕРСОНАЖ</th>
              <th style={hG3(60)}>АУДИТ</th>
              <th style={hG3(60)}>ПРОБЛ.</th>
              <th style={hG3(60)}>АПГР.</th>
              {window.GEAR_SLOTS.map(s => (
                <th key={s.id} style={{
                  ...hG3(54),
                  background: hoverSlot===s.id ? T.primary+'15' : window.T.surfaceLow,
                  color: hoverSlot===s.id ? T.primary : window.T.textLow
                }}>{s.l.toUpperCase()}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {['tank','heal','dps'].map(role => (
              <React.Fragment key={role}>
                <GroupHeader role={role} count={grouped[role].length}/>
                {grouped[role].map(c => (
                  <RowG3 key={c.id} c={c} onAudit={()=>setAuditOpen(c)} hoverSlot={hoverSlot}/>
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

const hG3 = (w, left=false) => ({
  position:'sticky', top:0, zIndex:2,
  padding:'8px 4px', fontSize:9, fontWeight:800, color:window.T.textLow,
  letterSpacing:'0.1em', textAlign: left?'left':'center', width:w, minWidth:w,
  background:window.T.surfaceLow,
  borderBottom:`1px solid ${window.T.border}`
});

function RowG3({ c, onAudit, hoverSlot }) {
  const T = window.T;
  const cc = window.CLASS_COLORS[c.cls] || '#888';
  const g = window.GEAR_DATA[c.id];
  const ilvlColor = g.avgIlvl >= 285 ? '#FB923C' : g.avgIlvl >= 275 ? '#C084FC' : '#60A5FA';
  return (
    <tr style={{ borderBottom:`1px solid ${T.border}`, height:60 }}>
      <td style={{ padding:'6px 8px', borderBottom:`1px solid ${T.border}`, background:T.bg, position:'sticky', left:0, zIndex:1 }}>
        <div style={{ display:'flex', alignItems:'center', gap:9 }}>
          <RosterAvatar char={c} size={28}/>
          <div style={{ minWidth:0 }}>
            <div style={{ display:'flex', alignItems:'center', gap:5 }}>
              <span style={{ fontSize:12, fontWeight:800, color:cc }}>{c.nick}</span>
              <ClassMark cls={c.cls} size={10}/>
            </div>
            <div style={{ fontSize:8.5, color:ilvlColor, fontFamily:'JetBrains Mono', fontWeight:700 }}>
              {g.avgIlvl} avg · т {g.tierWorn}/6
            </div>
          </div>
        </div>
      </td>
      <td style={{ textAlign:'center', borderBottom:`1px solid ${T.border}` }}>
        <AuditChip count={g.audit.total} onClick={onAudit}/>
      </td>
      <td style={{ textAlign:'center', borderBottom:`1px solid ${T.border}` }}>
        {g.problemsCount > 0 ? (
          <span style={{ fontSize:11, fontWeight:800, fontFamily:'JetBrains Mono', color:T.error }}>{g.problemsCount}</span>
        ) : <span style={{ fontSize:9, color:T.success }}>✓</span>}
      </td>
      <td style={{ textAlign:'center', borderBottom:`1px solid ${T.border}` }}>
        {g.missedUpgrades > 0 ? (
          <span style={{ fontSize:11, fontWeight:800, fontFamily:'JetBrains Mono', color:T.tertiary }}>{g.missedUpgrades}</span>
        ) : <span style={{ fontSize:9, color:T.textLow }}>—</span>}
      </td>
      {window.GEAR_SLOTS.map(slot => (
        <td key={slot.id} style={{
          padding:'6px 2px', textAlign:'center', verticalAlign:'middle', borderBottom:`1px solid ${T.border}`,
          background: hoverSlot===slot.id ? T.primary+'08' : 'transparent'
        }}>
          <div style={{ display:'inline-flex' }}>
            <window.ItemCell slot={slot} item={g.slots[slot.id]} dense={true}/>
          </div>
        </td>
      ))}
    </tr>
  );
}

window.GearV3 = GearV3;
