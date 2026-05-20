// Pricing page — detailed plans, comparison table, FAQ
const { useState, useRef, useEffect } = React;
const { Nav, Footer, FinalCTA, PageHero, useReveal } = window;

const PLANS_DETAIL = [
  { name: 'Starter', sub: 'Solo doctors', price: 0, yearlyPrice: 0, cta: 'Start free', ctaCls: 'btn-dark', tag: null, featured: false, starter: true },
  { name: 'Clinic', sub: 'Single-location', price: 29, yearlyPrice: 278, cta: 'Start Clinic trial', ctaCls: 'btn-dark', tag: null, featured: false },
  { name: 'Practice', sub: 'Multi-doctor', price: 79, yearlyPrice: 758, cta: 'Get Practice', ctaCls: 'btn-primary', tag: 'Most chosen', featured: true },
  { name: 'Hospital', sub: 'Multi-location', price: 199, yearlyPrice: 1910, cta: 'Talk to sales', ctaCls: 'btn-dark', tag: null, featured: false },
];

const COMPARE = [
  { group: 'Core', rows: [
    ['Patient records', '200 active', 'Unlimited', 'Unlimited', 'Unlimited'],
    ['Appointments & reminders', true, true, true, true],
    ['WhatsApp & SMS reminders', false, true, true, true],
    ['Digital prescriptions', 'Basic', true, true, true],
    ['QR patient cards', false, true, true, true],
    ['Users', '1', '3', '10', 'Unlimited'],
    ['Locations', '1', '1', '3', 'Unlimited'],
  ]},
  { group: 'Clinical modules', rows: [
    ['Modules included', '0', '5', '12', 'All 24'],
    ['Vitals & trend charts', false, '+$5/mo', true, true],
    ['Lab orders', false, '+$8/mo', true, true],
    ['Pharmacy inventory', false, '+$12/mo', true, true],
    ['Telemedicine', false, '+$14/mo', true, true],
    ['Specialty modules (dental, homeo, etc.)', false, '+$7–11/mo', true, true],
  ]},
  { group: 'Business', rows: [
    ['Invoicing & payments', 'Basic', true, true, true],
    ['Multi-currency', false, true, true, true],
    ['Analytics & reports', false, false, true, true],
    ['Insurance claim submission', false, false, true, true],
    ['API & webhooks', false, false, true, true],
  ]},
  { group: 'Security & support', rows: [
    ['HIPAA / GDPR / DPDP', true, true, true, true],
    ['Audit logs', '30 days', '90 days', '1 year', 'Forever'],
    ['SSO (SAML)', false, false, false, true],
    ['Custom roles', false, false, true, true],
    ['Support', 'Community', 'Email · 24h', 'Chat · priority', 'Dedicated CSM'],
    ['SLA', false, false, false, '99.95%'],
  ]},
];

const renderCell = (v) => {
  if (v === true) return <span className="cmp-tick"><Icon name="check" size={16} stroke={2.5}/></span>;
  if (v === false) return <span className="cmp-dash">—</span>;
  return <span style={{ fontSize: 13, color: 'var(--ink-2)' }}>{v}</span>;
};

const ComparisonTable = ({ yearly }) => {
  return (
    <div className="cmp-wrap">
      <table className="cmp-table">
        <thead>
          <tr>
            <th style={{ width: '32%' }}><span style={{ fontSize: 12, color: 'var(--mute)', fontWeight: 500, letterSpacing: '0.06em', textTransform: 'uppercase' }}>Compare plans</span></th>
            {PLANS_DETAIL.map((p, i) => (
              <th key={i} className={p.featured ? 'cmp-featured' : ''}>
                <div className="cmp-plan">
                  {p.name}
                  <div className="sub">{p.sub}</div>
                  <div className="price">
                    {p.price === 0 ? 'Free' : <>${yearly ? p.yearlyPrice : p.price}<span style={{ fontSize: 12, color: 'var(--mute)' }}>{yearly ? '/yr' : '/mo'}</span></>}
                  </div>
                </div>
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {COMPARE.map((g, gi) => (
            <React.Fragment key={gi}>
              <tr className="cmp-group"><td colSpan="5">{g.group}</td></tr>
              {g.rows.map((row, ri) => (
                <tr key={ri}>
                  <td style={{ fontWeight: 500, color: 'var(--ink)' }}>{row[0]}</td>
                  {row.slice(1).map((c, ci) => (
                    <td key={ci} className={PLANS_DETAIL[ci].featured ? 'cmp-featured' : ''}>{renderCell(c)}</td>
                  ))}
                </tr>
              ))}
            </React.Fragment>
          ))}
          <tr>
            <td></td>
            {PLANS_DETAIL.map((p, i) => (
              <td key={i} className={p.featured ? 'cmp-featured' : ''} style={{ paddingTop: 24, paddingBottom: 24 }}>
                <a href="#" className={`btn ${p.ctaCls}`} style={{ width: '100%', maxWidth: 200 }}>{p.cta}</a>
              </td>
            ))}
          </tr>
        </tbody>
      </table>
    </div>
  );
};

const PricingHeader = ({ yearly, setYearly }) => {
  const togRef = useRef(null);
  const [pillStyle, setPillStyle] = useState({});
  useEffect(() => {
    if (!togRef.current) return;
    const buttons = togRef.current.querySelectorAll('button');
    const active = buttons[yearly ? 1 : 0];
    const rect = active.getBoundingClientRect();
    const parent = togRef.current.getBoundingClientRect();
    setPillStyle({ left: rect.left - parent.left, width: rect.width });
  }, [yearly]);

  return (
    <div style={{ display: 'flex', justifyContent: 'center', marginTop: 32 }}>
      <div className="pricing-toggle" ref={togRef}>
        <div className="pill" style={pillStyle}></div>
        <button className={!yearly ? 'active' : ''} onClick={() => setYearly(false)}>Monthly</button>
        <button className={yearly ? 'active' : ''} onClick={() => setYearly(true)}>
          Yearly <span className="save-pill">Save 20%</span>
        </button>
      </div>
    </div>
  );
};

const PRICING_FAQ = [
  { q: 'Can I change plans mid-month?', a: 'Yes. Upgrades take effect immediately and we prorate the difference. Downgrades take effect at the next billing cycle so you don\'t lose paid-for days.' },
  { q: 'What counts as an "active patient" on Starter?', a: 'A patient seen or contacted in the last 12 months. Inactive patients sit in your archive forever, free, and re-activate the moment you book them.' },
  { q: 'Do you offer a free trial of paid plans?', a: 'Every paid plan has a 30-day full-feature trial, no credit card needed. If you don\'t convert, your data stays accessible on the Starter free tier.' },
  { q: 'Is there a per-doctor fee on top?', a: 'No. The plan price is the plan price. User seats are included in each tier. If you outgrow seats, the next tier up usually costs less than per-seat pricing would.' },
  { q: 'How does multi-location billing work?', a: 'You\'re billed once for the whole organization. Practice includes 3 locations, Hospital is unlimited. Each location has its own dashboard but rolls up to one bill.' },
  { q: 'What payment methods do you accept?', a: 'Credit/debit card, ACH/SEPA bank transfer, UPI (India), Apple Pay, Google Pay. Enterprise plans support invoice + wire.' },
  { q: 'Do you discount for non-profits or rural clinics?', a: 'Yes — 40% off Practice/Hospital for registered non-profits, NGOs, and verified rural single-doctor clinics. Email hello@myclinic.app with documentation.' },
  { q: 'What if I just want one specific module, like dental charting?', a: 'Build your own plan. Start on Starter (free), then add only the modules you need à la carte from the marketplace. Many solo dentists run on $13/month total.' },
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

const PricingFAQ = () => {
  const [open, setOpen] = useState(0);
  return (
    <section style={{ background: 'var(--bg-2)' }}>
      <div className="wrap">
        <div className="section-head reveal">
          <span className="eyebrow">Pricing questions</span>
          <h2 className="h-section">What doctors ask before they sign.</h2>
        </div>
        <div className="faq-list reveal">
          {PRICING_FAQ.map((f, i) => (
            <FAQItem key={i} item={f} open={open === i} onClick={() => setOpen(open === i ? -1 : i)} />
          ))}
        </div>
      </div>
    </section>
  );
};

const Calculator = () => {
  const PRESETS = [
    { id: 'gp', label: 'Solo GP', mods: ['rx','qr','vitals','billing'], total: 24 },
    { id: 'dental', label: 'Single-chair dentist', mods: ['rx','chart','billing','reports'], total: 33 },
    { id: 'homeo', label: 'Homeopath', mods: ['rx','remedy','billing'], total: 22 },
    { id: 'multi', label: 'Multi-doctor practice', mods: ['rx','qr','vitals','pharma','lab','billing','reports','tele'], total: 73 },
  ];
  const [active, setActive] = useState('gp');
  const cur = PRESETS.find(p => p.id === active);

  return (
    <section style={{ background: '#fff', padding: '100px 0' }}>
      <div className="wrap">
        <div className="section-head reveal">
          <span className="eyebrow">Build your own plan</span>
          <h2 className="h-section">What would your clinic pay?</h2>
          <p className="lede">Pick a typical clinic profile to see what a real à-la-carte plan costs — almost always less than the all-in tiers above.</p>
        </div>
        <div style={{ display: 'flex', gap: 8, justifyContent: 'center', flexWrap: 'wrap', marginBottom: 36 }}>
          {PRESETS.map(p => (
            <button key={p.id}
              className={`spec-tab ${active === p.id ? 'active' : ''}`}
              onClick={() => setActive(p.id)}>{p.label}</button>
          ))}
        </div>
        <div className="reveal" style={{ maxWidth: 640, margin: '0 auto', background: 'var(--bg-2)', borderRadius: 18, padding: 28 }}>
          <div style={{ fontSize: 12, color: 'var(--mute)', textTransform: 'uppercase', letterSpacing: '0.08em', fontWeight: 500, marginBottom: 14 }}>For: {cur.label}</div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', padding: '10px 0', borderBottom: '0.5px solid var(--line)' }}>
              <span style={{ fontSize: 14 }}>Starter plan</span>
              <span style={{ fontSize: 14, fontWeight: 500 }}>Free</span>
            </div>
            {cur.mods.map((id, i) => {
              const m = window.MC_DATA.MODULES.find(x => x.id === id);
              return (
                <div key={i} style={{ display: 'flex', justifyContent: 'space-between', padding: '10px 0', borderBottom: '0.5px solid var(--line)' }}>
                  <span style={{ fontSize: 14 }}>{m.name}</span>
                  <span style={{ fontSize: 14, fontWeight: 500 }}>${m.price}/mo</span>
                </div>
              );
            })}
          </div>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', marginTop: 20, paddingTop: 14, borderTop: '1px solid var(--ink)' }}>
            <span style={{ fontSize: 14, fontWeight: 500 }}>Total per month</span>
            <span style={{ fontSize: 32, fontWeight: 300, letterSpacing: '-1px' }}>${cur.total}<span style={{ fontSize: 14, color: 'var(--mute)' }}>/mo</span></span>
          </div>
          <div style={{ marginTop: 20, textAlign: 'center' }}>
            <a href="#" className="btn btn-primary">Configure this plan</a>
          </div>
        </div>
      </div>
    </section>
  );
};

const App = () => {
  useReveal();
  const [yearly, setYearly] = useState(false);
  return (
    <>
      <Nav active="Pricing" />
      <PageHero
        eyebrow="Pricing"
        title="Simple pricing. No surprises."
        sub="Start free, forever. Upgrade only when your clinic outgrows it — or build your own plan from the module marketplace."
      >
        <PricingHeader yearly={yearly} setYearly={setYearly} />
      </PageHero>
      <section style={{ paddingTop: 20 }}>
        <div className="wrap">
          <ComparisonTable yearly={yearly} />
          <div style={{ textAlign: 'center', marginTop: 40, fontSize: 13, color: 'var(--mute)' }}>
            All prices in USD · Local currency at checkout · 30-day money-back · Cancel anytime
          </div>
        </div>
      </section>
      <Calculator />
      <PricingFAQ />
      <FinalCTA
        title="Start free. Add modules when it's worth it."
        sub="No card. No sales call. Just sign up and start seeing patients."
      />
      <Footer />
    </>
  );
};

ReactDOM.createRoot(document.getElementById('root')).render(<App />);
