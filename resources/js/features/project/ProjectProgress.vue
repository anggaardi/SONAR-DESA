<script setup lang="ts">
// ProjectProgress: progres fisik digambar sebagai ruas jalan yang sedang dikerjakan.
// Persen diterjemahkan ke meter bila RAB punya ukuran panjang, supaya bisa dicek di lokasi.

import { computed } from 'vue';
import { formatDate, formatLength } from '@/lib/format';
import { findMeasurement, measurementInMeters } from '@/lib/projectStory';
import type { ProjectDetail } from '@/types/project';

interface Props {
    project: ProjectDetail;
}

const props = defineProps<Props>();

const progress = computed(() => Math.min(100, Math.max(0, props.project.progress_pct)));

const lengthMeters = computed(() => measurementInMeters(findMeasurement(props.project.measurements, 'panjang')));

const doneMeters = computed(() =>
    lengthMeters.value !== null ? Math.round((lengthMeters.value * progress.value) / 100) : null,
);
</script>

<template>
    <section aria-label="Progres fisik" class="rounded-2xl border border-stroke bg-surface p-4 shadow-2xs sm:p-5">
        <div class="flex items-baseline justify-between gap-3">
            <p class="text-sm font-medium text-content-secondary">Progres di lapangan</p>
            <p v-if="project.last_photo_date" class="text-xs text-content-tertiary sm:text-sm">
                Foto {{ formatDate(project.last_photo_date) }}
            </p>
        </div>

        <div class="mt-2 flex items-center gap-4">
            <p class="text-4xl font-bold tracking-tight text-content tabular-nums">{{ progress }}%</p>
            <div v-if="lengthMeters !== null && doneMeters !== null" class="min-w-0">
                <p class="text-base font-semibold text-content tabular-nums">
                    &plusmn; {{ formatLength(doneMeters) }} sudah dikerjakan
                </p>
                <p class="text-sm text-content-secondary tabular-nums">dari {{ formatLength(lengthMeters) }} di RAB</p>
            </div>
            <p v-else class="text-sm text-content-secondary">Sesuai laporan foto dari pemerintah desa</p>
        </div>

        <!-- Ruas jalan: bagian gelap sudah dikerjakan, garis putus-putus belum. -->
        <div
            class="relative mt-4 h-6 rounded-full border-2 border-dashed border-info-border bg-surface-subtle"
            role="progressbar"
            :aria-valuenow="progress"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-label="Progres fisik"
        >
            <div
                v-if="progress > 0"
                class="absolute -inset-y-0.5 -left-0.5 flex items-center overflow-hidden rounded-full bg-content-secondary transition-all duration-500"
                :style="{ width: `calc(${progress}% + 4px)` }"
            >
                <span class="mx-3 h-0 w-full border-t-2 border-dashed border-surface/90" />
            </div>
            <span
                class="absolute top-1/2 h-5 w-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-[3px] border-surface bg-primary shadow-xs"
                :style="{ left: `${progress}%` }"
                aria-hidden="true"
            />
        </div>

        <div class="mt-2 flex justify-between text-xs text-content-tertiary tabular-nums">
            <span>{{ lengthMeters !== null ? '0 m' : '0%' }}</span>
            <span>{{ lengthMeters !== null ? formatLength(lengthMeters) : '100%' }}</span>
        </div>
    </section>
</template>
