// Shared first-column for ALL tabs (General / Raids / Gear).
// Identical pixel-for-pixel between tabs so the user perceives only the right side
// changing when switching. Width: 28 (gutter) + 220 (char) = 248px sticky left.

// Row height contract:
//   main row: 56px
//   alt row:  44px
// Header row: 36px (padding:10px 8px + 9px font)

// FIRST_COL_WIDTH = 28 + 220 = 248
window.FIRST_COL_W = 248;
window.FIRST_COL_GUTTER = 28;
window.FIRST_COL_NAME = 220;
window.FIRST_COL_ROW_H = 56;
window.FIRST_COL_ALT_H = 44;

// <FirstColHeader/> — exactly the two header cells (gutter + ПЕРСОНАЖ)
function FirstColHeader({ stickyLeft = true }) {
  const T = window.T;
  const sty = {
    padding:'10px 8px', fontSize:9, fontWeight:800, color:T.textLow,
    letterSpacing:'0.12em', textAlign:'center',
    borderBottom:`1px solid ${T.border}`,
    background: T.surfaceLow,
    position: stickyLeft ? 'sticky' : undefined,
    top: stickyLeft ? 0 : undefined,
    zIndex: 3
  };
  return (
    <React.Fragment>
      <th style={{ ...sty, width:window.FIRST_COL_GUTTER, minWidth:window.FIRST_COL_GUTTER, left:0 }}/>
      <th style={{ ...sty, width:window.FIRST_COL_NAME, minWidth:window.FIRST_COL_NAME, textAlign:'left', left:window.FIRST_COL_GUTTER }}>
        ПЕРСОНАЖ
      </th>
    </React.Fragment>
  );
}

// <FirstColCells/> — render the two TD cells for a row.
// Used by every tab. Behavior is uniform: checkbox in gutter, chevron+avatar+meta in name col.
function FirstColCells({
  c, isAlt = false,
  selected, onToggleSel,
  expanded, onToggleExpand,
  rowBg = 'transparent'
}) {
  const T = window.T;
  const cc = window.CLASS_COLORS[c.cls] || '#888';
  return (
    <React.Fragment>
      <td style={{
        padding:'4px 0 4px 8px', textAlign:'center',
        background: rowBg,
        position:'sticky', left:0, zIndex:1,
        width:window.FIRST_COL_GUTTER, minWidth:window.FIRST_COL_GUTTER,
        borderBottom:`1px solid ${T.border}`,
        verticalAlign:'middle'
      }}>
        {!isAlt && (
          <input type="checkbox" checked={!!selected} onChange={onToggleSel}
            style={{ accentColor:T.success, cursor:'pointer', width:13, height:13 }}/>
        )}
      </td>
      <td style={{
        padding:'6px 8px', verticalAlign:'middle',
        background: rowBg,
        position:'sticky', left:window.FIRST_COL_GUTTER, zIndex:1,
        width:window.FIRST_COL_NAME, minWidth:window.FIRST_COL_NAME,
        borderBottom:`1px solid ${T.border}`
      }}>
        <div style={{ display:'flex', alignItems:'center', gap:10, paddingLeft: isAlt?28:0 }}>
          {c.alt && !isAlt ? (
            <button onClick={onToggleExpand} style={{
              background:'none', border:'none', color:T.textLow, cursor:'pointer',
              transform: expanded?'rotate(90deg)':'none', transition:'transform .15s',
              padding:0, display:'flex', alignItems:'center'
            }} title={expanded?'Згорнути альтів':'Розгорнути альтів'}>
              <span className="ms" style={{ fontSize:14 }}>chevron_right</span>
            </button>
          ) : (!isAlt && <span style={{ width:14, display:'inline-block' }}/>)}
          <RosterAvatar char={c} size={isAlt?26:34}/>
          <div style={{ minWidth:0 }}>
            <div style={{ display:'flex', alignItems:'center', gap:6 }}>
              <span style={{ fontSize: isAlt?12:14, fontWeight:800, color:cc, letterSpacing:'-0.01em' }}>{c.nick}</span>
              <span style={{ fontSize:9, color:T.textLow, fontWeight:700, letterSpacing:'0.08em' }}>
                {(c.spec||'').slice(0,4).toUpperCase()}
              </span>
              <ClassMark cls={c.cls} size={12}/>
            </div>
            <div style={{ fontSize:9, color:T.textLow, fontFamily:'JetBrains Mono', letterSpacing:'0.04em', marginTop:1 }}>
              {c.bt}
            </div>
          </div>
        </div>
      </td>
    </React.Fragment>
  );
}

window.FirstColHeader = FirstColHeader;
window.FirstColCells = FirstColCells;
