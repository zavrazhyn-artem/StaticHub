// Roster Redesign — BlastR
// Кращий читабельний матричний інтерфейс прогресу

const rosterStyles = {
  bg: '#0e0e10',
  surface: '#19191c',
  surfaceHigh: '#1f1f22',
  surfaceLow: '#131315',
  primary: '#4fd3f7',
  secondary: '#fa7902',
  tertiary: '#fcf266',
  error: '#ff6e84',
  success: '#39FF14',
  textHi: '#f9f5f8',
  textMid: '#adaaad',
  textLow: '#767577',
  border: 'rgba(255,255,255,0.06)',
  borderStrong: 'rgba(255,255,255,0.12)',
};

function RosterRedesign() {
  const s = rosterStyles;
  const players = [
    { name: 'Zavrikk', cls: 'Druid', clsColor: '#FF7C0A', spec: 'Guardian', role: 'Tank', kills: [1, 1, 1, 0, 0, 0, 1, 0, 0] },
    { name: 'Chong', cls: 'Paladin', clsColor: '#F48CBA', spec: 'Protection', role: 'Tank', kills: [1, 1, 1, 0, 0, 0, 1, 0, 0] },
    { name: 'Yazneloh', cls: 'Warlock', clsColor: '#8788EE', spec: 'Demonology', role: 'Heal', kills: [1, 1, 1, 0, 0, 0, 1, 0, 0] },
    { name: 'Mcmorrio', cls: 'Mage', clsColor: '#3FC7EB', spec: 'Frost', role: 'Heal', bench: true, kills: [0, 0, 0, 0, 0, 0, 0, 0, 0] },
    { name: 'Pristula', cls: 'Priest', clsColor: '#FFFFFF', spec: 'Holy', role: 'Heal', kills: [0, 0, 0, 0, 0, 0, 0, 0, 0] },
    { name: 'Lumiral', cls: 'Druid', clsColor: '#FF7C0A', spec: 'Restoration', role: 'Heal', kills: [1, 1, 1, 0, 0, 0, 0, 0, 0] },
    { name: 'Ahrni', cls: 'Evoker', clsColor: '#33937F', spec: 'Preservation', role: 'Heal', kills: [1, 1, 1, 0, 0, 0, 1, 0, 0] },
  ];
  const wings = [
    { name: 'The Voidsphere', bosses: ['Imperator Averzian', 'Vorasius', 'Fallen-King Salhadaar', 'Vaelgor & Ezzorak', 'Lightblinded Vanguard'] },
    { name: 'The Dreamrift', bosses: ['Crown of the Cosmos', 'Chimaerus, the Undreamt God'] },
    { name: 'March on Quel\'Danas', bosses: ['Beloren, Child of Al\'ar', 'Midnight Falls'] },
  ];

  return (
    <div style={{
      width: '100%', height: '100%', background: s.bg, color: s.textHi,
      fontFamily: 'Inter, sans-serif', fontSize: 12,
      display: 'grid', gridTemplateColumns: '220px 1fr', overflow: 'hidden'
    }}>
      <aside style={{
        background: s.surfaceLow, borderRight: `1px solid ${s.border}`, padding: 12
      }}>
        <div style={{ padding: '4px 8px 16px', display: 'flex', alignItems: 'center', gap: 10 }}>
          <div style={{ width: 28, height: 28, borderRadius: 6, background: s.primary, color: '#003040', fontWeight: 800, display: 'grid', placeItems: 'center' }}>B</div>
          <div style={{ fontWeight: 700 }}>BLASTR</div>
        </div>
        <div style={{ padding: '6px 8px', fontSize: 11, color: s.textLow, letterSpacing: '0.10em' }}>СТАТІК</div>
        <div style={{ padding: '0 8px 12px', fontWeight: 700 }}>Характерники</div>
        <div style={{ height: 1, background: s.border, marginBottom: 8 }}></div>
        {[
          { l: 'Огляд' }, { l: 'Склад', active: true }, { l: 'Розклад' },
          { l: 'Boss Planner' }, { l: 'Скарбниця' }, { l: 'Аналітика' }, { l: 'Екіпіровка' }
        ].map((it, i) => (
          <div key={i} style={{
            padding: '8px 10px', borderRadius: 6, fontSize: 12, fontWeight: 600,
            background: it.active ? 'rgba(79,211,247,0.10)' : 'transparent',
            color: it.active ? s.primary : s.textHi,
            borderLeft: it.active ? `2px solid ${s.primary}` : '2px solid transparent',
            marginBottom: 2
          }}>{it.l}</div>
        ))}
      </aside>

      <main style={{ overflow: 'auto', padding: '20px 24px' }}>
        <div style={{ display: 'flex', alignItems: 'baseline', justifyContent: 'space-between', marginBottom: 16 }}>
          <div>
            <div style={{ fontSize: 11, color: s.textLow, letterSpacing: '0.12em' }}>СКЛАД РЕЙДУ</div>
            <div style={{ fontSize: 22, fontWeight: 800, marginTop: 2 }}>Прогрес кожного гравця</div>
            <div style={{ fontSize: 12, color: s.textMid, marginTop: 4 }}>9 босів · 7 з 20 гравців онлайн</div>
          </div>
          <div style={{ display: 'flex', gap: 8 }}>
            <div style={{ display: 'flex', background: s.surfaceLow, borderRadius: 6, padding: 2 }}>
              {['Звичайно', 'Героїк', 'Мітик'].map((d, i) => (
                <button key={i} style={{
                  padding: '6px 12px', borderRadius: 4, border: 'none',
                  background: i === 2 ? s.error : 'transparent',
                  color: i === 2 ? '#fff' : s.textMid, fontSize: 11, fontWeight: 700, cursor: 'pointer'
                }}>{d}</button>
              ))}
            </div>
          </div>
        </div>

        {/* Filter strip */}
        <div style={{
          display: 'flex', alignItems: 'center', gap: 12, padding: '10px 14px',
          background: s.surface, borderRadius: 8, marginBottom: 12,
          border: `1px solid ${s.border}`
        }}>
          <input placeholder="Шукати гравця..." style={{
            flex: 1, padding: '6px 10px', borderRadius: 5, background: s.surfaceLow,
            border: `1px solid ${s.border}`, color: s.textHi, fontSize: 12
          }} />
          {['Усі ролі', 'Танки', 'Хіли', 'DPS'].map((f, i) => (
            <button key={i} style={{
              padding: '6px 12px', borderRadius: 5, border: `1px solid ${s.border}`,
              background: i === 0 ? s.surfaceHigh : 'transparent',
              color: s.textHi, fontSize: 11, fontWeight: 600, cursor: 'pointer'
            }}>{f}</button>
          ))}
        </div>

        {/* Matrix */}
        <div style={{
          background: s.surface, borderRadius: 8, border: `1px solid ${s.border}`,
          overflow: 'hidden'
        }}>
          {/* Header */}
          <div style={{
            display: 'grid',
            gridTemplateColumns: `220px repeat(${wings.reduce((a, w) => a + w.bosses.length, 0)}, 1fr)`,
            background: s.surfaceLow, borderBottom: `1px solid ${s.borderStrong}`
          }}>
            <div style={{ padding: '10px 14px', fontSize: 10, color: s.textLow, letterSpacing: '0.10em' }}>ГРАВЕЦЬ</div>
            {wings.map((w, wi) => w.bosses.map((b, bi) => (
              <div key={`${wi}-${bi}`} style={{
                padding: '10px 4px', fontSize: 9, color: s.textMid,
                writingMode: 'vertical-rl', transform: 'rotate(180deg)',
                textAlign: 'left', borderLeft: bi === 0 ? `2px solid ${s.primary}` : `1px solid ${s.border}`,
                whiteSpace: 'nowrap', overflow: 'hidden', height: 110
              }}>{b}</div>
            )))}
          </div>

          {/* Rows by role */}
          {[
            { role: 'Танки', count: 2, color: '#3b82f6' },
            { role: 'Хіли', count: 5, color: s.success }
          ].map((g, gi) => (
            <div key={gi}>
              <div style={{
                padding: '8px 14px', fontSize: 10, color: g.color, fontWeight: 700,
                letterSpacing: '0.10em', background: 'rgba(255,255,255,0.02)',
                borderTop: gi > 0 ? `1px solid ${s.border}` : 'none'
              }}>
                {g.role.toUpperCase()} · {g.count}
              </div>
              {players.filter(p => g.role === 'Танки' ? p.role === 'Tank' : p.role === 'Heal').map((p, pi) => (
                <div key={pi} style={{
                  display: 'grid',
                  gridTemplateColumns: `220px repeat(${wings.reduce((a, w) => a + w.bosses.length, 0)}, 1fr)`,
                  borderTop: pi > 0 ? `1px solid ${s.border}` : 'none',
                  background: pi % 2 === 1 ? 'rgba(255,255,255,0.015)' : 'transparent',
                  alignItems: 'center'
                }}>
                  <div style={{ padding: '8px 14px', display: 'flex', alignItems: 'center', gap: 8 }}>
                    <div style={{
                      width: 24, height: 24, borderRadius: 4,
                      background: `linear-gradient(135deg, ${p.clsColor}55, ${p.clsColor}22)`,
                      border: `1px solid ${p.clsColor}66`
                    }}></div>
                    <div style={{ minWidth: 0, flex: 1 }}>
                      <div style={{
                        fontSize: 12, fontWeight: 600, color: p.clsColor,
                        display: 'flex', alignItems: 'center', gap: 6
                      }}>
                        {p.name}
                        {p.bench && <span style={{
                          fontSize: 8, padding: '1px 5px', borderRadius: 3,
                          background: s.error, color: '#fff', fontWeight: 700
                        }}>ЗАПАС</span>}
                      </div>
                      <div style={{ fontSize: 10, color: s.textMid }}>{p.spec}</div>
                    </div>
                  </div>
                  {wings.flatMap((w, wi) => w.bosses.map((b, bi) => {
                    const idx = wings.slice(0, wi).reduce((a, ww) => a + ww.bosses.length, 0) + bi;
                    const k = p.kills[idx];
                    return (
                      <div key={`${wi}-${bi}`} style={{
                        padding: '10px 0', display: 'grid', placeItems: 'center',
                        borderLeft: bi === 0 ? `2px solid ${s.primary}55` : `1px solid ${s.border}`
                      }}>
                        {k === 1 ? (
                          <div style={{
                            width: 18, height: 18, borderRadius: 3,
                            background: 'rgba(252,242,102,0.12)',
                            border: `1px solid ${s.tertiary}55`,
                            display: 'grid', placeItems: 'center', color: s.tertiary, fontSize: 11
                          }}>✓</div>
                        ) : (
                          <div style={{ width: 8, height: 8, borderRadius: '50%', background: 'rgba(255,255,255,0.08)' }}></div>
                        )}
                      </div>
                    );
                  }))}
                </div>
              ))}
            </div>
          ))}
        </div>
      </main>
    </div>
  );
}

window.RosterRedesign = RosterRedesign;
