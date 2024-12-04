/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./resources/**/*.twig"],
  theme: {
    extend: {},
  },
  plugins: [require("daisyui")],

  daisyui: {
    themes: ["dim"],
  },
};
