import { createInertiaApp } from '@inertiajs/vue3';

// `pages` is expanded by @inertiajs/vite into a lazy import.meta.glob resolver,
// so every page is its own chunk (A17: public page budget 150 KB gzip).
createInertiaApp({
    pages: './pages',
    title: (title) => (title ? `${title} · Sonar Desa` : 'Sonar Desa'),
    // Same value as --color-primary in resources/css/app.css (D-006).
    progress: { color: '#196bcd' },
});
