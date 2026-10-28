import js from '@eslint/js';
import jsxA11y from 'eslint-plugin-jsx-a11y';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    { ignores: ['public/build', 'vendor', 'node_modules', 'storage', 'bootstrap/cache'] },
    {
        files: ['resources/js/**/*.{ts,tsx}'],
        extends: [
            js.configs.recommended,
            ...tseslint.configs.recommended,
            jsxA11y.flatConfigs.recommended,
            reactHooks.configs.flat.recommended,
        ],
        languageOptions: { globals: globals.browser },
        rules: {
            // The UI primitives are shadcn/ui output; they export variants next to components by design.
            '@typescript-eslint/no-explicit-any': 'error',
        },
    },
);
