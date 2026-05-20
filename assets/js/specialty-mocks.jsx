// Phone-shaped specialty mockups
const { useState, useEffect, useRef } = React;

const PhoneShell = ({ children, tint = 'var(--bg-2)' }) => (
  <div className="device-frame">
    <div className="device-screen" style={{ background: tint }}>
      {/* status bar */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '12px 20px 6px', fontSize: 11, fontWeight: 600 }}>
        <span>9:41</span>
        <span style={{ display: 'inline-flex', gap: 4, alignItems: 'center' }}>
          <span style={{ width: 14, height: 8, border: '0.8px solid currentColor', borderRadius: 2, position: 'relative' }}>
            <span style={{ position: 'absolute', inset: 1, background: 'currentColor', borderRadius: 1 }}></span>
          </span>
        </span>
      </div>
      {children}
    </div>
  </div>
);

const GPMock = () => (
  <PhoneShell>
    <div style={{ padding: '8px 16px 14px' }}>
      <div style={{ fontSize: 11, color: 'var(--mute)', textTransform: 'uppercase', letterSpacing: '0.08em', fontWeight: 500 }}>Now in room</div>
      <div style={{ fontSize: 19, fontWeight: 500, letterSpacing: '-0.3px', marginTop: 2 }}>Riya Mehta, 38</div>
      <div style={{ display: 'flex', gap: 6, marginTop: 6, flexWrap: 'wrap' }}>
        <span style={{ fontSize: 10, padding: '2px 7px', background: 'var(--teal-50)', color: 'var(--teal-800)', borderRadius: 8 }}>HTN</span>
        <span style={{ fontSize: 10, padding: '2px 7px', background: '#FFF3E0', color: '#8B5500', borderRadius: 8 }}>F/U</span>
      </div>
    </div>
    <div style={{ padding: '0 12px', display: 'flex', flexDirection: 'column', gap: 8 }}>
      <div style={{ background: '#fff', borderRadius: 12, padding: '12px 14px', border: '0.5px solid var(--line)' }}>
        <div style={{ fontSize: 10, color: 'var(--mute)', fontWeight: 500, textTransform: 'uppercase', letterSpacing: '0.06em' }}>Vitals · captured 9:28</div>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 8, marginTop: 8 }}>
          {[['BP','138/86','mmHg','var(--amber)'], ['HR','78','bpm','var(--ink)'], ['SpO₂','98','%','#1B8B3D']].map((v, i) => (
            <div key={i}>
              <div style={{ fontSize: 9, color: 'var(--mute)' }}>{v[0]}</div>
              <div style={{ fontSize: 16, fontWeight: 500, color: v[3], letterSpacing: '-0.3px' }}>{v[1]}</div>
              <div style={{ fontSize: 9, color: 'var(--mute)' }}>{v[2]}</div>
            </div>
          ))}
        </div>
      </div>
      <div style={{ background: '#fff', borderRadius: 12, padding: '12px 14px', border: '0.5px solid var(--line)' }}>
        <div style={{ fontSize: 10, color: 'var(--mute)', fontWeight: 500, textTransform: 'uppercase', letterSpacing: '0.06em', marginBottom: 6 }}>Common Rx · suggested</div>
        <div style={{ fontSize: 12, fontWeight: 500 }}>Amlodipine 5mg</div>
        <div style={{ fontSize: 10.5, color: 'var(--mute)', marginTop: 2 }}>1 tab OD · 30 days</div>
      </div>
      <button style={{ background: 'var(--ink)', color: '#fff', borderRadius: 10, padding: '12px', fontSize: 13, fontWeight: 500 }}>Start visit notes</button>
    </div>
  </PhoneShell>
);

const HomeoMock = () => (
  <PhoneShell>
    <div style={{ padding: '8px 16px 14px' }}>
      <div style={{ fontSize: 11, color: 'var(--mute)', textTransform: 'uppercase', letterSpacing: '0.08em', fontWeight: 500 }}>Case taking</div>
      <div style={{ fontSize: 18, fontWeight: 500, letterSpacing: '-0.3px', marginTop: 2 }}>Suresh Kumar, 52</div>
    </div>
    <div style={{ padding: '0 12px', display: 'flex', flexDirection: 'column', gap: 8 }}>
      <div style={{ background: '#fff', borderRadius: 12, padding: '12px 14px', border: '0.5px solid var(--line)' }}>
        <div style={{ fontSize: 10, color: 'var(--mute)', fontWeight: 500, textTransform: 'uppercase' }}>Mental generals</div>
        <div style={{ fontSize: 12, color: 'var(--ink-2)', lineHeight: 1.55, marginTop: 4 }}>Anxiety, anticipation. Better lying on right side. Worse 4–8pm.</div>
      </div>
      <div style={{ background: '#fff', borderRadius: 12, padding: '12px 14px', border: '0.5px solid var(--line)' }}>
        <div style={{ fontSize: 10, color: 'var(--mute)', fontWeight: 500, textTransform: 'uppercase', marginBottom: 6 }}>Selected remedy</div>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <div>
            <div style={{ fontSize: 14, fontWeight: 500 }}>Lycopodium</div>
            <div style={{ fontSize: 10.5, color: 'var(--mute)' }}>Miasm: Sycotic</div>
          </div>
          <div style={{ background: 'var(--teal-50)', color: 'var(--teal-800)', fontSize: 11, fontWeight: 600, padding: '4px 9px', borderRadius: 8 }}>200C</div>
        </div>
      </div>
      <div style={{ background: 'var(--bg-2)', borderRadius: 10, padding: '8px 12px', fontSize: 10.5, color: 'var(--mute)' }}>
        ⚠ Antidote: coffee, camphor. Wait 4 weeks for assessment.
      </div>
    </div>
  </PhoneShell>
);

const DentalMock = () => (
  <PhoneShell>
    <div style={{ padding: '8px 16px 12px' }}>
      <div style={{ fontSize: 11, color: 'var(--mute)', textTransform: 'uppercase', letterSpacing: '0.08em', fontWeight: 500 }}>Dental chart · FDI</div>
      <div style={{ fontSize: 17, fontWeight: 500, letterSpacing: '-0.3px', marginTop: 2 }}>Emma Whitfield, 32</div>
    </div>
    <div style={{ padding: '0 12px' }}>
      <div style={{ background: '#fff', borderRadius: 12, padding: 12, border: '0.5px solid var(--line)' }}>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(8, 1fr)', gap: 3 }}>
          {Array.from({ length: 16 }).map((_, i) => {
            const c = i === 2 ? '#FF453A' : i === 5 ? '#FF9F0A' : i === 11 ? 'var(--teal-600)' : '#E5E5EA';
            return <div key={i} style={{ aspectRatio: '1', borderRadius: 4, background: c, opacity: c === '#E5E5EA' ? 0.6 : 1 }}></div>;
          })}
        </div>
        <div style={{ height: 8 }}></div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(8, 1fr)', gap: 3 }}>
          {Array.from({ length: 16 }).map((_, i) => {
            const c = i === 8 ? 'var(--teal-600)' : i === 13 ? '#FF453A' : '#E5E5EA';
            return <div key={i} style={{ aspectRatio: '1', borderRadius: 4, background: c, opacity: c === '#E5E5EA' ? 0.6 : 1 }}></div>;
          })}
        </div>
        <div style={{ display: 'flex', gap: 10, marginTop: 12, fontSize: 9.5, color: 'var(--mute)' }}>
          <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}><span style={{ width: 7, height: 7, background: '#FF453A', borderRadius: 2 }}></span>Caries</span>
          <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}><span style={{ width: 7, height: 7, background: '#FF9F0A', borderRadius: 2 }}></span>Watch</span>
          <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}><span style={{ width: 7, height: 7, background: 'var(--teal-600)', borderRadius: 2 }}></span>Filled</span>
        </div>
      </div>
      <div style={{ marginTop: 10, background: '#fff', borderRadius: 12, padding: '10px 12px', border: '0.5px solid var(--line)' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12 }}>
          <span style={{ fontWeight: 500 }}>Treatment plan</span>
          <span style={{ color: 'var(--teal-700)', fontWeight: 500 }}>$1,240</span>
        </div>
        <div style={{ fontSize: 10.5, color: 'var(--mute)', marginTop: 2 }}>3 visits · composite #16, RCT #26, scaling</div>
      </div>
    </div>
  </PhoneShell>
);

const DermaMock = () => (
  <PhoneShell>
    <div style={{ padding: '8px 16px 12px' }}>
      <div style={{ fontSize: 11, color: 'var(--mute)', textTransform: 'uppercase', letterSpacing: '0.08em', fontWeight: 500 }}>Lesion timeline · L. forearm</div>
      <div style={{ fontSize: 17, fontWeight: 500, letterSpacing: '-0.3px', marginTop: 2 }}>Marta Lopez, 29</div>
    </div>
    <div style={{ padding: '0 12px' }}>
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
        {[['Before', '#E8C9B6', 'Mar 4'], ['Week 6', '#F3D9C6', 'Apr 15']].map((p, i) => (
          <div key={i} style={{ background: '#fff', borderRadius: 12, overflow: 'hidden', border: '0.5px solid var(--line)' }}>
            <div style={{ aspectRatio: '1', background: `linear-gradient(135deg, ${p[1]}, #D8B69A)`, position: 'relative' }}>
              <div style={{ position: 'absolute', top: '38%', left: '40%', width: 18, height: 18, borderRadius: '50%', background: i === 0 ? '#A86040' : '#C88E70', boxShadow: '0 0 0 1px rgba(255,255,255,0.6)' }}></div>
            </div>
            <div style={{ padding: '6px 8px' }}>
              <div style={{ fontSize: 11, fontWeight: 500 }}>{p[0]}</div>
              <div style={{ fontSize: 9.5, color: 'var(--mute)' }}>{p[2]}</div>
            </div>
          </div>
        ))}
      </div>
      <div style={{ marginTop: 10, background: '#fff', borderRadius: 12, padding: '10px 12px', border: '0.5px solid var(--line)' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12 }}>
          <span style={{ fontWeight: 500 }}>Measurement</span>
          <span style={{ color: '#1B8B3D', fontWeight: 500 }}>−38%</span>
        </div>
        <div style={{ fontSize: 10.5, color: 'var(--mute)', marginTop: 2 }}>9.2mm → 5.7mm · responding well</div>
      </div>
    </div>
  </PhoneShell>
);

const PedsMock = () => (
  <PhoneShell>
    <div style={{ padding: '8px 16px 12px' }}>
      <div style={{ fontSize: 11, color: 'var(--mute)', textTransform: 'uppercase', letterSpacing: '0.08em', fontWeight: 500 }}>Growth · 14 mo</div>
      <div style={{ fontSize: 17, fontWeight: 500, letterSpacing: '-0.3px', marginTop: 2 }}>Sneha Iyer · F</div>
    </div>
    <div style={{ padding: '0 12px' }}>
      <div style={{ background: '#fff', borderRadius: 12, padding: 14, border: '0.5px solid var(--line)' }}>
        <div style={{ fontSize: 10, color: 'var(--mute)', fontWeight: 500, textTransform: 'uppercase' }}>Weight-for-age · WHO</div>
        <svg viewBox="0 0 200 100" style={{ width: '100%', height: 100, marginTop: 6 }}>
          <path d="M0 80 Q40 60 80 50 T160 35 L200 30" stroke="rgba(0,0,0,0.1)" fill="none" strokeWidth="1" strokeDasharray="3 3"/>
          <path d="M0 90 Q40 78 80 70 T160 58 L200 55" stroke="rgba(0,0,0,0.1)" fill="none" strokeWidth="1" strokeDasharray="3 3"/>
          <path d="M0 95 Q40 88 80 82 T160 75 L200 73" stroke="rgba(0,0,0,0.1)" fill="none" strokeWidth="1" strokeDasharray="3 3"/>
          <path d="M0 88 Q40 76 80 65 L120 55 L160 48" stroke="var(--teal-600)" fill="none" strokeWidth="2"/>
          {[[0,88],[40,76],[80,65],[120,55],[160,48]].map(([x,y],i) => (
            <circle key={i} cx={x} cy={y} r="3" fill="var(--teal-600)"/>
          ))}
        </svg>
        <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 10, color: 'var(--mute)', marginTop: 6 }}>
          <span>9.4 kg · 62nd %ile</span>
          <span style={{ color: '#1B8B3D', fontWeight: 500 }}>On track</span>
        </div>
      </div>
      <div style={{ marginTop: 10, background: 'var(--teal-50)', borderRadius: 10, padding: '10px 12px', fontSize: 11.5, color: 'var(--teal-800)' }}>
        <strong style={{ fontWeight: 600 }}>Vaccine due:</strong> MMR booster · book within 30 days
      </div>
    </div>
  </PhoneShell>
);

const PhysioMock = () => (
  <PhoneShell>
    <div style={{ padding: '8px 16px 12px' }}>
      <div style={{ fontSize: 11, color: 'var(--mute)', textTransform: 'uppercase', letterSpacing: '0.08em', fontWeight: 500 }}>Week 3 program</div>
      <div style={{ fontSize: 17, fontWeight: 500, letterSpacing: '-0.3px', marginTop: 2 }}>Lower back · L4-L5</div>
    </div>
    <div style={{ padding: '0 12px', display: 'flex', flexDirection: 'column', gap: 8 }}>
      {[
        { n: 'Cat-cow stretch', t: '3×10', done: true },
        { n: 'Bird dog', t: '3×8 each side', done: true },
        { n: 'Glute bridge', t: '3×12', done: false, today: true },
        { n: 'Dead bug', t: '3×10', done: false },
      ].map((e, i) => (
        <div key={i} style={{ background: '#fff', borderRadius: 12, padding: '10px 12px', border: '0.5px solid var(--line)', display: 'flex', alignItems: 'center', gap: 10 }}>
          <div style={{ width: 36, height: 36, borderRadius: 8, background: e.today ? 'var(--teal-50)' : 'var(--bg-2)', display: 'grid', placeItems: 'center', color: e.today ? 'var(--teal-700)' : 'var(--mute)' }}>
            <Icon name="play" size={12} />
          </div>
          <div style={{ flex: 1 }}>
            <div style={{ fontSize: 12.5, fontWeight: 500 }}>{e.n}</div>
            <div style={{ fontSize: 10.5, color: 'var(--mute)' }}>{e.t}</div>
          </div>
          {e.done && <Icon name="check" size={14} stroke={2.5} className="" />}
          {e.today && <span style={{ fontSize: 10, fontWeight: 500, color: 'var(--teal-700)' }}>Today</span>}
        </div>
      ))}
      <div style={{ fontSize: 10.5, color: 'var(--mute)', textAlign: 'center', marginTop: 4 }}>3 of 4 done · keep going 💪</div>
    </div>
  </PhoneShell>
);

const SPEC_MOCKS = { gp: GPMock, homeo: HomeoMock, dental: DentalMock, derma: DermaMock, peds: PedsMock, physio: PhysioMock };

window.SPEC_MOCKS = SPEC_MOCKS;
