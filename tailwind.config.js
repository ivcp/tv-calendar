/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./resources/**/*.js", "./resources/**/*.twig"],
  theme: {
    extend: {},
  },
  plugins: [require("daisyui")],

  daisyui: {
    themes: ["dim"],
  },
};
