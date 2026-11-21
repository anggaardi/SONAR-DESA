<script setup lang="ts">
// ProjectStatusNotice: status publik BPD (PRD Fitur 4.3). "none" tidak menampilkan apa pun.
// Bahasa tetap netral (D9): menjelaskan proses, tidak menilai.

import { computed } from 'vue';
import { publicStatus, type ReviewOutcome } from '@/lib/labels';
import type { ProjectDetail } from '@/types/project';

interface Props {
    project: ProjectDetail;
}

const props = defineProps<Props>();

const reviewedText = computed(() => {
    const outcome = props.project.review?.outcome;
    return outcome ? publicStatus.reviewed(outcome as ReviewOutcome) : 'Ditinjau BPD';
});
</script>

<template>
    <div
        v-if="project.public_status === 'in_review' && project.in_review_since"
        class="flex items-start gap-3 rounded-2xl border border-warning-border bg-warning-subtle p-4 text-warning"
        role="status"
    >
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-surface" aria-hidden="true">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <circle cx="12" cy="12" r="9" stroke-width="2" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 7v5l3 2" />
            </svg>
        </span>
        <div class="min-w-0">
            <p class="text-base font-semibold">{{ publicStatus.in_review(project.in_review_since) }}</p>
            <p class="mt-0.5 text-sm">Ada laporan warga yang sedang diperiksa BPD.</p>
        </div>
    </div>

    <div
        v-else-if="project.public_status === 'reviewed'"
        class="flex items-start gap-3 rounded-2xl border border-info-border bg-info-subtle p-4 text-info"
        role="status"
    >
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-surface" aria-hidden="true">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z"
                />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.5 12l2.5 2.5 4.5-5" />
            </svg>
        </span>
        <div class="min-w-0">
            <p class="text-base font-semibold">{{ reviewedText }}</p>
            <p v-if="project.review?.note" class="mt-0.5 text-sm">{{ project.review.note }}</p>
            <a
                href="#perjalanan"
                class="mt-2 inline-flex min-h-11 items-center gap-1 text-sm font-semibold text-primary hover:text-primary-hover"
            >
                Lihat di perjalanan proyek
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 6l6 6-6 6" />
                </svg>
            </a>
        </div>
    </div>
</template>
