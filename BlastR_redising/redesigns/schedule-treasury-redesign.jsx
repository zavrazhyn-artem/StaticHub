// Schedule + Treasury Redesign — BlastR

const schedStyles = {
  bg: '#0e0e10', surface: '#19191c', surfaceHigh: '#1f1f22', surfaceLow: '#131315',
  primary: '#4fd3f7', secondary: '#fa7902', tertiary: '#fcf266',
  error: '#ff6e84', success: '#39FF14',
  textHi: '#f9f5f8', textMid: '#adaaad', textLow: '#767577',
  border: 'rgba(255,255,255,0.06)', borderStrong: 'rgba(255,255,255,0.12)',
};

function ScheduleRedesign() {
  const s = schedStyles;
  const days = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Нд'];
  const cal = [
    [null, null, { d: 1 }, { d: 2, e: { t: '21:00–23:30', rsvp: '10/20', diff: 'M' } }, { d: 3 }, { d: 4 }, { d: 5 }],
    [{ d: 6 }, { d: 7 }, { d: 8, e: { t: '21:00–23:30', rsvp: '10/20', diff: 'M' } }, { d: 9, e: { t: '21:00–23:30', rsvp: '7/20', diff: 'H' } }, { d: 10 }, { d: 11 }, { d: 12 }],
    [{ d: 13, e: { t: '21:00–23:30', rsvp: '5/20', diff: 'H' } }, { d: 14 }, { d: 15, e: { t: '21:00–23:30', rsvp: '8/20', diff: 'H' } }, { d: 16, e: { t: '21:00–23:30', rsvp: '3/20', diff: 'H' } }, { d: 17 }, { d: 18 }, { d: 19 }],
    [{ d: 20, e: { t: '21:00–23:30', rsvp: '18/20', diff: 'M', live: true } }, { d: 21 }, { d: 22, e: { t: '21:00–23:30', rsvp: '17/20', diff: 'M' } }, { d: 23, e: { t: '21:00–23:30', rsvp: '16/20', diff: 'M' } }, { d: 24 }, { d: 25 }, { d: 26 }],
    [{ d: 27, e: { t: '21:00–23:30', rsvp: '14/20', diff: 'M', next: true } }, { d: 28 }, { d: 29, e: { t: '21:00–23:30', rsvp: '0/20', diff: 'M' } }, { d: 30, e: { t: '21:00–23:30', rsvp: '0/20', diff: 'M' } }, null, null, null],
  ];
  const diffColor = { M: s.error, H: s.tertiary, N: s.success };

  return (
    <div style={{ width: '100%', height: '100%', background: s.bg, color: s.textHi, fontFamily: 'Inter, sans-serif', fontSize: 12, display: 'grid', gridTemplateColumns: '220px 1fr', overflow: 'hidden' }}>
      <aside style={{ background: s.surfaceLow, borderRight: `1px solid ${s.border}`, padding: 12 }}>
        <div style={{ padding: '4px 8px 16px', display: 'flex', alignItems: 'center', gap: 10 }}>
          <div style={{ width: 28, height: 28, borderRadius: 6, background: s.primary, color: '#003040', fontWeight: 800, display: 'grid', placeItems: 'center' }}>B</div>
          <div style={{ fontWeight: 700 }}>BLASTR</div>
        </div>
        {['Огляд', 'Склад', 'Розклад', 'Boss Planner', 'Скарбниця', 'Аналітика', 'Екіпіровка'].map((l, i) => (
          <div key={i} style={{
            padding: '8px 10px', borderRadius: 6, fontSize: 12, fontWeight: 600,
            background: i === 2 ? 'rgba(79,211,247,0.10)' : 'transparent',
            color: i === 2 ? s.primary : s.textHi,
            borderLeft: i === 2 ? `2px solid ${s.primary}` : '2px solid transparent',
            marginBottom: 2
          }}>{l}</div>
        ))}
      </aside>

      <main style={{ overflow: 'auto', padding: '20px 24px' }}>
        <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', marginBottom: 16 }}>
          <div>
            <div style={{ fontSize: 11, color: s.textLow, letterSpacing: '0.12em' }}>РОЗКЛАД</div>
            <div style={{ fontSize: 22, fontWeight: 800, marginTop: 2 }}>Квітень 2026</div>
            <div style={{ fontSize: 12, color: s.textMid, marginTop: 4 }}>13 запланованих рейдів · 3 дні на тиждень</div>
          </div>
          <div style={{ display: 'flex', gap: 8 }}>
            <div style={{ display: 'flex', background: s.surfaceLow, borderRadius: 6, padding: 2 }}>
              {['Місяць', 'Тиждень', 'Список'].map((v, i) => (
                <button key={i} style={{
                  padding: '6px 12px', borderRadius: 4, border: 'none',
                  background: i === 0 ? s.surfaceHigh : 'transparent',
                  color: i === 0 ? s.textHi : s.textMid, fontSize: 11, fontWeight: 600, cursor: 'pointer'
                }}>{v}</button>
              ))}
            </div>
            <button style={{ padding: '6px 12px', borderRadius: 6, background: s.surface, border: `1px solid ${s.border}`, color: s.textHi, fontSize: 11, fontWeight: 600, cursor: 'pointer' }}>← Поп.</button>
            <button style={{ padding: '6px 12px', borderRadius: 6, background: s.surface, border: `1px solid ${s.border}`, color: s.textHi, fontSize: 11, fontWeight: 600, cursor: 'pointer' }}>Сьогодні</button>
            <button style={{ padding: '6px 12px', borderRadius: 6, background: s.primary, border: 'none', color: '#003040', fontSize: 11, fontWeight: 700, cursor: 'pointer' }}>+ Подія</button>
          </div>
        </div>

        {/* Day headers */}
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', gap: 4, marginBottom: 4 }}>
          {days.map((d, i) => (
            <div key={i} style={{
              padding: '8px 10px', fontSize: 10, color: i >= 5 ? s.primary : s.textLow,
              letterSpacing: '0.10em', fontWeight: 700
            }}>{d.toUpperCase()}</div>
          ))}
        </div>

        {/* Calendar */}
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', gap: 4 }}>
          {cal.flat().map((cell, i) => {
            if (!cell) return <div key={i} style={{ minHeight: 90 }}></div>;
            const isWeekend = (i % 7) >= 5;
            const today = cell.d === 25;
            return (
              <div key={i} style={{
                minHeight: 90, padding: 8, borderRadius: 6,
                background: today ? 'rgba(79,211,247,0.06)' : s.surface,
                border: today ? `1px solid ${s.primary}` : `1px solid ${s.border}`,
                cursor: 'pointer', transition: 'all .15s'
              }}>
                <div style={{
                  fontSize: 11, fontWeight: 700, marginBottom: 6,
                  color: today ? s.primary : (isWeekend ? s.textMid : s.textHi)
                }}>{cell.d}</div>
                {cell.e && (
                  <div style={{
                    padding: '6px 8px', borderRadius: 4,
                    background: cell.e.live ? `${diffColor[cell.e.diff]}20` : s.surfaceLow,
                    borderLeft: `3px solid ${diffColor[cell.e.diff]}`,
                    position: 'relative'
                  }}>
                    {cell.e.live && (
                      <div style={{
                        position: 'absolute', top: 4, right: 4, fontSize: 8,
                        color: s.success, fontWeight: 700,
                        display: 'flex', alignItems: 'center', gap: 3
                      }}>
                        <span style={{ width: 5, height: 5, borderRadius: '50%', background: s.success }}></span>LIVE
                      </div>
                    )}
                    {cell.e.next && (
                      <div style={{
                        position: 'absolute', top: 4, right: 4, fontSize: 8,
                        color: s.primary, fontWeight: 700
                      }}>↑ НАСТУПНИЙ</div>
                    )}
                    <div style={{ fontSize: 10, color: s.textHi, fontWeight: 600, fontFamily: 'monospace' }}>
                      {cell.e.t}
                    </div>
                    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginTop: 3 }}>
                      <span style={{ fontSize: 9, color: diffColor[cell.e.diff], fontWeight: 700 }}>
                        {cell.e.diff === 'M' ? 'МІТИК' : 'ГЕРОЇК'}
                      </span>
                      <span style={{ fontSize: 9, color: s.textMid, fontFamily: 'monospace' }}>{cell.e.rsvp}</span>
                    </div>
                  </div>
                )}
              </div>
            );
          })}
        </div>
      </main>
    </div>
  );
}

function TreasuryRedesign() {
  const s = schedStyles;
  return (
    <div style={{ width: '100%', height: '100%', background: s.bg, color: s.textHi, fontFamily: 'Inter, sans-serif', fontSize: 12, display: 'grid', gridTemplateColumns: '220px 1fr', overflow: 'hidden' }}>
      <aside style={{ background: s.surfaceLow, borderRight: `1px solid ${s.border}`, padding: 12 }}>
        <div style={{ padding: '4px 8px 16px', display: 'flex', alignItems: 'center', gap: 10 }}>
          <div style={{ width: 28, height: 28, borderRadius: 6, background: s.primary, color: '#003040', fontWeight: 800, display: 'grid', placeItems: 'center' }}>B</div>
          <div style={{ fontWeight: 700 }}>BLASTR</div>
        </div>
        {['Огляд', 'Склад', 'Розклад', 'Boss Planner', 'Скарбниця', 'Аналітика', 'Екіпіровка'].map((l, i) => (
          <div key={i} style={{
            padding: '8px 10px', borderRadius: 6, fontSize: 12, fontWeight: 600,
            background: i === 4 ? 'rgba(79,211,247,0.10)' : 'transparent',
            color: i === 4 ? s.primary : s.textHi,
            borderLeft: i === 4 ? `2px solid ${s.primary}` : '2px solid transparent',
            marginBottom: 2
          }}>{l}</div>
        ))}
      </aside>

      <main style={{ overflow: 'auto', padding: '20px 24px' }}>
        <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', marginBottom: 16 }}>
          <div>
            <div style={{ fontSize: 11, color: s.textLow, letterSpacing: '0.12em' }}>СКАРБНИЦЯ</div>
            <div style={{ fontSize: 22, fontWeight: 800, marginTop: 2 }}>Фінанси гільдії</div>
          </div>
          <div style={{ display: 'flex', gap: 8 }}>
            <button style={{ padding: '7px 12px', borderRadius: 6, background: s.surface, border: `1px solid ${s.border}`, color: s.textHi, fontSize: 11, fontWeight: 600, cursor: 'pointer' }}>− Витрата</button>
            <button style={{ padding: '7px 12px', borderRadius: 6, background: s.tertiary, border: 'none', color: '#1a1a00', fontSize: 11, fontWeight: 700, cursor: 'pointer' }}>+ Внесок</button>
          </div>
        </div>

        {/* Critical alert */}
        <div style={{
          padding: '12px 16px', borderRadius: 8, marginBottom: 16,
          background: 'rgba(255,110,132,0.08)', border: `1px solid ${s.error}44`,
          display: 'flex', alignItems: 'center', gap: 12
        }}>
          <div style={{ fontSize: 18 }}>⚠</div>
          <div style={{ flex: 1 }}>
            <div style={{ fontSize: 12, fontWeight: 700, color: s.error }}>Бюджет вичерпається за 6 днів</div>
            <div style={{ fontSize: 11, color: s.textMid, marginTop: 2 }}>Поточний баланс не покриває заплановані 3 рейд-дні. Потрібен збір 17 828g додатково.</div>
          </div>
          <button style={{ padding: '6px 12px', borderRadius: 5, background: s.error, border: 'none', color: '#fff', fontSize: 11, fontWeight: 700, cursor: 'pointer' }}>Запустити збір</button>
        </div>

        {/* Stats */}
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 12, marginBottom: 16 }}>
          <div style={{ padding: 16, borderRadius: 8, background: s.surface, border: `1px solid ${s.border}` }}>
            <div style={{ fontSize: 10, color: s.textLow, letterSpacing: '0.10em', marginBottom: 6 }}>ЗАГАЛЬНИЙ РЕЗЕРВ</div>
            <div style={{ fontSize: 28, fontWeight: 800, color: s.tertiary, fontFamily: 'monospace' }}>140 000<span style={{ fontSize: 14, color: s.textMid, marginLeft: 4 }}>g</span></div>
            <div style={{ fontSize: 11, color: s.success, marginTop: 4 }}>↑ +25 000g за тиждень</div>
          </div>
          <div style={{ padding: 16, borderRadius: 8, background: s.surface, border: `1px solid ${s.border}` }}>
            <div style={{ fontSize: 10, color: s.textLow, letterSpacing: '0.10em', marginBottom: 6 }}>ВНЕСОК / ГРАВЕЦЬ</div>
            <div style={{ fontSize: 28, fontWeight: 800, fontFamily: 'monospace' }}>10 000<span style={{ fontSize: 14, color: s.textMid, marginLeft: 4 }}>g</span></div>
            <div style={{ fontSize: 11, color: s.textMid, marginTop: 4 }}>обовʼязковий, тижневий</div>
          </div>
          <div style={{ padding: 16, borderRadius: 8, background: s.surface, border: `1px solid ${s.border}` }}>
            <div style={{ fontSize: 10, color: s.textLow, letterSpacing: '0.10em', marginBottom: 6 }}>АВТОНОМНІСТЬ</div>
            <div style={{ fontSize: 28, fontWeight: 800, color: s.error, fontFamily: 'monospace' }}>0.9<span style={{ fontSize: 14, color: s.textMid, marginLeft: 4 }}>тиж</span></div>
            <div style={{ fontSize: 11, color: s.textMid, marginTop: 4 }}>при 157 828g/тиж витрат</div>
          </div>
        </div>

        {/* Contributions */}
        <div style={{ display: 'grid', gridTemplateColumns: '1.4fr 1fr', gap: 12 }}>
          <div style={{ padding: 16, borderRadius: 8, background: s.surface, border: `1px solid ${s.border}` }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 12 }}>
              <div style={{ fontSize: 13, fontWeight: 700 }}>Тижневі внески</div>
              <div style={{ fontSize: 11, color: s.textMid }}>5 з 20 покрили</div>
            </div>
            <div style={{ height: 6, background: s.surfaceLow, borderRadius: 3, marginBottom: 16, overflow: 'hidden' }}>
              <div style={{ width: '25%', height: '100%', background: s.tertiary }}></div>
            </div>
            {[
              { n: 'Alcomagic', s: 'paid', a: '+10 000', cls: '#3FC7EB' },
              { n: 'Norsemilian', s: 'paid', a: '+10 000', cls: '#FFFFFF' },
              { n: 'Wildblood', s: 'paid', a: '+10 000', cls: '#FF7C0A' },
              { n: 'Dexter', s: 'partial', a: '+5 000', cls: '#C41F3B' },
              { n: 'Killork', s: 'late', a: '0', cls: '#C69B6D' },
              { n: 'Ahrni', s: 'late', a: '0', cls: '#33937F' },
            ].map((p, i) => (
              <div key={i} style={{
                display: 'flex', alignItems: 'center', gap: 12, padding: '8px 0',
                borderTop: i > 0 ? `1px solid ${s.border}` : 'none'
              }}>
                <div style={{ width: 6, height: 6, borderRadius: '50%', background: p.s === 'paid' ? s.success : p.s === 'partial' ? s.tertiary : s.error }}></div>
                <div style={{ flex: 1, fontSize: 12, fontWeight: 600, color: p.cls }}>{p.n}</div>
                <div style={{
                  fontSize: 10, padding: '2px 8px', borderRadius: 10,
                  background: p.s === 'paid' ? 'rgba(57,255,20,0.10)' : p.s === 'partial' ? 'rgba(252,242,102,0.10)' : 'rgba(255,110,132,0.10)',
                  color: p.s === 'paid' ? s.success : p.s === 'partial' ? s.tertiary : s.error,
                  fontWeight: 700
                }}>
                  {p.s === 'paid' ? 'ОПЛАЧЕНО' : p.s === 'partial' ? 'ЧАСТКОВО' : 'НЕ ПОКРИТО'}
                </div>
                <div style={{ fontSize: 12, fontFamily: 'monospace', color: p.s === 'late' ? s.textLow : s.textHi, fontWeight: 700, minWidth: 70, textAlign: 'right' }}>{p.a}g</div>
              </div>
            ))}
          </div>

          <div style={{ padding: 16, borderRadius: 8, background: s.surface, border: `1px solid ${s.border}` }}>
            <div style={{ fontSize: 13, fontWeight: 700, marginBottom: 12 }}>Тижневий план потреб</div>
            {[
              { n: 'Voldright Potion Cauldron', q: 1, p: 25000, c: '#a855f7' },
              { n: 'Cauldron of Sin\'dorei Flasks', q: 1, p: 30000, c: s.error },
              { n: 'Hearty Horander Celebration', q: 3, p: 15000, c: s.tertiary },
            ].map((it, i) => (
              <div key={i} style={{
                display: 'flex', alignItems: 'center', gap: 10, padding: '10px 0',
                borderTop: i > 0 ? `1px solid ${s.border}` : 'none'
              }}>
                <div style={{ width: 28, height: 28, borderRadius: 5, background: `${it.c}22`, border: `1px solid ${it.c}55` }}></div>
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ fontSize: 11, fontWeight: 600, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{it.n}</div>
                  <div style={{ fontSize: 10, color: s.textMid, fontFamily: 'monospace' }}>{it.p}g × {it.q}</div>
                </div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 4 }}>
                  <button style={{ width: 22, height: 22, borderRadius: 4, background: s.surfaceLow, border: `1px solid ${s.border}`, color: s.textHi, cursor: 'pointer' }}>−</button>
                  <span style={{ minWidth: 18, textAlign: 'center', fontSize: 12, fontWeight: 700 }}>{it.q}</span>
                  <button style={{ width: 22, height: 22, borderRadius: 4, background: s.surfaceLow, border: `1px solid ${s.border}`, color: s.textHi, cursor: 'pointer' }}>+</button>
                </div>
              </div>
            ))}
            <div style={{ marginTop: 12, padding: '10px 12px', borderRadius: 6, background: s.surfaceLow, display: 'flex', justifyContent: 'space-between' }}>
              <span style={{ fontSize: 11, color: s.textMid }}>Усього на тиждень</span>
              <span style={{ fontSize: 14, fontWeight: 800, color: s.tertiary, fontFamily: 'monospace' }}>157 828g</span>
            </div>
          </div>
        </div>
      </main>
    </div>
  );
}

window.ScheduleRedesign = ScheduleRedesign;
window.TreasuryRedesign = TreasuryRedesign;
