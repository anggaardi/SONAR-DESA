<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { myReports as myReportsLabels } from '@/lib/labels';

interface Props {
    activeTab?: string;
    villageSlug?: string;
}

const props = withDefaults(defineProps<Props>(), {
    activeTab: 'cari-desa',
    villageSlug: '',
});

const emit = defineEmits<{
    tabClick: [tabId: string];
}>();

const page = usePage();
const activeRole = computed(() => page.props.auth?.user?.role ?? page.props.demo?.role ?? null);
const showMyReports = computed(() => activeRole.value === null || activeRole.value === 'warga');

const dashboardLink = computed<{ href: string; label: string } | null>(() => {
    if (activeRole.value === 'bpd') {
        return { href: '/bpd', label: 'Dashboard' };
    }
    if (activeRole.value === 'desa') {
        return { href: '/desa', label: 'Dashboard' };
    }
    return null;
});

const exploreHref = computed(() => {
    if (props.villageSlug) {
        return `/d/${props.villageSlug}`;
    }
    if (page.props.demo?.village_slug) {
        return `/d/${page.props.demo.village_slug}`;
    }
    return '/';
});

const exploreLabel = computed(() => (exploreHref.value === '/' ? 'Beranda' : 'Proyek desa'));
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-30 border-t border-stroke bg-surface md:hidden"
        aria-label="Navigasi aplikasi"
    >
        <div class="mx-auto flex h-16 max-w-md items-center justify-around px-1 sm:max-w-xl md:max-w-2xl">
            <Link
                :href="exploreHref"
                :class="[
                    'flex min-h-11 flex-col items-center justify-center gap-0.5 rounded-lg px-2 py-1 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary',
                    activeTab === 'cari-desa' ? 'text-primary' : 'text-content-secondary hover:text-content',
                ]"
            >
                <div
                    :class="[
                        'flex items-center justify-center rounded-full transition-colors',
                        activeTab === 'cari-desa' ? 'bg-primary-subtle px-3 py-0.5' : '',
                    ]"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M11 4a7 7 0 100 14 7 7 0 000-14zM20 20l-4-4"
                        />
                    </svg>
                </div>
                <span
                    :class="['text-xs whitespace-nowrap', activeTab === 'cari-desa' ? 'font-semibold' : 'font-medium']"
                >
                    {{ exploreLabel }}
                </span>
            </Link>

            <Link
                v-if="showMyReports"
                href="/laporan-saya"
                :class="[
                    'flex min-h-11 flex-col items-center justify-center gap-0.5 rounded-lg px-2 py-1 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary',
                    activeTab === 'laporan-saya' ? 'text-primary' : 'text-content-secondary hover:text-content',
                ]"
            >
                <div
                    :class="[
                        'flex items-center justify-center rounded-full transition-colors',
                        activeTab === 'laporan-saya' ? 'bg-primary-subtle px-3 py-0.5' : '',
                    ]"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
                        />
                    </svg>
                </div>
                <span
                    :class="[
                        'text-xs whitespace-nowrap',
                        activeTab === 'laporan-saya' ? 'font-semibold' : 'font-medium',
                    ]"
                >
                    {{ myReportsLabels.navLabel }}
                </span>
            </Link>

            <Link
                v-else-if="dashboardLink"
                :href="dashboardLink.href"
                class="flex min-h-11 flex-col items-center justify-center gap-0.5 rounded-lg px-2 py-1 text-content-secondary hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M3 10.5 12 3l9 7.5M5 9v11a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1V9"
                    />
                </svg>
                <span class="text-xs font-medium whitespace-nowrap">{{ dashboardLink.label }}</span>
            </Link>

            <button
                type="button"
                class="flex min-h-11 flex-col items-center justify-center gap-0.5 rounded-lg px-2 py-1 text-content-secondary hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                @click="emit('tabClick', 'pindai-qr')"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 3h5a1 1 0 011 1v5a1 1 0 01-1 1H4a1 1 0 01-1-1V4a1 1 0 011-1zM15 3h5a1 1 0 011 1v5a1 1 0 01-1 1h-5a1 1 0 01-1-1V4a1 1 0 011-1zM4 14h5a1 1 0 011 1v5a1 1 0 01-1 1H4a1 1 0 01-1-1v-5a1 1 0 011-1zM14 14h3v3M21 14v.01M14 21h.01M17 21h4v-4"
                    />
                </svg>
                <span class="text-xs font-medium whitespace-nowrap">Pindai QR</span>
            </button>

            <button
                type="button"
                class="flex min-h-11 flex-col items-center justify-center gap-0.5 rounded-lg px-2 py-1 text-content-secondary hover:text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                @click="emit('tabClick', 'bantuan')"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 3a9 9 0 100 18 9 9 0 000-18zM9.5 9.5a2.5 2.5 0 113.5 2.3c-.6.3-1 .9-1 1.6V14M12 17h.01"
                    />
                </svg>
                <span class="text-xs font-medium whitespace-nowrap">Bantuan</span>
            </button>
        </div>
    </nav>
</template>
