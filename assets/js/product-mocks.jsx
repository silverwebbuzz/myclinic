// Product UI mockups for the Product Tour page
// Each component returns a single self-contained UI screen (no outer chrome).
// The tour page wraps each in a browser window or device frame as needed.

const { useState } = React;

// shared sub-bits
const BrowserBar = ({ url }) => (
  <div className="ui-bar">
    <span className="dot r"></span><span className="dot y"></span><span className="dot g"></span>
    <span className="url">{url}</span>
  </div>
);
const Sidebar = ({ active }) => {
  const items = [
    { i: 'cal', l: 'Today' },
    { i: 'records', l: 'Patients' },
    { i: 'rx', l: 'Prescriptions' },
    { i: 'pill', l: 'Pharmacy' },
    { i: 'lab', l: 'Lab orders' },
    { i: 'invoice', l: 'Billing' },
    { i: 'graph', l: 'Analytics' },
    { i: 'video', l: 'Telemedicine' },
  ];
  return (
    <div style={{ borderRight: '0.5px solid var(--line)', background: '#FAFAFB', padding: '20px 14px', display: 'flex', flexDirection: 'column', gap: 4, minHeight: '100%' }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '0 8px 16px' }}>
        <div style={{ width: 24, height: 24, borderRadius: 7, background: 'var(--teal-600)', color: '#fff', display: 'grid', placeItems: 'center', fontSize: 11, fontWeight: 700 }}>M</div>
        <span style={{ fontSize: 13, fontWeight: 600, letterSpacing: '-0.2px' }}>Sunrise Clinic</span>
      </div>
      {items.map((x, i) => (
        <div key={i} style={{
          display: 'flex', alignItems: 'center', gap: 10,
          padding: '7px 10px', borderRadius: 7,
          fontSize: 12.5, fontWeight: 500,
          color: x.l === active ? 'var(--ink)' : 'var(--mute)',
          background: x.l === active ? '#fff' : 'transparent',
          boxShadow: x.l === active ? '0 1px 2px rgba(0,0,0,0.05)' : 'none',
        }}>
          <Icon name={x.i} size={15} />
          <span>{x.l}</span>
        </div>
      ))}
    </div>
  );
};

// ===================== PATIENT PROFILE =====================
const PatientProfileMock = () => (
  <div style={{ background: '#fff', minHeight: 520 }}>
    <BrowserBar url="myclinic.app/p/riya-mehta" />
    <div style={{ display: 'grid', gridTemplateColumns: '180px 1fr' }}>
      <Sidebar active="Patients" />
      <div style={{ padding: '20px 28px' }}>
        {/* breadcrumb + actions */}
        <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 14, fontSize: 11, color: 'var(--mute)' }}>
          <span>Patients › Riya Mehta</span>
          <span style={{ display: 'flex', gap: 8 }}>
            <span style={{ padding: '3px 10px', background: 'var(--bg-2)', borderRadius: 6 }}>Print</span>
            <span style={{ padding: '3px 10px', background: 'var(--ink)', color: '#fff', borderRadius: 6 }}>New visit</span>
          </span>
        </div>

        {/* patient header */}
        <div style={{ display: 'flex', alignItems: 'center', gap: 16, paddingBottom: 18, borderBottom: '0.5px solid var(--line)', marginBottom: 18 }}>
          <div style={{ width: 56, height: 56, borderRadius: '50%', background: 'linear-gradient(135deg, #C6EBDE, #2DC08A)', display: 'grid', placeItems: 'center', color: '#fff', fontSize: 19, fontWeight: 500 }}>RM</div>
          <div style={{ flex: 1 }}>
            <div style={{ fontSize: 20, fontWeight: 500, letterSpacing: '-0.3px' }}>Riya Mehta</div>
            <div style={{ fontSize: 12, color: 'var(--mute)', marginTop: 2 }}>F · 38 · A+ · ID MC-RIY-4421</div>
          </div>
          <div style={{ display: 'flex', gap: 6 }}>
            <span style={{ fontSize: 10, padding: '3px 8px', borderRadius: 8, background: 'rgba(255,69,58,0.1)', color: 'var(--red)', fontWeight: 500 }}>⚠ Allergy: Penicillin</span>
            <span style={{ fontSize: 10, padding: '3px 8px', borderRadius: 8, background: 'var(--teal-50)', color: 'var(--teal-800)', fontWeight: 500 }}>Chronic: HTN</span>
          </div>
        </div>

        {/* tabs */}
        <div style={{ display: 'flex', gap: 4, marginBottom: 18, borderBottom: '0.5px solid var(--line)' }}>
          {['Overview', 'Visits (12)', 'Prescriptions', 'Vitals', 'Files', 'Billing'].map((t, i) => (
            <div key={i} style={{
              padding: '8px 14px',
              fontSize: 12,
              fontWeight: 500,
              color: i === 0 ? 'var(--ink)' : 'var(--mute)',
              borderBottom: i === 0 ? '2px solid var(--teal-600)' : '2px solid transparent',
              marginBottom: -1,
            }}>{t}</div>
          ))}
        </div>

        {/* body grid */}
        <div style={{ display: 'grid', gridTemplateColumns: '1.4fr 1fr', gap: 16 }}>
          {/* left: recent visit */}
          <div style={{ border: '0.5px solid var(--line)', borderRadius: 10, padding: 16 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 10 }}>
              <div style={{ fontSize: 13, fontWeight: 500 }}>Last visit · 12 May</div>
              <span style={{ fontSize: 11, color: 'var(--teal-600)', fontWeight: 500 }}>Edit</span>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '60px 1fr', gap: 10, fontSize: 12, lineHeight: 1.6 }}>
              <span style={{ color: 'var(--mute)' }}>S:</span><span>Headache 2 days, worse evenings. No vision change.</span>
              <span style={{ color: 'var(--mute)' }}>O:</span><span>BP 138/86 · HR 76 · Afebrile. Fundi clear.</span>
              <span style={{ color: 'var(--mute)' }}>A:</span><span>Tension-type headache, suspect uncontrolled HTN.</span>
              <span style={{ color: 'var(--mute)' }}>P:</span><span>Amlodipine 5mg OD · review BP daily x 2 wks · F/U.</span>
            </div>
          </div>
          {/* right: quick facts */}
          <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            <div style={{ border: '0.5px solid var(--line)', borderRadius: 10, padding: 14 }}>
              <div style={{ fontSize: 10, color: 'var(--mute)', textTransform: 'uppercase', letterSpacing: '0.05em', fontWeight: 500 }}>Latest BP</div>
              <div style={{ fontSize: 22, fontWeight: 300, marginTop: 2, letterSpacing: '-0.5px' }}>132<span style={{ color: 'var(--mute)' }}>/80</span> <span style={{ fontSize: 11, color: '#1B8B3D', fontWeight: 500 }}>↓ improving</span></div>
            </div>
            <div style={{ border: '0.5px solid var(--line)', borderRadius: 10, padding: 14 }}>
              <div style={{ fontSize: 10, color: 'var(--mute)', textTransform: 'uppercase', letterSpacing: '0.05em', fontWeight: 500 }}>Active Rx</div>
              <div style={{ fontSize: 13, marginTop: 4 }}>Amlodipine 5mg · Metformin 500</div>
            </div>
            <div style={{ border: '0.5px solid var(--line)', borderRadius: 10, padding: 14 }}>
              <div style={{ fontSize: 10, color: 'var(--mute)', textTransform: 'uppercase', letterSpacing: '0.05em', fontWeight: 500 }}>Next visit</div>
              <div style={{ fontSize: 13, marginTop: 4 }}>1 Jun · 10:30 · F/U HTN</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
);

// ===================== CALENDAR =====================
const CalendarMock = () => {
  const slots = [
    { d: 0, t: 9, h: 1, n: 'Riya Mehta', s: 'F/U HTN', c: 'var(--teal-600)' },
    { d: 0, t: 11, h: 1, n: 'Karan Vyas', s: 'New · cough', c: 'var(--amber)' },
    { d: 1, t: 10, h: 2, n: 'Sneha Iyer', s: '14mo well-visit', c: 'var(--teal-600)' },
    { d: 2, t: 9, h: 1, n: 'P. Krishnan', s: 'Diabetes review', c: 'var(--teal-600)' },
    { d: 2, t: 14, h: 1, n: 'A. Patel', s: 'Lab review', c: 'var(--teal-600)' },
    { d: 3, t: 11, h: 1, n: 'M. Iyer', s: 'Tele consult', c: 'var(--blue-600)' },
    { d: 3, t: 15, h: 2, n: 'R. Bose', s: 'Procedure', c: 'var(--ink-2)' },
    { d: 4, t: 10, h: 1, n: 'D. Khan', s: 'F/U', c: 'var(--teal-600)' },
    { d: 4, t: 13, h: 1, n: 'S. Roy', s: 'New', c: 'var(--amber)' },
  ];
  const days = ['Mon 18', 'Tue 19', 'Wed 20', 'Thu 21', 'Fri 22'];
  const hours = [9, 10, 11, 12, 13, 14, 15, 16, 17];
  return (
    <div style={{ background: '#fff', minHeight: 520 }}>
      <BrowserBar url="myclinic.app/calendar" />
      <div style={{ display: 'grid', gridTemplateColumns: '180px 1fr' }}>
        <Sidebar active="Today" />
        <div style={{ padding: '18px 24px' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14 }}>
            <div>
              <div style={{ fontSize: 18, fontWeight: 500, letterSpacing: '-0.3px' }}>This week</div>
              <div style={{ fontSize: 12, color: 'var(--mute)' }}>May 18 – 24 · 27 visits scheduled</div>
            </div>
            <div style={{ display: 'flex', gap: 6 }}>
              <span style={{ fontSize: 11, padding: '5px 10px', background: 'var(--bg-2)', borderRadius: 6 }}>Day</span>
              <span style={{ fontSize: 11, padding: '5px 10px', background: 'var(--ink)', color: '#fff', borderRadius: 6 }}>Week</span>
              <span style={{ fontSize: 11, padding: '5px 10px', background: 'var(--bg-2)', borderRadius: 6 }}>Month</span>
              <span style={{ fontSize: 11, padding: '5px 10px', background: 'var(--teal-600)', color: '#fff', borderRadius: 6, marginLeft: 8, fontWeight: 500 }}>+ New visit</span>
            </div>
          </div>
          {/* grid */}
          <div style={{ border: '0.5px solid var(--line)', borderRadius: 8, overflow: 'hidden' }}>
            <div style={{ display: 'grid', gridTemplateColumns: '50px repeat(5, 1fr)', borderBottom: '0.5px solid var(--line)', background: 'var(--bg-3)' }}>
              <div></div>
              {days.map((d, i) => <div key={i} style={{ padding: '8px 10px', fontSize: 11, fontWeight: 500, color: 'var(--ink-2)', borderLeft: '0.5px solid var(--line)' }}>{d}</div>)}
            </div>
            <div style={{ position: 'relative', display: 'grid', gridTemplateColumns: '50px repeat(5, 1fr)' }}>
              <div>
                {hours.map((h, i) => (
                  <div key={i} style={{ height: 32, padding: '4px 8px', fontSize: 10, color: 'var(--mute)', fontFamily: '"JetBrains Mono", monospace', borderBottom: '0.5px solid var(--line-2)' }}>{h}:00</div>
                ))}
              </div>
              {[0,1,2,3,4].map(di => (
                <div key={di} style={{ position: 'relative', borderLeft: '0.5px solid var(--line)' }}>
                  {hours.map((_, i) => (
                    <div key={i} style={{ height: 32, borderBottom: '0.5px solid var(--line-2)' }}></div>
                  ))}
                  {slots.filter(s => s.d === di).map((s, i) => (
                    <div key={i} style={{
                      position: 'absolute',
                      top: (s.t - 9) * 32 + 2,
                      left: 4, right: 4,
                      height: s.h * 32 - 4,
                      background: `${s.c}15`,
                      borderLeft: `3px solid ${s.c}`,
                      borderRadius: 4,
                      padding: '4px 6px',
                      overflow: 'hidden',
                    }}>
                      <div style={{ fontSize: 11, fontWeight: 500, color: 'var(--ink)' }}>{s.n}</div>
                      <div style={{ fontSize: 10, color: 'var(--mute)' }}>{s.s}</div>
                    </div>
                  ))}
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

// ===================== VISIT NOTES (SOAP) =====================
const VisitNotesMock = () => (
  <div style={{ background: '#fff', minHeight: 520 }}>
    <BrowserBar url="myclinic.app/p/riya-mehta/visit/new" />
    <div style={{ display: 'grid', gridTemplateColumns: '180px 1fr' }}>
      <Sidebar active="Patients" />
      <div style={{ padding: '20px 28px' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 18 }}>
          <div style={{ width: 36, height: 36, borderRadius: '50%', background: 'linear-gradient(135deg, #C6EBDE, #2DC08A)', display: 'grid', placeItems: 'center', color: '#fff', fontSize: 14, fontWeight: 500 }}>RM</div>
          <div style={{ flex: 1 }}>
            <div style={{ fontSize: 16, fontWeight: 500 }}>Riya Mehta · 38F</div>
            <div style={{ fontSize: 11, color: 'var(--mute)' }}>Visit started 10:24 · Auto-saved 30 sec ago</div>
          </div>
          <span style={{ fontSize: 11, padding: '4px 10px', borderRadius: 6, background: 'var(--bg-2)', fontWeight: 500 }}>Voice dictate</span>
          <span style={{ fontSize: 11, padding: '4px 10px', borderRadius: 6, background: 'var(--ink)', color: '#fff', fontWeight: 500 }}>Sign & close</span>
        </div>

        {/* vitals strip */}
        <div style={{ display: 'flex', gap: 8, marginBottom: 16 }}>
          {[['BP', '138/86', 'mmHg', 'var(--amber)'], ['HR', '76', 'bpm', 'var(--ink)'], ['SpO₂', '98', '%', '#1B8B3D'], ['Temp', '36.9', '°C', 'var(--ink)'], ['Wt', '64.2', 'kg', 'var(--ink)']].map((v, i) => (
            <div key={i} style={{ flex: 1, background: 'var(--bg-2)', borderRadius: 8, padding: '10px 12px' }}>
              <div style={{ fontSize: 10, color: 'var(--mute)' }}>{v[0]}</div>
              <div style={{ fontSize: 17, fontWeight: 500, color: v[3], letterSpacing: '-0.3px' }}>{v[1]} <span style={{ fontSize: 10, color: 'var(--mute)', fontWeight: 400 }}>{v[2]}</span></div>
            </div>
          ))}
        </div>

        {/* SOAP */}
        {[
          ['Subjective', 'Headache 2 days, worse in the evenings. No vision change, no neck stiffness. Sleep ok. No new meds.'],
          ['Objective', 'Alert, oriented. BP 138/86 confirmed seated. Fundoscopy clear. Neck supple. No focal deficits.'],
          ['Assessment', 'Tension-type headache. Suspect uncontrolled HTN — last 3 readings trending upward.'],
          ['Plan', '1. Amlodipine 5mg PO OD\n2. BP log at home, daily, for 2 weeks\n3. F/U in 2 weeks — review BP, consider ACE-I if not controlled\n4. Paracetamol 500mg PRN for headache'],
        ].map((s, i) => (
          <div key={i} style={{ marginBottom: 10, border: '0.5px solid var(--line)', borderRadius: 10, overflow: 'hidden' }}>
            <div style={{ padding: '8px 12px', background: 'var(--bg-2)', fontSize: 11, fontWeight: 500, color: 'var(--mute)', textTransform: 'uppercase', letterSpacing: '0.06em' }}>{s[0]}</div>
            <div style={{ padding: '10px 12px', fontSize: 13, color: 'var(--ink-2)', lineHeight: 1.6, whiteSpace: 'pre-line' }}>{s[1]}</div>
          </div>
        ))}
      </div>
    </div>
  </div>
);

// ===================== Rx WRITER =====================
const RxWriterMock = () => {
  const [drug] = useState('Amlodipine');
  return (
    <div style={{ background: '#fff', minHeight: 520 }}>
      <BrowserBar url="myclinic.app/p/riya-mehta/rx/new" />
      <div style={{ display: 'grid', gridTemplateColumns: '180px 1fr' }}>
        <Sidebar active="Prescriptions" />
        <div style={{ padding: '20px 28px' }}>
          <div style={{ marginBottom: 16 }}>
            <div style={{ fontSize: 11, color: 'var(--mute)', textTransform: 'uppercase', letterSpacing: '0.08em', fontWeight: 500 }}>New prescription · Riya Mehta</div>
            <div style={{ fontSize: 22, fontWeight: 300, letterSpacing: '-0.5px', marginTop: 4 }}>Write Rx</div>
          </div>

          {/* search */}
          <div style={{ marginBottom: 16 }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '12px 16px', borderRadius: 12, border: '0.5px solid var(--line)' }}>
              <Icon name="scan" size={16} />
              <input value="Amlo" readOnly style={{ flex: 1, border: 'none', outline: 'none', fontSize: 14, background: 'transparent', color: 'var(--ink)' }}/>
              <span style={{ fontSize: 11, color: 'var(--mute)' }}>3 matches</span>
            </div>
            {/* dropdown results */}
            <div style={{ border: '0.5px solid var(--line)', borderRadius: 12, marginTop: 6, overflow: 'hidden' }}>
              {[
                { n: 'Amlodipine 5mg', c: 'BP · CCB', sel: true },
                { n: 'Amlodipine 10mg', c: 'BP · CCB' },
                { n: 'Amlodipine + Telmisartan 5/40', c: 'BP · combo' },
              ].map((r, i) => (
                <div key={i} style={{ padding: '10px 14px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderBottom: i < 2 ? '0.5px solid var(--line)' : 'none', background: r.sel ? 'var(--teal-50)' : '#fff' }}>
                  <div>
                    <div style={{ fontSize: 13, fontWeight: 500, color: r.sel ? 'var(--teal-800)' : 'var(--ink)' }}>{r.n}</div>
                    <div style={{ fontSize: 11, color: 'var(--mute)' }}>{r.c}</div>
                  </div>
                  {r.sel && <span style={{ fontSize: 11, color: 'var(--teal-700)', fontWeight: 500 }}>✓ added</span>}
                </div>
              ))}
            </div>
          </div>

          {/* selected meds */}
          <div style={{ border: '0.5px solid var(--line)', borderRadius: 12, padding: 16, marginBottom: 14 }}>
            <div style={{ fontSize: 11, color: 'var(--mute)', textTransform: 'uppercase', letterSpacing: '0.06em', fontWeight: 500, marginBottom: 10 }}>On this prescription</div>
            <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr 1fr 0.8fr', gap: 12, padding: '10px 0', borderBottom: '0.5px solid var(--line)', alignItems: 'center' }}>
              <div>
                <div style={{ fontSize: 13, fontWeight: 500 }}>Amlodipine 5mg</div>
                <div style={{ fontSize: 11, color: 'var(--mute)' }}>Tablet</div>
              </div>
              <div style={{ fontSize: 12 }}>1 tab OD</div>
              <div style={{ fontSize: 12 }}>30 days</div>
              <div style={{ fontSize: 12, color: 'var(--teal-700)', fontWeight: 500 }}>Refill: 2</div>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr 1fr 0.8fr', gap: 12, padding: '10px 0', alignItems: 'center' }}>
              <div>
                <div style={{ fontSize: 13, fontWeight: 500 }}>Paracetamol 500mg</div>
                <div style={{ fontSize: 11, color: 'var(--mute)' }}>Tablet</div>
              </div>
              <div style={{ fontSize: 12 }}>1 PRN</div>
              <div style={{ fontSize: 12 }}>14 days</div>
              <div style={{ fontSize: 12, color: 'var(--mute)' }}>Refill: 0</div>
            </div>
          </div>

          {/* warnings */}
          <div style={{ padding: '10px 14px', background: 'rgba(255,69,58,0.06)', borderRadius: 10, marginBottom: 14, display: 'flex', gap: 10 }}>
            <span style={{ color: 'var(--red)' }}>⚠</span>
            <div style={{ fontSize: 12, color: 'var(--ink-2)', lineHeight: 1.55 }}>
              <strong style={{ color: 'var(--red)' }}>Patient allergy:</strong> Penicillin — none in this Rx. Safe to sign.
            </div>
          </div>

          {/* actions */}
          <div style={{ display: 'flex', gap: 8 }}>
            <div style={{ flex: 1, padding: '12px 16px', borderRadius: 10, border: '0.5px solid var(--line)', fontSize: 13, fontWeight: 500, textAlign: 'center' }}>Save draft</div>
            <div style={{ flex: 2, padding: '12px 16px', borderRadius: 10, background: 'var(--teal-600)', color: '#fff', fontSize: 13, fontWeight: 500, textAlign: 'center' }}>Sign & send via WhatsApp</div>
          </div>
        </div>
      </div>
    </div>
  );
};

// ===================== ANALYTICS =====================
const AnalyticsMock = () => {
  const bars = [40, 55, 48, 70, 62, 80, 72, 90, 78, 95, 88, 105];
  const max = 110;
  return (
    <div style={{ background: '#fff', minHeight: 520 }}>
      <BrowserBar url="myclinic.app/analytics" />
      <div style={{ display: 'grid', gridTemplateColumns: '180px 1fr' }}>
        <Sidebar active="Analytics" />
        <div style={{ padding: '20px 28px' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end', marginBottom: 18 }}>
            <div>
              <div style={{ fontSize: 22, fontWeight: 300, letterSpacing: '-0.5px' }}>Analytics</div>
              <div style={{ fontSize: 12, color: 'var(--mute)' }}>Last 12 months · all locations</div>
            </div>
            <div style={{ display: 'flex', gap: 6 }}>
              {['7d','30d','90d','1y'].map((t, i) => (
                <span key={i} style={{ fontSize: 11, padding: '4px 10px', borderRadius: 8, background: i === 3 ? 'var(--ink)' : 'var(--bg-2)', color: i === 3 ? '#fff' : 'var(--ink-2)', fontWeight: 500 }}>{t}</span>
              ))}
            </div>
          </div>

          {/* KPI row */}
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 10, marginBottom: 18 }}>
            {[
              ['Revenue', '$48.2k', '+18% MoM', '#1B8B3D'],
              ['Visits', '1,247', '+12% MoM', '#1B8B3D'],
              ['New patients', '184', '+7% MoM', '#1B8B3D'],
              ['No-shows', '4.2%', '−1.1pp', '#1B8B3D'],
            ].map((k, i) => (
              <div key={i} style={{ border: '0.5px solid var(--line)', borderRadius: 10, padding: '14px 16px' }}>
                <div style={{ fontSize: 11, color: 'var(--mute)' }}>{k[0]}</div>
                <div style={{ fontSize: 24, fontWeight: 300, letterSpacing: '-0.6px', marginTop: 4 }}>{k[1]}</div>
                <div style={{ fontSize: 11, color: k[3], fontWeight: 500, marginTop: 2 }}>{k[2]}</div>
              </div>
            ))}
          </div>

          {/* chart */}
          <div style={{ border: '0.5px solid var(--line)', borderRadius: 12, padding: 18 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14 }}>
              <div style={{ fontSize: 13, fontWeight: 500 }}>Revenue · monthly</div>
              <div style={{ display: 'flex', gap: 14, fontSize: 11, color: 'var(--mute)' }}>
                <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}><span style={{ width: 8, height: 8, background: 'var(--teal-600)', borderRadius: 2 }}></span>This year</span>
                <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}><span style={{ width: 8, height: 8, background: 'var(--line)', borderRadius: 2 }}></span>Last year</span>
              </div>
            </div>
            <div style={{ display: 'flex', gap: 6, alignItems: 'flex-end', height: 150 }}>
              {bars.map((b, i) => (
                <div key={i} style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 6 }}>
                  <div style={{ width: '100%', display: 'flex', gap: 2, height: 130, alignItems: 'flex-end' }}>
                    <div style={{ flex: 1, height: `${(b - 20) / max * 130}px`, background: 'rgba(0,0,0,0.08)', borderRadius: '2px 2px 0 0' }}></div>
                    <div style={{ flex: 1, height: `${b / max * 130}px`, background: 'var(--teal-600)', borderRadius: '2px 2px 0 0' }}></div>
                  </div>
                  <div style={{ fontSize: 10, color: 'var(--mute)', fontFamily: '"JetBrains Mono", monospace' }}>{['J','F','M','A','M','J','J','A','S','O','N','D'][i]}</div>
                </div>
              ))}
            </div>
          </div>

          {/* bottom row */}
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginTop: 14 }}>
            <div style={{ border: '0.5px solid var(--line)', borderRadius: 10, padding: 14 }}>
              <div style={{ fontSize: 12, fontWeight: 500, marginBottom: 10 }}>Top procedures</div>
              {[['Routine consult', '412', '$8.2k'], ['Follow-up', '301', '$3.1k'], ['Vaccination', '188', '$5.6k']].map((p, i) => (
                <div key={i} style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12, padding: '5px 0', borderBottom: i < 2 ? '0.5px solid var(--line-2)' : 'none' }}>
                  <span>{p[0]}</span>
                  <span style={{ color: 'var(--mute)' }}>{p[1]} · {p[2]}</span>
                </div>
              ))}
            </div>
            <div style={{ border: '0.5px solid var(--line)', borderRadius: 10, padding: 14 }}>
              <div style={{ fontSize: 12, fontWeight: 500, marginBottom: 10 }}>Patient retention</div>
              <div style={{ fontSize: 28, fontWeight: 300, letterSpacing: '-0.6px' }}>92%</div>
              <div style={{ fontSize: 11, color: 'var(--mute)', marginTop: 2 }}>Of Q1 patients returned</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

// ===================== PHARMACY INVENTORY =====================
const PharmacyMock = () => (
  <div style={{ background: '#fff', minHeight: 520 }}>
    <BrowserBar url="myclinic.app/pharmacy" />
    <div style={{ display: 'grid', gridTemplateColumns: '180px 1fr' }}>
      <Sidebar active="Pharmacy" />
      <div style={{ padding: '20px 28px' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
          <div>
            <div style={{ fontSize: 22, fontWeight: 300, letterSpacing: '-0.5px' }}>Pharmacy stock</div>
            <div style={{ fontSize: 12, color: 'var(--mute)' }}>247 SKUs · 3 low · 1 expiring</div>
          </div>
          <div style={{ display: 'flex', gap: 8 }}>
            <span style={{ fontSize: 11, padding: '6px 12px', borderRadius: 8, background: 'var(--bg-2)' }}>Export</span>
            <span style={{ fontSize: 11, padding: '6px 12px', borderRadius: 8, background: 'var(--teal-600)', color: '#fff', fontWeight: 500 }}>+ Add stock</span>
          </div>
        </div>

        {/* alerts */}
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 10, marginBottom: 16 }}>
          {[
            ['LOW STOCK', '3 items', 'var(--amber)', 'Amlodipine, Cefixime, ORS'],
            ['EXPIRING ≤30d', '1 item', 'var(--red)', 'Insulin batch IN-4421'],
            ['REORDER NEEDED', '5 items', 'var(--ink-2)', 'Auto-draft created'],
          ].map((a, i) => (
            <div key={i} style={{ border: '0.5px solid var(--line)', borderRadius: 10, padding: '12px 14px', borderLeft: `3px solid ${a[2]}` }}>
              <div style={{ fontSize: 10, color: a[2], fontWeight: 600, letterSpacing: '0.06em' }}>{a[0]}</div>
              <div style={{ fontSize: 17, fontWeight: 500, letterSpacing: '-0.3px', marginTop: 2 }}>{a[1]}</div>
              <div style={{ fontSize: 11, color: 'var(--mute)', marginTop: 2 }}>{a[3]}</div>
            </div>
          ))}
        </div>

        {/* table */}
        <div style={{ border: '0.5px solid var(--line)', borderRadius: 10, overflow: 'hidden' }}>
          <div style={{ display: 'grid', gridTemplateColumns: '2fr 1fr 1fr 1fr 1fr 0.8fr', gap: 12, padding: '10px 16px', background: 'var(--bg-2)', fontSize: 11, color: 'var(--mute)', fontWeight: 500, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
            <div>Item</div><div>Stock</div><div>Reorder at</div><div>Expiry</div><div>Batch</div><div>Status</div>
          </div>
          {[
            { n: 'Amlodipine 5mg', s: 'Tablet · 50/strip', q: '12 strips', r: '20', e: 'Dec 2026', b: 'AM-2244', st: 'LOW', stc: 'var(--amber)' },
            { n: 'Paracetamol 500mg', s: 'Tablet · 100/strip', q: '84 strips', r: '30', e: 'Aug 2027', b: 'PR-9981', st: 'OK', stc: '#1B8B3D' },
            { n: 'Metformin 500mg', s: 'Tablet · 100/strip', q: '47 strips', r: '25', e: 'Mar 2027', b: 'MT-3120', st: 'OK', stc: '#1B8B3D' },
            { n: 'Insulin Regular', s: 'Vial · 10mL', q: '8 vials', r: '10', e: 'Jun 2026', b: 'IN-4421', st: 'EXP ≤30d', stc: 'var(--red)' },
            { n: 'ORS sachet', s: 'Sachet', q: '6 sachets', r: '15', e: 'Nov 2026', b: 'OR-7732', st: 'LOW', stc: 'var(--amber)' },
            { n: 'Cefixime 200mg', s: 'Tablet · 10/strip', q: '4 strips', r: '12', e: 'Feb 2027', b: 'CF-5510', st: 'LOW', stc: 'var(--amber)' },
          ].map((r, i) => (
            <div key={i} style={{ display: 'grid', gridTemplateColumns: '2fr 1fr 1fr 1fr 1fr 0.8fr', gap: 12, padding: '12px 16px', borderTop: '0.5px solid var(--line-2)', fontSize: 12 }}>
              <div>
                <div style={{ fontWeight: 500 }}>{r.n}</div>
                <div style={{ color: 'var(--mute)', fontSize: 11 }}>{r.s}</div>
              </div>
              <div>{r.q}</div>
              <div>{r.r}</div>
              <div>{r.e}</div>
              <div style={{ fontFamily: '"JetBrains Mono", monospace', fontSize: 11 }}>{r.b}</div>
              <div><span style={{ fontSize: 10, padding: '2px 8px', borderRadius: 6, background: `${r.stc}15`, color: r.stc, fontWeight: 600 }}>{r.st}</span></div>
            </div>
          ))}
        </div>
      </div>
    </div>
  </div>
);

// ===================== BILLING =====================
const BillingMock = () => (
  <div style={{ background: '#fff', minHeight: 520 }}>
    <BrowserBar url="myclinic.app/billing" />
    <div style={{ display: 'grid', gridTemplateColumns: '180px 1fr' }}>
      <Sidebar active="Billing" />
      <div style={{ padding: '20px 28px' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
          <div>
            <div style={{ fontSize: 22, fontWeight: 300, letterSpacing: '-0.5px' }}>Invoices</div>
            <div style={{ fontSize: 12, color: 'var(--mute)' }}>May 2026 · 184 invoices · $48.2k collected</div>
          </div>
          <span style={{ fontSize: 11, padding: '6px 12px', borderRadius: 8, background: 'var(--teal-600)', color: '#fff', fontWeight: 500 }}>+ New invoice</span>
        </div>

        {/* summary cards */}
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 10, marginBottom: 16 }}>
          {[
            ['Collected', '$48,210', '#1B8B3D'],
            ['Pending', '$3,420', 'var(--amber)'],
            ['Overdue', '$612', 'var(--red)'],
            ['This month', '184 inv.', 'var(--ink)'],
          ].map((c, i) => (
            <div key={i} style={{ border: '0.5px solid var(--line)', borderRadius: 10, padding: '14px 16px' }}>
              <div style={{ fontSize: 11, color: 'var(--mute)' }}>{c[0]}</div>
              <div style={{ fontSize: 22, fontWeight: 300, letterSpacing: '-0.5px', marginTop: 2, color: c[2] }}>{c[1]}</div>
            </div>
          ))}
        </div>

        {/* invoice list */}
        <div style={{ border: '0.5px solid var(--line)', borderRadius: 10, overflow: 'hidden' }}>
          <div style={{ display: 'grid', gridTemplateColumns: '0.8fr 2fr 1.4fr 1fr 1fr 0.8fr', gap: 12, padding: '10px 16px', background: 'var(--bg-2)', fontSize: 11, color: 'var(--mute)', fontWeight: 500, textTransform: 'uppercase', letterSpacing: '0.05em' }}>
            <div>Invoice</div><div>Patient</div><div>Items</div><div>Amount</div><div>Date</div><div>Status</div>
          </div>
          {[
            { n: '#2026-1842', p: 'Riya Mehta', it: 'OPD consult · Lab', a: '$54', d: '18 May', st: 'PAID', stc: '#1B8B3D' },
            { n: '#2026-1841', p: 'Karan Vyas', it: 'Consult · Rx', a: '$32', d: '18 May', st: 'PAID', stc: '#1B8B3D' },
            { n: '#2026-1840', p: 'Sneha Iyer', it: 'Well-visit · Vaccine', a: '$78', d: '18 May', st: 'PAID', stc: '#1B8B3D' },
            { n: '#2026-1839', p: 'P. Krishnan', it: 'F/U · HbA1c', a: '$96', d: '17 May', st: 'PENDING', stc: 'var(--amber)' },
            { n: '#2026-1838', p: 'A. Patel', it: 'Procedure · biopsy', a: '$240', d: '17 May', st: 'PAID', stc: '#1B8B3D' },
            { n: '#2026-1837', p: 'D. Khan', it: 'Consult', a: '$28', d: '16 May', st: 'OVERDUE', stc: 'var(--red)' },
          ].map((r, i) => (
            <div key={i} style={{ display: 'grid', gridTemplateColumns: '0.8fr 2fr 1.4fr 1fr 1fr 0.8fr', gap: 12, padding: '12px 16px', borderTop: '0.5px solid var(--line-2)', fontSize: 12, alignItems: 'center' }}>
              <div style={{ fontFamily: '"JetBrains Mono", monospace', fontSize: 11 }}>{r.n}</div>
              <div style={{ fontWeight: 500 }}>{r.p}</div>
              <div style={{ color: 'var(--mute)' }}>{r.it}</div>
              <div style={{ fontWeight: 500 }}>{r.a}</div>
              <div style={{ color: 'var(--mute)' }}>{r.d}</div>
              <div><span style={{ fontSize: 10, padding: '2px 8px', borderRadius: 6, background: `${r.stc}15`, color: r.stc, fontWeight: 600 }}>{r.st}</span></div>
            </div>
          ))}
        </div>
      </div>
    </div>
  </div>
);

// ===================== PATIENT PORTAL (mobile) =====================
const PatientPortalMock = () => {
  const PhoneShell = ({ children, tint = '#fff' }) => (
    <div className="device-frame" style={{ maxWidth: 280 }}>
      <div className="device-screen" style={{ background: tint }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', padding: '12px 20px 6px', fontSize: 11, fontWeight: 600 }}>
          <span>9:41</span>
          <span><span style={{ display: 'inline-block', width: 14, height: 8, border: '0.8px solid currentColor', borderRadius: 2, position: 'relative' }}><span style={{ position: 'absolute', inset: 1, background: 'currentColor', borderRadius: 1 }}></span></span></span>
        </div>
        {children}
      </div>
    </div>
  );

  return (
    <PhoneShell>
      <div style={{ padding: '8px 18px 12px' }}>
        <div style={{ fontSize: 11, color: 'var(--mute)' }}>Welcome back</div>
        <div style={{ fontSize: 22, fontWeight: 500, letterSpacing: '-0.4px' }}>Riya</div>
      </div>
      <div style={{ padding: '0 14px', display: 'flex', flexDirection: 'column', gap: 10 }}>
        <div style={{ background: 'linear-gradient(135deg, #C6EBDE, #2DC08A)', borderRadius: 14, padding: 14, color: '#fff' }}>
          <div style={{ fontSize: 10, opacity: 0.8, textTransform: 'uppercase', letterSpacing: '0.06em', fontWeight: 500 }}>Next visit</div>
          <div style={{ fontSize: 15, fontWeight: 500, marginTop: 4 }}>Dr. Sharma · 1 Jun</div>
          <div style={{ fontSize: 11, opacity: 0.85 }}>10:30am · F/U HTN</div>
          <div style={{ display: 'flex', gap: 6, marginTop: 10 }}>
            <div style={{ fontSize: 10, padding: '3px 8px', background: 'rgba(255,255,255,0.2)', borderRadius: 6, fontWeight: 500 }}>Reschedule</div>
            <div style={{ fontSize: 10, padding: '3px 8px', background: '#fff', color: 'var(--teal-700)', borderRadius: 6, fontWeight: 500 }}>Directions</div>
          </div>
        </div>
        <div style={{ background: '#fff', borderRadius: 12, padding: '12px 14px', border: '0.5px solid var(--line)' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
            <div style={{ fontSize: 12, fontWeight: 500 }}>Active prescriptions</div>
            <span style={{ fontSize: 10, color: 'var(--teal-600)', fontWeight: 500 }}>Request refill</span>
          </div>
          <div style={{ fontSize: 11, color: 'var(--ink-2)', lineHeight: 1.6 }}>
            Amlodipine 5mg · 1 OD<br/>
            Paracetamol 500 · PRN
          </div>
        </div>
        <div style={{ background: '#fff', borderRadius: 12, padding: '12px 14px', border: '0.5px solid var(--line)' }}>
          <div style={{ fontSize: 12, fontWeight: 500, marginBottom: 6 }}>BP log this week</div>
          <svg viewBox="0 0 200 50" style={{ width: '100%', height: 40 }}>
            <path d="M0 38 L40 32 L80 28 L120 30 L160 24 L200 22" stroke="var(--teal-600)" fill="none" strokeWidth="2"/>
            {[[0,38],[40,32],[80,28],[120,30],[160,24],[200,22]].map(([x,y],i) => <circle key={i} cx={x} cy={y} r="2.5" fill="var(--teal-600)"/>)}
          </svg>
          <div style={{ fontSize: 10, color: 'var(--mute)', marginTop: 4 }}>Trending down · keep going</div>
        </div>
        <div style={{ background: 'var(--bg-2)', borderRadius: 10, padding: '8px 12px', fontSize: 11, color: 'var(--mute)', textAlign: 'center' }}>
          📥 Your visit summary is ready
        </div>
      </div>
    </PhoneShell>
  );
};

// ===================== TELEMEDICINE =====================
const TelemedicineMock = () => (
  <div style={{ background: '#0A0A0A', minHeight: 520, position: 'relative' }}>
    <BrowserBar url="myclinic.app/tele/room/4982" />
    {/* video area */}
    <div style={{ position: 'relative', height: 484, background: 'linear-gradient(135deg, #1a1a1a, #2a2a2a)', overflow: 'hidden' }}>
      {/* main patient video — placeholder */}
      <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(135deg, #2a2a3a 0%, #1a1a2a 50%, #2a2a3a 100%)', display: 'grid', placeItems: 'center' }}>
        <div style={{ width: 120, height: 120, borderRadius: '50%', background: 'linear-gradient(135deg, #C6EBDE, #2DC08A)', display: 'grid', placeItems: 'center', color: '#fff', fontSize: 40, fontWeight: 300 }}>RM</div>
      </div>
      {/* doctor PIP */}
      <div style={{ position: 'absolute', top: 16, right: 16, width: 160, height: 110, borderRadius: 12, background: 'linear-gradient(135deg, #3a3a4a 0%, #2a2a3a 100%)', border: '1px solid rgba(255,255,255,0.1)', display: 'grid', placeItems: 'center' }}>
        <div style={{ width: 48, height: 48, borderRadius: '50%', background: 'linear-gradient(135deg, #1A6FC4, #0E4F92)', display: 'grid', placeItems: 'center', color: '#fff', fontWeight: 500 }}>AS</div>
      </div>
      {/* labels */}
      <div style={{ position: 'absolute', bottom: 80, left: 20, background: 'rgba(0,0,0,0.5)', color: '#fff', padding: '6px 12px', borderRadius: 8, fontSize: 12, fontWeight: 500, backdropFilter: 'blur(8px)' }}>
        Riya Mehta · F/U HTN · 8:24
      </div>
      {/* notes sidebar */}
      <div style={{ position: 'absolute', top: 16, left: 16, width: 220, background: 'rgba(255,255,255,0.95)', borderRadius: 12, padding: '14px 16px', backdropFilter: 'blur(20px)' }}>
        <div style={{ fontSize: 10, color: 'var(--mute)', textTransform: 'uppercase', letterSpacing: '0.06em', fontWeight: 500, marginBottom: 8 }}>Quick reference</div>
        <div style={{ fontSize: 11, lineHeight: 1.55, color: 'var(--ink-2)' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 4 }}><span>Last BP</span><strong>138/86</strong></div>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 4 }}><span>Active Rx</span><strong>Amlodipine 5</strong></div>
          <div style={{ display: 'flex', justifyContent: 'space-between' }}><span>Allergy</span><strong style={{ color: 'var(--red)' }}>Penicillin</strong></div>
        </div>
        <div style={{ marginTop: 12, paddingTop: 10, borderTop: '0.5px solid var(--line)' }}>
          <div style={{ fontSize: 10, color: 'var(--mute)', fontWeight: 500, marginBottom: 4 }}>Live transcript</div>
          <div style={{ fontSize: 10.5, color: 'var(--mute)', lineHeight: 1.5 }}>"...readings have been around 135 to 140..."</div>
        </div>
      </div>
      {/* controls */}
      <div style={{ position: 'absolute', bottom: 16, left: '50%', transform: 'translateX(-50%)', display: 'flex', gap: 10 }}>
        {[
          { i: 'video', c: 'rgba(255,255,255,0.15)' },
          { i: 'whatsapp', c: 'rgba(255,255,255,0.15)' },
          { i: 'records', c: 'rgba(255,255,255,0.15)' },
          { i: 'rx', c: 'var(--teal-600)' },
          { i: 'bolt', c: 'var(--red)' },
        ].map((b, i) => (
          <div key={i} style={{ width: 44, height: 44, borderRadius: '50%', background: b.c, display: 'grid', placeItems: 'center', color: '#fff', backdropFilter: 'blur(8px)' }}>
            <Icon name={b.i} size={18} />
          </div>
        ))}
      </div>
    </div>
  </div>
);

// ===================== MODULE MARKETPLACE (settings) =====================
const MarketplaceSettingsMock = () => (
  <div style={{ background: '#fff', minHeight: 520 }}>
    <BrowserBar url="myclinic.app/settings/modules" />
    <div style={{ display: 'grid', gridTemplateColumns: '180px 1fr' }}>
      <Sidebar active="" />
      <div style={{ padding: '20px 28px' }}>
        <div style={{ marginBottom: 16 }}>
          <div style={{ fontSize: 11, color: 'var(--mute)', textTransform: 'uppercase', letterSpacing: '0.08em', fontWeight: 500 }}>Settings › Modules</div>
          <div style={{ fontSize: 22, fontWeight: 300, letterSpacing: '-0.5px', marginTop: 4 }}>Your modules</div>
          <div style={{ fontSize: 12, color: 'var(--mute)' }}>Toggle on/off any time · bill adjusts within 24h</div>
        </div>

        {/* active total */}
        <div style={{ background: 'var(--bg-2)', borderRadius: 12, padding: '14px 18px', marginBottom: 18, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <div>
            <div style={{ fontSize: 12, color: 'var(--mute)' }}>4 modules active</div>
            <div style={{ fontSize: 14, fontWeight: 500 }}>Your monthly bill</div>
          </div>
          <div style={{ fontSize: 28, fontWeight: 300, letterSpacing: '-0.8px' }}>$24<span style={{ fontSize: 13, color: 'var(--mute)' }}>/mo</span></div>
        </div>

        {/* modules grid */}
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 10 }}>
          {[
            { n: 'Patient records', p: 'Free', on: true, i: 'records', lock: true },
            { n: 'Appointments', p: 'Free', on: true, i: 'cal', lock: true },
            { n: 'Digital Rx', p: '$6/mo', on: true, i: 'rx' },
            { n: 'QR patient card', p: '$4/mo', on: true, i: 'qr' },
            { n: 'Vitals & charts', p: '$5/mo', on: true, i: 'chart' },
            { n: 'Billing', p: '$9/mo', on: true, i: 'invoice' },
            { n: 'Pharmacy', p: '$12/mo', on: false, i: 'pill' },
            { n: 'Lab orders', p: '$8/mo', on: false, i: 'lab' },
            { n: 'Telemedicine', p: '$14/mo', on: false, i: 'video' },
            { n: 'Analytics', p: '$9/mo', on: false, i: 'graph' },
            { n: 'Diet plans', p: '$6/mo', on: false, i: 'leaf' },
            { n: 'Dental charting', p: '$9/mo', on: false, i: 'tooth' },
          ].map((m, i) => (
            <div key={i} style={{ border: '0.5px solid', borderColor: m.on ? 'var(--teal-400)' : 'var(--line)', borderRadius: 12, padding: '12px 14px', background: m.on ? 'rgba(15,155,110,0.03)' : '#fff' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                <div style={{ width: 28, height: 28, borderRadius: 7, background: m.on ? 'var(--teal-50)' : 'var(--bg-2)', color: m.on ? 'var(--teal-700)' : 'var(--mute)', display: 'grid', placeItems: 'center' }}>
                  <Icon name={m.i} size={14}/>
                </div>
                {/* toggle */}
                <div style={{ width: 30, height: 18, borderRadius: 10, background: m.on ? 'var(--teal-600)' : 'var(--bg-2)', position: 'relative', opacity: m.lock ? 0.6 : 1 }}>
                  <div style={{ width: 14, height: 14, borderRadius: '50%', background: '#fff', position: 'absolute', top: 2, left: m.on ? 14 : 2, transition: 'left .2s', boxShadow: '0 1px 2px rgba(0,0,0,0.15)' }}></div>
                </div>
              </div>
              <div style={{ fontSize: 13, fontWeight: 500, marginTop: 10 }}>{m.n}</div>
              <div style={{ fontSize: 11, color: m.on ? 'var(--teal-700)' : 'var(--mute)', marginTop: 2 }}>{m.p}{m.lock ? ' · always on' : ''}</div>
            </div>
          ))}
        </div>
      </div>
    </div>
  </div>
);

window.PRODUCT_MOCKS = {
  PatientProfileMock, CalendarMock, VisitNotesMock, RxWriterMock,
  AnalyticsMock, PharmacyMock, BillingMock,
  PatientPortalMock, TelemedicineMock, MarketplaceSettingsMock,
};
