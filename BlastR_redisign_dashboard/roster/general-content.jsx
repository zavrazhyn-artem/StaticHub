// General tab content — right-side table only (first column owned by the shell).
// Row heights MUST match shell: main=56, alt=44, group=36, header=38.
// Columns: ILVL · Тирові частини · M+ забіги · Рейтинг · Аудит · Статус · Роль · ⋯

function GeneralContent({ plan, selected, onAudit }) {
  const T = window.T;
  const D = window.SHELL_DIMS;

  return (
    <table style={{ width:'100%', borderCollapse:'separate', borderSpacing:0, fontSize:12, tableLayout:'fixed' }}>
      <colgroup>
        <col style={{ width:70 }}/>{/* ilvl */}
        <col style={{ width:130 }}/>{/* tier */}
        <col style={{ width:130 }}/>{/* m+ keys */}
        <col style={{ width:90 }}/>{/* rio */}
        <col style={{ width:118 }}/>{/* audit */}
        <col style={{ width:130 }}/>{/* status */}
        <col style={{ width:130 }}/>{/* acc */}
        <col style={{ width:42 }}/>{/* dots */}
      </colgroup>
      <thead>
        <tr style={{ height: D.HDR_H, background:T.surfaceLow }}>
          <th style={hdrG(70)}>ILVL</th>
          <th style={hdrG(130)}>ТИРОВІ ЧАСТИНИ</th>
          <th style={hdrG(130)}>M+ ЗАБІГИ</th>
          <th style={hdrG(90)}>РЕЙТИНГ</th>
          <th style={hdrG(118)}>АУДИТ</th>
          <th style={hdrG(130)}>СТАТУС</th>
          <th style={hdrG(130)}>РОЛЬ</th>
          <th style={hdrG(42)}/>
        </tr>
      </thead>
      <tbody>
        {plan.map((e, i) => {
          if (e.kind === 'group') {
            return (
              <tr key={`g-${e.role}-${i}`} style={{ height:D.GROUP_H }}>
                <td colSpan={8} style={{
                  borderBottom:`1px solid ${T.border}`, background:T.surfaceLow
                }}/>
              </tr>
            );
          }
          const c = e.c;
          const ilvlColor = c.ilvl >= 280 ? T.success : c.ilvl >= 270 ? T.tertiary : T.error;
          const sel = selected.has(c.id);
          const rowBg = sel ? T.success+'08' : 'transparent';
          return (
            <tr key={c.id} style={{
              background: rowBg, height: e.isAlt ? D.ALT_H : D.ROW_H,
              borderBottom:`1px solid ${T.border}`
            }}>
              <td style={{ ...tdG, textAlign:'center' }}>
                <span style={{ fontSize: e.isAlt?12:14, fontWeight:800, fontFamily:'JetBrains Mono', color:ilvlColor, letterSpacing:'-0.01em' }}>
                  {c.ilvl.toFixed(1)}
                </span>
              </td>
              <td style={{ ...tdG, textAlign:'center' }}>
                <TierPips tier={c.tier} size={e.isAlt?'sm':'md'}/>
              </td>
              <td style={{ ...tdG, padding:'6px 12px' }}>
                <KeyBar keys={c.keys}/>
              </td>
              <td style={{ ...tdG, textAlign:'center' }}>
                <span style={{ fontSize: e.isAlt?11:13, fontWeight:800, fontFamily:'JetBrains Mono',
                  color: c.rio >= 3000 ? '#a855f7' : c.rio >= 2000 ? T.tank : T.textLow }}>
                  {c.rio || '—'}
                </span>
              </td>
              <td style={{ ...tdG, textAlign:'center' }}>
                <AuditChip count={c.audit} onClick={()=>onAudit(c)}/>
              </td>
              <td style={{ ...tdG, textAlign:'center' }}>
                <StatusPill kind="status" value={c.status}/>
              </td>
              <td style={{ ...tdG, textAlign:'center' }}>
                <StatusPill kind="acc" value={c.acc}/>
              </td>
              <td style={{ ...tdG, textAlign:'center' }}>
                <button style={{ background:'none', border:'none', color:T.textLow, cursor:'pointer' }}>
                  <span className="ms" style={{ fontSize:18 }}>more_vert</span>
                </button>
              </td>
            </tr>
          );
        })}
      </tbody>
    </table>
  );
}

const hdrG = (w) => ({
  padding:'10px 8px', fontSize:9, fontWeight:800, color:window.T.textLow,
  letterSpacing:'0.12em', textAlign:'center', width:w, minWidth:w,
  borderBottom:`1px solid ${window.T.border}`
});
const tdG = {
  padding:'6px 8px', verticalAlign:'middle',
  borderBottom:`1px solid ${window.T.border}`
};

window.GeneralContent = GeneralContent;
