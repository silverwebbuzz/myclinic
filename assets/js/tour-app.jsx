// Product Tour page — visual walk-through of every major screen
const { useState, useEffect } = React;
const { Nav, Footer, FinalCTA, PageHero, useReveal } = window;
const P = window.PRODUCT_MOCKS;
const S = window.SPEC_MOCKS;
const F = window.FEATURE_MOCKS;
const HeroPreview = window.HeroPreview;
// Alias specialty mocks (their keys are lowercase ids, but JSX needs PascalCase)
const DentalMock = S.dental, DermaMock = S.derma, PedsMock = S.peds, HomeoMock = S.homeo, PhysioMock = S.physio, GPMock = S.gp;

// ---------- Tour content config ----------
const CHAPTERS = [
  {
    id: 'daily',
    label: 'Daily flow',
    title: 'A day at the clinic.',
    blurb: 'The screens you live in. Designed for speed, scannability, and the rhythm of a busy clinic.',
    screens: [
      {
        n: '1.1', t: "Today's dashboard",
        d: "Open the app and see exactly what your day looks like. Today's queue, waiting times, key metrics, modules in use. No setup needed — it just knows.",
        bullets: [
          { i: 'bolt', b: 'Live queue', s: 'Updates as patients arrive and move through.' },
          { i: 'chart', b: 'Daily KPIs', s: 'Average wait, revenue, retention — at a glance.' },
          { i: 'scan', b: 'Smart greeting', s: 'AI surfaces the patients needing attention first.' },
        ],
        frame: <HeroPreview />,
      },
      {
        n: '1.2', t: 'Weekly calendar',
        d: "Drag to reschedule. Click empty space to book. Multi-doctor, multi-room — colour-coded by visit type. Walk-ins join the queue, scheduled visits appear here.",
        bullets: [
          { i: 'cal', b: 'Drag & drop', s: 'Reschedule with a single drag. Conflicts auto-block.' },
          { i: 'whatsapp', b: 'Auto reminders', s: 'WhatsApp + SMS 24h and 1h before each visit.' },
          { i: 'video', b: 'Mixed slots', s: 'In-person and telemedicine in one calendar.' },
        ],
        frame: <P.CalendarMock />,
      },
    ],
  },
  {
    id: 'record',
    label: 'Patient record',
    title: 'The patient, top to bottom.',
    blurb: 'Every visit, prescription, lab, photo, payment — one record, instantly searchable.',
    screens: [
      {
        n: '2.1', t: 'Patient profile',
        d: "Everything about a patient on one screen. Tabs along the top let you drill into visits, prescriptions, vitals, files, and bills. Allergy and chronic-condition flags follow you everywhere.",
        bullets: [
          { i: 'shield', b: 'Allergy flags', s: 'Surface across every Rx and procedure screen.' },
          { i: 'records', b: 'Visit history', s: 'Twelve months on one scrollable timeline.' },
          { i: 'image', b: 'Files & images', s: 'Inline preview for PDFs, X-rays, scans.' },
        ],
        frame: <P.PatientProfileMock />,
      },
      {
        n: '2.2', t: 'Visit notes',
        d: "SOAP, free-form, or specialty templates. Voice-dictate or type. Auto-saves every 2 seconds. The vitals strip stays pinned so you always have the numbers in view.",
        bullets: [
          { i: 'records', b: 'SOAP or free', s: 'Pick the format. Templates by specialty.' },
          { i: 'bolt', b: 'Voice dictation', s: '18 languages. Edit before signing.' },
          { i: 'check', b: 'Auto-save', s: 'Every 2 seconds. Nothing ever lost.' },
        ],
        frame: <P.VisitNotesMock />,
      },
      {
        n: '2.3', t: 'Vitals trends',
        d: "Every reading flows into a chart you can review during a 30-second walk into the room. Target bands shaded. Trend direction called out. Patient sees the same view on their app.",
        bullets: [
          { i: 'chart', b: 'Target bands', s: 'The system knows what your patient should be at.' },
          { i: 'graph', b: 'Auto trends', s: 'Improving, stable, worsening — labelled clearly.' },
          { i: 'globe', b: 'Shared view', s: "Patient sees the same chart in their app." },
        ],
        frame: <F.VitalsMock />,
      },
    ],
  },
  {
    id: 'rx',
    label: 'Prescriptions',
    title: 'From writing to delivery.',
    blurb: 'A drug DB that catches interactions and allergies. A delivery channel patients actually use.',
    screens: [
      {
        n: '3.1', t: 'Prescription writer',
        d: "Type three letters of a drug. The 200,000-item DB suggests with dosage, interaction, and allergy warnings. Common Rx templates are one tap away. Sign with biometric, send anywhere.",
        bullets: [
          { i: 'scan', b: 'Smart search', s: 'Drug DB autocompletes with dose and form.' },
          { i: 'shield', b: 'Allergy check', s: 'Cross-references the patient automatically.' },
          { i: 'check', b: 'Refill control', s: 'Set refill count per drug, per patient.' },
        ],
        frame: <P.RxWriterMock />,
      },
      {
        n: '3.2', t: 'WhatsApp Rx delivery',
        d: "The moment you sign, a clean PDF is sent to the patient's WhatsApp — and optionally to a family caregiver or pharmacy. Read-receipts come back to you. No more 11pm photo requests.",
        bullets: [
          { i: 'whatsapp', b: 'Instant delivery', s: 'Sent before the patient leaves the chair.' },
          { i: 'records', b: 'PDF + signed', s: 'Legally compliant in every region we serve.' },
          { i: 'graph', b: 'Read receipts', s: "See who opened it, who didn't, who picked up." },
        ],
        frame: <F.WhatsAppMock />,
      },
    ],
  },
  {
    id: 'clinical',
    label: 'Specialty tools',
    title: 'Built for your specialty.',
    blurb: "The modules that make My Clinic feel made for you. Add the ones you need; ignore the rest.",
    screens: [
      {
        n: '4.1', t: 'Dental chart',
        d: "Tooth-by-tooth visual chart. Tap a tooth — see every note, image, and procedure since the first visit. FDI, Palmer, or Universal numbering — pick at setup.",
        bullets: [
          { i: 'tooth', b: 'Per-tooth log', s: 'Every action attached to the right tooth.' },
          { i: 'image', b: 'Image attach', s: 'X-ray or intraoral photo, drag and drop.' },
          { i: 'invoice', b: 'Plan + quote', s: 'Auto-generates multi-visit quote PDF.' },
        ],
        frame: <DentalMock />,
      },
      {
        n: '4.2', t: 'Photo timeline (derma)',
        d: "Body-map photo logging, side-by-side compare, lesion measurement. Tap a body region, attach photos — they organize by area and date, forever.",
        bullets: [
          { i: 'image', b: 'Body map', s: 'Photos pinned to anatomical zones.' },
          { i: 'scan', b: 'Compare any two', s: 'Slide wipe between visits.' },
          { i: 'chart', b: 'Measurement', s: 'Area, diameter, asymmetry tracked.' },
        ],
        frame: <DermaMock />,
      },
      {
        n: '4.3', t: 'Growth charts (peds)',
        d: "WHO percentile bands for weight, height, head circumference, BMI. Auto-plotted at every visit. Vaccine reminders woven in. Weight-based dosing flows from the latest measurement.",
        bullets: [
          { i: 'sprout', b: 'WHO bands', s: 'Country-specific schedules supported.' },
          { i: 'cal', b: 'Vaccine due', s: '30-day reminder to parents via WhatsApp.' },
          { i: 'rx', b: 'Weight dosing', s: 'Rx dose calculates from latest weight.' },
        ],
        frame: <PedsMock />,
      },
      {
        n: '4.4', t: 'Repertory (homeo)',
        d: "Long-form case taking — mental generals, physical generals, particulars, modalities. A 3,200-remedy database with antidote rules and miasm tags. Built with classical homeopaths.",
        bullets: [
          { i: 'flask', b: '3,200 remedies', s: 'Searchable with potency and antidotes.' },
          { i: 'records', b: 'Case taking', s: 'Real long-form template, editable per case.' },
          { i: 'shield', b: 'Antidote alerts', s: 'Warns when an antidote shows up in chart.' },
        ],
        frame: <HomeoMock />,
      },
      {
        n: '4.5', t: 'Exercise plans (physio)',
        d: "Drag from a 600-video library into a 7-day program. Send to the patient on WhatsApp; they tap to play and check off as they go. Adherence rolls back into your view.",
        bullets: [
          { i: 'video', b: '600+ videos', s: '8 languages of voice-over.' },
          { i: 'whatsapp', b: 'Follow-along', s: 'Patient checks off exercises in chat.' },
          { i: 'graph', b: 'Adherence', s: "See who's doing it, who isn't." },
        ],
        frame: <PhysioMock />,
      },
    ],
  },
  {
    id: 'business',
    label: 'The business',
    title: 'Run the business of medicine.',
    blurb: 'Invoices, payments, pharmacy stock, revenue analytics — without an accountant or a spreadsheet.',
    screens: [
      {
        n: '5.1', t: 'Analytics & reports',
        d: "Revenue, visits, retention, top procedures — month-over-month, year-over-year. KPIs at the top, charts that respond to your filters, exports to CSV with one click.",
        bullets: [
          { i: 'graph', b: 'KPI grid', s: 'Revenue, visits, new patients, no-shows.' },
          { i: 'chart', b: 'Cohort retention', s: 'See who came back, and why.' },
          { i: 'records', b: 'Export', s: 'CSV, PDF, or scheduled email reports.' },
        ],
        frame: <P.AnalyticsMock />,
      },
      {
        n: '5.2', t: 'Pharmacy inventory',
        d: "Every SKU, every batch, every expiry — tracked. Low-stock and expiring-soon alerts surface before they bite. Reorder drafts assemble themselves from your usage patterns.",
        bullets: [
          { i: 'pill', b: 'Batch tracking', s: 'Lot numbers and expiry per pack.' },
          { i: 'bolt', b: 'Smart alerts', s: 'Low stock + expiring within 30 days.' },
          { i: 'invoice', b: 'Auto-reorder', s: "Drafts the PO so you just confirm." },
        ],
        frame: <P.PharmacyMock />,
      },
      {
        n: '5.3', t: 'Invoicing & payments',
        d: "Every visit generates an invoice. Multi-currency, GST/VAT-ready. Pay by card, UPI, bank transfer, Apple/Google Pay, or cash. Reconciled automatically against the calendar.",
        bullets: [
          { i: 'invoice', b: 'Multi-currency', s: 'Pay in local currency, settle in yours.' },
          { i: 'bolt', b: 'Many methods', s: 'Card, UPI, bank, wallet, cash.' },
          { i: 'check', b: 'Auto-reconcile', s: 'Payments match visits automatically.' },
        ],
        frame: <P.BillingMock />,
      },
    ],
  },
  {
    id: 'patient',
    label: 'Patient experience',
    title: 'What your patients see.',
    blurb: "The white-labeled side of My Clinic. Branded with your clinic's name, designed to make patients want to come back.",
    screens: [
      {
        n: '6.1', t: 'QR patient card',
        d: "Every patient gets a printable card with an encrypted QR. Tap with any phone or webcam to load the full chart in under 200ms. Cuts check-in time by 80%.",
        bullets: [
          { i: 'qr', b: '200ms load', s: 'Faster than typing a name.' },
          { i: 'shield', b: 'Encrypted', s: 'No PHI in the QR itself.' },
          { i: 'records', b: 'Wallet card', s: 'Printable or Apple/Google Wallet.' },
        ],
        frame: <F.QRMock />,
      },
      {
        n: '6.2', t: 'Patient mobile portal',
        d: "Records, prescriptions, bills, upcoming visits, vital trends — in one app, branded with your clinic's name. Patients tap to request refills, see results, message your front desk.",
        bullets: [
          { i: 'globe', b: 'White-labeled', s: 'Your clinic name and brand.' },
          { i: 'rx', b: 'Refill request', s: 'One tap. You approve on your end.' },
          { i: 'chart', b: 'Their own data', s: 'Vitals, growth, lab results — theirs.' },
        ],
        frame: <div style={{ display: 'grid', placeItems: 'center', padding: 40, background: 'linear-gradient(135deg, #F8F9FB 0%, #E0F4EE 100%)' }}><P.PatientPortalMock /></div>,
      },
      {
        n: '6.3', t: 'Telemedicine',
        d: "HD video in the browser — no app install, works on 3G. The patient's record is right there on screen during the call. Sign and send the Rx without leaving the room.",
        bullets: [
          { i: 'video', b: 'In-browser HD', s: 'No app for the patient to install.' },
          { i: 'records', b: 'Chart in-call', s: 'Vitals, allergies, last visit visible.' },
          { i: 'rx', b: 'Sign mid-call', s: 'Rx goes out before the call ends.' },
        ],
        frame: <P.TelemedicineMock />,
      },
    ],
  },
  {
    id: 'setup',
    label: 'Setup & control',
    title: 'Tune the system to your clinic.',
    blurb: 'Turn modules on or off any time. Your monthly bill adjusts within 24 hours. No long contracts, no negotiation.',
    screens: [
      {
        n: '7.1', t: 'Module marketplace',
        d: "Toggle any module on or off. Free modules — patient records, appointments — are always on. Everything else is a single switch. See your projected monthly bill update in real time.",
        bullets: [
          { i: 'check', b: 'One-tap toggle', s: 'On now, off next month — easy.' },
          { i: 'invoice', b: 'Live bill', s: 'See your total update as you change.' },
          { i: 'shield', b: 'Always-on core', s: 'Records & appointments never charge.' },
        ],
        frame: <P.MarketplaceSettingsMock />,
      },
    ],
  },
];

// ---------- Active chapter tracking ----------
const useActiveChapter = () => {
  const [active, setActive] = useState(CHAPTERS[0].id);
  useEffect(() => {
    const io = new IntersectionObserver((entries) => {
      entries.forEach(e => { if (e.isIntersecting) setActive(e.target.id); });
    }, { rootMargin: '-30% 0px -60% 0px' });
    CHAPTERS.forEach(c => {
      const el = document.getElementById(c.id);
      if (el) io.observe(el);
    });
    return () => io.disconnect();
  }, []);
  return active;
};

const TOC = () => {
  const active = useActiveChapter();
  return (
    <div className="tour-toc">
      <h6>Chapters</h6>
      {CHAPTERS.map((c, i) => (
        <a key={c.id} href={`#${c.id}`}
          className={active === c.id ? 'is-active' : ''}>
          <span style={{ color: 'var(--mute)', fontFamily: '"JetBrains Mono", monospace', fontSize: 11, marginRight: 8 }}>{i + 1}</span>
          {c.label}
        </a>
      ))}
    </div>
  );
};

const ScreenBlock = ({ s }) => (
  <div className="screen-block reveal">
    <div className="meta">
      <div>
        <span className="num">{s.n}</span>
        <h3>{s.t}</h3>
      </div>
      <p>{s.d}</p>
    </div>
    <div className="screen-frame">{s.frame}</div>
    <div className="bullets">
      {s.bullets.map((b, i) => (
        <div key={i} className="bullet">
          <div className="ico"><Icon name={b.i} size={12} stroke={2}/></div>
          <div>
            <b>{b.b}</b>
            <span>{b.s}</span>
          </div>
        </div>
      ))}
    </div>
  </div>
);

const App = () => {
  useReveal();
  return (
    <>
      <Nav />
      <PageHero
        eyebrow="Product tour"
        title="See My Clinic, screen by screen."
        sub="Twenty-plus screens across seven chapters. The same UI doctors in 47 countries use every day. Scroll, or jump to a chapter."
      >
        <div style={{ display: 'inline-flex', gap: 10, marginTop: 8 }}>
          <a href="index.html#cta" className="btn btn-primary">Start free</a>
          <a href="book-a-demo.html" className="btn btn-ghost-dark">Live walkthrough <Icon name="arrow" size={14}/></a>
        </div>
      </PageHero>

      <section style={{ paddingTop: 40, paddingBottom: 60 }}>
        <div className="wrap-wide">
          <div className="tour-wrap">
            <TOC />
            <div>
              {CHAPTERS.map((c, ci) => (
                <section key={c.id} id={c.id} className="tour-chapter">
                  <div className="head reveal">
                    <span className="eyebrow">{`Chapter ${ci + 1} · ${c.label}`}</span>
                    <h2>{c.title}</h2>
                    <p className="lede">{c.blurb}</p>
                  </div>
                  {c.screens.map((s, si) => <ScreenBlock key={si} s={s} />)}
                </section>
              ))}
            </div>
          </div>
        </div>
      </section>

      <FinalCTA
        title="See it in your hands, in 15 minutes."
        sub="Book a live demo. We'll set up the screens for your specialty before the call starts."
      />
      <Footer />
    </>
  );
};

ReactDOM.createRoot(document.getElementById('root')).render(<App />);
