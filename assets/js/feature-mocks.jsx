// Feature-row UI mockups for the deep-dive sections

const QRMock = () => (
  <div className="feature-mock" style={{ aspectRatio: '4/3', position: 'relative', background: 'linear-gradient(135deg, #F8F9FB 0%, #E8F1FC 100%)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
    {/* card */}
    <div style={{ background: '#fff', borderRadius: 16, padding: 24, width: 240, boxShadow: '0 10px 40px rgba(0,0,0,0.08)', position: 'relative', transform: 'rotate(-3deg)' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 14 }}>
        <div>
          <div style={{ fontSize: 9, color: 'var(--mute)', textTransform: 'uppercase', letterSpacing: '0.08em', fontWeight: 600 }}>Patient ID</div>
          <div style={{ fontSize: 11, fontFamily: '"JetBrains Mono", monospace', color: 'var(--ink)', marginTop: 2 }}>MC-AAR-8841</div>
        </div>
        <div style={{ fontSize: 11, fontWeight: 600, color: 'var(--teal-600)' }}>My<span style={{ color: 'var(--ink)' }}>Clinic</span></div>
      </div>
      {/* QR */}
      <div style={{ width: '100%', aspectRatio: '1', background: '#fff', display: 'grid', gridTemplateColumns: 'repeat(11, 1fr)', gap: 1, padding: 0 }}>
        {Array.from({ length: 121 }).map((_, i) => {
          // generate pseudo-stable pattern
          const on = ((i * 7 + Math.floor(i / 11) * 3) % 5 < 2) || (i < 11 && i % 4 !== 1) || (i > 109);
          // corner squares
          const r = Math.floor(i / 11), c = i % 11;
          const isCorner = (r < 3 && c < 3) || (r < 3 && c > 7) || (r > 7 && c < 3);
          return <div key={i} style={{ background: isCorner || on ? '#0A0A0A' : 'transparent' }}></div>;
        })}
      </div>
      <div style={{ fontSize: 13, fontWeight: 500, marginTop: 14 }}>Aarav Sharma</div>
      <div style={{ fontSize: 10.5, color: 'var(--mute)' }}>DOB 1986 · A+ · Allergies: Penicillin</div>
    </div>

    {/* scan beam */}
    <div style={{ position: 'absolute', right: 40, top: '50%', transform: 'translateY(-50%)', width: 110, height: 200, border: '2px solid var(--teal-600)', borderRadius: 14, padding: 12 }}>
      <div style={{ width: '100%', height: 2, background: 'var(--teal-400)', boxShadow: '0 0 12px var(--teal-400)', marginTop: 90 }}></div>
      <div style={{ position: 'absolute', top: -10, left: -1, right: -1, height: 8, display: 'flex', justifyContent: 'space-between' }}>
        <span style={{ width: 14, height: 14, borderTop: '2px solid var(--teal-600)', borderLeft: '2px solid var(--teal-600)', borderRadius: '4px 0 0 0' }}></span>
        <span style={{ width: 14, height: 14, borderTop: '2px solid var(--teal-600)', borderRight: '2px solid var(--teal-600)', borderRadius: '0 4px 0 0' }}></span>
      </div>
    </div>

    {/* status pill */}
    <div style={{ position: 'absolute', bottom: 20, left: 24, background: '#fff', borderRadius: 10, padding: '8px 12px', display: 'flex', alignItems: 'center', gap: 8, boxShadow: '0 4px 16px rgba(0,0,0,0.06)' }}>
      <span style={{ width: 8, height: 8, borderRadius: '50%', background: 'var(--teal-400)' }}></span>
      <span style={{ fontSize: 11, fontWeight: 500 }}>Loaded in 184ms</span>
    </div>
  </div>
);

const WhatsAppMock = () => (
  <div className="feature-mock" style={{ aspectRatio: '4/3', background: '#E5DDD5', backgroundImage: 'radial-gradient(circle, rgba(0,0,0,0.04) 1px, transparent 1px)', backgroundSize: '10px 10px', padding: 28, display: 'flex', flexDirection: 'column', justifyContent: 'flex-end', gap: 10 }}>
    {/* incoming msg (clinic) */}
    <div style={{ alignSelf: 'flex-start', maxWidth: '75%', background: '#fff', borderRadius: '12px 12px 12px 2px', padding: '8px 12px', boxShadow: '0 1px 1px rgba(0,0,0,0.06)' }}>
      <div style={{ fontSize: 11, fontWeight: 600, color: 'var(--teal-700)', marginBottom: 2 }}>Sunrise Clinic</div>
      <div style={{ fontSize: 13, color: 'var(--ink-2)', lineHeight: 1.4 }}>Hi Riya — your prescription from today is ready.</div>
      <div style={{ fontSize: 10, color: 'var(--mute)', textAlign: 'right', marginTop: 2 }}>9:47</div>
    </div>

    {/* attachment card */}
    <div style={{ alignSelf: 'flex-start', maxWidth: '75%', background: '#fff', borderRadius: '12px 12px 12px 2px', padding: 8, boxShadow: '0 1px 1px rgba(0,0,0,0.06)' }}>
      <div style={{ background: 'var(--bg-2)', borderRadius: 8, padding: '12px 14px', display: 'flex', alignItems: 'center', gap: 10 }}>
        <div style={{ width: 36, height: 44, background: '#fff', border: '0.5px solid var(--line)', borderRadius: 4, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', flexShrink: 0 }}>
          <div style={{ fontSize: 8, fontWeight: 700, color: 'var(--red)' }}>PDF</div>
        </div>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ fontSize: 12, fontWeight: 500, color: 'var(--ink)' }}>Rx_Mehta_18May.pdf</div>
          <div style={{ fontSize: 10, color: 'var(--mute)', marginTop: 1 }}>2 medicines · Dr. A. Sharma</div>
        </div>
      </div>
      <div style={{ fontSize: 10, color: 'var(--mute)', textAlign: 'right', marginTop: 4 }}>9:47</div>
    </div>

    {/* reply */}
    <div style={{ alignSelf: 'flex-end', maxWidth: '70%', background: '#DCF8C6', borderRadius: '12px 12px 2px 12px', padding: '8px 12px' }}>
      <div style={{ fontSize: 13, color: 'var(--ink-2)' }}>Got it! Thank you doctor 🙏</div>
      <div style={{ fontSize: 10, color: 'var(--mute)', textAlign: 'right', marginTop: 2 }}>9:48 ✓✓</div>
    </div>

    {/* delivery toast */}
    <div style={{ position: 'absolute', top: 24, right: 24, background: '#fff', borderRadius: 10, padding: '8px 12px', display: 'flex', alignItems: 'center', gap: 8, boxShadow: '0 4px 16px rgba(0,0,0,0.08)' }}>
      <span style={{ width: 18, height: 18, borderRadius: '50%', background: 'var(--teal-50)', color: 'var(--teal-700)', display: 'grid', placeItems: 'center' }}>
        <Icon name="check" size={11} stroke={2.5} />
      </span>
      <span style={{ fontSize: 11, fontWeight: 500 }}>Delivered automatically</span>
    </div>
  </div>
);

const VitalsMock = () => {
  const bpSys = [148, 142, 138, 140, 136, 134, 132];
  const bpDia = [92, 90, 86, 88, 84, 82, 80];
  const months = ['Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May'];

  const pathFrom = (vals, min, max, color) => {
    const w = 360, h = 100, pad = 0;
    const pts = vals.map((v, i) => {
      const x = (i / (vals.length - 1)) * w;
      const y = h - ((v - min) / (max - min)) * h;
      return [x, y];
    });
    const d = pts.map((p, i) => (i === 0 ? `M${p[0]} ${p[1]}` : `L${p[0]} ${p[1]}`)).join(' ');
    return { d, pts, color };
  };
  const sys = pathFrom(bpSys, 120, 160, 'var(--teal-600)');
  const dia = pathFrom(bpDia, 70, 100, 'var(--blue-600)');

  return (
    <div className="feature-mock" style={{ aspectRatio: '4/3' }}>
      <div className="ui-bar">
        <span className="dot r"></span>
        <span className="dot y"></span>
        <span className="dot g"></span>
        <span className="url">myclinic.app/p/aaravsharma/vitals</span>
      </div>
      <div style={{ padding: 24 }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end', marginBottom: 18 }}>
          <div>
            <div style={{ fontSize: 11, color: 'var(--mute)', textTransform: 'uppercase', letterSpacing: '0.08em', fontWeight: 500 }}>Blood pressure · 7 visits</div>
            <div style={{ fontSize: 26, fontWeight: 300, letterSpacing: '-0.6px', marginTop: 4 }}>132<span style={{ color: 'var(--mute)', fontSize: 18 }}>/80</span> <span style={{ fontSize: 12, fontWeight: 500, color: '#1B8B3D', marginLeft: 4 }}>↓ improving</span></div>
          </div>
          <div style={{ display: 'flex', gap: 6 }}>
            <span style={{ fontSize: 11, padding: '4px 10px', borderRadius: 8, background: 'var(--ink)', color: '#fff', fontWeight: 500 }}>6 mo</span>
            <span style={{ fontSize: 11, padding: '4px 10px', borderRadius: 8, color: 'var(--mute)' }}>1 yr</span>
            <span style={{ fontSize: 11, padding: '4px 10px', borderRadius: 8, color: 'var(--mute)' }}>All</span>
          </div>
        </div>

        {/* chart */}
        <div style={{ position: 'relative' }}>
          <svg viewBox="0 0 360 110" style={{ width: '100%', height: 130, overflow: 'visible' }}>
            {/* gridlines */}
            {[0, 25, 50, 75, 100].map((y, i) => (
              <line key={i} x1="0" x2="360" y1={y} y2={y} stroke="rgba(0,0,0,0.05)" strokeDasharray="2 3"/>
            ))}
            {/* target band */}
            <rect x="0" y="30" width="360" height="40" fill="rgba(15,155,110,0.05)" />
            {/* sys line */}
            <path d={sys.d} fill="none" stroke={sys.color} strokeWidth="2.2" strokeLinecap="round"/>
            {sys.pts.map((p, i) => <circle key={i} cx={p[0]} cy={p[1]} r="3" fill={sys.color}/>)}
            {/* dia line */}
            <path d={dia.d} fill="none" stroke={dia.color} strokeWidth="2.2" strokeLinecap="round" strokeDasharray="0"/>
            {dia.pts.map((p, i) => <circle key={i} cx={p[0]} cy={p[1]} r="3" fill={dia.color}/>)}
          </svg>
          <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 10, color: 'var(--mute)', fontFamily: '"JetBrains Mono", monospace', marginTop: 4 }}>
            {months.map((m, i) => <span key={i}>{m}</span>)}
          </div>
        </div>

        {/* legend */}
        <div style={{ display: 'flex', gap: 18, marginTop: 14, fontSize: 11, color: 'var(--mute)' }}>
          <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}><span style={{ width: 8, height: 8, borderRadius: '50%', background: 'var(--teal-600)' }}></span>Systolic</span>
          <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}><span style={{ width: 8, height: 8, borderRadius: '50%', background: 'var(--blue-600)' }}></span>Diastolic</span>
          <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, marginLeft: 'auto' }}><span style={{ width: 12, height: 8, borderRadius: 2, background: 'rgba(15,155,110,0.15)' }}></span>Target 120/80–130/85</span>
        </div>
      </div>
    </div>
  );
};

window.FEATURE_MOCKS = { QRMock, WhatsAppMock, VitalsMock };
