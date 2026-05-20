// Specialty page app — reads window.__SPEC, renders the right config.
const { Nav, Footer, FinalCTA, useReveal, SpecialtyPage, SPECIALTY_CONFIGS } = window;

const App = () => {
  useReveal();
  const id = window.__SPEC;
  const cfg = SPECIALTY_CONFIGS[id];
  if (!cfg) return <div style={{ padding: 60 }}>Unknown specialty: {String(id)}</div>;
  return <SpecialtyPage d={cfg} />;
};

ReactDOM.createRoot(document.getElementById('root')).render(<App />);
