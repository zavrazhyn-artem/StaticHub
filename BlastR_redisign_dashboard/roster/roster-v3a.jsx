// V3A — Tightened. Minimal change to current table:
// - tier slots → 5 colored pips (no horizontal scroll)
// - audit → AuditChip (✓ ALL CLEAR or ⚠ N as button)
// - M+ keys → number + colored progress bar to 8
// - destructive × moved into ⋯ menu
// - filter pills replace heavy header

function RosterV3A({ onTabChange }) {
  const T = window.T;
  const [filter, setFilter] = React.useState('all');
  const [auditOpen, setAuditOpen] = React.useState(null);
  const [selected, setSelected] = React.useState(new Set(['chong','yazhneloh','kaeris']));
  const [expanded, setExpanded] = React.useState(new Set(['zavrikk']));
  const [compareMode, setCompareMode] = React.useState(false);

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
    if (compareMode) return selected.has(c.id);
    if (filter==='all') return true;
    if (['tank','heal','dps'].includes(filter)) return c.role===filter;
    if (filter==='main')  return c.status==='main';
    if (filter==='bench') return c.status==='bench';
    return true;
  });

  const grouped = { tank:[], heal:[], dps:[] };
  filtered.forEach(c => grouped[c.role].push(c));

  const toggleSel = (id) => {
    const s = new Set(selected);
    if (s.has(id)) s.delete(id); else s.add(id);
    setSelected(s);
  };

  const renderRow = (c, isAlt=false) => (
    <RowA key={c.id} c={c} isAlt={isAlt}
      selected={selected.has(c.id)}
      expanded={expanded.has(c.id)}
      onToggleSel={()=>toggleSel(c.id)}
      onToggleExpand={()=>{ const s=new Set(expanded); s.has(c.id)?s.delete(c.id):s.add(c.id); setExpanded(s); }}
      onAudit={()=>setAuditOpen(c)}
    />
  );

  return (
    <div style={{ width:1280, height:900, color:T.textHi, fontFamily:'Inter', background:'#0e0e10', position:'relative', overflow:'hidden' }}>
      <PageChrome mainCount={counts.main} totalCount={counts.all}/>
      <div style={{ padding:'14px 16px 0', display:'flex', alignItems:'center', justifyContent:'space-between', gap:12 }}>
        <RosterTabs active="general" onChange={onTabChange}/>
      </div>
      <div style={{ padding:'10px 16px 10px', display:'flex', alignItems:'center', justifyContent:'space-between', gap:12, borderBottom:`1px solid ${T.border}` }}>
        {compareMode ? (
          <CompareModeBanner count={selected.size} onExit={()=>setCompareMode(false)}/>
        ) : (
          <FilterPills value={filter} onChange={setFilter} counts={counts}/>
        )}
      </div>

      <div style={{ padding:'0 16px', position:'relative', overflow:'auto', maxHeight:740 }}>
        <table style={{ width:'100%', borderCollapse:'collapse', fontSize:12 }}>
          <thead>
            <tr style={{ background:T.surfaceLow }}>
              <th style={hA(28)}></th>
              <th style={{ ...hA(220), textAlign:'left' }}>ПЕРСОНАЖ</th>
              <th style={hA(60)}>ILVL</th>
              <th style={hA(120)}>ТИРОВІ ЧАСТИНИ</th>
              <th style={hA(110)}>M+ ЗАБІГИ</th>
              <th style={hA(80)}>РЕЙТИНГ</th>
              <th style={hA(110)}>АУДИТ</th>
              <th style={hA(120)}>СТАТУС</th>
              <th style={hA(120)}>РОЛЬ</th>
              <th style={hA(34)}></th>
            </tr>
          </thead>
          <tbody>
            {['tank','heal','dps'].map(role => (
              <React.Fragment key={role}>
                <GroupHeader role={role} count={grouped[role].length}/>
                {grouped[role].flatMap(c => {
                  const rows = [renderRow(c)];
                  if (c.alt && expanded.has(c.id)) {
                    const alts = chars.filter(x => x.parentOf === c.id);
                    alts.forEach(a => rows.push(renderRow(a, true)));
                  }
                  return rows;
                })}
              </React.Fragment>
            ))}
          </tbody>
        </table>
      </div>

      <SelectionBar count={selected.size}
        compareMode={compareMode}
        onCompare={()=>setCompareMode(true)}
        onIsolate={()=>setCompareMode(true)}
        onClear={()=>{ setSelected(new Set()); setCompareMode(false); }}/>
      {auditOpen && <AuditModal char={auditOpen} onClose={()=>setAuditOpen(null)}/>}
    </div>
  );
}

const hA = (w) => ({
  padding:'10px 8px', fontSize:9, fontWeight:800, color:window.T.textLow,
  letterSpacing:'0.12em', textAlign:'center', width:w, minWidth:w,
  borderBottom:`1px solid ${window.T.border}`
});

function RowA({ c, isAlt, selected, expanded, onToggleSel, onToggleExpand, onAudit }) {
  const T = window.T;
  const cc = window.CLASS_COLORS[c.cls] || '#888';
  const ilvlColor = c.ilvl >= 280 ? T.success : c.ilvl >= 270 ? T.tertiary : T.error;
  return (
    <tr style={{
      background: selected ? T.success+'08' : 'transparent',
      borderLeft: selected ? `2px solid ${T.success}` : '2px solid transparent',
      borderBottom:`1px solid ${T.border}`,
      height: isAlt ? 44 : 56
    }}>
      <td style={{ padding:'4px 0 4px 8px', textAlign:'center' }}>
        <input type="checkbox" checked={selected} onChange={onToggleSel}
          style={{ accentColor:T.success, cursor:'pointer' }}/>
      </td>
      <td style={{ padding:'6px 8px' }}>
        <div style={{ display:'flex', alignItems:'center', gap:10, paddingLeft: isAlt?28:0 }}>
          {c.alt && !isAlt && (
            <button onClick={onToggleExpand} style={{
              background:'none', border:'none', color:T.textLow, cursor:'pointer',
              transform: expanded?'rotate(90deg)':'none', transition:'transform .15s', padding:0
            }}>
              <span className="ms" style={{ fontSize:14 }}>chevron_right</span>
            </button>
          )}
          {!c.alt && !isAlt && <span style={{ width:14 }}/>}
          <RosterAvatar char={c} size={isAlt?26:34}/>
          <div style={{ minWidth:0 }}>
            <div style={{ display:'flex', alignItems:'center', gap:6 }}>
              <span style={{ fontSize: isAlt?12:14, fontWeight:800, color:cc, letterSpacing:'-0.01em' }}>{c.nick}</span>
              <span style={{ fontSize:9, color:T.textLow, fontWeight:700, letterSpacing:'0.08em' }}>{c.spec.slice(0,4).toUpperCase()}</span>
              <ClassMark cls={c.cls} size={12}/>
            </div>
            <div style={{ fontSize:9, color:T.textLow, fontFamily:'JetBrains Mono', letterSpacing:'0.04em', marginTop:1 }}>{c.bt}</div>
          </div>
        </div>
      </td>
      <td style={{ padding:'6px 8px', textAlign:'center' }}>
        <span style={{ fontSize:14, fontWeight:800, fontFamily:'JetBrains Mono', color:ilvlColor, letterSpacing:'-0.01em' }}>
          {c.ilvl.toFixed(1)}
        </span>
      </td>
      <td style={{ padding:'6px 8px', textAlign:'center' }}>
        <TierPips tier={c.tier} size="md"/>
      </td>
      <td style={{ padding:'6px 12px' }}>
        <KeyBar keys={c.keys}/>
      </td>
      <td style={{ padding:'6px 8px', textAlign:'center' }}>
        <span style={{ fontSize:13, fontWeight:800, fontFamily:'JetBrains Mono',
          color: c.rio >= 3000 ? '#a855f7' : c.rio >= 2000 ? T.tank : T.textLow }}>{c.rio || '—'}</span>
      </td>
      <td style={{ padding:'6px 8px', textAlign:'center' }}>
        <AuditChip count={c.audit} onClick={onAudit}/>
      </td>
      <td style={{ padding:'6px 8px', textAlign:'center' }}>
        <StatusPill kind="status" value={c.status}/>
      </td>
      <td style={{ padding:'6px 8px', textAlign:'center' }}>
        <StatusPill kind="acc" value={c.acc}/>
      </td>
      <td style={{ padding:'6px 8px', textAlign:'center' }}>
        <button style={{ background:'none', border:'none', color:T.textLow, cursor:'pointer' }}>
          <span className="ms" style={{ fontSize:18 }}>more_vert</span>
        </button>
      </td>
    </tr>
  );
}

window.RosterV3A = RosterV3A;

// Compare-mode banner — replaces FilterPills while filtering to selected only
function CompareModeBanner({ count, onExit }) {
  const T = window.T;
  return (
    <div style={{
      display:'inline-flex', alignItems:'center', gap:10, padding:'5px 6px 5px 12px',
      borderRadius:8, background:T.primary+'15', border:`1px solid ${T.primary}55`
    }}>
      <span className="ms" style={{ fontSize:14, color:T.primary }}>compare_arrows</span>
      <span style={{ fontSize:10, fontWeight:800, color:T.primary, letterSpacing:'0.1em' }}>
        РЕЖИМ ПОРІВНЯННЯ
      </span>
      <span style={{
        fontSize:10, fontFamily:'JetBrains Mono', fontWeight:800, color:T.primary,
        padding:'2px 7px', borderRadius:4, background:T.primary+'22'
      }}>{count}</span>
      <button onClick={onExit} style={{
        display:'inline-flex', alignItems:'center', gap:4, padding:'5px 10px', borderRadius:6,
        background:'transparent', border:`1px solid ${T.border}`, color:T.textMid,
        fontSize:10, fontWeight:700, letterSpacing:'0.06em', cursor:'pointer'
      }}>
        <span className="ms" style={{ fontSize:12 }}>close</span>
        ВИЙТИ
      </button>
    </div>
  );
}
