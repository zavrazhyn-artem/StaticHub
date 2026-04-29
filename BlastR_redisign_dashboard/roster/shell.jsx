// RosterShell — the page chrome + sticky first column.
// Renders ALL rows of the first column once; switching tabs only swaps the
// content table on the right. The first column never re-mounts → zero pixel shift.
//
// Layout:
//   ┌──────────────────────────────────────────────────────────┐
//   │ PageChrome (live banner + role counts)                   │
//   │ Tabs row                                                 │
//   │ Filter pills row                                         │
//   ├──────────────┬──────────────────────────────────────────┤
//   │ FIRST COL    │  CONTENT (general / raids / gear)        │
//   │  (sticky)    │   (scrolls horizontally if needed)       │
//   │  248px wide  │                                          │
//   │  ┌─gutter─┐  │                                          │
//   │  │ checkbx│  │                                          │
//   │  └────────┘  │                                          │
//   │  avatar+name │                                          │
//   └──────────────┴──────────────────────────────────────────┘
//
// Content components receive: { chars, expanded, selected, ... } and must
// render rows with EXACTLY the same row heights as the first column:
//   main row 56px, alt row 44px, group-header 36px.

const SHELL_W = 1280;
const SHELL_H = 900;
const FC_GUTTER = 28;
const FC_NAME = 220;
const FC_W = FC_GUTTER + FC_NAME; // 248
const ROW_H = 56;
const ALT_H = 44;
const GROUP_H = 36;
const HDR_H = 38;

function RosterShell({ tab, onTabChange }) {
  const T = window.T;
  const [filter, setFilter] = React.useState('all');
  const [selected, setSelected] = React.useState(new Set(['chong','yazhneloh','kaeris']));
  const [expanded, setExpanded] = React.useState(new Set(['zavrikk']));
  const [auditOpen, setAuditOpen] = React.useState(null);
  const [compareMode, setCompareMode] = React.useState(false);

  // Per-tab side state
  const [diff, setDiff] = React.useState('M');
  const [sortBy, setSortBy] = React.useState({ key:'default', dir:'desc' });
  const [vaultWeek, setVaultWeek] = React.useState('cur');

  const chars = window.ROSTER_CHARS;
  const counts = {
    all: chars.filter(c=>!c.parentOf).length,
    tank: chars.filter(c=>c.role==='tank' && !c.parentOf).length,
    heal: chars.filter(c=>c.role==='heal' && !c.parentOf).length,
    dps:  chars.filter(c=>c.role==='dps'  && !c.parentOf).length,
    main: chars.filter(c=>c.status==='main' && !c.parentOf).length,
    bench:chars.filter(c=>c.status==='bench' && !c.parentOf).length,
  };

  const filteredMains = chars.filter(c => {
    if (c.parentOf) return false;
    if (compareMode) return selected.has(c.id);
    if (filter==='all') return true;
    if (['tank','heal','dps'].includes(filter)) return c.role===filter;
    if (filter==='main')  return c.status==='main';
    if (filter==='bench') return c.status==='bench';
    return true;
  });
  const grouped = { tank:[], heal:[], dps:[] };
  filteredMains.forEach(c => grouped[c.role].push(c));

  // Build the flat rendering plan: array of either { kind:'group', role } or { kind:'row', c, isAlt }
  const plan = [];
  ['tank','heal','dps'].forEach(role => {
    plan.push({ kind:'group', role, count:grouped[role].length });
    grouped[role].forEach(c => {
      plan.push({ kind:'row', c, isAlt:false });
      if (c.alt && expanded.has(c.id)) {
        chars.filter(x => x.parentOf === c.id).forEach(a => {
          plan.push({ kind:'row', c:a, isAlt:true });
        });
      }
    });
  });

  const toggleSel = (id) => {
    const s = new Set(selected);
    if (s.has(id)) s.delete(id); else s.add(id);
    setSelected(s);
  };
  const toggleExp = (id) => {
    const s = new Set(expanded);
    if (s.has(id)) s.delete(id); else s.add(id);
    setExpanded(s);
  };

  // Right-side content per tab
  const tabExtras = (
    tab === 'raids' ? (
      <div style={{ display:'flex', alignItems:'center', gap:10 }}>
        <window.SortLabel sortBy={sortBy} onReset={()=>setSortBy({ key:'default', dir:'desc' })}/>
        <DiffToggle value={diff} onChange={setDiff}/>
      </div>
    ) : tab === 'gear' ? (
      <window.TrackLegend/>
    ) : null
  );

  return (
    <div style={{ width:SHELL_W, height:SHELL_H, color:T.textHi, fontFamily:'Inter', background:T.bg, position:'relative', overflow:'hidden' }}>
      <PageChrome mainCount={counts.main} totalCount={counts.all} week={vaultWeek} onWeekChange={setVaultWeek}/>

      <div style={{ padding:'14px 16px 0', display:'flex', alignItems:'center', justifyContent:'space-between', gap:12 }}>
        <RosterTabs active={tab} onChange={onTabChange}/>
      </div>

      <div style={{ padding:'10px 16px', minHeight:60, display:'flex', alignItems:'center', justifyContent:'space-between', gap:12, borderBottom:`1px solid ${T.border}`, boxSizing:'border-box' }}>
        {compareMode ? (
          <CompareModeBanner count={selected.size} onExit={()=>setCompareMode(false)}/>
        ) : (
          <FilterPills value={filter} onChange={setFilter} counts={counts}/>
        )}
        {tabExtras}
      </div>

      {/* Two-column body */}
      <div style={{ display:'flex', position:'relative', overflow:'hidden', height: SHELL_H - 168 }}>
        {/* Sticky first column — never re-mounts when tab changes */}
        <FirstColTable
          plan={plan}
          selected={selected}
          expanded={expanded}
          onToggleSel={toggleSel}
          onToggleExp={toggleExp}
          compareMode={compareMode}
        />

        {/* Right side — content swaps on tab change */}
        <div style={{ flex:1, overflow:'auto' }}>
          {tab === 'general' && (
            <window.GeneralContent
              plan={plan}
              selected={selected}
              onAudit={(c)=>setAuditOpen(c)}
            />
          )}
          {tab === 'raids' && (
            <window.RaidsContent
              plan={plan}
              diff={diff} sortBy={sortBy} onSort={(key)=>{
                setSortBy(p => p.key===key ? { key, dir: p.dir==='desc'?'asc':'desc' } : { key, dir:'desc' });
              }}
            />
          )}
          {tab === 'gear' && (
            <window.GearContent
              plan={plan}
              onAudit={(c)=>setAuditOpen(c)}
            />
          )}
          {tab === 'vault' && <window.VaultV1 plan={plan} week={vaultWeek}/>}
          {tab === 'vault-v1' && <window.VaultV1 plan={plan} week={vaultWeek}/>}
          {tab === 'vault-v2' && <window.VaultV2 plan={plan} week={vaultWeek}/>}
          {tab === 'vault-v3' && <window.VaultV3 plan={plan} week={vaultWeek}/>}
        </div>
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

// FirstColTable — one table, two columns (gutter 28 + name 220), all rows.
// Heights: header 38, group 36, row 56, alt 44.
function FirstColTable({ plan, selected, expanded, onToggleSel, onToggleExp, compareMode }) {
  const T = window.T;
  return (
    <div style={{
      width: FC_W, minWidth: FC_W, flexShrink:0,
      borderRight:`1px solid ${T.border}`,
      background: T.bg, overflow:'hidden'
    }}>
      <table style={{ width:FC_W, borderCollapse:'separate', borderSpacing:0, tableLayout:'fixed' }}>
        <colgroup>
          <col style={{ width:FC_GUTTER }}/>
          <col style={{ width:FC_NAME }}/>
        </colgroup>
        <thead>
          <tr style={{ height:HDR_H, background:T.surfaceLow }}>
            <th style={fcHdr(FC_GUTTER, false)}/>
            <th style={fcHdr(FC_NAME, true)}>ПЕРСОНАЖ</th>
          </tr>
        </thead>
        <tbody>
          {plan.map((entry, i) => {
            if (entry.kind === 'group') {
              return (
                <tr key={`g-${entry.role}-${i}`} style={{ height:GROUP_H }}>
                  <td colSpan={2} style={{
                    padding:'0 10px', fontSize:10, fontWeight:800, letterSpacing:'0.12em',
                    color: roleColor(entry.role), borderBottom:`1px solid ${T.border}`,
                    background:T.surfaceLow,
                    borderLeft:`3px solid ${roleColor(entry.role)}`
                  }}>
                    <span style={{ marginRight:6 }}>{roleLabel(entry.role)}</span>
                    <span style={{ color:T.textLow, fontWeight:700 }}>({entry.count})</span>
                  </td>
                </tr>
              );
            }
            // row
            const c = entry.c;
            const sel = selected.has(c.id);
            const rowBg = sel ? window.T.success+'08' : 'transparent';
            return (
              <tr key={c.id} style={{
                height: entry.isAlt ? ALT_H : ROW_H,
                background: rowBg,
                borderLeft: sel && !entry.isAlt ? `2px solid ${T.success}` : '2px solid transparent'
              }}>
                <FirstColCells
                  c={c} isAlt={entry.isAlt}
                  selected={sel}
                  onToggleSel={()=>onToggleSel(c.id)}
                  expanded={expanded.has(c.id)}
                  onToggleExpand={()=>onToggleExp(c.id)}
                  rowBg={rowBg}
                />
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}

const fcHdr = (w, left) => ({
  padding:'10px 8px', fontSize:9, fontWeight:800, color:window.T.textLow,
  letterSpacing:'0.12em', textAlign: left?'left':'center',
  width:w, minWidth:w,
  borderBottom:`1px solid ${window.T.border}`
});

function roleColor(r) {
  return r==='tank' ? window.T.tank : r==='heal' ? window.T.heal : window.T.dps;
}
function roleLabel(r) {
  return r==='tank' ? 'ТАНКИ' : r==='heal' ? 'ХІЛИ' : 'ДД';
}

window.RosterShell = RosterShell;
window.SHELL_DIMS = { ROW_H, ALT_H, GROUP_H, HDR_H };
