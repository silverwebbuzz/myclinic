// eClinicPro — main app (homepage)
const { useState, useEffect, useRef, useMemo } = React;
const { Nav, Footer, FinalCTA, useReveal } = window;

// ---------- Hero ----------
const Hero = () => (
  <section className="hero" id="top">
    <div className="hero-bg"></div>
    <div className="hero-dots"></div>
    <div className="hero-inner">
      <span className="eyebrow">The global clinic OS</span>
      <h1>
        The clinic software<br/>doctors <span className="grad">actually love.</span>
      </h1>
      <p className="lede">
        Pick your modules. Pay for what you use. One beautifully simple
        system for GPs, dentists, homeopaths, and every specialty in between.
      </p>
      <div className="hero-ctas">
        <a href="#cta" className="btn btn-primary btn-lg">Start free — no card needed</a>
        <a href="#features" className="btn btn-ghost-dark btn-lg">
          <Icon name="play" size={12} /> Watch 2-min demo
        </a>
      </div>
      <div className="hero-trust">
        <div className="dots">
          <span></span><span></span><span></span><span></span>
        </div>
        <span>Trusted by <strong style={{ color: 'var(--ink)', fontWeight: 500 }}>2,847 clinics</strong> in 47 countries</span>
      </div>

      <div className="hero-preview reveal">
        <HeroPreview />
      </div>
    </div>
  </section>
);

// ---------- Marquee ----------
const Marquee = () => {
  const items = window.MC_DATA.MARQUEE;
  const doubled = [...items, ...items];
  return (
    <div className="marquee">
      <div className="marquee-track">
        {doubled.map((m, i) => (
          <span key={i} className="marquee-item">
            <span className="dot"></span>{m}
          </span>
        ))}
      </div>
    </div>
  );
};

// ---------- Stats with count-up ----------
const useCountUp = (target, duration = 1400) => {
  const [val, setVal] = useState(0);
  const ref = useRef(null);
  const done = useRef(false);
  useEffect(() => {
    const io = new IntersectionObserver((entries) => {
      entries.forEach(e => {
        if (e.isIntersecting && !done.current) {
          done.current = true;
          const start = performance.now();
          const tick = (t) => {
            const p = Math.min(1, (t - start) / duration);
            const eased = 1 - Math.pow(1 - p, 3);
            setVal(target * eased);
            if (p < 1) requestAnimationFrame(tick);
          };
          requestAnimationFrame(tick);
        }
      });
    }, { threshold: 0.5 });
    if (ref.current) io.observe(ref.current);
    return () => io.disconnect();
  }, [target]);
  return [val, ref];
};

const Stat = ({ value, suffix = '', label, decimals = 0, format }) => {
  const [v, ref] = useCountUp(value);
  const display = format ? format(v) : Math.round(v).toLocaleString();
  return (
    <div className="stat reveal" ref={ref}>
      <div className="stat-num">{display}{suffix}</div>
      <div className="stat-label">{label}</div>
    </div>
  );
};

const Stats = () => (
  <section style={{ padding: '72px 0', background: '#fff' }}>
    <div className="wrap">
      <div className="stats">
        <Stat value={2847} label="Clinics worldwide" />
        <Stat value={47} label="Countries" />
        <Stat value={1.2} label="Patients managed" format={(v) => v.toFixed(1) + 'M'} />
        <Stat value={24} label="Modular tools" />
      </div>
    </div>
  </section>
);

// ---------- Problem / Solution ----------
const ProblemSolution = () => (
  <section className="bg-grey" id="problem">
    <div className="wrap">
      <div className="section-head reveal">
        <span className="eyebrow">A clinic, simplified</span>
        <h2 className="h-section">There's the old way of running a clinic.<br/>And then there's My&nbsp;Clinic.</h2>
      </div>
      <div className="psgrid">
        <div className="ps-col reveal">
          <h3 style={{ color: 'var(--mute)' }}>The old way</h3>
          <ul className="ps-list">
            {[
              ['Paper registers', 'piled on a shelf and lost when it matters.'],
              ['WhatsApp Rx photos', 'sent at 11pm, untracked, unsigned.'],
              ['Five different apps', 'one for billing, one for scheduling, none that talk.'],
              ['Per-seat pricing', 'paying for features your clinic will never use.'],
              ['No specialty support', 'a dental chart that\'s just a checkbox.'],
            ].map((p, i) => (
              <li key={i} className="ps-item">
                <span className="ps-icon bad">✕</span>
                <span className="ps-text"><strong>{p[0]}</strong> — {p[1]}</span>
              </li>
            ))}
          </ul>
        </div>
        <div className="divider"></div>
        <div className="ps-col reveal">
          <h3>My Clinic</h3>
          <ul className="ps-list">
            {[
              ['One encrypted record', 'searchable in 200ms, exportable in one click.'],
              ['Signed digital Rx', 'delivered by WhatsApp before the patient leaves.'],
              ['One system, 24 modules', 'every part designed to work together.'],
              ['Pay per module', 'turn off what you don\'t need. Your bill drops.'],
              ['Built for your specialty', 'real tools for dental, homeo, derma, peds, physio.'],
            ].map((p, i) => (
              <li key={i} className="ps-item">
                <span className="ps-icon good"><Icon name="check" size={11} stroke={3}/></span>
                <span className="ps-text"><strong>{p[0]}</strong> — {p[1]}</span>
              </li>
            ))}
          </ul>
        </div>
      </div>
    </div>
  </section>
);

// ---------- Module marketplace ----------
const ModuleMarketplace = () => {
  const [filter, setFilter] = useState('all');
  const { MODULES, SPEC_TABS } = window.MC_DATA;
  const visible = MODULES;

  return (
    <section id="modules">
      <div className="wrap">
        <div className="section-head reveal">
          <span className="eyebrow">Module marketplace</span>
          <h2 className="h-section">Buy only what you need.</h2>
          <p className="lede">Every module is independent. Add, remove, or upgrade — anytime.
          Your bill adjusts the same day.</p>
        </div>

        <div className="spec-tabs reveal">
          {SPEC_TABS.map(t => (
            <button key={t.id}
              className={`spec-tab ${filter === t.id ? 'active' : ''}`}
              onClick={() => setFilter(t.id)}>
              {t.label}
            </button>
          ))}
        </div>

        <div className="modules-grid">
          {visible.map(m => {
            const match = filter === 'all' || m.specs.includes(filter);
            return (
              <div key={m.id} className={`module-card reveal ${!match ? 'disabled' : ''}`} style={{ transition: 'opacity .3s, transform .2s, border-color .2s, box-shadow .2s' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                  <div className="module-icon"><Icon name={m.icon} size={18} /></div>
                  <div className="module-add">+</div>
                </div>
                <div>
                  <div className="module-name">{m.name}</div>
                  <div className="module-desc" style={{ marginTop: 4 }}>{m.desc}</div>
                </div>
                <div className="module-foot">
                  {m.free
                    ? <span className="badge-free">Free forever</span>
                    : <span className="module-price">${m.price}<span className="per">/mo</span></span>
                  }
                  {!m.free && <span style={{ fontSize: 11, color: 'var(--mute)' }}>{m.specs.length === 1 ? 'Specialty' : 'Universal'}</span>}
                </div>
              </div>
            );
          })}
        </div>

        <div style={{ textAlign: 'center', marginTop: 36 }}>
          <a href="#" className="btn-link">See full module catalog <Icon name="arrow" size={14} /></a>
        </div>
      </div>
    </section>
  );
};

// ---------- Specialty showcase ----------
const SpecialtyShowcase = () => {
  const { SPECIALTIES } = window.MC_DATA;
  const [active, setActive] = useState(SPECIALTIES[0].id);
  const cur = SPECIALTIES.find(s => s.id === active);
  const Mock = window.SPEC_MOCKS[cur.mock];

  return (
    <section className="bg-grey" id="specialties">
      <div className="wrap">
        <div className="section-head reveal">
          <span className="eyebrow">Specialty modes</span>
          <h2 className="h-section">Built for your specialty.</h2>
          <p className="lede">Not a generic record system bent into your workflow.
          Real tools, real templates, real specialty knowledge baked in.</p>
        </div>

        <div className="spec-tabs reveal" style={{ marginBottom: 56 }}>
          {SPECIALTIES.map(s => (
            <button key={s.id}
              className={`spec-tab ${active === s.id ? 'active' : ''}`}
              onClick={() => setActive(s.id)}>
              {s.label}
            </button>
          ))}
        </div>

        <div className="spec-showcase">
          <div className="desc reveal" key={cur.id + '-d'}>
            <span className="eyebrow">For {cur.label.toLowerCase()}</span>
            <h3>{cur.title}</h3>
            <p className="lede">{cur.blurb}</p>
            <div className="spec-features">
              {cur.feats.map((f, i) => (
                <div key={i} className="spec-feat">
                  <Icon name="check" size={14} stroke={2.5} className="tick" />
                  <span>{f}</span>
                </div>
              ))}
            </div>
            <div style={{ marginTop: 28 }}>
              <a href={`For ${cur.label === 'General practice' ? 'GPs' : cur.label === 'Homeopathy' ? 'Homeopaths' : cur.label === 'Dental' ? 'Dentists' : cur.label === 'Dermatology' ? 'Dermatologists' : cur.label === 'Pediatrics' ? 'Pediatricians' : cur.label === 'Physiotherapy' ? 'Physiotherapists' : ''}.html`} className="btn btn-dark">
                Explore {cur.label.toLowerCase()} setup <Icon name="arrow" size={14}/>
              </a>
            </div>
          </div>
          <div key={cur.id + '-m'} className="reveal" style={{ animation: 'mockfade .4s ease' }}>
            <Mock />
          </div>
        </div>
      </div>
      <style>{`@keyframes mockfade { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }`}</style>
    </section>
  );
};

// ---------- Feature deep-dives ----------
const FeatureDeepDive = ({ eyebrow, h, body, link, mock, reverse, bg }) => (
  <section style={{ background: bg, padding: '120px 0' }}>
    <div className="wrap">
      <div className={`feature-row ${reverse ? 'reverse' : ''}`}>
        <div className="feature-text reveal">
          <span className="eyebrow">{eyebrow}</span>
          <h3>{h}</h3>
          <p className="lede">{body}</p>
          <a href="#" className="btn-link">{link} <Icon name="arrow" size={14} /></a>
        </div>
        <div className="reveal">{mock}</div>
      </div>
    </div>
  </section>
);

const Features = () => {
  const { QRMock, WhatsAppMock, VitalsMock } = window.FEATURE_MOCKS;
  return (
    <div id="features">
      <FeatureDeepDive
        bg="#fff"
        eyebrow="QR patient card"
        h="Scan. Load. See everything."
        body="Every patient gets a printable card with a unique encrypted QR. Tap it with any phone or webcam and the full chart loads in under 200 milliseconds — vitals, allergies, last visit, the works."
        link="Learn how QR cards work"
        mock={<QRMock />}
      />
      <FeatureDeepDive
        bg="var(--bg-2)"
        reverse
        eyebrow="Rx via WhatsApp"
        h="Prescriptions, delivered before they leave the chair."
        body="Every signed digital Rx gets a PDF copy and a WhatsApp message sent automatically — to your patient, to the partner pharmacy if you want, and to the family member they share with. No more lost paper, no more 11pm photo requests."
        link="See the full prescription workflow"
        mock={<WhatsAppMock />}
      />
      <FeatureDeepDive
        bg="#fff"
        eyebrow="Vitals trends"
        h="See the full picture, visit by visit."
        body="BP, blood sugar, weight, oxygen — every reading flows into a clean trend chart you can review during a 30-second walk into the room. The chart knows your target band. Your patient sees the same view on their app."
        link="Explore vitals & chart modules"
        mock={<VitalsMock />}
      />
    </div>
  );
};

// ---------- Pricing ----------
const Pricing = () => {
  const [yearly, setYearly] = useState(false);
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

  const factor = yearly ? 0.8 : 1;
  const fmt = (p) => yearly ? Math.round(p * 12 * 0.8) : p;

  const plans = [
    {
      name: 'Starter', price: 0, sub: 'For solo doctors getting started',
      cta: 'Start free', ctaStyle: 'btn-dark', tier: 'starter',
      feats: ['Patient records (up to 200)', 'Appointments + reminders', 'Basic Rx (digital signature)', '1 user, 1 location', 'Community support'],
    },
    {
      name: 'Clinic', price: 29, sub: 'For single-location clinics',
      cta: 'Start with Clinic', ctaStyle: 'btn-dark',
      feats: ['Unlimited records & visits', 'WhatsApp Rx delivery', '5 modules included', 'Up to 3 users', 'Email support'],
    },
    {
      name: 'Practice', price: 79, sub: 'Most popular for multi-doc clinics',
      cta: 'Get Practice', ctaStyle: 'btn-primary', featured: true, tag: 'Most chosen',
      feats: ['Everything in Clinic, plus:', '12 modules included', 'Up to 10 users', 'Analytics & reports', 'Priority chat support'],
    },
    {
      name: 'Hospital', price: 199, sub: 'For multi-location & hospitals',
      cta: 'Talk to sales', ctaStyle: 'btn-dark',
      feats: ['Everything in Practice, plus:', 'All 24 modules', 'Unlimited users & locations', 'SSO, audit logs, SLA', 'Dedicated success manager'],
    },
  ];

  return (
    <section id="pricing">
      <div className="wrap">
        <div className="section-head reveal">
          <span className="eyebrow">Pricing</span>
          <h2 className="h-section">Simple pricing. No surprises.</h2>
          <p className="lede">Start free, forever. Upgrade only when your clinic outgrows it.
          Build a custom plan from the marketplace whenever you like.</p>
        </div>

        <div style={{ display: 'flex', justifyContent: 'center' }}>
          <div className="pricing-toggle reveal" ref={togRef}>
            <div className="pill" style={pillStyle}></div>
            <button className={!yearly ? 'active' : ''} onClick={() => setYearly(false)}>Monthly</button>
            <button className={yearly ? 'active' : ''} onClick={() => setYearly(true)}>
              Yearly <span className="save-pill">Save 20%</span>
            </button>
          </div>
        </div>

        <div className="pricing-grid">
          {plans.map((p, i) => (
            <div key={i} className={`price-card reveal ${p.featured ? 'featured' : ''} ${p.tier === 'starter' ? 'starter' : ''}`}>
              {p.tag && <span className="price-tag">{p.tag}</span>}
              <div>
                <div className="price-name">{p.name}</div>
                <div className="price-mute" style={{ fontSize: 12, color: 'var(--mute)', marginTop: 4, minHeight: 32 }}>{p.sub}</div>
              </div>
              <div className="price-amt">
                {p.price === 0
                  ? <span>Free</span>
                  : <>
                      <span className="currency">$</span>{fmt(p.price)}
                      <span className="per">{yearly ? '/year' : '/month'}</span>
                    </>
                }
              </div>
              <ul className="price-feat">
                {p.feats.map((f, j) => (
                  <li key={j}>
                    <Icon name="check" size={14} stroke={2.5} className="tick"/>
                    <span>{f}</span>
                  </li>
                ))}
              </ul>
              <a href="#" className={`btn ${p.ctaStyle} price-cta`}>{p.cta}</a>
            </div>
          ))}
        </div>

        <div style={{ textAlign: 'center', marginTop: 36, fontSize: 14, color: 'var(--mute)' }}>
          Or <a href="#modules" style={{ color: 'var(--teal-600)', fontWeight: 500 }}>build your own plan</a> from the module marketplace.
          <div style={{ fontSize: 12, marginTop: 6 }}>All prices in USD · Local currency at checkout · Cancel anytime</div>
        </div>
      </div>
    </section>
  );
};

// ---------- Testimonials ----------
const Testimonials = () => {
  const { TESTIMONIALS } = window.MC_DATA;
  return (
    <section className="bg-grey">
      <div className="wrap">
        <div className="section-head reveal">
          <span className="eyebrow">From the clinics</span>
          <h2 className="h-section">Clinics that switched.<br/>And never looked back.</h2>
        </div>
        <div className="tgrid">
          {TESTIMONIALS.map((t, i) => (
            <div key={i} className="tcard reveal" style={{ transitionDelay: `${(i % 3) * 80}ms` }}>
              <div className="stars">★★★★★</div>
              <blockquote>"{t.quote}"</blockquote>
              <div className="tperson">
                <div className="tavatar">{t.initials}</div>
                <div>
                  <div className="nm">{t.name}</div>
                  <div className="sp">{t.spec}</div>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
};

// ---------- FAQ ----------
const FAQ = () => {
  const { FAQS } = window.MC_DATA;
  const [open, setOpen] = useState(0);
  return (
    <section id="faq">
      <div className="wrap">
        <div className="section-head reveal">
          <span className="eyebrow">Questions</span>
          <h2 className="h-section">Everything you'd ask in a sales call.</h2>
        </div>
        <div className="faq-list reveal">
          {FAQS.map((f, i) => (
            <div key={i} className={`faq-item ${open === i ? 'open' : ''}`}>
              <button className="faq-q" onClick={() => setOpen(open === i ? -1 : i)}>
                <span>{f.q}</span>
                <span className="plus"></span>
              </button>
              <div className="faq-a">{f.a}</div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
};

// ---------- App ----------
const App = () => {
  useReveal();
  return (
    <>
      <Nav />
      <Hero />
      <Marquee />
      <Stats />
      <ProblemSolution />
      <ModuleMarketplace />
      <SpecialtyShowcase />
      <Features />
      <Pricing />
      <Testimonials />
      <FAQ />
      <FinalCTA />
      <Footer />
    </>
  );
};

ReactDOM.createRoot(document.getElementById('root')).render(<App />);
