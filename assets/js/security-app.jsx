// Security & Compliance page
const { useState } = React;
const { Nav, Footer, FinalCTA, PageHero, useReveal } = window;

const PILLARS = [
  {
    i: 'shield', t: 'Encryption everywhere',
    d: 'AES-256 at rest. TLS 1.3 in transit. Per-clinic keys, rotated quarterly. Field-level encryption for the most sensitive data (allergies, diagnoses, mental health notes).',
  },
  {
    i: 'check', t: 'Compliant by default',
    d: 'HIPAA (US), GDPR (EU/UK), DPDP (India), PIPEDA (Canada), POPIA (South Africa), HDS (France). Region-aware data residency.',
  },
  {
    i: 'records', t: 'You own your data',
    d: 'Export everything as portable JSON, CSV, or HL7 FHIR — anytime, free. Delete your account and we erase within 30 days, audit-logged.',
  },
  {
    i: 'scan', t: 'Granular access control',
    d: 'Roles for doctor, nurse, receptionist, accountant, owner. Per-module, per-action permissions. Time-limited access for locums.',
  },
  {
    i: 'graph', t: 'Audit trail forever',
    d: 'Every read, write, and export is logged with user, IP, device, and timestamp. Tamper-evident, exportable on demand.',
  },
  {
    i: 'globe', t: 'Data residency you choose',
    d: 'Pick where your data lives: US, EU, India, UAE, Singapore. It never leaves that region — not for backups, not for analytics.',
  },
  {
    i: 'bolt', t: 'Resilient infrastructure',
    d: '99.95% uptime SLA (Hospital plan). Three-region failover. Backups every 15 minutes, restorable to any point in the last 90 days.',
  },
  {
    i: 'lab', t: 'Independently tested',
    d: 'Quarterly third-party penetration tests. Annual SOC 2 Type II audit. Public bug bounty up to $25,000 per critical finding.',
  },
  {
    i: 'rx', t: 'Vendor & sub-processor list',
    d: 'A short, public list of every vendor that touches your data. We notify you 30 days before any change.',
  },
];

const CERTS = [
  { t: 'HIPAA' },
  { t: 'GDPR' },
  { t: 'DPDP 2023' },
  { t: 'SOC 2 Type II' },
  { t: 'ISO 27001' },
  { t: 'HL7 FHIR R4' },
  { t: 'PIPEDA' },
  { t: 'POPIA' },
  { t: 'HDS (FR)' },
];

const FACTS = [
  ['256-bit', 'AES encryption'],
  ['99.95%', 'Uptime SLA'],
  ['15 min', 'Backup interval'],
  ['<24h', 'Critical patch SLA'],
];

const PRACTICES = [
  { t: 'Background checks for every employee', d: 'Every Clinic engineer with production access undergoes a criminal background check and signs an enforceable confidentiality agreement.' },
  { t: 'Zero-trust internal network', d: 'No long-lived credentials. Production access is mediated through a session-based broker with mandatory MFA, full session recording, and per-action approval for sensitive operations.' },
  { t: 'Quarterly disaster recovery drills', d: 'Every quarter we simulate a full region failure, restore from backups, and measure RTO/RPO. The results are published to enterprise customers.' },
  { t: 'Annual SOC 2 Type II audit', d: 'Independent audit covering security, availability, confidentiality, and privacy. Reports available under NDA.' },
  { t: 'Subprocessor transparency', d: 'A public list of every vendor that touches customer data. We notify in advance of any change with a 30-day window to object.' },
  { t: 'Phishing-resistant MFA mandatory', d: 'WebAuthn / passkeys for all employees. SMS-only MFA is not permitted internally and not recommended for clinics.' },
];

const FAQ_SEC = [
  { q: 'Where is my data stored?', a: 'You choose your region at signup — US, EU (Frankfurt), India (Mumbai), UAE, or Singapore. Data, backups, and analytics never leave that region.' },
  { q: 'Who can see my patients\' data?', a: 'Only the people you grant access to in your clinic, plus a tiny on-call team of engineers when responding to a support ticket you opened — and only with your explicit consent for that ticket. Every access is logged.' },
  { q: 'What if a patient asks for their data to be deleted?', a: 'GDPR Article 17 / DPDP "right to erasure" is built in. Click delete on the patient record — the data is purged from production within 24 hours and from backups within 30 days, with a tamper-evident certificate.' },
  { q: 'How does export work if I want to leave?', a: 'Go to Settings → Export. You get a signed ZIP containing every patient record, prescription, invoice, and attachment as portable JSON + HL7 FHIR R4 + PDF. No fee, no lock-in.' },
  { q: 'Do you train AI on my data?', a: 'No. Patient data is never used to train models — ours or anyone else\'s. AI assistants that work on your data run inside your data residency region and forget after each session.' },
  { q: 'Can I get a signed Business Associate Agreement (BAA) or DPA?', a: 'Yes. BAA (HIPAA), DPA (GDPR), and India DPDP processor agreement are available on all paid plans. Download instantly from your dashboard — no sales call.' },
];

const FAQItem = ({ item, open, onClick }) => (
  <div className={`faq-item ${open ? 'open' : ''}`}>
    <button className="faq-q" onClick={onClick}>
      <span>{item.q}</span>
      <span className="plus"></span>
    </button>
    <div className="faq-a">{item.a}</div>
  </div>
);

const App = () => {
  useReveal();
  const [open, setOpen] = useState(0);
  return (
    <>
      <Nav active="Security" />
      <PageHero
        eyebrow="Trust & security"
        title="Patient data deserves better than &quot;trust us&quot;."
        sub="Real encryption, real compliance, real audit logs — and a real list of every vendor that touches your data. Built so cautious doctors stay cautious about everything except us."
      />

      {/* Quick facts strip */}
      <section style={{ padding: '40px 0', borderTop: '0.5px solid var(--line)', borderBottom: '0.5px solid var(--line)', background: 'var(--bg-2)' }}>
        <div className="wrap">
          <div className="stats">
            {FACTS.map((f, i) => (
              <div key={i} className="stat reveal">
                <div className="stat-num" style={{ fontSize: 36 }}>{f[0]}</div>
                <div className="stat-label">{f[1]}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Certifications */}
      <section id="compliance" style={{ padding: '80px 0 40px' }}>
        <div className="wrap">
          <div className="section-head reveal" style={{ marginBottom: 32 }}>
            <span className="eyebrow">Certifications & frameworks</span>
            <h2 className="h-section">Compliant in the regions you operate.</h2>
            <p className="lede">My Clinic is built to the highest healthcare privacy standards in every region we serve. Reports and DPAs available on demand.</p>
          </div>
          <div className="cert-row reveal">
            {CERTS.map((c, i) => (
              <div key={i} className="cert">
                <span className="ico"><Icon name="shield" size={14} stroke={2}/></span>
                {c.t}
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Pillars */}
      <section style={{ paddingTop: 40, paddingBottom: 100 }}>
        <div className="wrap">
          <div className="section-head reveal">
            <span className="eyebrow">Nine pillars of trust</span>
            <h2 className="h-section">How we protect every record.</h2>
          </div>
          <div className="sec-grid">
            {PILLARS.map((p, i) => (
              <div key={i} className="sec-card reveal" style={{ transitionDelay: `${(i % 3) * 60}ms` }}>
                <div className="ico"><Icon name={p.i} size={20}/></div>
                <h4>{p.t}</h4>
                <p>{p.d}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Internal practices */}
      <section className="bg-grey">
        <div className="wrap">
          <div className="section-head reveal">
            <span className="eyebrow">Internal practices</span>
            <h2 className="h-section">What we do behind the scenes.</h2>
            <p className="lede">Security isn't a feature — it's the daily operating system. Here's how the team works.</p>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: 16 }}>
            {PRACTICES.map((p, i) => (
              <div key={i} className="reveal" style={{ background: '#fff', borderRadius: 16, padding: 28, border: '0.5px solid var(--line)' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 12 }}>
                  <div style={{ width: 28, height: 28, borderRadius: 8, background: 'var(--teal-50)', color: 'var(--teal-700)', display: 'grid', placeItems: 'center' }}>
                    <Icon name="check" size={14} stroke={2.5}/>
                  </div>
                  <h4 style={{ fontSize: 16, fontWeight: 500 }}>{p.t}</h4>
                </div>
                <p style={{ fontSize: 14, color: 'var(--mute)', lineHeight: 1.6 }}>{p.d}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Documents */}
      <section>
        <div className="wrap">
          <div className="section-head reveal">
            <span className="eyebrow">Documents you can download</span>
            <h2 className="h-section">No NDAs. No sales calls.</h2>
            <p className="lede">The documents your compliance officer wants — available instantly from your dashboard.</p>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: 12, maxWidth: 800, margin: '0 auto' }}>
            {[
              ['Business Associate Agreement (HIPAA)', 'Auto-countersigned PDF · 12 pages'],
              ['Data Processing Agreement (GDPR)', 'Standard Contractual Clauses included · 18 pages'],
              ['India DPDP Processor Agreement', 'Section 8 compliant · 9 pages'],
              ['Subprocessor List', 'Live — 14 vendors, last updated Apr 2026'],
              ['SOC 2 Type II Report', 'Q1 2026 · under NDA, 1-click request'],
              ['Penetration Test Summary', 'Q1 2026 · public summary'],
            ].map((d, i) => (
              <a key={i} href="#" className="reveal" style={{ background: '#fff', border: '0.5px solid var(--line)', borderRadius: 12, padding: '16px 18px', display: 'flex', alignItems: 'center', gap: 14, transition: 'border-color .15s' }}
                 onMouseOver={e => e.currentTarget.style.borderColor = 'var(--teal-400)'}
                 onMouseOut={e => e.currentTarget.style.borderColor = 'var(--line)'}>
                <div style={{ width: 32, height: 40, background: 'var(--bg-2)', borderRadius: 4, display: 'grid', placeItems: 'center', fontSize: 9, fontWeight: 700, color: 'var(--red)', flexShrink: 0 }}>PDF</div>
                <div style={{ flex: 1 }}>
                  <div style={{ fontSize: 14, fontWeight: 500 }}>{d[0]}</div>
                  <div style={{ fontSize: 12, color: 'var(--mute)', marginTop: 2 }}>{d[1]}</div>
                </div>
                <Icon name="arrow" size={16} />
              </a>
            ))}
          </div>
        </div>
      </section>

      {/* Security FAQ */}
      <section className="bg-grey">
        <div className="wrap">
          <div className="section-head reveal">
            <span className="eyebrow">Security FAQ</span>
            <h2 className="h-section">The hard questions, answered honestly.</h2>
          </div>
          <div className="faq-list reveal">
            {FAQ_SEC.map((f, i) => (
              <FAQItem key={i} item={f} open={open === i} onClick={() => setOpen(open === i ? -1 : i)} />
            ))}
          </div>
        </div>
      </section>

      <FinalCTA
        title="Run a clinic that respects your patients."
        sub="Real security, real compliance, real ownership of your data."
      />
      <Footer />
    </>
  );
};

ReactDOM.createRoot(document.getElementById('root')).render(<App />);
