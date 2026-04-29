// Gear tab content — right-side table only.
// Row heights MUST match shell: main=56, alt=44, group=36, header=38.
// Columns: АУДИТ · ПРОПУЩЕНІ АПГРЕЙДИ · 16 слотів спорядження.

function GearContent({ plan, onAudit }) {
  const T = window.T;
  const D = window.SHELL_DIMS;

  return (
    <table style={{ borderCollapse:'separate', borderSpacing:0, fontSize:11, tableLayout:'fixed' }}>
      <colgroup>
        <col style={{ width:74 }}/>
        <col style={{ width:120 }}/>
        {window.GEAR_SLOTS.map(s => <col key={s.id} style={{ width:62 }}/>)}
      </colgroup>
      <thead>
        <tr style={{ height: D.HDR_H, background:T.surfaceLow }}>
          <th style={hdrGr(74)}>АУДИТ</th>
          <th style={hdrGr(120)} title="К-сть слотів які можна апгрейднути">ПРОПУЩЕНІ АПГРЕЙДИ</th>
          {window.GEAR_SLOTS.map(s => (
            <th key={s.id} style={hdrGr(62)}>{s.l.toUpperCase()}</th>
          ))}
        </tr>
      </thead>
      <tbody>
        {plan.map((e, i) => {
          if (e.kind === 'group') {
            return (
              <tr key={`g-${e.role}-${i}`} style={{ height:D.GROUP_H }}>
                <td colSpan={2 + window.GEAR_SLOTS.length} style={{
                  borderBottom:`1px solid ${T.border}`, background:T.surfaceLow
                }}/>
              </tr>
            );
          }
          const c = e.c;
          const g = window.GEAR_DATA[c.id];
          if (!g) return null;
          return (
            <tr key={c.id} style={{
              borderBottom:`1px solid ${T.border}`,
              height: e.isAlt ? D.ALT_H : D.ROW_H
            }}>
              <td style={{ ...tdGr, textAlign:'center' }}>
                <AuditChip count={g.audit.total} onClick={()=>onAudit(c)}/>
              </td>
              <td style={{ ...tdGr, textAlign:'center' }}>
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
              {window.GEAR_SLOTS.map(slot => (
                <td key={slot.id} style={{ ...tdGr, padding: '6px 4px', textAlign:'center', verticalAlign:'middle' }}>
                  <div style={{ display:'inline-flex', justifyContent:'center' }}>
                    <window.ItemCell slot={slot} item={g.slots[slot.id]} dense={e.isAlt}/>
                  </div>
                </td>
              ))}
            </tr>
          );
        })}
      </tbody>
    </table>
  );
}

const hdrGr = (w) => ({
  padding:'10px 6px', fontSize:9, fontWeight:800, color:window.T.textLow,
  letterSpacing:'0.12em', textAlign:'center', width:w, minWidth:w,
  borderBottom:`1px solid ${window.T.border}`
});
const tdGr = {
  padding:'6px 4px', verticalAlign:'middle',
  borderBottom:`1px solid ${window.T.border}`
};

window.GearContent = GearContent;
