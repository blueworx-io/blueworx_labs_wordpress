import js from '@eslint/js';
import globals from 'globals';

export default [
  js.configs.recommended,
  {
    // Shipped verbatim from the shared design system, and held there by the CI
    // sync check — we cannot act on a lint finding in it without breaking that
    // check, so linting it only produces noise. It is an ES module; everything
    // else in assets/js is a plain script.
    ignores: ['assets/js/blueworx-admin-design-icons.js'],
  },
  {
    files: ['assets/js/**/*.js'],
    languageOptions: {
      ecmaVersion: 2021,
      sourceType: 'script',
      globals: {
        ...globals.browser,
        ...globals.jquery,
        jQuery: 'readonly',
        wp: 'readonly',
        ajaxurl: 'readonly',
      },
    },
    rules: {
      'no-unused-vars': 'warn',
    },
  },
];
