/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  theme: {
    extend: {
      colors: {
        midnight: '#0b1220',
        ocean: '#0f1b33'
      }
    }
  },
  plugins: []
};
