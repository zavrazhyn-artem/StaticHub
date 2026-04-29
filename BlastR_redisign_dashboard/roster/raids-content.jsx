// Raids tab content — right-side table only.
// Row heights MUST match shell: main=56, alt=44, group=36, header=38.
// But raids has TWO header rows (raid banner + boss names), so total header
// height is 38 (we make each row 19, sum=38) — to keep alignment with shell.

function RaidsContent({ plan, diff, sortBy, onSort }) {
  const T = window.T;
  const D = window.SHELL_DIMS;
  const diffColor = window.DIFFS[diff]?.color || '#FB923C';
  const RAID_BANNER_H = 18;
  const BOSS_HDR_H = D.HDR_H - RAID_BANNER_H; // = 20

  return (
    <table style={{ width:'100%', borderCollapse:'separate', borderSpacing:0, fontSize:12, tableLayout:'fixed' }}>
      <colgroup>
        {window.ALL_BOSSES.map(b => <col key={b.id} style={{ width: 100 }}/>)}
        <col style={{ width: 132 }}/>
      </colgroup>
      <thead>
        {/* Raid banner row */}
        <tr style={{ height: RAID_BANNER_H, background:T.surfaceLow }}>
          {window.RAIDS.map(r => {
            const cfg = raidColor(r.tier);
            return (
              <th key={r.id} colSpan={r.bosses.length} style={{
                padding:'2px 6px', fontSize:9, fontWeight:800,
                letterSpacing:'0.14em', textAlign:'center',
                color: cfg, borderBottom:`1px solid ${T.border}`,
                background:`linear-gradient(180deg, ${cfg}18 0%, transparent 100%)`,
                whiteSpace:'nowrap', overflow:'hidden', textOverflow:'ellipsis'
              }}>
                <span className="ms" style={{ fontSize:10, marginRight:4, verticalAlign:'-2px' }}>swords</span>
                {r.name}
              </th>
            );
          })}
          <th style={{
            padding:'2px 6px', fontSize:9, fontWeight:800, color:T.textLow,
            letterSpacing:'0.12em', textAlign:'center',
            borderBottom:`1px solid ${T.border}`, background:T.surfaceLow
          }}>УБИТО</th>
        </tr>
        {/* Boss row */}
        <tr style={{ height: BOSS_HDR_H, background:T.surfaceLow }}>
          {window.ALL_BOSSES.map(b => (
            <BossHeader key={b.id} boss={b} color={raidColor(b.raidTier)}
              sortDir={sortBy.key===b.id ? sortBy.dir : null}
              onSort={()=>onSort(b.id)}/>
          ))}
          <th style={{ borderBottom:`1px solid ${T.border}`, background:T.surfaceLow }}/>
        </tr>
      </thead>
      <tbody>
        {plan.map((e, i) => {
          if (e.kind === 'group') {
            return (
              <tr key={`g-${e.role}-${i}`} style={{ height:D.GROUP_H }}>
                <td colSpan={window.ALL_BOSSES.length + 1} style={{
                  borderBottom:`1px solid ${T.border}`, background:T.surfaceLow
                }}/>
              </tr>
            );
          }
          const c = e.c;
          const stats = killStats(c, diff);
          return (
            <tr key={c.id} style={{
              borderBottom:`1px solid ${T.border}`,
              height: e.isAlt ? D.ALT_H : D.ROW_H
            }}>
              {window.ALL_BOSSES.map(b => {
                const killed = window.RAID_KILLS[c.id]?.[b.id]?.[diff];
                return <KillCell key={b.id} killed={killed} color={diffColor} isAlt={e.isAlt}/>;
              })}
              <td style={{ padding:'4px 10px', verticalAlign:'middle', borderBottom:`1px solid ${T.border}` }}>
                <MiniProgress killed={stats.killed} total={stats.total} color={diffColor} w={68}/>
              </td>
            </tr>
          );
        })}
      </tbody>
    </table>
  );
}

function raidColor(tier) {
  return tier===1 ? '#4fd3f7' : tier===2 ? '#a855f7' : '#fcf266';
}

// SortLabel — helper used by shell
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

window.RaidsContent = RaidsContent;
window.SortLabel = SortLabel;
