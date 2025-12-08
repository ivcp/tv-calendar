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
    themes: [
      'dim',
      {
        fantasy: {
          ...require('daisyui/src/theming/themes')['fantasy'],
          'base-100': '#f7f7f7',
          'base-content': '#293649',
          warning: '#d68708',
        },
      },
    ],
  },
};
