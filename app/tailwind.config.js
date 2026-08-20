/** @type {import('tailwindcss').Config} */
module.exports = {
  // Scan every place a Tailwind class can appear — views, controllers/helpers
  // that return literal class strings, and nav/config helpers.
  content: [
    './views/**/*.php',
    './app/**/*.php',
    './config/**/*.php',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
      // Brand palette resolves to per-clinic CSS variables injected inline in
      // layouts/base.php (:root), so one compiled stylesheet themes every clinic.
      colors: {
        brand: {
          DEFAULT: 'var(--brand)',
          light: 'var(--brand-light)',
          soft: 'var(--brand-soft)',
          dark: 'var(--brand-dark)',
        },
      },
    },
  },
  plugins: [],
};
