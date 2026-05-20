// Book a Demo page — interactive scheduling
const { useState, useMemo } = React;
const { Nav, Footer, useReveal } = window;

// Generate next 14 weekdays
const genDays = () => {
  const days = [];
  const today = new Date();
  let d = new Date(today);
  d.setDate(d.getDate() + 1); // start tomorrow
  while (days.length < 14) {
    if (d.getDay() !== 0 && d.getDay() !== 6) {
      days.push(new Date(d));
    }
    d.setDate(d.getDate() + 1);
  }
  return days;
};

const TIMES = ['09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00', '17:00'];
// Pseudo-random availability
const isAvail = (d, t) => {
  const k = d.getDate() + t.charCodeAt(0);
  return k % 7 !== 0 && k % 5 !== 0;
};

const fmtDay = (d) => d.toLocaleDateString('en', { weekday: 'short' });
const fmtDate = (d) => d.toLocaleDateString('en', { month: 'short', day: 'numeric' });
const fmtLong = (d) => d.toLocaleDateString('en', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });

const DemoSidebar = () => (
  <div style={{ position: 'sticky', top: 96 }}>
    <span className="eyebrow">15-minute demo</span>
    <h1 className="h-display" style={{ fontSize: 42, letterSpacing: '-1.2px', marginTop: 14, marginBottom: 18 }}>
      A 15-minute look at your clinic on My Clinic.
    </h1>
    <p className="lede" style={{ fontSize: 17, marginBottom: 28 }}>
      Tell us about your clinic. We'll set up a tailored demo using your specialty's templates and walk you through it live.
    </p>

    <div style={{ display: 'flex', flexDirection: 'column', gap: 16, marginTop: 32 }}>
      {[
        { i: 'check', t: 'Tailored to your specialty', d: 'GP, dental, homeo, derma, peds, physio — we configure the demo to match.' },
        { i: 'video', t: 'Live walk-through', d: 'Real screens, real workflow. Bring your hardest question.' },
        { i: 'records', t: 'Migration plan', d: "We'll show you exactly how to move your existing records." },
        { i: 'shield', t: 'No pressure', d: 'No sales pitch. No follow-up calls unless you ask for them.' },
      ].map((r, i) => (
        <div key={i} style={{ display: 'flex', gap: 14, alignItems: 'flex-start' }}>
          <div style={{ width: 32, height: 32, borderRadius: 8, background: 'var(--teal-50)', color: 'var(--teal-700)', display: 'grid', placeItems: 'center', flexShrink: 0 }}>
            <Icon name={r.i} size={16}/>
          </div>
          <div>
            <div style={{ fontSize: 15, fontWeight: 500 }}>{r.t}</div>
            <div style={{ fontSize: 13.5, color: 'var(--mute)', marginTop: 2, lineHeight: 1.55 }}>{r.d}</div>
          </div>
        </div>
      ))}
    </div>

    <div style={{ marginTop: 40, padding: '20px 22px', background: 'var(--bg-2)', borderRadius: 14 }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 12 }}>
        <div style={{ width: 38, height: 38, borderRadius: '50%', background: 'linear-gradient(135deg, #C6EBDE, #2DC08A)', display: 'grid', placeItems: 'center', color: '#fff', fontSize: 14, fontWeight: 500 }}>NK</div>
        <div>
          <div style={{ fontSize: 14, fontWeight: 500 }}>Naomi Kestler</div>
          <div style={{ fontSize: 12, color: 'var(--mute)' }}>Customer Success · My Clinic</div>
        </div>
      </div>
      <p style={{ fontSize: 13, color: 'var(--mute)', lineHeight: 1.55, fontStyle: 'italic' }}>"I run demos for clinics in 14 time zones. Bring your messy spreadsheet, your old EMR, your weirdest specialty workflow — I love them all."</p>
    </div>
  </div>
);

const Scheduler = () => {
  const days = useMemo(genDays, []);
  const [weekStart, setWeekStart] = useState(0);
  const [selDate, setSelDate] = useState(null);
  const [selTime, setSelTime] = useState(null);
  const [form, setForm] = useState({ name: '', email: '', clinic: '', role: '', specialty: 'gp', country: '', size: '1', notes: '' });
  const [step, setStep] = useState('pick'); // pick | form | done
  const visibleDays = days.slice(weekStart, weekStart + 7);

  const update = (k, v) => setForm({ ...form, [k]: v });

  if (step === 'done') {
    return (
      <div style={{ background: '#fff', borderRadius: 18, border: '0.5px solid var(--line)', padding: 56, textAlign: 'center' }}>
        <div style={{ width: 64, height: 64, borderRadius: '50%', background: 'var(--teal-50)', color: 'var(--teal-700)', display: 'grid', placeItems: 'center', margin: '0 auto 22px' }}>
          <Icon name="check" size={32} stroke={2.5}/>
        </div>
        <h2 style={{ fontSize: 28, fontWeight: 300, letterSpacing: '-0.6px', marginBottom: 12 }}>You're booked.</h2>
        <p style={{ fontSize: 15, color: 'var(--mute)', maxWidth: 380, margin: '0 auto 24px', lineHeight: 1.6 }}>
          A calendar invite is on its way to <strong style={{ color: 'var(--ink)' }}>{form.email}</strong> for <strong style={{ color: 'var(--ink)' }}>{fmtLong(selDate)}</strong> at <strong style={{ color: 'var(--ink)' }}>{selTime}</strong>.
        </p>
        <p style={{ fontSize: 13, color: 'var(--mute)', marginBottom: 28 }}>
          Naomi will run the demo. She'll join the Google Meet at the scheduled time.
        </p>
        <a href="index.html" className="btn btn-dark">Back to home</a>
      </div>
    );
  }

  if (step === 'form') {
    return (
      <div style={{ background: '#fff', borderRadius: 18, border: '0.5px solid var(--line)', padding: 32 }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 24, paddingBottom: 20, borderBottom: '0.5px solid var(--line)' }}>
          <div>
            <div style={{ fontSize: 12, color: 'var(--mute)', textTransform: 'uppercase', letterSpacing: '0.06em', fontWeight: 500 }}>Booking</div>
            <div style={{ fontSize: 18, fontWeight: 500, marginTop: 4 }}>{fmtLong(selDate)} · {selTime}</div>
            <div style={{ fontSize: 13, color: 'var(--mute)', marginTop: 2 }}>15 minutes · Google Meet</div>
          </div>
          <button onClick={() => setStep('pick')} style={{ fontSize: 13, color: 'var(--teal-600)', fontWeight: 500 }}>← Change time</button>
        </div>

        <form onSubmit={(e) => { e.preventDefault(); setStep('done'); }}>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 14 }}>
            <Field label="Your name" req val={form.name} onChange={(v) => update('name', v)} placeholder="Dr. Jane Patel" />
            <Field label="Work email" req type="email" val={form.email} onChange={(v) => update('email', v)} placeholder="jane@clinic.com" />
            <Field label="Clinic name" req val={form.clinic} onChange={(v) => update('clinic', v)} placeholder="Patel Family Care" />
            <Field label="Your role" req val={form.role} onChange={(v) => update('role', v)} placeholder="Owner / GP" />
            <SelectField label="Specialty" val={form.specialty} onChange={(v) => update('specialty', v)}
              options={[['gp','General practice'],['dental','Dental'],['homeo','Homeopathy'],['derma','Dermatology'],['peds','Pediatrics'],['physio','Physiotherapy'],['other','Other']]} />
            <Field label="Country" req val={form.country} onChange={(v) => update('country', v)} placeholder="United Kingdom" />
            <SelectField label="Clinic size" val={form.size} onChange={(v) => update('size', v)} full
              options={[['1','Solo doctor'],['2-5','2–5 doctors'],['6-20','6–20 doctors'],['20+','20+ doctors / hospital']]} />
          </div>
          <div style={{ marginTop: 14 }}>
            <label style={{ fontSize: 12, fontWeight: 500, color: 'var(--ink-2)', display: 'block', marginBottom: 6 }}>Anything we should prepare for? <span style={{ color: 'var(--mute)', fontWeight: 400 }}>(optional)</span></label>
            <textarea value={form.notes} onChange={(e) => update('notes', e.target.value)}
              placeholder="Currently on Practo, moving 2,000 records. Want to focus on dental charting and recall."
              rows="3"
              style={{ width: '100%', padding: '10px 14px', borderRadius: 10, border: '0.5px solid var(--line)', fontFamily: 'inherit', fontSize: 14, resize: 'vertical', lineHeight: 1.5, color: 'var(--ink)', background: '#fff' }} />
          </div>
          <button type="submit" className="btn btn-primary btn-lg" style={{ width: '100%', marginTop: 22 }}>
            Confirm booking <Icon name="arrow" size={14}/>
          </button>
          <p style={{ fontSize: 12, color: 'var(--mute)', textAlign: 'center', marginTop: 14 }}>
            By booking you agree to be contacted about your demo. We won't sell your info to anyone — ever.
          </p>
        </form>
      </div>
    );
  }

  // pick step
  return (
    <div style={{ background: '#fff', borderRadius: 18, border: '0.5px solid var(--line)', padding: 28 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 22 }}>
        <div>
          <div style={{ fontSize: 12, color: 'var(--mute)', textTransform: 'uppercase', letterSpacing: '0.06em', fontWeight: 500 }}>Step 1 of 2</div>
          <div style={{ fontSize: 18, fontWeight: 500, marginTop: 2 }}>Pick a time</div>
        </div>
        <div style={{ display: 'flex', gap: 4 }}>
          <button onClick={() => setWeekStart(Math.max(0, weekStart - 7))} disabled={weekStart === 0}
            style={{ width: 32, height: 32, borderRadius: 8, border: '0.5px solid var(--line)', display: 'grid', placeItems: 'center', opacity: weekStart === 0 ? 0.3 : 1 }}>
            <Icon name="arrow" size={14} className="" />
            <style>{`button[disabled] { cursor: not-allowed; }`}</style>
          </button>
          <button onClick={() => setWeekStart(Math.min(7, weekStart + 7))} disabled={weekStart >= 7}
            style={{ width: 32, height: 32, borderRadius: 8, border: '0.5px solid var(--line)', display: 'grid', placeItems: 'center', opacity: weekStart >= 7 ? 0.3 : 1, transform: 'scaleX(-1)' }}>
            <Icon name="arrow" size={14} className="" />
          </button>
        </div>
      </div>

      {/* Days strip */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', gap: 6, marginBottom: 22 }}>
        {visibleDays.map((d, i) => {
          const active = selDate && selDate.toDateString() === d.toDateString();
          return (
            <button key={i} onClick={() => { setSelDate(d); setSelTime(null); }}
              style={{
                padding: '10px 4px',
                borderRadius: 10,
                background: active ? 'var(--ink)' : 'var(--bg-2)',
                color: active ? '#fff' : 'var(--ink)',
                transition: 'all .15s',
              }}>
              <div style={{ fontSize: 11, opacity: 0.7, textTransform: 'uppercase', letterSpacing: '0.04em', fontWeight: 500 }}>{fmtDay(d)}</div>
              <div style={{ fontSize: 15, fontWeight: 500, marginTop: 2 }}>{d.getDate()}</div>
            </button>
          );
        })}
      </div>

      {/* Time slots */}
      {!selDate ? (
        <div style={{ padding: '50px 0', textAlign: 'center', fontSize: 14, color: 'var(--mute)' }}>Pick a day to see available times</div>
      ) : (
        <>
          <div style={{ fontSize: 12, color: 'var(--mute)', textTransform: 'uppercase', letterSpacing: '0.06em', fontWeight: 500, marginBottom: 12 }}>
            {fmtLong(selDate)}
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 8 }}>
            {TIMES.map((t, i) => {
              const avail = isAvail(selDate, t);
              const active = selTime === t;
              return (
                <button key={i} disabled={!avail} onClick={() => setSelTime(t)}
                  style={{
                    padding: '12px 0',
                    borderRadius: 10,
                    fontSize: 14,
                    fontWeight: 500,
                    background: active ? 'var(--teal-600)' : avail ? '#fff' : 'var(--bg-2)',
                    color: active ? '#fff' : avail ? 'var(--ink)' : 'rgba(0,0,0,0.25)',
                    border: '0.5px solid',
                    borderColor: active ? 'var(--teal-600)' : 'var(--line)',
                    cursor: avail ? 'pointer' : 'not-allowed',
                    transition: 'all .15s',
                  }}>
                  {t}
                </button>
              );
            })}
          </div>
          {selTime && (
            <button onClick={() => setStep('form')} className="btn btn-primary btn-lg" style={{ width: '100%', marginTop: 22 }}>
              Continue to details <Icon name="arrow" size={14}/>
            </button>
          )}
        </>
      )}

      <div style={{ marginTop: 22, paddingTop: 18, borderTop: '0.5px solid var(--line)', fontSize: 12, color: 'var(--mute)', display: 'flex', justifyContent: 'space-between', flexWrap: 'wrap', gap: 8 }}>
        <span>Times shown in your local timezone</span>
        <span>15 min · Google Meet</span>
      </div>
    </div>
  );
};

const Field = ({ label, val, onChange, placeholder, type = 'text', req }) => (
  <div>
    <label style={{ fontSize: 12, fontWeight: 500, color: 'var(--ink-2)', display: 'block', marginBottom: 6 }}>
      {label}{req && <span style={{ color: 'var(--red)' }}> *</span>}
    </label>
    <input type={type} required={req} value={val} onChange={(e) => onChange(e.target.value)} placeholder={placeholder}
      style={{ width: '100%', padding: '10px 14px', borderRadius: 10, border: '0.5px solid var(--line)', fontFamily: 'inherit', fontSize: 14, color: 'var(--ink)', background: '#fff' }} />
  </div>
);

const SelectField = ({ label, val, onChange, options, full }) => (
  <div style={{ gridColumn: full ? 'span 2' : 'auto' }}>
    <label style={{ fontSize: 12, fontWeight: 500, color: 'var(--ink-2)', display: 'block', marginBottom: 6 }}>{label}</label>
    <select value={val} onChange={(e) => onChange(e.target.value)}
      style={{ width: '100%', padding: '10px 14px', borderRadius: 10, border: '0.5px solid var(--line)', fontFamily: 'inherit', fontSize: 14, color: 'var(--ink)', background: '#fff', appearance: 'none', backgroundImage: 'url("data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'14\' height=\'8\' viewBox=\'0 0 14 8\' fill=\'none\'><path d=\'M1 1l6 6 6-6\' stroke=\'%236E6E73\' stroke-width=\'1.5\' stroke-linecap=\'round\' stroke-linejoin=\'round\'/></svg>")', backgroundRepeat: 'no-repeat', backgroundPosition: 'right 14px center', paddingRight: 36 }}>
      {options.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
    </select>
  </div>
);

const App = () => {
  useReveal();
  return (
    <>
      <Nav />
      <section style={{ padding: '140px 0 100px' }}>
        <div className="wrap">
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1.05fr', gap: 80, alignItems: 'start' }}>
            <div className="reveal"><DemoSidebar /></div>
            <div className="reveal"><Scheduler /></div>
          </div>
        </div>
      </section>

      {/* small trust strip */}
      <section style={{ paddingTop: 0, paddingBottom: 80 }}>
        <div className="wrap" style={{ textAlign: 'center' }}>
          <p style={{ fontSize: 13, color: 'var(--mute)', marginBottom: 20 }}>2,847 clinics use My Clinic across 47 countries</p>
          <div style={{ display: 'flex', gap: 14, flexWrap: 'wrap', justifyContent: 'center', opacity: 0.7 }}>
            {['Sunrise Family · IN', 'Whitfield Dental · CA', 'PediaCare · NG', 'Skin & Co · ES', 'Riverside Physio · UK', 'Iyer Homeopathy · IN'].map((c, i) => (
              <span key={i} style={{ fontSize: 13, color: 'var(--mute)', fontWeight: 500 }}>{c}{i < 5 && ' ·'}</span>
            ))}
          </div>
        </div>
      </section>

      <Footer />
    </>
  );
};

ReactDOM.createRoot(document.getElementById('root')).render(<App />);
