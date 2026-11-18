import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript';
import prettier from 'eslint-config-prettier';
import vue from 'eslint-plugin-vue';

export default defineConfigWithVueTs(
    {
        ignores: ['public/**', 'vendor/**', 'node_modules/**', 'storage/**', 'bootstrap/ssr/**', 'docs/**'],
    },
    vue.configs['flat/recommended'],
    vueTsConfigs.recommended,
    {
        rules: {
            // PRD Arsitektur 9.4: no console.log in commits, no `any`.
            'no-console': 'error',
            '@typescript-eslint/no-explicit-any': 'error',
            'vue/multi-word-component-names': 'off',
            'vue/block-lang': ['error', { script: { lang: 'ts' } }],
            'vue/component-api-style': ['error', ['script-setup']],
        },
    },
    prettier,
);
