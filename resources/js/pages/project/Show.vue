<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DesktopSidebar from '@/components/ui/DesktopSidebar.vue';
import Dialog from '@/components/ui/Dialog.vue';
import ProjectChanges from '@/features/project/ProjectChanges.vue';
import ProjectHeader from '@/features/project/ProjectHeader.vue';
import ProjectItems, { type BudgetFilter } from '@/features/project/ProjectItems.vue';
import ProjectBudget from '@/features/project/ProjectBudget.vue';
import ProjectCheckSpecInvite from '@/features/project/ProjectCheckSpecInvite.vue';
import ProjectHero from '@/features/project/ProjectHero.vue';
import ProjectJourney from '@/features/project/ProjectJourney.vue';
import ProjectMeasurements from '@/features/project/ProjectMeasurements.vue';
import ProjectProgress from '@/features/project/ProjectProgress.vue';
import ProjectReviewsFab from '@/features/project/ProjectReviewsFab.vue';
import ProjectStatusNotice from '@/features/project/ProjectStatusNotice.vue';
import type { ProjectDetail } from '@/types/project';

interface Props {
    project: ProjectDetail;
}

const props = defineProps<Props>();

const page = usePage();
const dashboardLink = computed<{ href: string; label: string } | null>(() => {
    const role = page.props.auth?.user?.role ?? page.props.demo?.role ?? null;
    if (role === 'bpd') {
        return { href: '/bpd', label: 'Kembali ke Dashboard BPD' };
    }
    if (role === 'desa') {
        return { href: '/desa', label: 'Kembali ke Dashboard Pemdes' };
    }
    return null;
});

const toastMessage = ref<string | null>(null);
const activeFilter = ref<BudgetFilter>('all');
const checkSpecHref = computed(() => `/p/${props.project.slug}/cek`);
const showPrivacyHint = computed(() => !page.props.auth?.user && !page.props.demo);

let toastTimer: ReturnType<typeof setTimeout> | null = null;

function showToast(message: string): void {
    toastMessage.value = message;
    if (toastTimer) clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
        toastMessage.value = null;
    }, 2500);
}

async function handleShare(): Promise<void> {
    const url = window.location.href;
    const shareData = {
        title: props.project.name,
        text: `Transparansi ${props.project.name} - Desa ${props.project.village.name}`,
        url,
    };
    if (navigator.share) {
        try {
            await navigator.share(shareData);
            return;
        } catch {
            // User cancelled or browser fell back to clipboard
        }
    }
    if (navigator.clipboard) {
        try {
            await navigator.clipboard.writeText(url);
            showToast('Tautan proyek berhasil disalin!');
        } catch {
            showToast('Gagal menyalin tautan');
        }
    }
}

/** Lompatan cepat ke tiap bagian halaman, pengganti 4 ikon pintasan lama. */
const sectionLinks = [
    { id: 'anggaran', label: 'Anggaran' },
    { id: 'rincian', label: 'Harga' },
    { id: 'ukuran', label: 'Ukuran' },
    { id: 'perjalanan', label: 'Perjalanan' },
];

const showHelpDialog = ref(false);
const showQrDialog = ref(false);

function handleCheckSpecClick(): void {
    window.location.assign(checkSpecHref.value);
}

function handleTabClick(tabId: string): void {
    if (tabId === 'bantuan') {
        showHelpDialog.value = true;
    } else if (tabId === 'pindai-qr') {
        showQrDialog.value = true;
    }
}
</script>

<template>
    <div class="min-h-screen bg-surface-subtle text-content antialiased">
        <Head :title="project.name" />

        <div class="flex min-h-screen">
            <!-- Desktop Sidebar -->
            <DesktopSidebar
                active-tab="cari-desa"
                :village-name="project.village.name"
                :village-slug="project.village.slug"
                :regency="project.village.regency"
                @tab-click="handleTabClick"
                @share="handleShare"
            />

            <!-- Main Content Area -->
            <div class="min-w-0 flex-1 pb-32 lg:pb-16">
                <!-- Mobile Header (Sticky agar warga mudah keluar / kembali ke desa) -->
                <div class="sticky top-0 z-20 md:hidden">
                    <ProjectHeader
                        :village-name="project.village.name"
                        :village-slug="project.village.slug"
                        :project-name="project.name"
                        @share="handleShare"
                    />
                    <div
                        v-if="dashboardLink"
                        class="flex items-center justify-between border-b border-primary/20 bg-primary-subtle px-4 py-2"
                    >
                        <Link
                            :href="dashboardLink.href"
                            class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline"
                        >
                            <svg
                                class="h-3.5 w-3.5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M15 19l-7-7 7-7"
                                />
                            </svg>
                            <span>{{ dashboardLink.label }}</span>
                        </Link>
                    </div>
                </div>

                <!-- Desktop Header -->
                <header
                    class="sticky top-0 z-20 hidden items-center justify-between border-b border-stroke bg-surface/95 px-6 py-3.5 backdrop-blur-sm md:flex lg:px-8"
                >
                    <div class="flex items-center gap-2 text-sm text-content-secondary">
                        <Link href="/" class="transition-colors hover:text-primary">Beranda</Link>
                        <span>/</span>
                        <Link :href="`/d/${project.village.slug}`" class="transition-colors hover:text-primary">
                            {{
                                project.village.name.startsWith('Desa ')
                                    ? project.village.name
                                    : `Desa ${project.village.name}`
                            }}
                        </Link>
                        <span>/</span>
                        <span class="max-w-md truncate font-medium text-content">{{ project.name }}</span>
                    </div>

                    <div class="flex items-center gap-2.5">
                        <Link
                            v-if="dashboardLink"
                            :href="dashboardLink.href"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-primary/30 bg-primary-subtle px-3 py-1.5 text-sm font-semibold text-primary transition-colors hover:bg-primary hover:text-content-inverse focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                        >
                            <svg
                                class="h-4 w-4"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M15 19l-7-7 7-7"
                                />
                            </svg>
                            <span>{{ dashboardLink.label }}</span>
                        </Link>
                        <button
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-stroke bg-surface px-3 py-1.5 text-sm font-medium text-content transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                            aria-label="Bagikan rincian proyek"
                            @click="handleShare"
                        >
                            <svg
                                class="h-4 w-4 text-content-secondary"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"
                                />
                            </svg>
                            <span>Bagikan</span>
                        </button>
                    </div>
                </header>

                <ProjectHero :project="project" />

                <main class="mx-auto -mt-8 max-w-md px-4 sm:max-w-xl sm:px-6 md:max-w-3xl lg:max-w-7xl lg:px-8">
                    <!-- Two-column desktop grid: Left narrative & data (8 col), Right sticky action rail (4 col) -->
                    <div class="lg:grid lg:grid-cols-12 lg:items-start lg:gap-8">
                        <!-- Left Column (8 cols on desktop) -->
                        <div class="flex flex-col gap-6 lg:col-span-8">
                            <ProjectProgress :project="project" />

                            <nav
                                aria-label="Bagian halaman"
                                class="-mx-4 -mt-2 no-scrollbar flex gap-2 overflow-x-auto px-4 lg:mx-0 lg:px-0"
                            >
                                <a
                                    v-for="link in sectionLinks"
                                    :key="link.id"
                                    :href="`#${link.id}`"
                                    class="inline-flex h-10 shrink-0 items-center rounded-full border border-stroke bg-surface px-4 text-sm font-medium text-content transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                >
                                    {{ link.label }}
                                </a>
                            </nav>

                            <ProjectStatusNotice :project="project" />
                            <ProjectBudget :project="project" />
                            <ProjectItems v-model:active-filter="activeFilter" :project="project" />
                            <ProjectMeasurements :project="project" />

                            <!-- Mobile-only Cek Spek invite (on desktop, lives in sticky right rail) -->
                            <div class="lg:hidden">
                                <ProjectCheckSpecInvite
                                    :citizen-count="project.citizen_reports_count"
                                    :check-spec-href="checkSpecHref"
                                    :show-privacy-hint="showPrivacyHint"
                                />
                            </div>

                            <ProjectJourney :project="project" />
                            <ProjectChanges :changes="project.changes" />
                        </div>

                        <!-- Right Column: Sticky Action Rail (4 cols on desktop) -->
                        <aside
                            aria-label="Aksi dan informasi pendukung"
                            class="hidden lg:sticky lg:top-20 lg:col-span-4 lg:flex lg:flex-col lg:gap-6"
                        >
                            <!-- 1. Primary Action Hub Card: Cek Spek di Lokasi -->
                            <ProjectCheckSpecInvite
                                :citizen-count="project.citizen_reports_count"
                                :check-spec-href="checkSpecHref"
                                :show-privacy-hint="showPrivacyHint"
                            />

                            <!-- 2. Dokumen Publik RAB Asli Card -->
                            <div class="rounded-2xl border border-stroke bg-surface p-5 shadow-xs">
                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-subtle text-primary"
                                    >
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                            />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold text-content">Dokumen RAB Asli Desa</h3>
                                        <p class="text-xs text-content-tertiary">
                                            Disahkan &amp; diverifikasi pemdes · PDF resmi
                                        </p>
                                    </div>
                                </div>
                                <a
                                    :href="`/p/${project.slug}/dokumen`"
                                    class="mt-4 inline-flex h-10 w-full items-center justify-center gap-2 rounded-xl border border-stroke bg-surface px-4 text-sm font-medium text-content transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <svg
                                        class="h-4 w-4 text-content-secondary"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                        />
                                    </svg>
                                    Lihat Dokumen Asli
                                </a>
                            </div>
                        </aside>
                    </div>

                    <footer class="mt-8 border-t border-stroke py-8 text-center text-sm text-content-secondary">
                        <p class="font-medium text-content">Sonar Desa &middot; Keterbukaan Informasi Publik Desa</p>
                        <p class="mt-1 text-xs text-content-tertiary">
                            Berdasarkan UU No. 6/2014 tentang Desa &amp; UU No. 14/2008 tentang KIP
                        </p>
                    </footer>
                </main>
            </div>
        </div>

        <!-- Satu tombol utama per layar (PRD 6.1), menempel di bawah (hanya di mobile/tablet; di desktop sudah ada di aside) -->
        <div class="fixed inset-x-0 bottom-0 z-30 border-t border-stroke bg-surface/95 backdrop-blur-sm lg:hidden">
            <div class="mx-auto max-w-md px-4 py-3 sm:max-w-xl sm:px-6 md:max-w-3xl">
                <a
                    :href="checkSpecHref"
                    class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 text-base font-semibold text-content-inverse transition-colors hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary active:bg-primary-hover"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M3.5 15.5 15.5 3.5l5 5-12 12zM7.5 11.5l2 2M10.5 8.5l2 2M13.5 5.5l2 2"
                        />
                    </svg>
                    Cek spek di lokasi
                </a>
            </div>
        </div>

        <!-- Tombol sticky ulasan warga & catatan BPD (IDDS) -->
        <ProjectReviewsFab :project="project" @check-spec="handleCheckSpecClick" />

        <!-- Dialog: Bantuan -->
        <Dialog :open="showHelpDialog" title="Bantuan & Keterbukaan Informasi" @close="showHelpDialog = false">
            <div class="space-y-3 text-sm text-content-secondary">
                <p>
                    <strong class="font-semibold text-content">Sonar Desa</strong> adalah portal transparansi
                    pembangunan fisik desa yang dibiayai dana publik (Dana Desa, ADD, dll).
                </p>
                <p>
                    Anda dapat memantau rincian anggaran, ukuran fisik hasil pekerjaan di lapangan (Cek Spek), foto
                    progres, dan tindak lanjut BPD.
                </p>
                <p class="text-xs text-content-tertiary">
                    Sesuai amanat UU No. 6/2014 tentang Desa &amp; UU No. 14/2008 tentang Keterbukaan Informasi Publik.
                </p>
            </div>
            <template #actions>
                <button
                    type="button"
                    class="h-10 w-full rounded-md bg-primary px-4 text-sm font-medium text-content-inverse hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                    @click="showHelpDialog = false"
                >
                    Tutup
                </button>
            </template>
        </Dialog>

        <!-- Dialog: Pindai QR -->
        <Dialog :open="showQrDialog" title="Pindai QR Plang Proyek" @close="showQrDialog = false">
            <div class="space-y-3 text-sm text-content-secondary">
                <p>Setiap plang proyek fisik di desa memiliki kode QR unik Sonar Desa.</p>
                <p>
                    Gunakan kamera ponsel Anda untuk memindai kode QR pada plang di lokasi pembangunan untuk langsung
                    membuka rincian anggaran dan spesifikasi proyek.
                </p>
            </div>
            <template #actions>
                <button
                    type="button"
                    class="h-10 w-full rounded-md bg-primary px-4 text-sm font-medium text-content-inverse hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                    @click="showQrDialog = false"
                >
                    Mengerti
                </button>
            </template>
        </Dialog>

        <!-- Toast feedback -->
        <div
            v-if="toastMessage"
            class="fixed bottom-24 left-1/2 z-50 -translate-x-1/2 rounded-full bg-content px-4 py-2 text-sm font-medium text-content-inverse shadow-sm"
            role="status"
        >
            {{ toastMessage }}
        </div>
    </div>
</template>
