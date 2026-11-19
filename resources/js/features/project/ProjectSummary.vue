<script setup lang="ts">
// ProjectSummary: hero card with project name, status, budget, progress, and label counts.
// Covers PRD P1, P2, P5, P6, P7 (Langkah 1-4).

import { computed } from 'vue';
import { formatDate, formatRupiah } from '@/lib/format';
import { citizenCount, projectType, publicStatus, type ReviewOutcome } from '@/lib/labels';
import type { ProjectDetail } from '@/types/project';

interface Props {
    project: ProjectDetail;
}

const props = defineProps<Props>();

const projectTypeName = computed(
    () => projectType[props.project.type as keyof typeof projectType] ?? props.project.type,
);

const statusText = computed(() => {
    if (props.project.public_status === 'in_review' && props.project.in_review_since) {
        return publicStatus.in_review(props.project.in_review_since);
    }
    if (props.project.public_status === 'reviewed' && props.project.review?.outcome) {
        return publicStatus.reviewed(props.project.review.outcome as ReviewOutcome);
    }
    return null;
});
</script>

<template>
    <section class="rounded-lg border border-stroke bg-surface p-4">
        <!-- Type + Fund source badges -->
        <div class="flex flex-wrap items-center gap-2">
            <span
                class="rounded-full border border-stroke bg-surface-muted px-2.5 py-0.5 text-sm font-medium text-content-secondary"
            >
                {{ projectTypeName }}
            </span>
            <span class="text-sm text-content-secondary">
                {{ project.fund_source }} &middot; Tahun {{ project.year }}
            </span>
        </div>

        <h1 class="mt-3 text-2xl font-semibold text-content">{{ project.name }}</h1>
        <p class="mt-1 text-sm text-content-secondary">
            Desa {{ project.village.name }}, Kabupaten {{ project.village.regency }}
        </p>

        <!-- Public status (P5, P6) — never marked green -->
        <div v-if="statusText" class="mt-4">
            <div
                v-if="project.public_status === 'in_review'"
                class="rounded-lg border border-warning-border bg-warning-subtle p-3 text-sm text-warning"
            >
                <div class="flex items-center gap-2 font-semibold">
                    <span class="inline-block h-2 w-2 rounded-full bg-warning-solid" aria-hidden="true" />
                    <span>{{ statusText }}</span>
                </div>
                <p class="mt-1">BPD sedang memverifikasi laporan fisik dan dokumen pendukung proyek ini.</p>
            </div>

            <div
                v-else-if="project.public_status === 'reviewed'"
                class="rounded-lg border border-info-border bg-info-subtle p-3 text-sm text-info"
            >
                <div class="flex items-center gap-2 font-semibold">
                    <span class="inline-block h-2 w-2 rounded-full bg-info-solid" aria-hidden="true" />
                    <span>{{ statusText }}</span>
                </div>
                <p v-if="project.review?.note" class="mt-1.5">{{ project.review.note }}</p>
                <div
                    v-if="project.review?.notes && project.review.notes.length > 0"
                    class="mt-2 flex flex-col gap-1.5 border-t border-info-border pt-2"
                >
                    <p class="font-medium">Catatan tindak lanjut:</p>
                    <p
                        v-for="(subNote, idx) in project.review.notes"
                        :key="idx"
                        class="rounded-md bg-surface p-2 text-content"
                    >
                        {{ subNote.note }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Budget + Progress grid -->
        <div class="mt-4 grid grid-cols-2 gap-3 border-t border-stroke pt-4">
            <div class="rounded-md bg-surface-subtle p-3">
                <p class="text-sm text-content-secondary">Total anggaran (RAB)</p>
                <p class="mt-0.5 text-lg font-bold text-content tabular-nums">
                    {{ formatRupiah(project.total_amount_rp) }}
                </p>
            </div>

            <div class="rounded-md bg-surface-subtle p-3">
                <div class="flex items-center justify-between text-sm text-content-secondary">
                    <span>Progres fisik</span>
                    <span class="font-semibold text-content tabular-nums">{{ project.progress_pct }}%</span>
                </div>
                <div
                    class="mt-2 h-2 w-full overflow-hidden rounded-full bg-stroke"
                    role="progressbar"
                    :aria-valuenow="project.progress_pct"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-label="Progres fisik"
                >
                    <div
                        class="h-full rounded-full bg-primary transition-all duration-300"
                        :style="{ width: `${Math.min(100, Math.max(0, project.progress_pct))}%` }"
                    />
                </div>
                <p v-if="project.last_photo_date" class="mt-1.5 text-xs text-content-tertiary">
                    Foto: {{ formatDate(project.last_photo_date) }}
                </p>
            </div>
        </div>

        <!-- Citizen count (P7) — neutral, not an endorsement -->
        <div
            v-if="project.has_citizen_reports"
            class="mt-3 flex items-center gap-2 rounded-md bg-surface-subtle px-3 py-2 text-sm text-content"
        >
            <svg
                class="h-4 w-4 shrink-0 text-content-secondary"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"
                />
            </svg>
            <span class="font-medium">{{ citizenCount(project.citizen_reports_count) }}</span>
        </div>

        <!-- Label counts (PRD 4.2 Langkah 2) -->
        <div class="mt-3 flex flex-wrap gap-2 text-sm">
            <span
                v-if="project.label_counts.above_standard > 0"
                class="inline-flex items-center gap-1 rounded-full border border-negative-border bg-negative-subtle px-2.5 py-0.5 font-medium text-negative"
            >
                <span class="font-bold tabular-nums">{{ project.label_counts.above_standard }}</span>
                <span>Di atas standar</span>
            </span>
            <span
                v-if="project.label_counts.within_standard > 0"
                class="inline-flex items-center gap-1 rounded-full border border-positive-border bg-positive-subtle px-2.5 py-0.5 font-medium text-positive"
            >
                <span class="font-bold tabular-nums">{{ project.label_counts.within_standard }}</span>
                <span>Sesuai standar</span>
            </span>
            <span
                v-if="project.label_counts.no_reference > 0"
                class="inline-flex items-center gap-1 rounded-full border border-stroke bg-surface-muted px-2.5 py-0.5 font-medium text-content-secondary"
            >
                <span class="font-bold tabular-nums">{{ project.label_counts.no_reference }}</span>
                <span>Tanpa acuan</span>
            </span>
        </div>
    </section>
</template>
