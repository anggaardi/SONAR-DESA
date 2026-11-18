import { createInertiaApp } from '@inertiajs/vue3';

// `pages` is expanded by @inertiajs/vite into a lazy import.meta.glob resolver,
// so every page is its own chunk (A17: public page budget 150 KB gzip).
createInertiaApp({
    pages: './pages',
    title: (title) => (title ? `${title} · Sonar Desa` : 'Sonar Desa'),
    progress: { color: '#4f46e5' },
});
