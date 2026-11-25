<script setup lang="ts">
// AppLayout: sidebar + content layout for desa and BPD authenticated screens.
// Aligned with DesktopSidebar (w-[260px], sticky top-0 h-screen) and Village desktop header.
// See docs/DESIGN.md §4.

import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const sidebarOpen = ref(false);
const isBpd = computed(() => (page.props.auth?.user?.role ?? page.props.demo?.role) === 'bpd');
const homeHref = computed(() => (isBpd.value ? '/bpd' : '/desa'));
const roleSubtitle = computed(() => (isBpd.value ? 'Badan Permusyawaratan Desa' : 'Pemerintah Desa'));

function toggleSidebar(): void {
    sidebarOpen.value = !sidebarOpen.value;
}
</script>

<template>
    <div class="min-h-screen bg-surface-subtle text-content antialiased">
        <div class="flex min-h-screen">
            <!-- Sidebar (Drawer on mobile, Sticky on desktop) -->
            <nav
                id="app-sidebar"
                :class="[
                    'fixed inset-y-0 left-0 z-30 flex w-[260px] flex-col overflow-y-auto border-r border-stroke bg-surface transition-transform md:sticky md:top-0 md:h-screen md:shrink-0 md:translate-x-0',
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full',
                ]"
                aria-label="Navigasi aplikasi"
            >
                <!-- Sidebar Header / Logo -->
                <div class="flex items-center gap-3 border-b border-stroke px-5 py-4">
                    <slot name="logo">
                        <Link
                            :href="homeHref"
                            class="flex items-center gap-3 text-content transition-colors hover:text-primary"
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary text-content-inverse shadow-xs"
                                aria-hidden="true"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 21a9 9 0 100-18 9 9 0 000 18z"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 15a3 3 0 100-6 3 3 0 000 6z"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 3v3m0 12v3m9-9h-3M6 12H3"
                                    />
                                </svg>
                            </div>
                            <div>
                                <span class="block text-[17px] leading-tight font-bold tracking-tight text-content"
                                    >Sonar Desa</span
                                >
                                <span class="block text-[11px] text-content-tertiary">{{ roleSubtitle }}</span>
                            </div>
                        </Link>
                    </slot>
                </div>

                <!-- Navigation Links -->
                <div class="flex flex-1 flex-col gap-1 overflow-y-auto px-3 py-3" aria-label="Menu navigasi">
                    <p class="px-3 pt-2 pb-1 text-[10px] font-bold tracking-wider text-content-tertiary uppercase">
                        MENU UTAMA
                    </p>
                    <slot name="nav" />
                </div>

                <!-- Sidebar Footer -->
                <div v-if="$slots.sidebarFooter" class="border-t border-stroke p-4 text-[11px] text-content-tertiary">
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

            <!-- Main Content Area -->
            <div class="flex min-w-0 flex-1 flex-col pb-20 md:pb-12">
                <!-- Mobile header (Sticky) -->
                <header
                    class="sticky top-0 z-20 flex items-center justify-between border-b border-stroke bg-surface px-4 py-3 md:hidden"
                >
                    <div class="flex items-center gap-2.5">
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary text-content-inverse shadow-xs"
                            aria-hidden="true"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 21a9 9 0 100-18 9 9 0 000 18z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 15a3 3 0 100-6 3 3 0 000 6z"
                                />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v3m0 12v3m9-9h-3M6 12H3" />
                            </svg>
                        </div>
                        <div>
                            <span class="block text-sm leading-tight font-bold text-content">Sonar Desa</span>
                            <span class="block text-[10px] text-content-tertiary">{{ roleSubtitle }}</span>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-stroke text-content-secondary transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
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
                        <svg
                            v-else
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
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </header>

                <!-- Desktop Header Breadcrumb (Sticky) -->
                <header
                    v-if="$slots.breadcrumb || $slots.headerActions"
                    class="sticky top-0 z-20 hidden items-center justify-between border-b border-stroke bg-surface/95 px-6 py-3.5 backdrop-blur-sm md:flex lg:px-8"
                >
                    <div class="flex items-center gap-2 text-sm text-content-secondary">
                        <slot name="breadcrumb" />
                    </div>

                    <div v-if="$slots.headerActions" class="flex items-center gap-3">
                        <slot name="headerActions" />
                    </div>
                </header>

                <!-- Main Content -->
                <main class="flex-1 space-y-6 px-4 pt-6 pb-8 sm:px-6 md:px-8 lg:px-10">
                    <div class="mx-auto max-w-7xl">
                        <slot />
                    </div>
                </main>
            </div>
        </div>
    </div>
</template>
