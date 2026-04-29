// V1 — Numeric (як зараз). Просто числа ілвл, кольорені по треку.
// 9 колонок (3 групи по 3), Слот 1/2/3 в кожній. Близько до оригіналу.
// V2 — Cards. Картки з ilvl + track + upgrade level.
// V3 — Strip. Однорядкова смуга-tracker — 9 кружечків кольору треку з ілвл всередині.

// SHARED — group banner row + group/slot column layout
const VAULT_GROUPS = [
  { id:'raid',  label:'РЕЙДИ',         icon:'swords',         color:'#a855f7' },
  { id:'mp',    label:'M+ ПІДЗЕМЕЛЛЯ', icon:'castle',         color:'#FB923C' },
  { id:'world', label:'ЗАНУРЕННЯ / СВІТ', icon:'public',     color:'#4fd3f7' },
];

// Each variant content fills (1280 - 248) = 1032 width.
// 9 slot columns + small spacers — split evenly.
// We'll use: each group = 344px wide → 3 slots × ~115px each.

// ============================================================================
// V1 — Numeric
// ============================================================================
function VaultV1({ plan, week }) {
  const T = window.T;
  const D = window.SHELL_DIMS;
  const SLOT_W = 115;

  return (
    <table style={{ width:'100%', borderCollapse:'separate', borderSpacing:0, fontSize:12, tableLayout:'fixed' }}>
      <colgroup>
        {VAULT_GROUPS.map(g => [
          <col key={g.id+'-1'} style={{ width:SLOT_W }}/>,
          <col key={g.id+'-2'} style={{ width:SLOT_W }}/>,
          <col key={g.id+'-3'} style={{ width:SLOT_W }}/>
        ])}
      </colgroup>
      <thead>
        {/* Group banner row */}
        <tr style={{ height:18, background:T.surfaceLow }}>
          {VAULT_GROUPS.map(g => (
            <th key={g.id} colSpan={3} style={{
              padding:'2px 6px', fontSize:9, fontWeight:800,
              letterSpacing:'0.14em', textAlign:'center',
              color: g.color, borderBottom:`1px solid ${T.border}`,
              background:`linear-gradient(180deg, ${g.color}18 0%, transparent 100%)`,
              borderLeft: g.id!=='raid' ? `1px solid ${T.border}` : 'none'
            }}>
              <span className="ms" style={{ fontSize:11, marginRight:5, verticalAlign:'-2px' }}>{g.icon}</span>
              {g.label}
            </th>
          ))}
        </tr>
        {/* Slot row */}
        <tr style={{ height: D.HDR_H - 18, background:T.surfaceLow }}>
          {VAULT_GROUPS.flatMap(g => [1,2,3].map(n => (
            <th key={g.id+n} style={{
              padding:'8px 6px', fontSize:9, fontWeight:800, color:T.textLow,
              letterSpacing:'0.12em', textAlign:'center',
              borderBottom:`1px solid ${T.border}`,
              borderLeft: (g.id!=='raid' && n===1) ? `1px solid ${T.border}` : 'none'
            }}>СЛОТ {n}</th>
          )))}
        </tr>
      </thead>
      <tbody>
        {plan.map((e, i) => {
          if (e.kind === 'group') {
            return (
              <tr key={`g-${e.role}-${i}`} style={{ height:D.GROUP_H }}>
                <td colSpan={9} style={{ borderBottom:`1px solid ${T.border}`, background:T.surfaceLow }}/>
              </tr>
            );
          }
          const c = e.c;
          const slots = window.VAULT_DATA[c.id]?.[week] || [];
          return (
            <tr key={c.id} style={{
              borderBottom:`1px solid ${T.border}`,
              height: e.isAlt ? D.ALT_H : D.ROW_H
            }}>
              {VAULT_GROUPS.flatMap((g, gi) => [0,1,2].map(si => {
                const idx = gi*3 + si;
                return (
                  <td key={idx} style={{
                    padding:'6px 8px', textAlign:'center', verticalAlign:'middle',
                    borderBottom:`1px solid ${T.border}`,
                    borderLeft: (gi!==0 && si===0) ? `1px solid ${T.border}` : 'none'
                  }}>
                    <window.VaultIlvl slot={slots[idx]} size={e.isAlt?'sm':'md'}/>
                  </td>
                );
              }))}
            </tr>
          );
        })}
      </tbody>
    </table>
  );
}

// ============================================================================
// V2 — Cards. Each slot is a small bordered card (ilvl + track badge).
// ============================================================================
function VaultV2({ plan, week }) {
  const T = window.T;
  const D = window.SHELL_DIMS;
  const SLOT_W = 115;

  return (
    <table style={{ width:'100%', borderCollapse:'separate', borderSpacing:0, fontSize:12, tableLayout:'fixed' }}>
      <colgroup>
        {VAULT_GROUPS.map(g => [
          <col key={g.id+'-1'} style={{ width:SLOT_W }}/>,
          <col key={g.id+'-2'} style={{ width:SLOT_W }}/>,
          <col key={g.id+'-3'} style={{ width:SLOT_W }}/>
        ])}
      </colgroup>
      <thead>
        <tr style={{ height:18, background:T.surfaceLow }}>
          {VAULT_GROUPS.map(g => (
            <th key={g.id} colSpan={3} style={{
              padding:'2px 6px', fontSize:9, fontWeight:800,
              letterSpacing:'0.14em', textAlign:'center',
              color: g.color, borderBottom:`1px solid ${T.border}`,
              background:`linear-gradient(180deg, ${g.color}18 0%, transparent 100%)`,
              borderLeft: g.id!=='raid' ? `1px solid ${T.border}` : 'none'
            }}>
              <span className="ms" style={{ fontSize:11, marginRight:5, verticalAlign:'-2px' }}>{g.icon}</span>
              {g.label}
            </th>
          ))}
        </tr>
        <tr style={{ height: D.HDR_H - 18, background:T.surfaceLow }}>
          {VAULT_GROUPS.flatMap(g => [1,2,3].map(n => (
            <th key={g.id+n} style={{
              padding:'8px 6px', fontSize:9, fontWeight:800, color:T.textLow,
              letterSpacing:'0.12em', textAlign:'center',
              borderBottom:`1px solid ${T.border}`,
              borderLeft: (g.id!=='raid' && n===1) ? `1px solid ${T.border}` : 'none'
            }}>СЛОТ {n}</th>
          )))}
        </tr>
      </thead>
      <tbody>
        {plan.map((e, i) => {
          if (e.kind === 'group') {
            return (
              <tr key={`g-${e.role}-${i}`} style={{ height:D.GROUP_H }}>
                <td colSpan={9} style={{ borderBottom:`1px solid ${T.border}`, background:T.surfaceLow }}/>
              </tr>
            );
          }
          const c = e.c;
          const slots = window.VAULT_DATA[c.id]?.[week] || [];
          return (
            <tr key={c.id} style={{
              borderBottom:`1px solid ${T.border}`,
              height: e.isAlt ? D.ALT_H : D.ROW_H
            }}>
              {VAULT_GROUPS.flatMap((g, gi) => [0,1,2].map(si => {
                const idx = gi*3 + si;
                return (
                  <td key={idx} style={{
                    padding:'6px 8px', textAlign:'center', verticalAlign:'middle',
                    borderBottom:`1px solid ${T.border}`,
                    borderLeft: (gi!==0 && si===0) ? `1px solid ${T.border}` : 'none'
                  }}>
                    <window.VaultCard slot={slots[idx]}/>
                  </td>
                );
              }))}
            </tr>
          );
        })}
      </tbody>
    </table>
  );
}

// ============================================================================
// V3 — Strip. Compact horizontal track-strip with 9 chips, no group columns.
// ============================================================================
function VaultV3({ plan, week }) {
  const T = window.T;
  const D = window.SHELL_DIMS;

  return (
    <table style={{ width:'100%', borderCollapse:'separate', borderSpacing:0, fontSize:12, tableLayout:'fixed' }}>
      <colgroup>
        <col/>
      </colgroup>
      <thead>
        <tr style={{ height:18, background:T.surfaceLow }}>
          <th style={{
            padding:'2px 16px', fontSize:9, fontWeight:800,
            letterSpacing:'0.14em', textAlign:'left',
            color: T.textLow, borderBottom:`1px solid ${T.border}`,
            background: T.surfaceLow
          }}>
            <span style={{ display:'inline-flex', alignItems:'center', gap:18 }}>
              <span style={{ color:'#a855f7' }}>
                <span className="ms" style={{ fontSize:11, marginRight:4, verticalAlign:'-2px' }}>swords</span>
                РЕЙДИ
              </span>
              <span style={{ color:'#FB923C' }}>
                <span className="ms" style={{ fontSize:11, marginRight:4, verticalAlign:'-2px' }}>castle</span>
                M+ ПІДЗЕМЕЛЛЯ
              </span>
              <span style={{ color:'#4fd3f7' }}>
                <span className="ms" style={{ fontSize:11, marginRight:4, verticalAlign:'-2px' }}>public</span>
                ЗАНУРЕННЯ / СВІТ
              </span>
            </span>
          </th>
        </tr>
        <tr style={{ height: D.HDR_H - 18, background:T.surfaceLow }}>
          <th style={{
            padding:'8px 16px', fontSize:9, fontWeight:800, color:T.textLow,
            letterSpacing:'0.12em', textAlign:'left',
            borderBottom:`1px solid ${T.border}`
          }}>9 СЛОТІВ ВЕЛИКОЇ СКАРБНИЦІ</th>
        </tr>
      </thead>
      <tbody>
        {plan.map((e, i) => {
          if (e.kind === 'group') {
            return (
              <tr key={`g-${e.role}-${i}`} style={{ height:D.GROUP_H }}>
                <td style={{ borderBottom:`1px solid ${T.border}`, background:T.surfaceLow }}/>
              </tr>
            );
          }
          const c = e.c;
          const slots = window.VAULT_DATA[c.id]?.[week] || [];
          return (
            <tr key={c.id} style={{
              borderBottom:`1px solid ${T.border}`,
              height: e.isAlt ? D.ALT_H : D.ROW_H
            }}>
              <td style={{ padding:'6px 16px', verticalAlign:'middle', borderBottom:`1px solid ${T.border}` }}>
                <VaultStrip slots={slots} dense={e.isAlt}/>
              </td>
            </tr>
          );
        })}
      </tbody>
    </table>
  );
}

function VaultStrip({ slots, dense }) {
  const T = window.T;
  // 9 chips with separators between groups (after 3 and 6).
  const chipW = dense ? 28 : 34;
  const chipH = dense ? 28 : 34;
  return (
    <div style={{ display:'flex', alignItems:'center', gap:5 }}>
      {[0,1,2,3,4,5,6,7,8].map(idx => {
        const slot = slots[idx];
        const groupColor = idx<3 ? '#a855f7' : idx<6 ? '#FB923C' : '#4fd3f7';
        return (
          <React.Fragment key={idx}>
            {(idx===3 || idx===6) && (
              <div style={{
                width:1, height: chipH-6, background:T.border, margin:'0 4px'
              }}/>
            )}
            {slot ? (
              <div style={{
                width:chipW, height:chipH, borderRadius:6,
                background: window.VAULT_TRACKS[slot.track].color + '22',
                border: `1.5px solid ${window.VAULT_TRACKS[slot.track].color}`,
                display:'flex', alignItems:'center', justifyContent:'center',
                position:'relative'
              }}>
                <span style={{
                  fontSize: dense?10:11, fontWeight:800,
                  color: window.VAULT_TRACKS[slot.track].color,
                  fontFamily:'JetBrains Mono', letterSpacing:'-0.02em', lineHeight:1
                }}>{slot.ilvl}</span>
                <span style={{
                  position:'absolute', top:-2, right:-2,
                  fontSize:7, fontWeight:800, color:'#0e0e10',
                  background: window.VAULT_TRACKS[slot.track].color,
                  width:11, height:11, borderRadius:'50%',
                  display:'flex', alignItems:'center', justifyContent:'center',
                  fontFamily:'JetBrains Mono', letterSpacing:0
                }}>{slot.track}</span>
              </div>
            ) : (
              <div style={{
                width:chipW, height:chipH, borderRadius:6,
                border: `1px dashed ${T.border}`,
                display:'flex', alignItems:'center', justifyContent:'center',
                color:T.textLow, fontSize:13, opacity:0.4
              }}>—</div>
            )}
          </React.Fragment>
        );
      })}
    </div>
  );
}

window.VaultV1 = VaultV1;
window.VaultV2 = VaultV2;
window.VaultV3 = VaultV3;
