// Customer Stories — long-form case studies
const { useState } = React;
const { Nav, Footer, FinalCTA, PageHero, useReveal } = window;

const STORIES = [
  {
    id: 'sunrise',
    name: 'Sunrise Family Clinic',
    person: 'Dr. Aarav Sharma · GP',
    location: 'Mumbai, India',
    yrs: 'Customer since 2024',
    quote: 'I went from 40 patients a day and chaos at the front desk to 55 patients a day and a calm clinic. The QR cards alone saved my nurses an hour every morning.',
    metrics: [
      { v: '+38%', l: 'Patient volume' },
      { v: '8 min', l: 'Avg wait time' },
      { v: '0', l: 'Paper registers' },
    ],
    body: [
      "Sunrise Family Clinic in Bandra ran on paper for 11 years. Three OPD doctors, four nurses, one receptionist, and a queue that stretched into the corridor by 10am. Dr. Aarav Sharma had tried three EMRs before; none stuck.",
      "\"They were all built for hospitals. We're a clinic. We just need to see patients fast and not lose track of them.\" The team moved to My Clinic over two weekends, importing 14 years of paper records via a phone-scan + OCR flow.",
      "Within a quarter, the front desk had cut check-in time by 80% using QR patient cards — a small printed card a patient brings, scanned with any phone. The chart loads in under 200ms. Vitals are captured on a tablet by the nurse before the patient sits down with the doctor.",
      "\"My favorite part is that I read fewer screens and see more patients. The system shows me what I need: vitals, last visit, allergies, and the suggested Rx template. Three minutes per follow-up instead of seven.\"",
    ],
    modules: ['Patient records', 'QR patient card', 'Digital Rx', 'Vitals & charts', 'Billing'],
    tint: 'linear-gradient(135deg, #C6EBDE, #2DC08A)',
  },
  {
    id: 'whitfield',
    name: 'Whitfield Dental',
    person: 'Dr. James Whitfield · Dentist',
    location: 'Toronto, Canada',
    yrs: 'Customer since 2024',
    quote: 'My recall rate went from 38% to 71% in the first quarter. That single number paid for the software for the next decade.',
    metrics: [
      { v: '+71%', l: 'Recall rate' },
      { v: '+30%', l: 'Plan acceptance' },
      { v: '$87k', l: 'Annual revenue lift' },
    ],
    body: [
      "Whitfield Dental is a two-chair practice in Toronto's east end. Dr. James Whitfield ran it on Excel and a wall calendar for 14 years. The pain wasn't billing or charting — it was recall.",
      "\"People in Toronto don't think about their teeth until something hurts. We'd lose track of follow-ups for six months, a year. Then they'd come back with a root canal we could have caught at a routine cleaning.\"",
      "After migrating to My Clinic, every visit ends with a recall scheduled and tagged: 6-month cleaning, 3-month perio, annual whitening. WhatsApp reminders go out automatically — 30 days before, 7 days before, and the morning of.",
      "But the bigger win was treatment plan acceptance. \"The quote PDF is so clean. Three procedures, total, materials, time. My patients used to ignore the email. Now they accept on their phone before they leave the parking lot.\"",
    ],
    modules: ['Dental charting', 'Skin imaging', 'Digital Rx', 'Billing & insurance', 'Analytics'],
    tint: 'linear-gradient(135deg, #E8F1FC, #3B8EE8)',
  },
  {
    id: 'priya',
    name: 'Dr. Priya Iyer Homeopathy',
    person: 'Dr. Priya Iyer · Homeopath',
    location: 'Pune, India',
    yrs: 'Customer since 2025',
    quote: "I'd given up on software ever understanding homeopathy. My Clinic is the first time I've felt seen as a professional, not converted into a generic record.",
    metrics: [
      { v: '15 min', l: 'Per case taken' },
      { v: '3,200', l: 'Remedies indexed' },
      { v: '92%', l: 'Follow-up adherence' },
    ],
    body: [
      "Dr. Priya Iyer practices classical homeopathy in Pune. Her case files used to live in physical Word documents — one per patient, sometimes 60-pages long. She'd email them to herself for backup.",
      "\"Every time I'd evaluate a software, the case-taking form was a joke. Five fields: chief complaint, examination, diagnosis, prescription. That's not how I work.\"",
      "My Clinic's long-form case-taking template surprised her: mental generals, physical generals, particulars, modalities, aggravation/amelioration — built in, editable. The 3,200-remedy database with antidote rules earned her trust within a week.",
      "The antidote engine has caught her three times already. \"A patient was on Lycopodium and I almost gave Coffea for a follow-up symptom. The system warned me — they're antidotes. I'd have wasted three weeks of treatment.\"",
    ],
    modules: ['Patient records', 'Remedy database', 'Digital Rx', 'Billing'],
    tint: 'linear-gradient(135deg, #F0E0F8, #BF5AF2)',
  },
  {
    id: 'pediacare',
    name: 'PediaCare Clinic',
    person: 'Dr. Amara Okonkwo · Pediatrician',
    location: 'Lagos, Nigeria',
    yrs: 'Customer since 2024',
    quote: "Parents finally trust the schedule. Our on-time vaccination rate jumped from 64% to 91% in six months — and our work-life balance came back.",
    metrics: [
      { v: '+27pp', l: 'On-time vaccine rate' },
      { v: '4,200', l: 'Active patients' },
      { v: '12', l: 'Languages used' },
    ],
    body: [
      "PediaCare in Lagos serves 4,200 active patients across three associate pediatricians. The vaccination schedule was the chronic headache: paper cards lost, WhatsApp reminders forgotten, parents calling the clinic to ask \"is something due?\"",
      "Dr. Amara Okonkwo had tried building a spreadsheet automation. \"It worked for three months and then I had a child whose mother said the system had her on the wrong schedule. That was the day we moved to My Clinic.\"",
      "The country-specific vaccine schedule, paired with WhatsApp reminders 30 days before each dose, lifted on-time rates from 64% to 91%. Parent satisfaction (measured by post-visit NPS) jumped too.",
      "The weight-based dosing assistant became unexpectedly valuable. \"My junior associates were occasionally off on dosing — pediatric ranges are tight. Now the system calculates from the latest weight. We've had zero dosing errors flagged in 14 months.\"",
    ],
    modules: ['Growth charts', 'Weight-based dosing', 'Vaccine scheduler', 'WhatsApp summary', 'Patient records'],
    tint: 'linear-gradient(135deg, #FFF0E0, #FF9F0A)',
  },
];

const StoryCard = ({ s, i }) => (
  <article style={{ background: '#fff', borderRadius: 24, overflow: 'hidden', border: '0.5px solid var(--line)', display: 'grid', gridTemplateColumns: i % 2 === 0 ? '5fr 7fr' : '7fr 5fr' }} className="reveal">
    {/* image side — placeholder with monogram */}
    <div style={{ background: s.tint, padding: 48, display: 'flex', flexDirection: 'column', justifyContent: 'space-between', minHeight: 460, order: i % 2 === 0 ? 0 : 1 }}>
      <div style={{ fontSize: 11, fontWeight: 600, color: 'rgba(255,255,255,0.85)', letterSpacing: '0.1em', textTransform: 'uppercase' }}>Case study</div>
      <div>
        <div style={{ fontSize: 64, fontWeight: 300, letterSpacing: '-2px', color: '#fff', marginBottom: 14, lineHeight: 1 }}>
          {s.metrics[0].v}
        </div>
        <div style={{ fontSize: 14, color: 'rgba(255,255,255,0.85)', fontWeight: 500 }}>{s.metrics[0].l}</div>
      </div>
      <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
        <div style={{ width: 48, height: 48, borderRadius: '50%', background: 'rgba(255,255,255,0.2)', backdropFilter: 'blur(8px)', display: 'grid', placeItems: 'center', color: '#fff', fontWeight: 500, fontSize: 16 }}>
          {s.person.split(' ').filter(w => w[0] && w[0] === w[0].toUpperCase()).slice(0, 2).map(w => w[0]).join('')}
        </div>
        <div>
          <div style={{ color: '#fff', fontSize: 14, fontWeight: 500 }}>{s.name}</div>
          <div style={{ color: 'rgba(255,255,255,0.75)', fontSize: 12 }}>{s.location}</div>
        </div>
      </div>
    </div>

    {/* content side */}
    <div style={{ padding: '48px 44px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', marginBottom: 18, flexWrap: 'wrap', gap: 8 }}>
        <div>
          <h3 style={{ fontSize: 26, fontWeight: 500, letterSpacing: '-0.4px' }}>{s.name}</h3>
          <div style={{ fontSize: 13, color: 'var(--mute)', marginTop: 4 }}>{s.person} · {s.location}</div>
        </div>
        <span style={{ fontSize: 11, color: 'var(--teal-700)', background: 'var(--teal-50)', padding: '4px 9px', borderRadius: 8, fontWeight: 500 }}>{s.yrs}</span>
      </div>

      <blockquote style={{ fontSize: 19, fontWeight: 300, letterSpacing: '-0.3px', lineHeight: 1.4, color: 'var(--ink)', borderLeft: '2px solid var(--teal-400)', paddingLeft: 18, margin: '20px 0 28px' }}>
        "{s.quote}"
      </blockquote>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 12, marginBottom: 24, padding: '18px 0', borderTop: '0.5px solid var(--line)', borderBottom: '0.5px solid var(--line)' }}>
        {s.metrics.map((m, i) => (
          <div key={i}>
            <div style={{ fontSize: 22, fontWeight: 300, letterSpacing: '-0.5px', color: 'var(--ink)' }}>{m.v}</div>
            <div style={{ fontSize: 12, color: 'var(--mute)', marginTop: 2 }}>{m.l}</div>
          </div>
        ))}
      </div>

      <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
        {s.body.map((p, i) => <p key={i} style={{ fontSize: 15, color: 'var(--ink-2)', lineHeight: 1.65 }}>{p}</p>)}
      </div>

      <div style={{ marginTop: 24, paddingTop: 20, borderTop: '0.5px solid var(--line)' }}>
        <div style={{ fontSize: 11, color: 'var(--mute)', textTransform: 'uppercase', letterSpacing: '0.08em', fontWeight: 500, marginBottom: 10 }}>Modules they use</div>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
          {s.modules.map((m, i) => (
            <span key={i} style={{ fontSize: 12, padding: '4px 11px', borderRadius: 10, background: 'var(--bg-2)', color: 'var(--ink-2)', fontWeight: 500 }}>{m}</span>
          ))}
        </div>
      </div>
    </div>
  </article>
);

const App = () => {
  useReveal();
  return (
    <>
      <Nav />
      <PageHero
        eyebrow="Customer stories"
        title="Clinics that switched. And never looked back."
        sub="Four clinics, four specialties, four countries. The hard numbers and the human stories behind them."
      />
      <section style={{ paddingTop: 60 }}>
        <div className="wrap-wide">
          <div style={{ display: 'flex', flexDirection: 'column', gap: 32 }}>
            {STORIES.map((s, i) => <StoryCard key={s.id} s={s} i={i} />)}
          </div>
        </div>
      </section>

      {/* Logos / brag bar */}
      <section style={{ paddingTop: 80, paddingBottom: 80 }}>
        <div className="wrap">
          <div className="section-head reveal">
            <span className="eyebrow">2,847 clinics, and counting</span>
            <h2 className="h-section" style={{ fontSize: 32 }}>A few of the names you might recognize.</h2>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 12, maxWidth: 900, margin: '0 auto' }}>
            {[
              'Sunrise Family · IN', 'Whitfield Dental · CA', 'PediaCare · NG', 'Iyer Homeopathy · IN',
              'Skin & Co · ES', 'Hill Family · AU', 'Bright Smiles · AE', 'Riverside Physio · UK',
              'Northside Derma · US', 'Sato Clinic · JP', 'Vega Pediatrics · CO', 'Patel Wellness · UK',
            ].map((c, i) => (
              <div key={i} style={{ background: 'var(--bg-2)', borderRadius: 12, padding: '20px 18px', fontSize: 13, fontWeight: 500, color: 'var(--ink-2)', textAlign: 'center' }}>{c}</div>
            ))}
          </div>
        </div>
      </section>

      <FinalCTA
        title="Become the next story."
        sub="Start free in 2 minutes. We'll check in after your first 30 days to see how you're doing."
      />
      <Footer />
    </>
  );
};

ReactDOM.createRoot(document.getElementById('root')).render(<App />);
