<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Dialog from '@/components/ui/Dialog.vue';
import { formatRupiah } from '@/lib/format';
import { projectType } from '@/lib/labels';
import type { ProjectPlangInfo } from '@/types/project';

export interface PlangProjectSummary {
    id: number;
    slug: string;
    name: string;
    type: string;
    fund_source: string;
    year: number;
    total_amount_rp: number;
    progress_pct: number;
}

interface Props {
    open: boolean;
    villageName: string;
    regency: string;
    projects: PlangProjectSummary[];
    plangs?: Record<number, ProjectPlangInfo>;
    initialProjectId?: number | null;
    canPrintPoster?: boolean;
    isSingleProject?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    plangs: () => ({}),
    initialProjectId: null,
    canPrintPoster: false,
    isSingleProject: false,
});

const emit = defineEmits<{
    close: [];
    copied: [message: string];
}>();

const selectedProjectId = ref<number | null>(null);
const copiedLocal = ref(false);

watch(
    () => [props.open, props.initialProjectId, props.projects] as const,
    ([isOpen]) => {
        if (!isOpen) {
            copiedLocal.value = false;
            return;
        }
        if (props.initialProjectId && props.projects.some((p) => p.id === props.initialProjectId)) {
            selectedProjectId.value = props.initialProjectId;
            return;
        }
        selectedProjectId.value = props.projects[0]?.id ?? null;
    },
    { immediate: true },
);

const selectedProject = computed<PlangProjectSummary | null>(() => {
    if (props.projects.length === 0) {
        return null;
    }
    return props.projects.find((p) => p.id === selectedProjectId.value) ?? props.projects[0] ?? null;
});

const selectedPlang = computed<ProjectPlangInfo | null>(() => {
    if (!selectedProject.value) {
        return null;
    }
    return props.plangs[selectedProject.value.id] ?? null;
});

const formattedVillageName = computed(() =>
    props.villageName.startsWith('Desa ') ? props.villageName : `Desa ${props.villageName}`,
);

const typeLabel = computed(() => {
    if (!selectedProject.value) {
        return '';
    }
    return projectType[selectedProject.value.type as keyof typeof projectType] ?? selectedProject.value.type;
});

async function copyPlangUrl(): Promise<void> {
    if (!selectedPlang.value || typeof navigator === 'undefined' || !navigator.clipboard) {
        return;
    }
    try {
        await navigator.clipboard.writeText(selectedPlang.value.short_url);
        copiedLocal.value = true;
        emit('copied', 'Tautan plang proyek berhasil disalin!');
        setTimeout(() => {
            copiedLocal.value = false;
        }, 2000);
    } catch {
        emit('copied', 'Gagal menyalin tautan plang');
    }
}
</script>

<template>
    <Dialog :open="open" title="Plang Proyek Digital" @close="emit('close')">
        <div v-if="projects.length === 0" class="space-y-2 text-sm text-content-secondary">
            <p>Belum ada proyek yang diterbitkan di {{ formattedVillageName }}.</p>
            <p class="text-xs text-content-tertiary">
                Plang proyek beserta kode QR akan tersedia otomatis setelah Pemerintah Desa atau BPD menerbitkan proyek.
            </p>
        </div>

        <div v-else-if="selectedProject" class="space-y-4">
            <!-- Project picker when browsing village -->
            <div v-if="!isSingleProject && projects.length > 1" class="space-y-1.5">
                <label for="plang-project-select" class="block text-xs font-semibold text-content-secondary">
                    Pilih proyek di {{ formattedVillageName }}
                </label>
                <select
                    id="plang-project-select"
                    v-model="selectedProjectId"
                    class="h-11 w-full rounded-md border border-stroke-strong bg-surface px-3 text-sm text-content focus:border-primary focus:outline-none"
                >
                    <option v-for="item in projects" :key="item.id" :value="item.id">
                        {{ item.name }} ({{ formatRupiah(item.total_amount_rp) }})
                    </option>
                </select>
            </div>

            <!-- Plang Board Preview -->
            <div class="rounded-lg border-2 border-content bg-surface p-4 text-center">
                <p class="text-xs font-medium text-content-secondary">
                    Pemerintah Kab. {{ regency }} &middot; {{ formattedVillageName }}
                </p>
                <span
                    class="mt-1.5 inline-block rounded-full bg-content px-3 py-0.5 text-xs font-semibold text-content-inverse"
                >
                    Papan Transparansi Proyek Fisik
                </span>

                <h3 class="mt-3 text-base leading-snug font-bold text-content">
                    {{ selectedProject.name }}
                </h3>
                <p class="mt-1 text-xs text-content-secondary">
                    {{ typeLabel }} &middot; {{ selectedProject.fund_source }} &middot; TA {{ selectedProject.year }}
                </p>

                <div
                    class="mt-3 grid grid-cols-2 gap-2 rounded-lg border border-stroke bg-surface-subtle p-2.5 text-left"
                >
                    <div>
                        <p class="text-xs text-content-tertiary">Anggaran RAB</p>
                        <p class="text-sm font-bold text-content tabular-nums">
                            {{ formatRupiah(selectedProject.total_amount_rp) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-content-tertiary">Progres fisik</p>
                        <p class="text-sm font-bold text-content tabular-nums">{{ selectedProject.progress_pct }}%</p>
                    </div>
                </div>

                <div v-if="selectedPlang" class="mt-4 flex flex-col items-center">
                    <div class="rounded-lg border border-stroke-strong bg-surface p-2.5">
                        <!-- eslint-disable-next-line vue/no-v-html -->
                        <div class="h-40 w-40 [&>svg]:h-full [&>svg]:w-full" v-html="selectedPlang.qr_svg" />
                    </div>
                    <p class="mt-2 text-xs font-medium text-content-secondary">
                        Pindai QR dengan kamera HP untuk membuka rincian RAB &amp; Cek Spek
                    </p>
                    <p class="mt-1 max-w-full truncate text-xs font-semibold text-primary tabular-nums">
                        {{ selectedPlang.short_url }}
                    </p>
                </div>
            </div>
        </div>

        <template #actions>
            <div class="flex w-full flex-col gap-2 sm:flex-row sm:justify-end">
                <button
                    v-if="selectedPlang"
                    type="button"
                    class="inline-flex h-10 items-center justify-center rounded-md border border-stroke-strong bg-surface px-4 text-sm font-medium text-content transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                    @click="copyPlangUrl"
                >
                    {{ copiedLocal ? 'Tautan tersalin' : 'Salin tautan' }}
                </button>
                <a
                    v-if="canPrintPoster && selectedProject"
                    :href="`/projects/${selectedProject.id}/qr`"
                    class="inline-flex h-10 items-center justify-center rounded-md border border-stroke-strong bg-surface px-4 text-sm font-medium text-content transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                >
                    Cetak poster A4
                </a>
                <Link
                    v-if="!isSingleProject && selectedProject"
                    :href="`/p/${selectedProject.slug}`"
                    class="inline-flex h-10 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-content-inverse transition-colors hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                    @click="emit('close')"
                >
                    Buka halaman proyek
                </Link>
                <button
                    v-else
                    type="button"
                    class="inline-flex h-10 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-content-inverse transition-colors hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                    @click="emit('close')"
                >
                    Tutup
                </button>
            </div>
        </template>
    </Dialog>
</template>
