<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import BottomNav from '@/components/ui/BottomNav.vue';
import Dialog from '@/components/ui/Dialog.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import VillageHeader from '@/features/project/VillageHeader.vue';
import VillageHero from '@/features/project/VillageHero.vue';
import VillageProjectCard from '@/features/project/VillageProjectCard.vue';
import VillageProjectFilter, { type FilterCategory } from '@/features/project/VillageProjectFilter.vue';
import VillageStatSummary from '@/features/project/VillageStatSummary.vue';
import { projectType } from '@/lib/labels';
import type { ProjectVillage, VillageProjectSummary } from '@/types/project';

interface Props {
    village: ProjectVillage;
    projects: VillageProjectSummary[];
}

const props = defineProps<Props>();

const searchQuery = ref('');
const activeCategory = ref('all');
const toastMessage = ref<string | null>(null);
const showHelpDialog = ref(false);
const showQrDialog = ref(false);

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

function handleTabClick(tabId: string): void {
    if (tabId === 'bantuan') {
        showHelpDialog.value = true;
    } else if (tabId === 'pindai-qr') {
        showQrDialog.value = true;
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
    const list: FilterCategory[] = [{ id: 'all', label: 'Semua' }];
    const uniqueTypes = [...new Set(props.projects.map((p) => p.type))];
    for (const t of uniqueTypes) {
        list.push({
            id: t,
            label: projectType[t as keyof typeof projectType] ?? t,
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
    <div class="min-h-screen bg-surface-subtle pb-20 text-content antialiased">
        <Head :title="`Proyek Pembangunan ${formattedVillageName}`" />

        <!-- App Bar / Header -->
        <VillageHeader :village-name="village.name" @share="handleShare" />

        <!-- Hero Section with Balai Spot Illustration -->
        <VillageHero :regency="village.regency" :name="village.name" />

        <!-- Main Content -->
        <main class="mx-auto max-w-md space-y-4 px-4 pt-4 pb-8 sm:max-w-xl sm:px-6 md:max-w-2xl">
            <!-- 2-column Stat Summary Card -->
            <VillageStatSummary
                :project-count="projects.length"
                :total-budget-rp="totalVillageBudget"
                :year-text="publishedYears"
            />

            <!-- Search Field and Category Chips Filter -->
            <VillageProjectFilter
                v-model:search-query="searchQuery"
                v-model:active-category="activeCategory"
                :categories="categories"
            />

            <!-- Project Cards List / Empty State -->
            <section class="flex flex-col gap-3 pt-1" aria-label="Daftar proyek">
                <EmptyState
                    v-if="projects.length === 0"
                    illustration="plang-empty"
                    title="Belum ada proyek yang diterbitkan"
                    :description="`Pemerintah Desa ${village.name} belum menerbitkan proyek pembangunan di Sonar Desa. Proyek akan muncul di sini setelah diterbitkan.`"
                />

                <EmptyState
                    v-else-if="filteredProjects.length === 0"
                    illustration="search-empty"
                    title="Tidak ada proyek yang sesuai"
                    description="Belum ada proyek yang diterbitkan untuk kata kunci ini atau nama kegiatan belum terdaftar."
                />

                <VillageProjectCard v-for="p in filteredProjects" :key="p.id" :project="p" />
            </section>
        </main>

        <!-- Bottom Navigation Bar -->
        <BottomNav active-tab="cari-desa" @tab-click="handleTabClick" />

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
    </div>
</template>
