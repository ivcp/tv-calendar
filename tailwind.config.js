/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./resources/**/*.ts', './resources/**/*.twig'],
  safelist: [
    'lg:col-start-1',
    'lg:col-start-2',
    'lg:col-start-3',
    'lg:col-start-4',
    'lg:col-start-5',
    'lg:col-start-6',
    'lg:col-start-7',
  ],
  theme: {
    extend: {
      gridColumnStart: {
        1: '1',
        2: '2',
        3: '3',
        4: '4',
        5: '5',
        6: '6',
        7: '7',
      },
    },
    fontFamily: {
      sans: ['Rubik', 'system-ui', 'sans-serif'],
    },
  },
  plugins: [require('daisyui')],

  daisyui: {
    // `dim` stays first so dark remains the default for visitors with no saved
    // preference. `tvlight` mirrors dim's hues, darkened for light surfaces.
    themes: [
      'dim',
      {
        tvlight: {
          'color-scheme': 'light',
          'base-100': '#f8f9fb',
          'base-200': '#e9edf3',
          'base-300': '#dbe1e9',
          'base-content': '#26303c',
          primary: '#2f7d4f',
          'primary-content': '#f2fbf4',
          secondary: '#b34a2c',
          'secondary-content': '#fff5f1',
          accent: '#7b4fae',
          'accent-content': '#f8f1ff',
          neutral: '#26303c',
          'neutral-content': '#e6edf2',
          info: '#1d4ed8',
          'info-content': '#eff6ff',
          success: '#15803d',
          'success-content': '#f0fdf4',
          warning: '#b45309',
          'warning-content': '#fff8e6',
          error: '#b3261e',
          'error-content': '#fff5f5',
        },
      },
    ],
  },
};
