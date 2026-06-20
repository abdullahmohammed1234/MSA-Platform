import type { Config } from 'tailwindcss'

export default {
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          DEFAULT: '#640c0e',
          dark: '#4d090b',
          light: '#8a1a1d',
        },
        secondary: {
          DEFAULT: '#b02e32',
          light: '#c94a4e',
          dark: '#8c1e21',
        },
        accent: {
          gold: '#ffdc83',
          red: '#ea2128',
        },
        neutral: {
          background: '#fffbf4',
          ivory: '#ebe8de',
          gray: '#c2c4c7',
          black: '#000000',
          white: '#ffffff',
          muted: '#6b6e73',
        },
        maroon: {
          50: '#fdf2f2',
          100: '#fbe4e5',
          200: '#f7ced1',
          300: '#f1abb0',
          400: '#e57a82',
          500: '#d54d58',
          600: '#c0333e',
          700: '#a1252f',
          800: '#86222b',
          900: '#712128',
          950: '#3f0d11',
        },
        gold: {
          50: '#fffbeb',
          100: '#fef3c7',
          200: '#fde68a',
          300: '#fcd34d',
          400: '#fbbf24',
          500: '#f59e0b',
          600: '#d97706',
          700: '#b45309',
          800: '#92400e',
          900: '#78350f',
          950: '#451a03',
        },
        success: '#065f46',
        warning: '#d97706',
        error: '#ea2128',
        info: '#640c0e',
      },
      fontFamily: {
        sans: ['Plus Jakarta Sans', 'Inter', 'sans-serif'],
        display: ['Outfit', 'Playfair Display', 'serif'],
        serif: ['Cormorant Garamond', 'Georgia', 'serif'],
        mono: ['Space Grotesk', 'monospace'],
      },
      borderRadius: {
        'xs': '0.25rem',
        'sm': '0.5rem',
        'md': '0.75rem',
        'lg': '1.0rem',
        'xl': '1.5rem',
        '2xl': '2.0rem',
        '3xl': '3.0rem',
      },
      boxShadow: {
        'premium': '0 10px 40px -10px rgba(100, 12, 14, 0.3)',
        'premium-md': '0 20px 50px -12px rgba(0, 0, 0, 0.08)',
        'brand': '0 10px 30px -10px rgba(100, 12, 14, 0.15)',
        'glow': '0 0 25px rgba(176, 46, 50, 0.25)',
        'soft': '0 4px 20px rgba(0, 0, 0, 0.08)',
      },
      screens: {
        'xs': '480px',
      }
    },
  },
  plugins: [],
} satisfies Config
