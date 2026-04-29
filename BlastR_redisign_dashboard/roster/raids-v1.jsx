// Raids V1 — Compact Matrix.
// Classic dense ✓/× grid. Sticky char column on left. Bosses grouped by raid
// with raid-name banner spanning their columns. Right side: per-char kill bar.
// Difficulty toggle top-right. Filter pills. Sort: by joined-date (default) or
// click any boss column to sort by who-killed-this.

function RaidsV1({ onTabChange }) {
  const T = window.T;
  const [diff, setDiff] = React.useState('M');
  const [filter, setFilter] = React.useState('all');
  const [sortBy, setSortBy] = React.useState({ key:'default', dir:'desc' });
  const [expanded, setExpanded] = React.useState(new Set(['zavrikk']));

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

  // Sort within group
  Object.keys(grouped).forEach(k => {
    if (sortBy.key === 'default') return; // joined-date order = original
    grouped[k].sort((a,b) => {
      if (sortBy.key === 'pct') {
        const av = killStats(a, diff).pct;
        const bv = killStats(b, diff).pct;
        return sortBy.dir==='desc' ? bv-av : av-bv;
      }
      // boss column: killed first
      const ak = window.RAID_KILLS[a.id]?.[sortBy.key]?.[diff] ? 1 : 0;
      const bk = window.RAID_KILLS[b.id]?.[sortBy.key]?.[diff] ? 1 : 0;
      return sortBy.dir==='desc' ? bk-ak : ak-bk;
    });
  });

  const handleSort = (key) => {
    if (sortBy.key === key) {
      setSortBy({ key, dir: sortBy.dir==='desc' ? 'asc' : 'desc' });
    } else {
      setSortBy({ key, dir: 'desc' });
    }
  };

  const diffColor = window.DIFFS[diff].color;

  return (
    <div style={{ width:1280, height:900, color:T.textHi, fontFamily:'Inter', background:'#0e0e10', position:'relative', overflow:'hidden' }}>
      <PageChrome mainCount={counts.main} totalCount={counts.all}/>
      <div style={{ padding:'14px 16px 0', display:'flex', alignItems:'center', justifyContent:'space-between', gap:12 }}>
        <RosterTabs active="raids" onChange={onTabChange}/>
      </div>
      <div style={{ padding:'10px 16px 10px', display:'flex', alignItems:'center', justifyContent:'space-between', gap:12, borderBottom:`1px solid ${T.border}` }}>
        <div style={{ display:'flex', alignItems:'center', gap:14 }}>
          <FilterPills value={filter} onChange={setFilter} counts={counts}/>
          <SortLabel sortBy={sortBy} onReset={()=>setSortBy({ key:'default', dir:'desc' })}/>
        </div>
        <DiffToggle value={diff} onChange={setDiff}/>
      </div>

      <div style={{ padding:'0 16px', overflow:'auto', maxHeight:720 }}>
        <table style={{ width:'100%', borderCollapse:'collapse', fontSize:12, tableLayout:'fixed' }}>
          <colgroup>
            <col style={{ width:14 }}/>
            <col style={{ width:220 }}/>
            {window.ALL_BOSSES.map(b => <col key={b.id} style={{ width:90 }}/>)}
            <col style={{ width:140 }}/>
          </colgroup>
          <thead>
            {/* Raid name row */}
            <tr style={{ background:T.surfaceLow }}>
              <th colSpan={2} style={{
                padding:'8px 8px 6px 14px', fontSize:9, fontWeight:800,
                color:T.textLow, letterSpacing:'0.12em', textAlign:'left',
                borderBottom:`1px solid ${T.border}`
              }}>ПЕРСОНАЖ</th>
              {window.RAIDS.map(r => {
                const cfg = raidColor(r.tier);
                return (
                  <th key={r.id} colSpan={r.bosses.length} style={{
                    padding:'4px 6px', fontSize:9, fontWeight:800,
                    letterSpacing:'0.14em', textAlign:'center',
                    color: cfg, borderBottom:`1px solid ${T.border}`,
                    background:`linear-gradient(180deg, ${cfg}18 0%, transparent 100%)`
                  }}>
                    <span className="ms" style={{ fontSize:11, marginRight:4, verticalAlign:'-2px' }}>swords</span>
                    {r.name}
                  </th>
                );
              })}
              <th style={{
                padding:'4px 6px', fontSize:9, fontWeight:800, color:T.textLow,
                letterSpacing:'0.12em', textAlign:'center',
                borderBottom:`1px solid ${T.border}`
              }} onClick={()=>handleSort('pct')}>
                <span style={{ cursor:'pointer' }}>УБИТО {sortBy.key==='pct' && (sortBy.dir==='desc'?'↓':'↑')}</span>
              </th>
            </tr>
            {/* Boss row */}
            <tr style={{ background:T.surfaceLow }}>
              <th colSpan={2} style={{ borderBottom:`1px solid ${T.border}` }}/>
              {window.ALL_BOSSES.map(b => (
                <BossHeader key={b.id} boss={b} color={raidColor(b.raidTier)}
                  sortDir={sortBy.key===b.id ? sortBy.dir : null}
                  onSort={()=>handleSort(b.id)}/>
              ))}
              <th style={{ borderBottom:`1px solid ${T.border}` }}/>
            </tr>
          </thead>
          <tbody>
            {['tank','heal','dps'].map(role => (
              <React.Fragment key={role}>
                <GroupHeader role={role} count={grouped[role].length}/>
                {grouped[role].flatMap(c => {
                  const rows = [<RowR1 key={c.id} c={c} diff={diff} diffColor={diffColor}
                    expanded={expanded.has(c.id)}
                    onToggleExpand={()=>{ const s=new Set(expanded); s.has(c.id)?s.delete(c.id):s.add(c.id); setExpanded(s); }}
                  />];
                  if (c.alt && expanded.has(c.id)) {
                    chars.filter(x=>x.parentOf===c.id).forEach(a => rows.push(
                      <RowR1 key={a.id} c={a} isAlt diff={diff} diffColor={diffColor}/>
                    ));
                  }
                  return rows;
                })}
              </React.Fragment>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function raidColor(tier) {
  return tier===1 ? '#4fd3f7' : tier===2 ? '#a855f7' : '#fcf266';
}

function SortLabel({ sortBy, onReset }) {
  const T = window.T;
  if (sortBy.key === 'default') {
    return (
      <span style={{ fontSize:9, color:T.textLow, letterSpacing:'0.1em', fontWeight:700 }}>
        <span className="ms" style={{ fontSize:11, marginRight:4, verticalAlign:'-2px' }}>schedule</span>
        ЗА ДАТОЮ ПРИЄДНАННЯ
      </span>
    );
  }
  return (
    <button onClick={onReset} style={{
      display:'inline-flex', alignItems:'center', gap:4, padding:'3px 8px', borderRadius:5,
      background:T.surfaceLow, border:`1px solid ${T.border}`, color:T.textMid,
      fontSize:9, fontWeight:700, letterSpacing:'0.08em', cursor:'pointer'
    }}>
      <span className="ms" style={{ fontSize:11 }}>close</span>
      СКАСУВАТИ СОРТУВАННЯ
    </button>
  );
}

function RowR1({ c, isAlt, diff, diffColor, expanded, onToggleExpand }) {
  const T = window.T;
  const cc = window.CLASS_COLORS[c.cls] || '#888';
  const stats = killStats(c, diff);
  return (
    <tr style={{
      borderBottom:`1px solid ${T.border}`,
      height: isAlt ? 38 : 48
    }}>
      <td/>
      <td style={{ padding:'4px 8px' }}>
        <div style={{ display:'flex', alignItems:'center', gap:8, paddingLeft: isAlt?28:0 }}>
          {c.alt && !isAlt && (
            <button onClick={onToggleExpand} style={{
              background:'none', border:'none', color:T.textLow, cursor:'pointer',
              transform: expanded?'rotate(90deg)':'none', transition:'transform .15s', padding:0
            }}>
              <span className="ms" style={{ fontSize:14 }}>chevron_right</span>
            </button>
          )}
          {!c.alt && !isAlt && <span style={{ width:14 }}/>}
          <RosterAvatar char={c} size={isAlt?24:30}/>
          <div style={{ minWidth:0 }}>
            <div style={{ display:'flex', alignItems:'center', gap:6 }}>
              <span style={{ fontSize: isAlt?12:13, fontWeight:800, color:cc }}>{c.nick}</span>
              <ClassMark cls={c.cls} size={11}/>
            </div>
            <div style={{ fontSize:9, color:T.textLow, marginTop:1 }}>{c.spec}</div>
          </div>
        </div>
      </td>
      {window.ALL_BOSSES.map(b => {
        const killed = window.RAID_KILLS[c.id]?.[b.id]?.[diff];
        return <KillCell key={b.id} killed={killed} color={diffColor} isAlt={isAlt}/>;
      })}
      <td style={{ padding:'4px 10px' }}>
        <MiniProgress killed={stats.killed} total={stats.total} color={diffColor} w={68}/>
      </td>
    </tr>
  );
}

window.RaidsV1 = RaidsV1;
