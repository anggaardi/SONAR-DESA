<script setup lang="ts">
// AppLayout: sidebar + content layout for desa and BPD authenticated screens.
// Sidebar 256px + content max-w-7xl px-6. Responsive: collapses to top nav on mobile.
// See docs/DESIGN.md §4.

import { ref } from 'vue';
import DemoBanner from '@/layouts/DemoBanner.vue';

interface Props {
    isDemo?: boolean;
}

withDefaults(defineProps<Props>(), {
    isDemo: false,
});

const sidebarOpen = ref(false);

function toggleSidebar(): void {
    sidebarOpen.value = !sidebarOpen.value;
}
</script>

<template>
    <div class="min-h-screen bg-surface-subtle text-content antialiased">
        <DemoBanner v-if="isDemo" />

        <!-- Mobile header -->
        <header class="flex items-center justify-between border-b border-stroke bg-surface px-4 py-3 md:hidden">
            <slot name="logo">
                <span class="text-base font-semibold text-content">Sonar Desa</span>
            </slot>
            <button
                type="button"
                class="inline-flex h-10 w-10 items-center justify-center rounded-md text-content-secondary transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                :aria-expanded="sidebarOpen"
                aria-controls="app-sidebar"
                aria-label="Buka menu navigasi"
                @click="toggleSidebar"
            >
                <svg
                    v-if="!sidebarOpen"
                    class="h-5 w-5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.5"
                        d="M4 6h16M4 12h16M4 18h16"
                    />
                </svg>
                <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </header>

        <div class="flex">
            <!-- Sidebar -->
            <nav
                id="app-sidebar"
                :class="[
                    'fixed inset-y-0 left-0 z-30 w-64 border-r border-stroke bg-surface transition-transform md:static md:flex md:flex-shrink-0 md:translate-x-0 md:flex-col',
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full',
                ]"
                aria-label="Navigasi aplikasi"
            >
                <!-- Sidebar header -->
                <div class="hidden items-center border-b border-stroke px-6 py-4 md:flex">
                    <slot name="logo">
                        <span class="text-base font-semibold text-content">Sonar Desa</span>
                    </slot>
                </div>

                <!-- Navigation links -->
                <div class="flex flex-1 flex-col gap-1 overflow-y-auto px-3 py-4">
                    <slot name="nav" />
                </div>

                <!-- Sidebar footer (user info, logout) -->
                <div v-if="$slots.sidebarFooter" class="border-t border-stroke px-3 py-3">
                    <slot name="sidebarFooter" />
                </div>
            </nav>

            <!-- Overlay for mobile sidebar -->
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-20 bg-content/50 md:hidden"
                aria-hidden="true"
                @click="sidebarOpen = false"
            />

            <!-- Main content -->
            <main class="min-w-0 flex-1 px-4 py-6 md:px-6">
                <div class="mx-auto max-w-7xl">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
