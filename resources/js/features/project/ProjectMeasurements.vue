<script setup lang="ts">
// ProjectMeasurements: "Ukuran di RAB" block + CTA Cek Spek (disabled until M4) + document link.
// PRD P11, P12, F20, F21 (Langkah 7, 9).

import { document as documentLabels, measurementsInRab } from '@/lib/labels';
import type { ProjectDetail } from '@/types/project';
import { computed } from 'vue';

interface Props {
    project: ProjectDetail;
}

const props = defineProps<Props>();

const measurementSummary = computed(() =>
    props.project.measurements?.length ? measurementsInRab(props.project.measurements) : null,
);
</script>

<template>
    <section class="mt-4 rounded-lg border border-stroke bg-surface p-4">
        <div v-if="measurementSummary">
            <p class="text-base font-semibold text-content">{{ measurementSummary }}</p>
            <p class="mt-1 text-sm text-content-secondary">
                Ukuran fisik resmi dari dokumen RAB untuk dicocokkan langsung di lokasi proyek.
            </p>
        </div>

        <div class="mt-4 flex flex-col gap-3">
            <!-- CTA: Cek Spek di lokasi — disabled until M4 provides the route -->
            <button
                type="button"
                disabled
                aria-disabled="true"
                class="inline-flex h-11 w-full cursor-not-allowed items-center justify-center gap-2 rounded-md bg-surface-muted px-4 text-base font-medium text-content-tertiary"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"
                    />
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"
                    />
                </svg>
                <span>Cek Spek di lokasi</span>
            </button>

            <!-- CTA: Dokumen asli (P11, F20, Langkah 7) -->
            <div v-if="project.document_url" class="border-t border-stroke pt-3 text-center">
                <a
                    :href="project.document_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex min-h-11 items-center gap-1.5 text-base font-medium text-primary underline-offset-2 hover:text-primary-hover hover:underline"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                        />
                    </svg>
                    <span>{{ documentLabels.viewOriginal }}</span>
                </a>
                <p class="text-sm text-content-secondary">{{ documentLabels.readFrom }}</p>
            </div>
        </div>
    </section>
</template>
