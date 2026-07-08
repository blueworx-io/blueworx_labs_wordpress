import js from '@eslint/js';
import globals from 'globals';

export default [
  js.configs.recommended,
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
