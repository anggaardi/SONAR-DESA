import { defineConfig } from 'vitest/config';

// Vitest covers resources/js/lib only (PRD Arsitektur 5, 9.6). Kept apart from
// vite.config.ts so the Laravel plugin does not run during tests.
export default defineConfig({
    resolve: {
        alias: { '@': new URL('./resources/js', import.meta.url).pathname },
    },
    test: {
        include: ['resources/js/**/*.test.ts'],
        environment: 'node',
    },
});
