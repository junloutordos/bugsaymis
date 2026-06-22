import defaultTheme from 'tailwindcss/defaultTheme'
import forms from '@tailwindcss/forms'
import typography from '@tailwindcss/typography'

/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
    './storage/framework/views/*.php',
    './resources/views/**/*.blade.php',
    './resources/js/**/*.vue',
  ],

  theme: {
    extend: {
      fontFamily: {
        // Use Inter first, fallback to Figtree, then system sans
        sans: [
          'Inter',        // Primary: clean, modern
          'Figtree',      // Secondary: fallback from Laravel Breeze
          'system-ui',
          '-apple-system',
          'BlinkMacSystemFont',
          'Segoe UI',
          'Roboto',
          'Helvetica Neue',
          'Arial',
          'Noto Sans',
          'sans-serif',
        ],
        // Atlas brand heading typeface — apply via `font-heading`
        heading: ['Poppins', ...defaultTheme.fontFamily.sans],
      },
      colors: {
        // Atlas brand blue scale — overrides Tailwind's stock `indigo`.
        // 500 = Sky Cyan (#019FE6), 600 = Wordmark Blue (#0867DB), 900 = Navy Deep (#0A2A5E),
        // per the Atlas Brand Identity Guide. This repaints every existing `indigo-*` class
        // (buttons, links, focus rings) app-wide without touching individual components.
        indigo: {
          50: '#EFF6FF',
          100: '#DCEEFE',
          200: '#BFE0FD',
          300: '#92CBFC',
          400: '#58AEF8',
          500: '#019FE6',
          600: '#0867DB',
          700: '#0552B0',
          800: '#073E85',
          900: '#0A2A5E',
        },
        success: {
          50: '#ECFDF5',
          100: '#D1FAE5',
          500: '#10B981',
          600: '#059669',
        },
        warning: {
          50: '#FFFBEB',
          100: '#FEF3C7',
          500: '#F59E0B',
          600: '#D97706',
        },
        danger: {
          50: '#FEF2F2',
          100: '#FEE2E2',
          500: '#EF4444',
          600: '#DC2626',
        },
      },
      boxShadow: {
        card: '0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1)',
        soft: '0 1px 2px 0 rgb(0 0 0 / 0.05)',
      },
      borderRadius: {
        xl: '1rem',
        '2xl': '1.5rem',
      },
    },
  },

  plugins: [forms, typography],
  safelist: [
  'dataTable-selector',
  'dataTable-input',
  'dataTable-pagination',
],
}
