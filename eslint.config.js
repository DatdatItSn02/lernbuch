import js from '@eslint/js';
import globals from 'globals';
import reactHooks from 'eslint-plugin-react-hooks';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    { ignores: ['public/**', 'vendor/**', 'node_modules/**', 'storage/**', 'bootstrap/**'] },
    {
        files: ['resources/js/**/*.{ts,tsx}'],
        extends: [js.configs.recommended, ...tseslint.configs.recommended],
        languageOptions: { globals: globals.browser },
        plugins: { 'react-hooks': reactHooks },
        rules: { ...reactHooks.configs.recommended.rules },
    },
    {
        files: ['tests/e2e/**/*.ts', 'playwright.config.ts'],
        extends: [js.configs.recommended, ...tseslint.configs.recommended],
        languageOptions: { globals: globals.node },
    },
);
