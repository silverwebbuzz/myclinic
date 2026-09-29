<?php
/**
 * Seller-portal design tokens (same system as the PayGate consoles):
 * Inter 13px, slate neutrals, 10px cards, emerald accent for sellers.
 * Tailwind utilities: bg-bg, bg-sf, bg-sf2, border-ln, text-tx/tx2/tx3, bg-ac, bg-acs, text-act,
 * tones ok/wn/er/in/rv/nt with *b backgrounds (bg-okb text-ok …), bg-nav.
 */
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: {
                    sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    mono: ['"JetBrains Mono"', 'ui-monospace', 'SFMono-Regular', 'Menlo', 'monospace'],
                },
                colors: {
                    bg: '#f5f6f8', sf: '#ffffff', sf2: '#f8fafc', ln: '#e4e7ec', ln2: '#eef0f3',
                    tx: '#0f172a', tx2: '#475569', tx3: '#64748b',
                    ac: '#059669', acs: '#ecfdf5', act: '#065f46',
                    ok: '#047857', okb: '#ecfdf5', wn: '#b45309', wnb: '#fffbeb', er: '#b91c1c', erb: '#fef2f2',
                    in: '#1d4ed8', inb: '#eff6ff', rv: '#6d28d9', rvb: '#f5f3ff', nt: '#475569', ntb: '#f1f5f9',
                    nav: '#0b1220',
                },
            },
        },
    };
</script>
<style>
    body { font-size: 13px; font-feature-settings: 'tnum' 1, 'cv11' 1; -webkit-font-smoothing: antialiased; }
    [x-cloak] { display: none !important; }
</style>
