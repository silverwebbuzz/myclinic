// Simple SF-Symbols-style outline icons drawn with SVG strokes
const Icon = ({ name, size = 20, stroke = 1.5, className = '' }) => {
  const props = { width: size, height: size, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: stroke, strokeLinecap: 'round', strokeLinejoin: 'round', className };
  switch (name) {
    case 'arrow':
      return <svg {...props}><path d="M5 12h14M13 5l7 7-7 7"/></svg>;
    case 'play':
      return <svg {...props}><polygon points="6 4 20 12 6 20 6 4" fill="currentColor" stroke="none"/></svg>;
    case 'check':
      return <svg {...props}><polyline points="4 12 10 18 20 6"/></svg>;
    case 'records':
      return <svg {...props}><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>;
    case 'cal':
      return <svg {...props}><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>;
    case 'rx':
      return <svg {...props}><path d="M7 4h5a3 3 0 0 1 0 6H7zM7 10v10M7 14h4l5 6M14 14l6 6"/></svg>;
    case 'qr':
      return <svg {...props}><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3M21 14v3M14 21h3M21 17v4"/></svg>;
    case 'chart':
      return <svg {...props}><path d="M4 19V5M4 19h16M8 16l3-5 3 3 4-7"/></svg>;
    case 'pill':
      return <svg {...props}><rect x="2.5" y="7" width="19" height="10" rx="5" transform="rotate(-30 12 12)"/><path d="M9 8l6 8"/></svg>;
    case 'lab':
      return <svg {...props}><path d="M9 3v6L4 19a2 2 0 0 0 2 3h12a2 2 0 0 0 2-3l-5-10V3M9 3h6M9 14h6"/></svg>;
    case 'video':
      return <svg {...props}><rect x="3" y="6" width="13" height="12" rx="2"/><path d="M16 10l5-3v10l-5-3z"/></svg>;
    case 'leaf':
      return <svg {...props}><path d="M21 4S15 2 10 7s-5 11-5 13c0 0 6 0 11-5s5-11 5-11zM5 20l11-11"/></svg>;
    case 'invoice':
      return <svg {...props}><path d="M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6M9 16h3"/></svg>;
    case 'tooth':
      return <svg {...props}><path d="M7 4c-2 0-3 2-3 4 0 3 2 4 2 7s1 7 3 7 2-4 3-4 1 4 3 4 3-4 3-7 2-4 2-7c0-2-1-4-3-4-2 0-3 1-5 1s-3-1-5-1z"/></svg>;
    case 'flask':
      return <svg {...props}><path d="M9 3h6M10 3v6L5 19a2 2 0 0 0 2 3h10a2 2 0 0 0 2-3l-5-10V3"/><circle cx="11" cy="16" r="0.8" fill="currentColor" stroke="none"/><circle cx="14" cy="18" r="0.6" fill="currentColor" stroke="none"/></svg>;
    case 'image':
      return <svg {...props}><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.5"/><path d="M3 17l5-5 4 4 3-3 6 6"/></svg>;
    case 'sprout':
      return <svg {...props}><path d="M12 21v-9M12 12c0-4 3-6 6-6 0 4-3 6-6 6zM12 12c0-3-2-5-5-5 0 3 2 5 5 5z"/></svg>;
    case 'physio':
      return <svg {...props}><circle cx="9" cy="5" r="2"/><path d="M9 8v5l-3 4M9 13l4 2 3 5M13 11l4-1"/></svg>;
    case 'graph':
      return <svg {...props}><path d="M4 4v16h16M8 16V10M12 16V6M16 16v-4M20 16v-8"/></svg>;
    case 'scan':
      return <svg {...props}><path d="M4 8V5a1 1 0 0 1 1-1h3M4 16v3a1 1 0 0 0 1 1h3M20 8V5a1 1 0 0 0-1-1h-3M20 16v3a1 1 0 0 1-1 1h-3M4 12h16"/></svg>;
    case 'whatsapp':
      return <svg {...props}><path d="M3 21l1.7-5.1A8 8 0 1 1 8.1 19.3z"/><path d="M8.5 9c.3 1 1.4 4 4.5 5.5 1.5.7 2.4-.4 2.7-.9.3-.5-.2-1-1-1.4-.8-.4-.8-.1-1.2.2-.3.3-1.6-.4-2.5-1.8s-.6-1.7-.4-1.9c.2-.2.5-.5.4-1-.1-.5-.7-1.6-1-1.7-.3-.1-.6 0-.9.2-.3.2-.9.8-.6 2.8z"/></svg>;
    case 'globe':
      return <svg {...props}><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>;
    case 'shield':
      return <svg {...props}><path d="M12 3l8 3v6c0 5-4 8-8 9-4-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/></svg>;
    case 'bolt':
      return <svg {...props}><polygon points="13 2 4 14 11 14 9 22 20 9 13 9 15 2"/></svg>;
    case 'menu':
      return <svg {...props}><path d="M3 6h18M3 12h18M3 18h18"/></svg>;
    default:
      return <svg {...props}><circle cx="12" cy="12" r="6"/></svg>;
  }
};

window.Icon = Icon;
