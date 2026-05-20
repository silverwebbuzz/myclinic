// Shared data for My Clinic prototype

const MODULES = [
  { id: 'records', name: 'Patient records', desc: 'Encrypted, structured notes with attachments.', price: 0, free: true, specs: ['gp','dental','homeo','derma','peds','physio'], icon: 'records' },
  { id: 'appts', name: 'Appointments', desc: 'Calendar, reminders, no-show tracking.', price: 0, free: true, specs: ['gp','dental','homeo','derma','peds','physio'], icon: 'cal' },
  { id: 'rx', name: 'Prescriptions', desc: 'Digital Rx with WhatsApp delivery.', price: 6, specs: ['gp','dental','homeo','derma','peds','physio'], icon: 'rx' },
  { id: 'qr', name: 'QR patient card', desc: 'Tap a card, load the chart in 200ms.', price: 4, specs: ['gp','dental','homeo','derma','peds','physio'], icon: 'qr' },
  { id: 'vitals', name: 'Vitals & charts', desc: 'BP, sugar, weight — visual trends.', price: 5, specs: ['gp','derma','peds','physio','homeo'], icon: 'chart' },
  { id: 'pharma', name: 'Pharmacy', desc: 'Inventory, batches, expiry alerts.', price: 12, specs: ['gp','dental','homeo','derma','peds'], icon: 'pill' },
  { id: 'lab', name: 'Lab orders', desc: 'Order, receive, attach results.', price: 8, specs: ['gp','derma','peds','physio'], icon: 'lab' },
  { id: 'tele', name: 'Telemedicine', desc: 'HD video visits, in-browser.', price: 14, specs: ['gp','derma','peds','physio','homeo'], icon: 'video' },
  { id: 'diet', name: 'Diet plans', desc: 'Templated meal plans, customizable.', price: 6, specs: ['gp','derma','peds','physio'], icon: 'leaf' },
  { id: 'billing', name: 'Billing & invoices', desc: 'Multi-currency, GST/VAT ready.', price: 9, specs: ['gp','dental','homeo','derma','peds','physio'], icon: 'invoice' },
  { id: 'chart', name: 'Dental charting', desc: 'Tooth-by-tooth visual chart.', price: 9, specs: ['dental'], icon: 'tooth' },
  { id: 'remedy', name: 'Remedy database', desc: '3,200 remedies with potency & antidotes.', price: 7, specs: ['homeo'], icon: 'flask' },
  { id: 'derma', name: 'Skin imaging', desc: 'Before/after with annotation.', price: 11, specs: ['derma'], icon: 'image' },
  { id: 'growth', name: 'Growth charts', desc: 'WHO percentile tracking for kids.', price: 5, specs: ['peds'], icon: 'sprout' },
  { id: 'physio', name: 'Exercise plans', desc: 'Video-led home exercise programs.', price: 8, specs: ['physio'], icon: 'physio' },
  { id: 'reports', name: 'Analytics & reports', desc: 'Revenue, retention, top diagnoses.', price: 9, specs: ['gp','dental','homeo','derma','peds','physio'], icon: 'graph' },
];

const SPECIALTIES = [
  {
    id: 'gp', label: 'General practice',
    title: 'The everyday doctor, finally given great tools.',
    blurb: 'OPD-ready out of the box. Vitals, history, common Rx templates, and a fast triage flow for walk-ins.',
    feats: [
      'Triage view with vitals on entry',
      'Common Rx templates for top 200 conditions',
      'Family history & chronic care tracking',
      'Patient queue with average wait estimate',
    ],
    mock: 'gp'
  },
  {
    id: 'homeo', label: 'Homeopathy',
    title: 'Built for case taking, not just clicking.',
    blurb: 'Long-form repertory notes, potency picker, antidote warnings, and a built-in remedy database with 3,200 entries.',
    feats: [
      'Repertory case-taking template',
      'Potency picker (6X — 1M) with notes',
      'Antidote & follow-up rules engine',
      'Miasmatic classification tags',
    ],
    mock: 'homeo'
  },
  {
    id: 'dental', label: 'Dental',
    title: 'A tooth-shaped chart, not a clipboard.',
    blurb: 'Visual dental charting, treatment plans with quotes, and image attachments per tooth — all in the patient record.',
    feats: [
      'FDI/Palmer/Universal numbering',
      'Treatment plan with multi-visit quotes',
      'Image attach per quadrant or tooth',
      'Recall reminders by procedure type',
    ],
    mock: 'dental'
  },
  {
    id: 'derma', label: 'Dermatology',
    title: 'Before, after, and everything in between.',
    blurb: 'Photo timelines per body area, side-by-side compare, and annotation tools designed for skin and lesion tracking.',
    feats: [
      'Body-map photo logging',
      'Side-by-side before/after compare',
      'Lesion annotation & measurement',
      'Procedure-linked consent forms',
    ],
    mock: 'derma'
  },
  {
    id: 'peds', label: 'Pediatrics',
    title: 'Watch them grow, on real percentile curves.',
    blurb: 'WHO growth charts, vaccination scheduler, parent-facing summaries — and dosing by weight, automatically.',
    feats: [
      'WHO percentile growth charts',
      'Vaccination scheduler with reminders',
      'Weight-based Rx dosing assistant',
      'Parent-facing visit summary',
    ],
    mock: 'peds'
  },
  {
    id: 'physio', label: 'Physiotherapy',
    title: 'Programs your patients actually follow.',
    blurb: 'Build exercise programs from a 600-video library and send them to your patient as a follow-along plan in WhatsApp.',
    feats: [
      'Video library: 600+ exercises',
      'Weekly program builder',
      'Patient follow-along with check-ins',
      'Outcome scales (ODI, NDI, etc.)',
    ],
    mock: 'physio'
  },
];

const SPEC_TABS = [
  { id: 'all', label: 'All' },
  { id: 'gp', label: 'General' },
  { id: 'dental', label: 'Dental' },
  { id: 'homeo', label: 'Homeopathy' },
  { id: 'derma', label: 'Dermatology' },
  { id: 'peds', label: 'Pediatrics' },
  { id: 'physio', label: 'Physio' },
];

const TESTIMONIALS = [
  {
    name: 'Dr. Aarav Sharma', initials: 'AS', spec: 'GP · Mumbai, India',
    quote: 'I switched from paper in two weekends. The QR patient cards alone cut my check-in time by 80%. My nurses cried with joy.',
  },
  {
    name: 'Dr. Priya Iyer', initials: 'PI', spec: 'Homeopath · Pune, India',
    quote: 'Finally, software that knows what a repertory case-taking sheet looks like. The remedy DB is shockingly thorough.',
  },
  {
    name: 'Dr. James Whitfield', initials: 'JW', spec: 'Dentist · Toronto, Canada',
    quote: 'The dental chart is genuinely beautiful. We added the imaging module after three weeks and the timelines sold our patients on whitening.',
  },
  {
    name: 'Dr. Amara Okonkwo', initials: 'AO', spec: 'Pediatrician · Lagos, Nigeria',
    quote: 'Parents love the weight-based dosing and the summary they get on WhatsApp. I love that I no longer fight a spreadsheet.',
  },
  {
    name: 'Dr. Sofia Marín', initials: 'SM', spec: 'Dermatologist · Madrid, Spain',
    quote: 'The before/after compare convinced me to stay. The pricing convinced my accountant. We pay €34/month for what we use.',
  },
  {
    name: 'Dr. Ben Carter', initials: 'BC', spec: 'GP · Bristol, UK',
    quote: 'Calm, fast, no junk. It feels like a tool made for clinicians, not for the people selling to clinicians.',
  },
];

const FAQS = [
  { q: 'Can I really pick only the modules I need?', a: 'Yes. Every module is independent — you can add, remove, or pause any module at any time. Your bill updates immediately and we never charge for unused modules.' },
  { q: 'What does "free forever" mean?', a: 'Patient records and appointments stay free forever, up to 200 active patients. No trial expiry, no credit card needed. Real free, not "free until we trap you" free.' },
  { q: 'Is my patient data private and secure?', a: 'HIPAA, GDPR, and India DPDP compliant. Data is encrypted at rest and in transit. You can export everything as portable JSON or PDF anytime.' },
  { q: 'Can I migrate from another system?', a: 'Yes — we import from Practo, Drchrono, SimplePractice, Cliniko, and most spreadsheets. Migration is free and our team helps for clinics with over 500 records.' },
  { q: 'Does it work offline?', a: 'Yes. The desktop and tablet apps cache your day\'s schedule and records locally. When you reconnect, everything syncs automatically.' },
  { q: 'What languages are supported?', a: 'The interface is in 18 languages including English, Hindi, Spanish, Portuguese, Arabic, Mandarin, French, and Bahasa. Prescription printing supports any UTF-8 script.' },
];

const MARQUEE = [
  'Sunrise Dental · London',
  'Dr. Sharma OPD · Mumbai',
  'PediaCare · Toronto',
  'Skin & Co · Madrid',
  'Dr. Patel Homeo · Pune',
  'Hill Family Practice · Sydney',
  'Bright Smiles · Dubai',
  'Riverside Physio · Bristol',
  'Akin Pediatrics · Lagos',
  'Dr. Liang GP · Singapore',
  'Northside Derma · Chicago',
  'Dr. Sato Clinic · Tokyo',
];

window.MC_DATA = { MODULES, SPECIALTIES, SPEC_TABS, TESTIMONIALS, FAQS, MARQUEE };
