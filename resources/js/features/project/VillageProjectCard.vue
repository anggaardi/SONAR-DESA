<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatRupiah } from '@/lib/format';
import { projectType } from '@/lib/labels';
import type { VillageProjectSummary } from '@/types/project';

interface Props {
    project: VillageProjectSummary;
}

const props = defineProps<Props>();

const typeLabel = computed(() => {
    return projectType[props.project.type as keyof typeof projectType] ?? props.project.type;
});

const progressWidth = computed(() => {
    return `${Math.min(100, Math.max(0, props.project.progress_pct))}%`;
});
</script>

<template>
    <Link
        :href="`/p/${project.slug}`"
        class="group block rounded-2xl border border-stroke bg-surface p-4 shadow-xs transition-all hover:border-primary hover:shadow-sm"
    >
        <!-- Top header row -->
        <div class="flex items-start justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <!-- Icon chip -->
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-primary-subtle text-primary"
                    aria-hidden="true"
                >
                    <!-- Rabat beton / Jalan icon -->
                    <svg
                        v-if="project.type === 'rabat_beton' || project.type.includes('beton')"
                        class="h-5 w-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M3 21h18M5 21V10l7-5 7 5v11M10 21v-5h4v5M10 11h4"
                        />
                    </svg>
                    <!-- Irigasi / Saluran icon -->
                    <svg
                        v-else-if="project.type === 'saluran_irigasi' || project.type.includes('irigasi')"
                        class="h-5 w-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 20h16M6 16v-3M11 16V9M16 16V5"
                        />
                    </svg>
                    <!-- Gedung / Default icon -->
                    <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                        />
                    </svg>
                </div>

                <div class="min-w-0">
                    <span
                        class="inline-block rounded-full border border-stroke bg-surface-muted px-2.5 py-0.5 text-xs font-medium text-content"
                    >
                        {{ typeLabel }}
                    </span>
                    <p class="mt-0.5 truncate text-xs text-content-secondary">
                        {{ project.fund_source }} &middot; {{ project.year }}
                    </p>
                </div>
            </div>

            <!-- Chevron right -->
            <svg
                class="mt-2 h-5 w-5 shrink-0 text-content-tertiary transition-transform group-hover:translate-x-0.5 group-hover:text-primary"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </div>

        <!-- Project title -->
        <h3 class="mt-3 text-base font-semibold text-content transition-colors group-hover:text-primary sm:text-lg">
            {{ project.name }}
        </h3>

        <!-- Divider -->
        <div class="my-3 border-t border-stroke" />

        <!-- Metrics rows -->
        <div class="space-y-2 text-sm">
            <div class="flex items-center justify-between gap-2">
                <span class="text-content-secondary">Anggaran</span>
                <span class="text-base font-bold text-content tabular-nums">
                    {{ formatRupiah(project.total_amount_rp) }}
                </span>
            </div>

            <div>
                <div class="flex items-center justify-between text-content-secondary">
                    <span>Progres fisik</span>
                    <span class="font-semibold text-content tabular-nums">{{ project.progress_pct }}%</span>
                </div>
                <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-surface-muted" aria-hidden="true">
                    <div
                        class="h-full rounded-full bg-primary transition-all duration-300"
                        :style="{ width: progressWidth }"
                    />
                </div>
            </div>
        </div>

        <!-- Status chip (if review status exists) -->
        <div v-if="project.public_status === 'in_review'" class="mt-3">
            <span
                class="inline-flex items-center gap-1.5 rounded-full border border-warning-border bg-warning-subtle px-3 py-1 text-xs font-medium text-warning"
            >
                <svg
                    class="h-3.5 w-3.5 shrink-0 text-warning"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                <span>Dalam tinjauan BPD</span>
            </span>
        </div>
        <div v-else-if="project.public_status === 'reviewed'" class="mt-3">
            <span
                class="inline-flex items-center gap-1.5 rounded-full border border-info-border bg-info-subtle px-3 py-1 text-xs font-medium text-info"
            >
                <svg
                    class="h-3.5 w-3.5 shrink-0 text-info"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>Telah ditinjau BPD</span>
            </span>
        </div>
    </Link>
</template>
