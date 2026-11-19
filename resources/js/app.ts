import { createInertiaApp } from '@inertiajs/vue3';
import RootLayout from './layouts/RootLayout.vue';

createInertiaApp({
    pages: './pages',
    layout: () => RootLayout,
    title: (title) => (title ? `${title} · Sonar Desa` : 'Sonar Desa'),
    progress: { color: '#196bcd' },
});
