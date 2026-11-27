<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { demo as demoLabels, myReports as myReportsLabels } from '@/lib/labels';
import type { Role } from '@/types/inertia';

interface Props {
    activeTab?: string;
    villageName?: string;
    villageSlug?: string;
    regency?: string;
    isVillagePage?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    activeTab: 'cari-proyek',
    villageName: '',
    villageSlug: '',
    regency: '',
    isVillagePage: false,
});

const emit = defineEmits<{
    tabClick: [tabId: string];
    share: [];
}>();

const page = usePage();
const demo = computed(() => page.props.demo);
const activeRole = computed(() => page.props.auth?.user?.role ?? page.props.demo?.role ?? null);
const showMyReports = computed(() => activeRole.value === null || activeRole.value === 'warga');
const villageExploreHref = computed(() => {
    const slug = props.villageSlug || demo.value?.village_slug;
    return slug ? `/d/${slug}` : null;
});
const dashboardLink = computed<{ href: string; label: string } | null>(() => {
    if (activeRole.value === 'bpd') {
        return { href: '/bpd', label: 'Kembali ke Dashboard BPD' };
    }
    if (activeRole.value === 'desa') {
        return { href: '/desa', label: 'Kembali ke Dashboard Pemdes' };
    }
    return null;
});
const showRolePicker = ref(false);

const formattedVillageName = computed(() => {
    if (!props.villageName) return 'Desa Contoh';
    return props.villageName.startsWith('Desa ') ? props.villageName : `Desa ${props.villageName}`;
});

const demoRoleText = computed(() => {
    if (!demo.value) return '';
    const roleMap: Record<string, string> = {
        warga: 'Warga',
        desa: 'Pemerintah Desa',
        bpd: 'BPD',
    };
    const roleName = roleMap[demo.value.role] ?? 'Warga';
    return `${roleName} ${formattedVillageName.value}`;
});

function switchRole(role: Role): void {
    showRolePicker.value = false;
    router.post(`/demo/switch/${role}`);
}

function restartDemo(): void {
    router.post('/demo/reset');
}
</script>

<template>
    <aside
        class="sticky top-0 hidden h-screen overflow-y-auto border-r border-stroke bg-surface md:flex md:w-[260px] md:shrink-0 md:flex-col"
        aria-label="Navigasi desktop"
    >
        <!-- Logo & Branding -->
        <div class="flex items-center gap-3 border-b border-stroke px-5 py-4">
            <Link href="/" class="flex items-center gap-3 text-content transition-colors hover:text-primary">
                <div
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary text-content-inverse shadow-xs"
                    aria-hidden="true"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 100-18 9 9 0 000 18z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15a3 3 0 100-6 3 3 0 000 6z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v3m0 12v3m9-9h-3M6 12H3" />
                    </svg>
                </div>
                <div>
                    <span class="block text-[17px] leading-tight font-bold tracking-tight text-content"
                        >Sonar Desa</span
                    >
                    <span class="block text-[11px] text-content-tertiary">Papan Informasi Publik</span>
                </div>
            </Link>
        </div>

        <!-- Navigation Links -->
        <nav class="flex flex-1 flex-col gap-1 overflow-y-auto px-3 py-3" aria-label="Menu navigasi">
            <Link
                v-if="dashboardLink"
                :href="dashboardLink.href"
                class="mb-2 flex items-center gap-2.5 rounded-lg border border-primary/30 bg-primary-subtle px-3 py-2.5 text-sm font-semibold text-primary transition-colors hover:bg-primary hover:text-content-inverse focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
            >
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                <span>{{ dashboardLink.label }}</span>
            </Link>

            <p class="px-3 pt-2 pb-1 text-[10px] font-bold tracking-wider text-content-tertiary uppercase">
                MENU UTAMA
            </p>

            <!-- Beranda -->
            <Link
                href="/"
                :class="[
                    'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary',
                    activeTab === 'beranda'
                        ? 'bg-primary-subtle font-semibold text-primary'
                        : 'text-content-secondary hover:bg-surface-muted hover:text-content',
                ]"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M3 10.5 12 3l9 7.5M5 9v11a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1V9"
                    />
                </svg>
                <span>Beranda</span>
            </Link>

            <!-- Cari Proyek / Daftar Proyek Desa -->
            <button
                v-if="isVillagePage"
                type="button"
                :class="[
                    'flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary',
                    activeTab === 'cari-proyek' || activeTab === 'cari-desa'
                        ? 'bg-primary-subtle font-semibold text-primary'
                        : 'text-content-secondary hover:bg-surface-muted hover:text-content',
                ]"
                @click="emit('tabClick', 'cari-proyek')"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M11 4a7 7 0 100 14 7 7 0 000-14zM20 20l-4-4"
                    />
                </svg>
                <span>Cari Proyek</span>
            </button>
            <Link
                v-else-if="villageExploreHref"
                :href="villageExploreHref"
                :class="[
                    'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary',
                    activeTab === 'cari-proyek' || activeTab === 'cari-desa'
                        ? 'bg-primary-subtle font-semibold text-primary'
                        : 'text-content-secondary hover:bg-surface-muted hover:text-content',
                ]"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M11 4a7 7 0 100 14 7 7 0 000-14zM20 20l-4-4"
                    />
                </svg>
                <span>Daftar Proyek Desa</span>
            </Link>

            <!-- Laporan Saya (khusus warga & tamu) -->
            <Link
                v-if="showMyReports"
                href="/laporan-saya"
                :class="[
                    'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary',
                    activeTab === 'laporan-saya'
                        ? 'bg-primary-subtle font-semibold text-primary'
                        : 'text-content-secondary hover:bg-surface-muted hover:text-content',
                ]"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
                    />
                </svg>
                <span>{{ myReportsLabels.navLabel }}</span>
            </Link>

            <!-- Pindai QR -->
            <button
                type="button"
                :class="[
                    'flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary',
                    activeTab === 'pindai-qr'
                        ? 'bg-primary-subtle font-semibold text-primary'
                        : 'text-content-secondary hover:bg-surface-muted hover:text-content',
                ]"
                @click="emit('tabClick', 'pindai-qr')"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 3h5a1 1 0 011 1v5a1 1 0 01-1 1H4a1 1 0 01-1-1V4a1 1 0 011-1zM15 3h5a1 1 0 011 1v5a1 1 0 01-1 1h-5a1 1 0 01-1-1V4a1 1 0 011-1zM4 14h5a1 1 0 011 1v5a1 1 0 01-1 1H4a1 1 0 01-1-1v-5a1 1 0 011-1zM14 14h3v3M21 14v.01M14 21h.01M17 21h4v-4"
                    />
                </svg>
                <span>Pindai QR</span>
            </button>

            <!-- Panduan & Bantuan -->
            <button
                type="button"
                :class="[
                    'flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary',
                    activeTab === 'bantuan'
                        ? 'bg-primary-subtle font-semibold text-primary'
                        : 'text-content-secondary hover:bg-surface-muted hover:text-content',
                ]"
                @click="emit('tabClick', 'bantuan')"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 3a9 9 0 100 18 9 9 0 000-18zM9.5 9.5a2.5 2.5 0 113.5 2.3c-.6.3-1 .9-1 1.6V14M12 17h.01"
                    />
                </svg>
                <span>Panduan &amp; Bantuan</span>
            </button>

            <!-- Buka Plang Proyek -->
            <button
                type="button"
                :class="[
                    'flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary',
                    activeTab === 'plang'
                        ? 'bg-primary-subtle font-semibold text-primary'
                        : 'text-content-secondary hover:bg-surface-muted hover:text-content',
                ]"
                @click="emit('tabClick', 'plang')"
            >
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                    />
                </svg>
                <span>Buka Plang Proyek</span>
            </button>

            <!-- Demo Card di Sidebar (ala FigCraft deskSidebar) -->
            <div v-if="demo" class="mt-4 px-1">
                <div class="rounded-xl bg-demo-subtle p-3.5 text-demo">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 shrink-0 text-demo" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                        <span class="text-xs font-bold text-demo">Mode Demo Aktif</span>
                    </div>
                    <p class="mt-1.5 text-xs leading-tight text-demo">Peran: {{ demoRoleText }}</p>
                    <button
                        type="button"
                        class="mt-2.5 inline-flex h-6 w-full items-center justify-center rounded-md bg-white text-[11px] font-semibold text-demo shadow-2xs transition-colors hover:bg-surface-muted"
                        @click="showRolePicker = !showRolePicker"
                    >
                        Ganti Peran Demo
                    </button>
                    <div v-if="showRolePicker" class="mt-2 space-y-1 border-t border-demo/15 pt-1.5">
                        <button
                            v-for="r in ['warga', 'desa', 'bpd'] as const"
                            :key="r"
                            type="button"
                            class="w-full rounded px-2 py-1 text-left text-[11px] font-medium text-demo transition-colors hover:bg-white/60"
                            @click="switchRole(r)"
                        >
                            {{ demoLabels.roles[r] }}
                        </button>
                    </div>
                    <button
                        type="button"
                        class="mt-2 w-full text-center text-[11px] font-medium text-demo underline underline-offset-2 hover:text-demo/80"
                        @click="restartDemo"
                    >
                        {{ demoLabels.restart }}
                    </button>
                </div>
            </div>

            <!-- Jika tidak dalam mode demo, tombol pintasan Mode Demo -->
            <div v-else class="mt-4 px-1">
                <Link
                    href="/demo"
                    class="flex items-center justify-between rounded-xl bg-demo-subtle px-3 py-2.5 text-xs font-semibold text-demo transition-colors hover:bg-demo-subtle/80"
                >
                    <span class="flex items-center gap-2">
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                        Mode Demo
                    </span>
                    <span class="text-[10px] text-demo/80">Coba &rarr;</span>
                </Link>
            </div>
        </nav>

        <!-- Sidebar Footer -->
        <div class="border-t border-stroke p-4 text-[11px] text-content-tertiary">
            <p class="leading-relaxed">
                Sonar Desa &middot; Keterbukaan Informasi Publik Desa &middot; UU No. 6/2014 &amp; UU No. 14/2008
            </p>
            <Link
                v-if="dashboardLink"
                :href="dashboardLink.href"
                class="mt-2.5 inline-block font-medium text-primary hover:underline"
            >
                &larr; {{ dashboardLink.label }}
            </Link>
            <Link v-else href="/login" class="mt-2.5 inline-block font-medium text-primary hover:underline">
                Pamong / BPD? Masuk &rarr;
            </Link>
        </div>
    </aside>
</template>
