<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import BottomNav from '@/components/ui/BottomNav.vue';
import DesktopSidebar from '@/components/ui/DesktopSidebar.vue';
import Dialog from '@/components/ui/Dialog.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import plangEmpty from '@/components/ui/illustrations/plang-empty.svg';
import ProjectPlangDialog from '@/features/project/ProjectPlangDialog.vue';
import QrScannerDialog from '@/features/project/QrScannerDialog.vue';
import VillageFollowDialog from '@/features/project/VillageFollowDialog.vue';
import VillageHeader from '@/features/project/VillageHeader.vue';
import VillageHero from '@/features/project/VillageHero.vue';
import VillageProjectCard from '@/features/project/VillageProjectCard.vue';
import VillageProjectFilter, { type FilterCategory } from '@/features/project/VillageProjectFilter.vue';
import VillageStatSummary from '@/features/project/VillageStatSummary.vue';
import { projectType } from '@/lib/labels';
import type { ProjectPlangInfo, ProjectVillage, VillageProjectSummary } from '@/types/project';

interface Props {
    village: ProjectVillage;
    projects: VillageProjectSummary[];
    citizenReportsCount?: number;
    plangs?: Record<number, ProjectPlangInfo>;
}

const props = withDefaults(defineProps<Props>(), {
    citizenReportsCount: 0,
    plangs: () => ({}),
});

const page = usePage();
const activeRole = computed(() => page.props.auth?.user?.role ?? page.props.demo?.role ?? null);
const dashboardLink = computed<{ href: string; label: string } | null>(() => {
    if (activeRole.value === 'bpd') {
        return { href: '/bpd', label: 'Kembali ke Dashboard BPD' };
    }
    if (activeRole.value === 'desa') {
        return { href: '/desa', label: 'Kembali ke Dashboard Pemdes' };
    }
    return null;
});

const searchQuery = ref('');
const activeCategory = ref('all');
const toastMessage = ref<string | null>(null);
const showHelpDialog = ref(false);
const showQrDialog = ref(false);
const showPlangDialog = ref(false);
const showFollowDialog = ref(false);

let toastTimer: ReturnType<typeof setTimeout> | null = null;

const formattedVillageName = computed(() => {
    return props.village.name.startsWith('Desa ') ? props.village.name : `Desa ${props.village.name}`;
});

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
        title: `Proyek Pembangunan ${formattedVillageName.value}`,
        text: `Daftar pembangunan fisik yang dibiayai anggaran publik di ${formattedVillageName.value}, Kab. ${props.village.regency}.`,
        url,
    };
    if (navigator.share) {
        try {
            await navigator.share(shareData);
            return;
        } catch {
            // User cancelled or fell back to clipboard
        }
    }
    if (navigator.clipboard) {
        try {
            await navigator.clipboard.writeText(url);
            showToast('Tautan desa berhasil disalin!');
        } catch {
            showToast('Gagal menyalin tautan');
        }
    }
}

function focusProjectSearch(): void {
    const input = document.getElementById('project-search-input');
    if (input instanceof HTMLInputElement) {
        input.scrollIntoView({ behavior: 'smooth', block: 'center' });
        input.focus();
    }
}

function handleTabClick(tabId: string): void {
    if (tabId === 'bantuan') {
        showHelpDialog.value = true;
    } else if (tabId === 'pindai-qr') {
        showQrDialog.value = true;
    } else if (tabId === 'plang') {
        showPlangDialog.value = true;
    } else if (tabId === 'cari-proyek') {
        focusProjectSearch();
    }
}

const publishedYears = computed(() => {
    if (props.projects.length === 0) return '2026';
    const years = [...new Set(props.projects.map((p) => p.year))].sort();
    if (years.length === 1) return String(years[0]);
    return `${years[0]}–${years[years.length - 1]}`;
});

const totalVillageBudget = computed(() => {
    return props.projects.reduce((acc, p) => acc + p.total_amount_rp, 0);
});

const categories = computed<FilterCategory[]>(() => {
    const list: FilterCategory[] = [{ id: 'all', label: `Semua (${props.projects.length})` }];
    const counts: Record<string, number> = {};
    for (const p of props.projects) {
        counts[p.type] = (counts[p.type] ?? 0) + 1;
    }
    for (const [type, count] of Object.entries(counts)) {
        const label = projectType[type as keyof typeof projectType] ?? type;
        list.push({
            id: type,
            label: `${label} (${count})`,
        });
    }
    return list;
});

const filteredProjects = computed(() => {
    let result = props.projects;

    if (activeCategory.value !== 'all') {
        result = result.filter((p) => p.type === activeCategory.value);
    }

    const q = searchQuery.value.trim().toLowerCase();
    if (q) {
        result = result.filter(
            (p) =>
                p.name.toLowerCase().includes(q) ||
                p.fund_source.toLowerCase().includes(q) ||
                p.type.toLowerCase().includes(q),
        );
    }

    return result;
});
</script>

<template>
    <div class="min-h-screen bg-surface-subtle text-content antialiased">
        <Head :title="`Proyek Pembangunan ${formattedVillageName}`" />

        <div class="flex min-h-screen">
            <!-- Desktop Sidebar -->
            <DesktopSidebar
                active-tab="cari-proyek"
                :village-name="village.name"
                :village-slug="village.slug"
                :regency="village.regency"
                is-village-page
                @tab-click="handleTabClick"
                @share="handleShare"
            />

            <!-- Main Content Area -->
            <div class="min-w-0 flex-1 pb-20 md:pb-12">
                <!-- Mobile App Bar / Header (Sticky) -->
                <div class="sticky top-0 z-20 md:hidden">
                    <VillageHeader :village-name="village.name" @share="handleShare" />
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

                <!-- Desktop Header Breadcrumb -->
                <header
                    class="sticky top-0 z-20 hidden items-center justify-between border-b border-stroke bg-surface/95 px-6 py-3.5 backdrop-blur-sm md:flex lg:px-8"
                >
                    <div class="flex items-center gap-2 text-sm text-content-secondary">
                        <Link href="/" class="transition-colors hover:text-primary">Beranda</Link>
                        <span>/</span>
                        <span class="font-medium text-content">{{ formattedVillageName }}</span>
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
                            aria-label="Bagikan profil desa"
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
                            <span>Bagikan profil</span>
                        </button>
                    </div>
                </header>

                <!-- Hero Section with Balai Spot Illustration -->
                <VillageHero :regency="village.regency" :name="village.name" />

                <!-- Main Content -->
                <main class="@container space-y-6 px-4 pt-6 pb-8 sm:px-6 md:px-8 lg:px-10">
                    <!-- 3-column Stat Summary Card on desktop -->
                    <VillageStatSummary
                        :project-count="projects.length"
                        :total-budget-rp="totalVillageBudget"
                        :year-text="publishedYears"
                        :citizen-count="citizenReportsCount"
                    />

                    <!-- Search Field and Category Chips Filter -->
                    <VillageProjectFilter
                        v-model:search-query="searchQuery"
                        v-model:active-category="activeCategory"
                        :categories="categories"
                    />

                    <!-- Project Cards Grid / Empty State -->
                    <section class="grid grid-cols-1 gap-5 @lg:grid-cols-2 @4xl:grid-cols-3" aria-label="Daftar proyek">
                        <div v-if="projects.length === 0" class="col-span-full">
                            <EmptyState
                                illustration="plang-empty"
                                title="Belum ada proyek yang diterbitkan"
                                :description="`Pemerintah Desa ${village.name} belum menerbitkan proyek pembangunan di Sonar Desa. Proyek akan muncul di sini setelah diterbitkan.`"
                            />
                        </div>

                        <div v-else-if="filteredProjects.length === 0" class="col-span-full">
                            <EmptyState
                                illustration="search-empty"
                                title="Tidak ada proyek yang sesuai"
                                description="Belum ada proyek yang diterbitkan untuk kata kunci ini atau nama kegiatan belum terdaftar."
                            />
                        </div>

                        <template v-else>
                            <VillageProjectCard v-for="p in filteredProjects" :key="p.id" :project="p" />

                            <!-- Banner info update di akhir grid (ala FigCraft desk-desa) -->
                            <div
                                v-if="filteredProjects.length > 0 && activeCategory === 'all'"
                                class="flex flex-col items-center gap-6 rounded-2xl bg-surface-subtle p-6 @lg:col-span-2 @xl:flex-row"
                            >
                                <img :src="plangEmpty" alt="" class="h-36 w-auto shrink-0 object-contain sm:h-44" />
                                <div class="min-w-0 flex-1">
                                    <h3 class="text-xl font-bold text-content">Proyek lain belum diterbitkan</h3>
                                    <p class="mt-2 text-sm leading-relaxed text-content-secondary">
                                        Pemerintah {{ formattedVillageName }} menerbitkan proyek setelah RAB selesai
                                        diverifikasi dan disetujui BPD. Cek kembali secara berkala.
                                    </p>
                                    <button
                                        type="button"
                                        class="mt-4 inline-flex h-10 items-center justify-center gap-2 rounded-xl border border-stroke bg-surface px-4 text-xs font-semibold text-content transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                        @click="showFollowDialog = true"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                            />
                                        </svg>
                                        <span>Ikuti Kabar Desa</span>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </section>

                    <!-- Desktop Footer (ala FigCraft desk-desa) -->
                    <footer
                        class="mt-12 hidden border-t border-stroke pt-6 pb-4 text-center text-xs text-content-secondary md:block"
                    >
                        Sonar Desa &middot; Keterbukaan Informasi Publik Desa &middot; UU No. 6/2014 &amp; UU No.
                        14/2008
                    </footer>
                </main>
            </div>
        </div>

        <!-- Bottom Navigation Bar (Mobile only) -->
        <BottomNav active-tab="cari-proyek" :village-slug="village.slug" is-village-page @tab-click="handleTabClick" />

        <!-- Toast Feedback -->
        <div
            v-if="toastMessage"
            class="fixed bottom-20 left-1/2 z-50 -translate-x-1/2 rounded-full bg-content px-4 py-2 text-sm font-medium text-content-inverse shadow-sm"
            role="status"
        >
            {{ toastMessage }}
        </div>

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
        <QrScannerDialog
            :open="showQrDialog"
            :village-name="village.name"
            :village-slug="village.slug"
            :projects="projects"
            @close="showQrDialog = false"
            @open-plang="showPlangDialog = true"
        />

        <!-- Dialog: Plang Proyek Digital -->
        <ProjectPlangDialog
            :open="showPlangDialog"
            :village-name="village.name"
            :regency="village.regency"
            :projects="projects"
            :plangs="plangs"
            :can-print-poster="activeRole === 'desa'"
            @close="showPlangDialog = false"
            @copied="showToast"
        />

        <!-- Dialog: Ikuti Kabar Desa -->
        <VillageFollowDialog
            :open="showFollowDialog"
            :village-name="village.name"
            :village-slug="village.slug"
            @close="showFollowDialog = false"
            @share="handleShare"
            @copied="showToast"
        />
    </div>
</template>
