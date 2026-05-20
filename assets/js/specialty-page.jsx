// Shared SpecialtyPage component — used by all 6 specialty landing pages.
// Each HTML file sets window.__SPEC = '<id>' then this renders the right config.

const SpecialtyPage = ({ d }) => {
  const Mock = window.SPEC_MOCKS[d.mockKey];
  const { Nav, Footer, FinalCTA } = window;

  return (
    <>
      <Nav />
      {/* Hero */}
      <section style={{ paddingTop: 140, paddingBottom: 80, position: 'relative', overflow: 'hidden' }}>
        <div style={{ position: 'absolute', inset: 0, background: 'radial-gradient(ellipse at 20% 0%, rgba(15,155,110,0.07) 0%, transparent 60%)', pointerEvents: 'none' }}></div>
        <div className="wrap" style={{ position: 'relative' }}>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 64, alignItems: 'center' }}>
            <div className="reveal">
              <span className="eyebrow">For {d.label.toLowerCase()}</span>
              <h1 className="h-display" style={{ fontSize: 'clamp(36px, 5vw, 56px)', letterSpacing: '-1.2px', marginTop: 14, marginBottom: 20 }}>
                {d.headline.before}<span style={{ color: 'var(--teal-600)' }}>{d.headline.accent}</span>{d.headline.after}
              </h1>
              <p className="lede" style={{ fontSize: 18, marginBottom: 28 }}>{d.heroBlurb}</p>
              <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                <a href="index.html#cta" className="btn btn-primary btn-lg">Start free — no card</a>
                <a href="#workflow" className="btn btn-ghost-dark btn-lg">See the workflow <Icon name="arrow" size={14}/></a>
              </div>
              <div style={{ marginTop: 32, display: 'flex', gap: 24, flexWrap: 'wrap', fontSize: 13, color: 'var(--mute)' }}>
                {d.heroProof.map((p, i) => (
                  <span key={i} style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                    <Icon name="check" size={14} stroke={2.5} className="" /> {p}
                  </span>
                ))}
              </div>
            </div>
            <div className="reveal" style={{ display: 'flex', justifyContent: 'center' }}>
              <Mock />
            </div>
          </div>
        </div>
      </section>

      {/* Stats */}
      <section style={{ padding: '40px 0', borderTop: '0.5px solid var(--line)', borderBottom: '0.5px solid var(--line)', background: 'var(--bg-2)' }}>
        <div className="wrap">
          <div className="stats">
            {d.stats.map((s, i) => (
              <div key={i} className="stat">
                <div className="stat-num" style={{ fontSize: 40 }}>{s.v}</div>
                <div className="stat-label">{s.l}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Features */}
      <section>
        <div className="wrap">
          <div className="section-head reveal">
            <span className="eyebrow">What {d.label.toLowerCase()} get</span>
            <h2 className="h-section">{d.featsHead.h}<br/>{d.featsHead.h2}</h2>
          </div>
          <div className="feat-grid">
            {d.feats.map((f, i) => (
              <div key={i} className="feat-item reveal" style={{ transitionDelay: `${(i % 3) * 60}ms` }}>
                <div className="ico"><Icon name={f.i} size={20}/></div>
                <h4>{f.t}</h4>
                <p>{f.d}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Workflow */}
      <section id="workflow" className="bg-grey">
        <div className="wrap">
          <div className="section-head reveal">
            <span className="eyebrow">A typical visit</span>
            <h2 className="h-section">{d.workflowHead}</h2>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: `repeat(${d.workflow.length}, 1fr)`, gap: 16, position: 'relative' }}>
            <div style={{ position: 'absolute', top: 24, left: '10%', right: '10%', height: '0.5px', background: 'var(--line)', zIndex: 0 }}></div>
            {d.workflow.map((w, i) => (
              <div key={i} className="reveal" style={{ position: 'relative', zIndex: 1, transitionDelay: `${i * 80}ms` }}>
                <div style={{ width: 48, height: 48, borderRadius: '50%', background: 'var(--teal-50)', color: 'var(--teal-700)', display: 'grid', placeItems: 'center', fontSize: 18, fontWeight: 500, margin: '0 auto 18px', border: '4px solid var(--bg-2)' }}>{i + 1}</div>
                <h4 style={{ fontSize: 15, fontWeight: 500, textAlign: 'center', marginBottom: 6 }}>{w.t}</h4>
                <p style={{ fontSize: 13, color: 'var(--mute)', textAlign: 'center', lineHeight: 1.55 }}>{w.d}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Pricing snapshot */}
      <section>
        <div className="wrap">
          <div className="section-head reveal">
            <span className="eyebrow">Pricing for {d.label.toLowerCase()}</span>
            <h2 className="h-section">{d.pricingHead}</h2>
            <p className="lede">{d.pricingBlurb}</p>
          </div>
          <div className="reveal" style={{ maxWidth: 520, margin: '0 auto', background: '#fff', borderRadius: 18, padding: 28, border: '0.5px solid var(--line)' }}>
            <div style={{ fontSize: 12, color: 'var(--mute)', textTransform: 'uppercase', letterSpacing: '0.08em', fontWeight: 500, marginBottom: 14 }}>{d.pricingLabel}</div>
            <div style={{ display: 'flex', flexDirection: 'column' }}>
              {d.pricingItems.map((r, i) => (
                <div key={i} style={{ display: 'flex', justifyContent: 'space-between', padding: '12px 0', borderBottom: '0.5px solid var(--line)' }}>
                  <span style={{ fontSize: 14 }}>{r[0]}</span>
                  <span style={{ fontSize: 14, fontWeight: 500 }}>{r[1]}</span>
                </div>
              ))}
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', marginTop: 16, paddingTop: 16, borderTop: '1px solid var(--ink)' }}>
              <span style={{ fontSize: 14, fontWeight: 500 }}>Total</span>
              <span style={{ fontSize: 32, fontWeight: 300, letterSpacing: '-1px' }}>${d.pricingTotal}<span style={{ fontSize: 14, color: 'var(--mute)' }}>/mo</span></span>
            </div>
            <div style={{ marginTop: 18, textAlign: 'center' }}>
              <a href="index.html#cta" className="btn btn-primary">Build my plan</a>
            </div>
            <div style={{ fontSize: 12, color: 'var(--mute)', textAlign: 'center', marginTop: 14 }}>
              Larger clinics: try the Practice plan at $79/mo · <a href="pricing.html" style={{ color: 'var(--teal-600)' }}>See all plans →</a>
            </div>
          </div>
        </div>
      </section>

      {/* Testimonials */}
      <section className="bg-grey">
        <div className="wrap">
          <div className="section-head reveal">
            <span className="eyebrow">From the clinics</span>
            <h2 className="h-section">{d.testimonialsHead}</h2>
          </div>
          <div className="tgrid">
            {d.testimonials.map((t, i) => {
              const ini = t.n.split(' ').filter(w => w[0] && w[0] === w[0].toUpperCase()).slice(-2).map(w => w[0]).join('');
              return (
                <div key={i} className="tcard reveal" style={{ transitionDelay: `${i * 80}ms` }}>
                  <div className="stars">★★★★★</div>
                  <blockquote>"{t.q}"</blockquote>
                  <div className="tperson">
                    <div className="tavatar">{ini}</div>
                    <div>
                      <div className="nm">{t.n}</div>
                      <div className="sp">{d.label} · {t.s}</div>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </section>

      {/* Migration */}
      <section>
        <div className="wrap">
          <div className="section-head reveal">
            <span className="eyebrow">Switching from somewhere else?</span>
            <h2 className="h-section">We move your data for you. Free.</h2>
            <p className="lede">{d.migrationBlurb}</p>
          </div>
          <div className="reveal" style={{ display: 'flex', flexWrap: 'wrap', gap: 10, justifyContent: 'center', maxWidth: 800, margin: '0 auto' }}>
            {d.migrateFrom.map((s, i) => (
              <div key={i} style={{ background: 'var(--bg-2)', borderRadius: 10, padding: '10px 16px', fontSize: 13, fontWeight: 500, color: 'var(--ink-2)' }}>{s}</div>
            ))}
          </div>
        </div>
      </section>

      {/* Sibling specialty links */}
      <section className="bg-grey">
        <div className="wrap">
          <div className="section-head reveal">
            <span className="eyebrow">Other specialties</span>
            <h2 className="h-section" style={{ fontSize: 32 }}>One platform. Built for each.</h2>
          </div>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: 10, justifyContent: 'center' }}>
            {window.SPECIALTY_CONFIGS && Object.entries(window.SPECIALTY_CONFIGS).filter(([id]) => id !== d.id).map(([id, s]) => (
              <a key={id} href={s.href} style={{ display: 'inline-flex', alignItems: 'center', gap: 8, padding: '10px 18px', borderRadius: 980, background: '#fff', border: '0.5px solid var(--line)', fontSize: 14, fontWeight: 500, color: 'var(--ink-2)', transition: 'all .15s' }}
                onMouseOver={e => { e.currentTarget.style.borderColor = 'var(--teal-400)'; e.currentTarget.style.color = 'var(--teal-700)'; }}
                onMouseOut={e => { e.currentTarget.style.borderColor = 'var(--line)'; e.currentTarget.style.color = 'var(--ink-2)'; }}>
                For {s.label.toLowerCase()} <Icon name="arrow" size={12}/>
              </a>
            ))}
          </div>
        </div>
      </section>

      <FinalCTA
        title={d.ctaTitle}
        sub={d.ctaSub}
      />
      <Footer />
    </>
  );
};

window.SpecialtyPage = SpecialtyPage;
