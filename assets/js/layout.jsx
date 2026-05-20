// Shared nav, footer, scroll reveal — used across all pages
const { useState: useStateL, useEffect: useEffectL, useRef: useRefL } = React;

const useReveal = () => {
  useEffectL(() => {
    const els = document.querySelectorAll('.reveal');
    const io = new IntersectionObserver((entries) => {
      entries.forEach(e => {
        if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -60px 0px' });
    els.forEach(el => io.observe(el));
    return () => io.disconnect();
  }, []);
};

const NAV_LINKS = [
  { href: 'features.html', label: 'Features' },
  { href: 'product-tour.html', label: 'Tour' },
  { href: 'index.html#specialties', label: 'Specialties' },
  { href: 'pricing.html', label: 'Pricing' },
  { href: 'security.html', label: 'Security' },
];

const Nav = ({ active }) => {
  return (
    <header className="nav">
      <div className="nav-inner">
        <a href="index.html" className="logo">e<em>ClinicPro</em></a>
        <nav className="nav-links">
          {NAV_LINKS.map(l => (
            <a key={l.label} href={l.href} className={`nav-link ${active === l.label ? 'is-active' : ''}`}>{l.label}</a>
          ))}
        </nav>
        <div className="nav-cta">
          <a href="https://app.eclinicpro.com/login" className="nav-signin">Sign in</a>
          <a href="https://app.eclinicpro.com/register" className="btn btn-primary">Start free</a>
        </div>
      </div>
    </header>
  );
};

const Footer = () => (
  <footer className="foot">
    <div className="wrap">
      <div className="foot-grid">
        <div className="foot-brand">
          <span className="logo">e<em>ClinicPro</em></span>
          <p>The clinic operating system, for every specialty, in every country. Made with care for clinicians worldwide.</p>
        </div>
        <div className="foot-col">
          <h5>Product</h5>
          <ul>
            <li><a href="features.html">Features</a></li>
            <li><a href="product-tour.html">Product tour</a></li>
            <li><a href="index.html#modules">Modules</a></li>
            <li><a href="pricing.html">Pricing</a></li>
            <li><a href="#">Changelog</a></li>
          </ul>
        </div>
        <div className="foot-col">
          <h5>Specialties</h5>
          <ul>
            <li><a href="for-gps.html">General practice</a></li>
            <li><a href="for-dentists.html">Dentistry</a></li>
            <li><a href="for-homeopaths.html">Homeopathy</a></li>
            <li><a href="for-dermatologists.html">Dermatology</a></li>
            <li><a href="for-pediatricians.html">Pediatrics</a></li>
            <li><a href="for-physiotherapists.html">Physiotherapy</a></li>
          </ul>
        </div>
        <div className="foot-col">
          <h5>Trust</h5>
          <ul>
            <li><a href="security.html">Security</a></li>
            <li><a href="customer-stories.html">Customer stories</a></li>
            <li><a href="security.html#compliance">HIPAA / GDPR</a></li>
            <li><a href="book-a-demo.html">Book a demo</a></li>
          </ul>
        </div>
        <div className="foot-col">
          <h5>Company</h5>
          <ul>
            <li><a href="#">About</a></li>
            <li><a href="#">Careers</a></li>
            <li><a href="#">Press kit</a></li>
            <li><a href="#">Contact</a></li>
          </ul>
        </div>
      </div>
      <div className="foot-bottom">
        <div>© 2026 eClinicPro, Inc. · Made with care for clinics worldwide 🌿</div>
        <div className="links">
          <a href="#">Privacy</a>
          <a href="#">Terms</a>
          <a href="security.html">Security</a>
          <a href="#">HIPAA</a>
          <a href="#">GDPR</a>
        </div>
      </div>
    </div>
  </footer>
);

// Reusable final CTA banner
const FinalCTA = ({ title = 'Ready to run your clinic beautifully?', sub = 'Join 2,847 clinics worldwide. Start free in 2 minutes.' }) => (
  <section className="cta-block" id="cta">
    <div className="wrap reveal">
      <h2>{title}</h2>
      <p className="lede">{sub}<br/>
      No credit card. No phone-tag with sales. Just a clean clinic.</p>
      <div className="hero-ctas">
        <a href="https://app.eclinicpro.com/register" className="btn btn-primary btn-lg">Start free — no card needed</a>
        <a href="book-a-demo.html" className="btn btn-ghost-dark btn-lg">Schedule a 15-min demo <Icon name="arrow" size={14} /></a>
      </div>
    </div>
  </section>
);

// Generic sub-page hero (smaller than homepage hero)
const PageHero = ({ eyebrow, title, sub, children }) => (
  <section style={{ padding: '140px 0 60px', textAlign: 'center', position: 'relative', overflow: 'hidden' }}>
    <div style={{ position: 'absolute', inset: 0, background: 'radial-gradient(ellipse at 50% 0%, rgba(15,155,110,0.06) 0%, transparent 60%)', pointerEvents: 'none' }}></div>
    <div className="wrap" style={{ position: 'relative', maxWidth: 820 }}>
      {eyebrow && <span className="eyebrow" style={{ display: 'block', marginBottom: 16 }}>{eyebrow}</span>}
      <h1 className="h-display" style={{ fontSize: 'clamp(40px, 5.5vw, 60px)', letterSpacing: '-1.3px' }}>{title}</h1>
      {sub && <p className="lede" style={{ fontSize: 19, marginTop: 22, maxWidth: 640, marginLeft: 'auto', marginRight: 'auto' }}>{sub}</p>}
      {children && <div style={{ marginTop: 28 }}>{children}</div>}
    </div>
  </section>
);

window.Nav = Nav;
window.Footer = Footer;
window.FinalCTA = FinalCTA;
window.PageHero = PageHero;
window.useReveal = useReveal;
