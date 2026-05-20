// Features page — comprehensive list of every product capability
const { useEffect, useState } = React;
const { Nav, Footer, FinalCTA, PageHero, useReveal } = window;

const CATS = [
  {
    id: 'records',
    title: 'Patient records',
    blurb: 'Encrypted, structured, searchable — and built around how doctors actually think, not how databases work.',
    items: [
      { i: 'records', n: 'Structured visit notes', d: 'SOAP, free-form, or specialty-shaped templates. Auto-save every 2 seconds.' },
      { i: 'qr', n: 'QR patient cards', d: 'Print or send. Tap to load any chart in under 200ms.' },
      { i: 'image', n: 'Attachments', d: 'PDFs, X-rays, lab reports, voice memos. Encrypted, viewable inline.' },
      { i: 'shield', n: 'Allergy & alert flags', d: 'Drug allergies and history conditions surface across every screen.' },
      { i: 'scan', n: 'Family history graph', d: 'Visual family tree with hereditary condition tagging.' },
      { i: 'chart', n: 'Chronic care tracking', d: 'Long-term condition timelines with target band visuals.' },
    ],
  },
  {
    id: 'visits',
    title: 'Appointments & visits',
    blurb: 'A booking system that respects walk-ins, no-shows, and the messiness of real clinical workflow.',
    items: [
      { i: 'cal', n: 'Smart scheduling', d: 'Multi-doctor, multi-room. Drag to reschedule. Conflicts auto-blocked.' },
      { i: 'whatsapp', n: 'WhatsApp + SMS reminders', d: 'Auto-sent 24h and 1h before. Patient can reply to confirm or cancel.' },
      { i: 'bolt', n: 'Walk-in triage', d: 'Quick-add a walk-in, place them in the queue with severity.' },
      { i: 'graph', n: 'No-show analytics', d: 'Which patients no-show, which slots, which time of week — track it all.' },
      { i: 'globe', n: 'Online booking page', d: 'Branded booking link patients can share. Sync to your calendar live.' },
      { i: 'video', n: 'Telemedicine slots', d: 'Mix in-person and video slots in one calendar. Patients pick what works.' },
    ],
  },
  {
    id: 'rx',
    title: 'Prescriptions & pharmacy',
    blurb: 'From the moment you write a script to the moment your patient picks it up — fully tracked.',
    items: [
      { i: 'rx', n: 'Digital prescriptions', d: '200,000-drug DB with dosage, interaction, and pediatric warnings.' },
      { i: 'whatsapp', n: 'WhatsApp Rx delivery', d: 'Signed PDF sent to patient before they leave the chair.' },
      { i: 'pill', n: 'Pharmacy inventory', d: 'Batch numbers, expiry alerts, low-stock auto-orders.' },
      { i: 'shield', n: 'Controlled substance log', d: 'Schedule-II compliant audit trail with DEA-ready exports.' },
      { i: 'check', n: 'Refill management', d: 'Patients request refills via WhatsApp. Approve with one tap.' },
      { i: 'graph', n: 'Adherence tracking', d: 'See who refilled, who didn\'t, and follow up automatically.' },
    ],
  },
  {
    id: 'clinical',
    title: 'Clinical tools',
    blurb: 'The specialty-specific toolkit. Picked from the marketplace, one module at a time.',
    items: [
      { i: 'chart', n: 'Vitals trend charts', d: 'BP, HR, glucose, weight — visual time series with target bands.' },
      { i: 'lab', n: 'Lab orders & results', d: 'Order, receive, attach. Auto-flagged abnormals.' },
      { i: 'tooth', n: 'Dental charting', d: 'FDI/Palmer/Universal. Per-tooth notes, images, and treatment plans.' },
      { i: 'image', n: 'Skin imaging', d: 'Side-by-side before/after with lesion measurement.' },
      { i: 'sprout', n: 'WHO growth charts', d: 'Pediatric percentile tracking for weight, height, head circumference.' },
      { i: 'flask', n: 'Homeo remedy DB', d: '3,200 remedies, potency picker, antidote rules, miasm tags.' },
    ],
  },
  {
    id: 'business',
    title: 'Billing & business',
    blurb: 'Run the business of medicine without spreadsheets — and with no enterprise tax.',
    items: [
      { i: 'invoice', n: 'Invoicing', d: 'Multi-currency, GST/VAT-ready, tax codes per region.' },
      { i: 'bolt', n: 'Payments', d: 'Card, UPI, Apple/Google Pay, bank transfer, cash. Reconciled automatically.' },
      { i: 'graph', n: 'Revenue & cohort reports', d: 'New vs repeat, by doctor, by procedure, exportable to CSV.' },
      { i: 'records', n: 'Insurance claims', d: 'Submit, track, and reconcile claims (region-dependent: US, UK, India, UAE).' },
      { i: 'check', n: 'Patient packages', d: 'Sell prepaid visit/treatment packages. Auto-deducted at each visit.' },
      { i: 'globe', n: 'Multi-location', d: 'One brand, many branches. Roll-up reporting, cross-branch records.' },
    ],
  },
  {
    id: 'patient',
    title: 'Patient experience',
    blurb: 'The patient-facing layer your front desk wishes they could build. White-labeled and beautiful.',
    items: [
      { i: 'globe', n: 'Patient web portal', d: 'Records, prescriptions, bills, upcoming visits — all in one tab.' },
      { i: 'video', n: 'Video consults', d: 'HD, browser-based, no app install. Works on 3G.' },
      { i: 'whatsapp', n: 'WhatsApp summaries', d: 'After every visit: a clean summary plus next-step instructions.' },
      { i: 'leaf', n: 'Diet & exercise plans', d: 'Templated programs with daily WhatsApp check-ins.' },
      { i: 'rx', n: 'Refill requests', d: 'Patients tap once. You approve or revise.' },
      { i: 'check', n: 'Feedback collection', d: 'Post-visit NPS via WhatsApp. Scores roll into your reports.' },
    ],
  },
  {
    id: 'platform',
    title: 'Platform & integrations',
    blurb: 'Everything underneath the surface — engineered for clinics, not enterprises.',
    items: [
      { i: 'shield', n: 'HIPAA / GDPR / DPDP', d: 'Compliant in 47 countries. Real audit logs, real DPAs.' },
      { i: 'bolt', n: 'Offline-first', d: 'Day cached on device. Syncs when you reconnect.' },
      { i: 'globe', n: '18 languages', d: 'UI in EN, HI, ES, PT, AR, ZH, FR, ID, BN, JA, KO, and more.' },
      { i: 'scan', n: 'Import from anywhere', d: 'Practo, Cliniko, SimplePractice, Drchrono — or messy spreadsheets.' },
      { i: 'records', n: 'API & webhooks', d: 'Full REST API. Webhook on every meaningful event.' },
      { i: 'check', n: 'SSO + audit logs', d: 'SAML SSO, granular roles, signed audit trail (Hospital tier).' },
    ],
  },
];

const CategoryNav = () => {
  const [active, setActive] = useState(CATS[0].id);
  useEffect(() => {
    const io = new IntersectionObserver((entries) => {
      entries.forEach(e => {
        if (e.isIntersecting) setActive(e.target.id);
      });
    }, { rootMargin: '-30% 0px -60% 0px' });
    CATS.forEach(c => {
      const el = document.getElementById(c.id);
      if (el) io.observe(el);
    });
    return () => io.disconnect();
  }, []);

  return (
    <div style={{
      position: 'sticky', top: 56, zIndex: 50,
      background: 'rgba(255,255,255,0.85)',
      backdropFilter: 'saturate(180%) blur(20px)',
      WebkitBackdropFilter: 'saturate(180%) blur(20px)',
      borderBottom: '0.5px solid var(--line)',
      padding: '14px 0',
    }}>
      <div className="wrap" style={{ display: 'flex', gap: 4, overflowX: 'auto', justifyContent: 'center', flexWrap: 'wrap' }}>
        {CATS.map(c => (
          <a key={c.id} href={`#${c.id}`}
            className={`spec-tab ${active === c.id ? 'active' : ''}`}
            style={{ whiteSpace: 'nowrap' }}>
            {c.title}
          </a>
        ))}
      </div>
    </div>
  );
};

const FeatureCategory = ({ cat }) => (
  <section id={cat.id} className="feat-category">
    <div className="feat-cat-head reveal">
      <div>
        <span className="eyebrow">{cat.items.length} features</span>
        <h2 style={{ marginTop: 10 }}>{cat.title}</h2>
      </div>
      <p className="lede">{cat.blurb}</p>
    </div>
    <div className="feat-grid">
      {cat.items.map((it, i) => (
        <div key={i} className="feat-item reveal" style={{ transitionDelay: `${(i % 3) * 60}ms` }}>
          <div className="ico"><Icon name={it.i} size={20} /></div>
          <h4>{it.n}</h4>
          <p>{it.d}</p>
        </div>
      ))}
    </div>
  </section>
);

const FeaturesStats = () => (
  <section style={{ padding: '64px 0', borderTop: '0.5px solid var(--line)', borderBottom: '0.5px solid var(--line)', background: 'var(--bg-2)' }}>
    <div className="wrap">
      <div className="stats">
        <div className="stat"><div className="stat-num">42</div><div className="stat-label">Features today</div></div>
        <div className="stat"><div className="stat-num">24</div><div className="stat-label">Modules</div></div>
        <div className="stat"><div className="stat-num">7</div><div className="stat-label">Specialties</div></div>
        <div className="stat"><div className="stat-num">18</div><div className="stat-label">Languages</div></div>
      </div>
    </div>
  </section>
);

const App = () => {
  useReveal();
  return (
    <>
      <Nav active="Features" />
      <PageHero
        eyebrow="Everything My Clinic does"
        title="The complete feature catalog."
        sub="Forty-plus features organized into seven categories. Turn on what your clinic needs, leave the rest off, never pay for what you don't use."
      />
      <FeaturesStats />
      <CategoryNav />
      <section style={{ paddingTop: 80, paddingBottom: 40 }}>
        <div className="wrap">
          {CATS.map(c => <FeatureCategory key={c.id} cat={c} />)}
        </div>
      </section>
      <FinalCTA
        title="See your favorite features in your clinic."
        sub="Start free with 6 features built in. Add modules à la carte."
      />
      <Footer />
    </>
  );
};

ReactDOM.createRoot(document.getElementById('root')).render(<App />);
