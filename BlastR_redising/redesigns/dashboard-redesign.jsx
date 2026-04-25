// Dashboard Redesign — BlastR
// Чистіша візуальна ієрархія, кращий контраст, нові акценти

const dashStyles = {
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

function DashboardRedesign() {
  const s = dashStyles;
  return (
    <div style={{
      width: '100%', height: '100%', background: s.bg, color: s.textHi,
      fontFamily: 'Inter, -apple-system, sans-serif', fontSize: 13,
      display: 'grid', gridTemplateColumns: '220px 1fr', overflow: 'hidden'
    }}>
      {/* Sidebar */}
      <aside style={{
        background: s.surfaceLow, borderRight: `1px solid ${s.border}`,
        padding: '16px 12px', display: 'flex', flexDirection: 'column', gap: 4
      }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '4px 8px 16px' }}>
          <div style={{
            width: 28, height: 28, borderRadius: 6, background: s.primary,
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            fontWeight: 800, color: '#003040', fontSize: 14
          }}>B</div>
          <div style={{ fontWeight: 700, letterSpacing: '0.04em' }}>BLASTR</div>
        </div>

        <div style={{ padding: '10px 8px 6px', display: 'flex', flexDirection: 'column', gap: 2 }}>
          <div style={{ fontSize: 10, color: s.textLow, letterSpacing: '0.12em', fontWeight: 600 }}>СТАТІК</div>
          <div style={{ fontWeight: 700, fontSize: 14 }}>Характерники</div>
          <div style={{ fontSize: 11, color: s.primary, letterSpacing: '0.04em' }}>Mythic Progression</div>
        </div>

        <div style={{ height: 1, background: s.border, margin: '8px 0' }}></div>

        {[
          { i: '◧', l: 'Огляд', active: true },
          { i: '◍', l: 'Склад' },
          { i: '▤', l: 'Розклад' },
          { i: '◎', l: 'Boss Planner' },
          { i: '◈', l: 'Скарбниця', badge: '0.9т' },
          { i: '◐', l: 'Аналітика' },
          { i: '◇', l: 'Екіпіровка' },
        ].map((it, i) => (
          <div key={i} style={{
            display: 'flex', alignItems: 'center', gap: 10, padding: '8px 10px',
            borderRadius: 6, fontSize: 12, fontWeight: 600,
            background: it.active ? 'rgba(79,211,247,0.10)' : 'transparent',
            color: it.active ? s.primary : s.textHi,
            borderLeft: it.active ? `2px solid ${s.primary}` : '2px solid transparent',
            cursor: 'pointer'
          }}>
            <span style={{ fontSize: 14, opacity: 0.7 }}>{it.i}</span>
            <span style={{ flex: 1 }}>{it.l}</span>
            {it.badge && <span style={{
              fontSize: 9, padding: '2px 6px', borderRadius: 8,
              background: 'rgba(255,110,132,0.15)', color: s.error, fontWeight: 700
            }}>{it.badge}</span>}
          </div>
        ))}

        <div style={{ flex: 1 }}></div>
        <div style={{ height: 1, background: s.border, margin: '8px 0' }}></div>
        {[{ i: '◉', l: 'Мої персонажі' }, { i: '⚙', l: 'Налаштування' }].map((it, i) => (
          <div key={i} style={{
            display: 'flex', alignItems: 'center', gap: 10, padding: '8px 10px',
            fontSize: 12, color: s.textMid, cursor: 'pointer'
          }}>
            <span>{it.i}</span><span>{it.l}</span>
          </div>
        ))}
      </aside>

      {/* Main */}
      <main style={{ overflow: 'auto', padding: '20px 28px 32px' }}>
        {/* Top bar */}
        <div style={{
          display: 'flex', alignItems: 'center', justifyContent: 'space-between',
          marginBottom: 18
        }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
            <div style={{ fontSize: 11, color: s.textLow, letterSpacing: '0.10em' }}>
              ОГЛЯД · ПОНЕДІЛОК, 27 КВІТНЯ
            </div>
            <div style={{
              display: 'flex', alignItems: 'center', gap: 6, padding: '3px 10px',
              borderRadius: 12, background: 'rgba(57,255,20,0.10)', fontSize: 10,
              color: s.success, fontWeight: 700, letterSpacing: '0.06em'
            }}>
              <span style={{ width: 6, height: 6, borderRadius: '50%', background: s.success }}></span>
              НА ЗВ'ЯЗКУ
            </div>
          </div>
          <div style={{ display: 'flex', gap: 8 }}>
            <button style={{
              padding: '7px 14px', borderRadius: 6, background: s.surface,
              border: `1px solid ${s.borderStrong}`, color: s.textHi, fontSize: 12,
              fontWeight: 600, cursor: 'pointer'
            }}>+ Запросити гравця</button>
            <button style={{
              padding: '7px 14px', borderRadius: 6, background: s.primary,
              border: 'none', color: '#003040', fontSize: 12, fontWeight: 700, cursor: 'pointer'
            }}>Анонс рейду</button>
          </div>
        </div>

        {/* Hero — Next Raid */}
        <div style={{
          padding: '20px 24px', borderRadius: 10, marginBottom: 16,
          background: `linear-gradient(135deg, ${s.surfaceHigh}, ${s.surface})`,
          border: `1px solid ${s.borderStrong}`,
          display: 'grid', gridTemplateColumns: '1.4fr 1fr', gap: 24
        }}>
          <div>
            <div style={{ fontSize: 10, color: s.textLow, letterSpacing: '0.14em', marginBottom: 6 }}>
              НАСТУПНИЙ РЕЙД · ЧЕРЕЗ
            </div>
            <div style={{
              display: 'flex', alignItems: 'baseline', gap: 14, marginBottom: 10,
              fontFamily: 'JetBrains Mono, monospace'
            }}>
              {[{ v: '02', l: 'д' }, { v: '07', l: 'г' }, { v: '11', l: 'хв' }, { v: '16', l: 'с' }].map((t, i) => (
                <div key={i}>
                  <span style={{ fontSize: 38, fontWeight: 800, color: s.textHi, letterSpacing: '-0.02em' }}>{t.v}</span>
                  <span style={{ fontSize: 13, color: s.textMid, marginLeft: 3 }}>{t.l}</span>
                </div>
              ))}
            </div>
            <div style={{ fontSize: 14, fontWeight: 600 }}>
              The Voidsphere · Mythic
            </div>
            <div style={{ fontSize: 12, color: s.textMid, marginTop: 2 }}>
              Понеділок, 27 квітня · 21:00–23:30 EET
            </div>
            <div style={{ display: 'flex', gap: 16, marginTop: 14 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                <span style={{ width: 8, height: 8, borderRadius: '50%', background: s.success }}></span>
                <span style={{ fontSize: 11, color: s.textMid }}>Опубліковано в Discord</span>
              </div>
              <div style={{ fontSize: 11, color: s.textMid }}>· 18/20 RSVP</div>
            </div>
          </div>

          {/* Roles */}
          <div>
            <div style={{ fontSize: 10, color: s.textLow, letterSpacing: '0.14em', marginBottom: 12 }}>
              ГОТОВНІСТЬ РОЛЕЙ
            </div>
            {[
              { l: 'Танки', v: 2, max: 2, c: '#3b82f6' },
              { l: 'Хіли', v: 4, max: 4, c: s.success },
              { l: 'Мілі', v: 6, max: 8, c: s.error },
              { l: 'Рендж', v: 6, max: 10, c: '#a855f7' },
            ].map((r, i) => (
              <div key={i} style={{ marginBottom: 8 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 3, fontSize: 11 }}>
                  <span style={{ color: s.textMid }}>{r.l}</span>
                  <span style={{ fontFamily: 'monospace', color: s.textHi }}>{r.v}/{r.max}</span>
                </div>
                <div style={{ height: 4, background: 'rgba(255,255,255,0.06)', borderRadius: 2, overflow: 'hidden' }}>
                  <div style={{ width: `${r.v / r.max * 100}%`, height: '100%', background: r.c, borderRadius: 2 }}></div>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Progression matrix — clearer */}
        <div style={{
          padding: 18, borderRadius: 10, marginBottom: 16,
          background: s.surface, border: `1px solid ${s.border}`
        }}>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 }}>
            <div>
              <div style={{ fontSize: 13, fontWeight: 700 }}>Прогресія рейдів</div>
              <div style={{ fontSize: 11, color: s.textMid, marginTop: 2 }}>9 босів · 2 інстанси · сезон 2</div>
            </div>
            <div style={{ display: 'flex', gap: 14, fontSize: 11, color: s.textMid }}>
              <span><span style={{ color: s.error }}>■</span> Мітик 2/9</span>
              <span><span style={{ color: s.tertiary }}>■</span> Героїк 7/9</span>
              <span><span style={{ color: s.success }}>■</span> Звичайно 8/9</span>
            </div>
          </div>

          {/* Wing 1 */}
          <div style={{ marginBottom: 14 }}>
            <div style={{ fontSize: 10, color: s.textLow, letterSpacing: '0.10em', marginBottom: 6 }}>
              THE VOIDSPHERE
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(5, 1fr)', gap: 6 }}>
              {[
                { n: 'Imperator Averzian', s: 'M', c: s.error },
                { n: 'Vorasius', s: 'M', c: s.error },
                { n: 'Fallen-King Salhadaar', s: 'H', c: s.tertiary },
                { n: 'Vaelgor & Ezzorak', s: 'H', c: s.tertiary },
                { n: 'Lightblinded Vanguard', s: 'H', c: s.tertiary },
              ].map((b, i) => (
                <div key={i} style={{
                  padding: '10px 12px', borderRadius: 6,
                  background: s.surfaceLow, border: `1px solid ${s.border}`,
                  borderLeft: `3px solid ${b.c}`
                }}>
                  <div style={{ fontSize: 10, color: s.textMid, marginBottom: 4 }}>#{i + 1}</div>
                  <div style={{ fontSize: 11, fontWeight: 600, lineHeight: 1.25, color: s.textHi }}>{b.n}</div>
                  <div style={{ fontSize: 10, color: b.c, fontWeight: 700, marginTop: 4 }}>{b.s === 'M' ? 'МІТИК' : 'ГЕРОЇК'}</div>
                </div>
              ))}
            </div>
          </div>

          {/* Wing 2 + 3 */}
          <div style={{ display: 'grid', gridTemplateColumns: '3fr 2fr', gap: 12 }}>
            <div>
              <div style={{ fontSize: 10, color: s.textLow, letterSpacing: '0.10em', marginBottom: 6 }}>
                THE DREAMRIFT
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 6 }}>
                {[
                  { n: 'Crown of the Cosmos', s: 'H', c: s.tertiary },
                  { n: 'Chimaerus, the Undreamt God', s: 'M', c: s.error },
                  { n: 'Beloren, Child of Al\'ar', s: 'H', c: s.tertiary },
                ].map((b, i) => (
                  <div key={i} style={{
                    padding: '10px 12px', borderRadius: 6,
                    background: s.surfaceLow, border: `1px solid ${s.border}`,
                    borderLeft: `3px solid ${b.c}`
                  }}>
                    <div style={{ fontSize: 10, color: s.textMid, marginBottom: 4 }}>#{i + 6}</div>
                    <div style={{ fontSize: 11, fontWeight: 600 }}>{b.n}</div>
                    <div style={{ fontSize: 10, color: b.c, fontWeight: 700, marginTop: 4 }}>{b.s === 'M' ? 'МІТИК' : 'ГЕРОЇК'}</div>
                  </div>
                ))}
              </div>
            </div>
            <div>
              <div style={{ fontSize: 10, color: s.textLow, letterSpacing: '0.10em', marginBottom: 6 }}>
                MARCH ON QUEL'DANAS
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(1, 1fr)', gap: 6 }}>
                <div style={{
                  padding: '10px 12px', borderRadius: 6,
                  background: s.surfaceLow, border: `1px solid ${s.border}`,
                  borderLeft: `3px solid ${s.textLow}`
                }}>
                  <div style={{ fontSize: 10, color: s.textMid, marginBottom: 4 }}>#9</div>
                  <div style={{ fontSize: 11, fontWeight: 600 }}>Midnight Falls</div>
                  <div style={{ fontSize: 10, color: s.textLow, fontWeight: 700, marginTop: 4 }}>НЕ ВБИТО</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Three columns */}
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 12 }}>
          {/* Treasury */}
          <div style={{
            padding: 16, borderRadius: 10, background: s.surface,
            border: `1px solid ${s.border}`
          }}>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 }}>
              <div style={{ fontSize: 12, fontWeight: 700, color: s.textHi }}>Скарбниця</div>
              <div style={{ fontSize: 10, color: s.error, fontWeight: 700 }}>↓ КРИТИЧНО</div>
            </div>
            <div style={{ fontSize: 26, fontWeight: 800, color: s.tertiary, fontFamily: 'monospace' }}>
              140 000<span style={{ fontSize: 13, color: s.textMid, marginLeft: 6 }}>g</span>
            </div>
            <div style={{ fontSize: 11, color: s.textMid, marginTop: 2 }}>
              Автономність: <span style={{ color: s.error, fontWeight: 700 }}>0.9 тижня</span>
            </div>
            <div style={{
              marginTop: 12, padding: '8px 10px', borderRadius: 6,
              background: 'rgba(255,110,132,0.08)', fontSize: 10, color: s.error, lineHeight: 1.5
            }}>
              ⚠ Бюджет вичерпається до пʼятниці. Призначте збір внесків.
            </div>
          </div>

          {/* Sync */}
          <div style={{
            padding: 16, borderRadius: 10, background: s.surface,
            border: `1px solid ${s.border}`
          }}>
            <div style={{ fontSize: 12, fontWeight: 700, marginBottom: 12 }}>Інтеграції</div>
            {[
              { l: 'Battle.net', v: '15 хв тому', ok: true },
              { l: 'Raider.IO', v: '32 хв тому', ok: true },
              { l: 'Warcraft Logs', v: 'Помилка авторизації', ok: false },
            ].map((sync, i) => (
              <div key={i} style={{
                display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                padding: '8px 0', borderBottom: i < 2 ? `1px solid ${s.border}` : 'none'
              }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                  <span style={{
                    width: 6, height: 6, borderRadius: '50%',
                    background: sync.ok ? s.success : s.error
                  }}></span>
                  <span style={{ fontSize: 11, fontWeight: 600 }}>{sync.l}</span>
                </div>
                <span style={{ fontSize: 10, color: sync.ok ? s.textMid : s.error }}>{sync.v}</span>
              </div>
            ))}
          </div>

          {/* Upcoming */}
          <div style={{
            padding: 16, borderRadius: 10, background: s.surface,
            border: `1px solid ${s.border}`
          }}>
            <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 }}>
              <div style={{ fontSize: 12, fontWeight: 700 }}>Майбутні рейди</div>
              <span style={{ fontSize: 10, color: s.primary, cursor: 'pointer' }}>усі →</span>
            </div>
            {[
              { d: 'СР', n: '29', t: '21:00', rsvp: '14/20', c: s.tertiary },
              { d: 'ЧТ', n: '30', t: '21:00', rsvp: '8/20', c: s.tertiary },
              { d: 'ПН', n: '04', t: '21:00', rsvp: '0/20', c: s.textLow },
            ].map((r, i) => (
              <div key={i} style={{
                display: 'flex', alignItems: 'center', gap: 10, padding: '8px 0',
                borderBottom: i < 2 ? `1px solid ${s.border}` : 'none'
              }}>
                <div style={{
                  width: 32, textAlign: 'center', borderLeft: `2px solid ${r.c}`,
                  paddingLeft: 6
                }}>
                  <div style={{ fontSize: 9, color: s.textMid, letterSpacing: '0.06em' }}>{r.d}</div>
                  <div style={{ fontSize: 14, fontWeight: 700 }}>{r.n}</div>
                </div>
                <div style={{ flex: 1 }}>
                  <div style={{ fontSize: 11, fontWeight: 600 }}>{r.t}</div>
                  <div style={{ fontSize: 10, color: s.textMid }}>The Voidsphere · M</div>
                </div>
                <div style={{ fontSize: 10, color: s.textMid, fontFamily: 'monospace' }}>{r.rsvp}</div>
              </div>
            ))}
          </div>
        </div>
      </main>
    </div>
  );
}

window.DashboardRedesign = DashboardRedesign;
