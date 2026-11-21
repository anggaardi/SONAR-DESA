<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
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
import ProjectStatusNotice from '@/features/project/ProjectStatusNotice.vue';
import type { ProjectDetail } from '@/types/project';

interface Props {
    project: ProjectDetail;
}

const props = defineProps<Props>();

const toastMessage = ref<string | null>(null);
const activeFilter = ref<BudgetFilter>('all');
const showCheckSpecDialog = ref(false);

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

function scrollToSection(sectionId: string): void {
    document.getElementById(sectionId)?.scrollIntoView({ behavior: 'smooth' });
}

/** Lompatan cepat ke tiap bagian halaman, pengganti 4 ikon pintasan lama. */
const sectionLinks = [
    { id: 'anggaran', label: 'Anggaran' },
    { id: 'rincian', label: 'Harga' },
    { id: 'ukuran', label: 'Ukuran' },
    { id: 'perjalanan', label: 'Perjalanan' },
];

function handleCheckSpecClick(): void {
    showCheckSpecDialog.value = true;
}
</script>

<template>
    <div class="min-h-screen bg-surface-subtle pb-28 text-content antialiased">
        <Head :title="project.name" />

        <ProjectHeader
            :village-name="project.village.name"
            :village-slug="project.village.slug"
            :project-name="project.name"
            @share="handleShare"
        />

        <ProjectHero :project="project" />

        <main class="mx-auto -mt-8 flex max-w-md flex-col gap-6 px-4 sm:max-w-xl sm:px-6 md:max-w-2xl">
            <ProjectProgress :project="project" />

            <nav aria-label="Bagian halaman" class="no-scrollbar -mx-4 -mt-2 flex gap-2 overflow-x-auto px-4">
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
            <ProjectCheckSpecInvite :citizen-count="project.citizen_reports_count" />
            <ProjectJourney :project="project" />
            <ProjectChanges :changes="project.changes" />

            <footer class="mt-4 border-t border-stroke py-6 text-center text-sm text-content-secondary">
                <p>Sonar Desa &middot; Keterbukaan Informasi Publik Desa</p>
                <p class="mt-1 text-xs text-content-tertiary">
                    Berdasarkan UU No. 6/2014 tentang Desa &amp; UU No. 14/2008 tentang KIP
                </p>
            </footer>
        </main>

        <!-- Satu tombol utama per layar (PRD 6.1), menempel di bawah. -->
        <div class="fixed inset-x-0 bottom-0 z-30 border-t border-stroke bg-surface/95 backdrop-blur-sm">
            <div class="mx-auto max-w-md px-4 py-3 sm:max-w-xl sm:px-6 md:max-w-2xl">
                <button
                    type="button"
                    class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 text-base font-semibold text-content-inverse transition-colors hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary active:bg-primary-hover"
                    @click="handleCheckSpecClick"
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
                </button>
            </div>
        </div>

        <!-- Dialog: Panduan Cek Spek di Lokasi -->
        <Dialog :open="showCheckSpecDialog" title="Cek Spek di Lokasi" @close="showCheckSpecDialog = false">
            <div class="space-y-4 text-sm text-content-secondary">
                <p>
                    <strong class="font-semibold text-content">Lewat lokasi proyek ini?</strong>
                    Bantu desa memverifikasi kesesuaian fisik antara kenyataan lapangan dengan dokumen anggaran resmi
                    (RAB).
                </p>

                <div v-if="project.measurements && project.measurements.length > 0" class="space-y-2">
                    <p class="text-xs font-medium tracking-wider text-content-tertiary uppercase">
                        Ukuran resmi di RAB:
                    </p>
                    <div class="divide-y divide-stroke rounded-xl border border-stroke bg-surface-subtle p-3">
                        <div
                            v-for="m in project.measurements"
                            :key="m.id"
                            class="flex items-center justify-between py-2 first:pt-0 last:pb-0"
                        >
                            <span class="font-medium text-content">{{ m.name }}</span>
                            <span class="font-bold text-primary tabular-nums">{{ m.value }} {{ m.unit }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-info-border bg-info-subtle p-3 text-xs text-info">
                    <p class="font-semibold">Tips pengecekan:</p>
                    <p class="mt-1">
                        Gunakan meteran fisik untuk mengukur panjang dan lebar di lapangan. Jika menemukan perbedaan
                        nyata, Anda dapat membawanya ke Musyawarah Desa atau menyampaikannya ke BPD.
                    </p>
                </div>
            </div>
            <template #actions>
                <div class="flex w-full flex-col gap-2 sm:flex-row">
                    <button
                        type="button"
                        class="h-10 w-full rounded-md border border-stroke bg-surface px-4 text-sm font-medium text-content hover:bg-surface-muted"
                        @click="showCheckSpecDialog = false"
                    >
                        Tutup
                    </button>
                    <button
                        type="button"
                        class="h-10 w-full rounded-md bg-primary px-4 text-sm font-semibold text-content-inverse hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                        @click="
                            showCheckSpecDialog = false;
                            scrollToSection('ukuran');
                        "
                    >
                        Lihat Spesifikasi Fisik
                    </button>
                </div>
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
