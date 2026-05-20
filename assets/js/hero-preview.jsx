// Hero UI preview — a custom dashboard mockup (NOT copying any branded UI)
const HeroPreview = () => {
  return (
    <div style={{ display: 'grid', gridTemplateColumns: '220px 1fr', minHeight: 460 }}>
      {/* Sidebar */}
      <div style={{ borderRight: '0.5px solid var(--line)', background: '#FAFAFB', padding: '20px 14px', display: 'flex', flexDirection: 'column', gap: 4 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '0 8px 16px' }}>
          <div style={{ width: 24, height: 24, borderRadius: 7, background: 'var(--teal-600)', color: '#fff', display: 'grid', placeItems: 'center', fontSize: 11, fontWeight: 700 }}>M</div>
          <span style={{ fontSize: 13, fontWeight: 600, letterSpacing: '-0.2px' }}>Sunrise Clinic</span>
        </div>
        {[
          { i: 'cal', l: 'Today', active: true, b: 8 },
          { i: 'records', l: 'Patients' },
          { i: 'rx', l: 'Prescriptions' },
          { i: 'pill', l: 'Pharmacy', b: 3 },
          { i: 'lab', l: 'Lab orders' },
          { i: 'video', l: 'Telemedicine' },
          { i: 'graph', l: 'Analytics' },
        ].map((x, i) => (
          <div key={i} style={{
            display: 'flex', alignItems: 'center', gap: 10,
            padding: '7px 10px',
            borderRadius: 7,
            fontSize: 12.5,
            fontWeight: 500,
            color: x.active ? 'var(--ink)' : 'var(--mute)',
            background: x.active ? '#fff' : 'transparent',
            boxShadow: x.active ? '0 1px 2px rgba(0,0,0,0.05)' : 'none',
          }}>
            <Icon name={x.i} size={15} />
            <span style={{ flex: 1 }}>{x.l}</span>
            {x.b && <span style={{ fontSize: 10, background: 'var(--teal-600)', color: '#fff', padding: '1px 6px', borderRadius: 8 }}>{x.b}</span>}
          </div>
        ))}
        <div style={{ flex: 1 }} />
        <div style={{ fontSize: 10, color: 'var(--mute)', padding: '8px 10px', borderTop: '0.5px solid var(--line)', marginTop: 14 }}>
          <div style={{ fontWeight: 500, color: 'var(--ink-2)', marginBottom: 3 }}>Dr. A. Sharma</div>
          GP · 12 modules
        </div>
      </div>

      {/* Main */}
      <div style={{ padding: '20px 24px', background: '#fff' }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 18 }}>
          <div>
            <div style={{ fontSize: 11, color: 'var(--mute)', textTransform: 'uppercase', letterSpacing: '0.08em', fontWeight: 500 }}>Monday · 18 May</div>
            <div style={{ fontSize: 22, fontWeight: 300, letterSpacing: '-0.5px', marginTop: 2 }}>Good morning, Aarav.</div>
          </div>
          <div style={{ display: 'flex', gap: 8 }}>
            <div style={{ fontSize: 11, padding: '5px 10px', background: 'var(--teal-50)', color: 'var(--teal-800)', borderRadius: 8, fontWeight: 500 }}>14 visits today</div>
            <div style={{ fontSize: 11, padding: '5px 10px', background: 'var(--bg-2)', color: 'var(--ink-2)', borderRadius: 8, fontWeight: 500 }}>3 in waiting</div>
          </div>
        </div>

        {/* Stats row */}
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 10, marginBottom: 18 }}>
          {[
            { l: 'Avg wait', v: '8 min', d: '−2 vs last wk', up: false },
            { l: 'Revenue', v: '$2,140', d: '+18% MTD', up: true },
            { l: 'Retention', v: '92%', d: 'Q1 cohort', up: true },
          ].map((s, i) => (
            <div key={i} style={{ border: '0.5px solid var(--line)', borderRadius: 10, padding: '10px 12px' }}>
              <div style={{ fontSize: 11, color: 'var(--mute)' }}>{s.l}</div>
              <div style={{ fontSize: 20, fontWeight: 400, letterSpacing: '-0.5px', marginTop: 2 }}>{s.v}</div>
              <div style={{ fontSize: 10, color: s.up ? '#1B8B3D' : 'var(--mute)', marginTop: 2 }}>{s.d}</div>
            </div>
          ))}
        </div>

        {/* Queue */}
        <div style={{ border: '0.5px solid var(--line)', borderRadius: 10, overflow: 'hidden' }}>
          <div style={{ padding: '10px 14px', background: 'var(--bg-3)', borderBottom: '0.5px solid var(--line)', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
            <span style={{ fontSize: 12, fontWeight: 500 }}>Today's queue</span>
            <span style={{ fontSize: 11, color: 'var(--mute)' }}>9 scheduled · 3 walk-in</span>
          </div>
          {[
            { t: '09:30', n: 'Riya Mehta', s: 'Follow-up · Hypertension', st: 'Now', stc: 'var(--teal-600)' },
            { t: '09:45', n: 'Karan Vyas', s: 'New · Cough, 4 days', st: 'Waiting', stc: 'var(--amber)' },
            { t: '10:00', n: 'Sneha Iyer', s: 'Pediatric · 14mo well-visit', st: 'Scheduled', stc: 'var(--mute)' },
            { t: '10:15', n: 'P. Krishnan', s: 'Diabetes review · A1c due', st: 'Scheduled', stc: 'var(--mute)' },
          ].map((r, i) => (
            <div key={i} style={{ display: 'grid', gridTemplateColumns: '60px 1fr auto', gap: 12, alignItems: 'center', padding: '10px 14px', borderBottom: i < 3 ? '0.5px solid var(--line)' : 'none' }}>
              <span style={{ fontSize: 12, fontFamily: '"JetBrains Mono", monospace', color: 'var(--mute)' }}>{r.t}</span>
              <div>
                <div style={{ fontSize: 13, fontWeight: 500, color: 'var(--ink)' }}>{r.n}</div>
                <div style={{ fontSize: 11, color: 'var(--mute)', marginTop: 1 }}>{r.s}</div>
              </div>
              <span style={{ fontSize: 11, color: r.stc, fontWeight: 500 }}>{r.st}</span>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
};

window.HeroPreview = HeroPreview;
