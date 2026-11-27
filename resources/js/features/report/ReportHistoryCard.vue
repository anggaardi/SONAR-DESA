<script setup lang="ts">
// ReportHistoryCard: single citizen report entry with threshold progress and synced M5 BPD follow-up status.
// Follows IDDS card structure (docs/DESIGN.md §6): single card container with divide-y sections, no nested cards.

import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import Card from '@/components/ui/Card.vue';
import { formatDate, formatDateTime } from '@/lib/format';
import { myReports as text, publicStatus, reportType, type ReviewOutcome } from '@/lib/labels';
import type { CitizenReportStatus, MyReportItem, ReportKind } from '@/types/report';

const props = defineProps<{
    item: MyReportItem;
}>();

const kindBadgeColor: Record<ReportKind, 'positive' | 'warning' | 'neutral'> = {
    match: 'positive',
    mismatch: 'warning',
    unmeasurable: 'neutral',
};

const statusBadgeColor: Record<CitizenReportStatus, 'positive' | 'neutral'> = {
    active: 'positive',
    superseded: 'neutral',
    not_counted: 'neutral',
};

const statusHint = computed(() => {
    if (props.item.status === 'superseded') {
        return text.statusHints.superseded;
    }
    if (props.item.status === 'not_counted') {
        return text.statusHints.notCounted;
    }
    return props.item.kind === 'mismatch' ? text.statusHints.activeMismatch : text.statusHints.activeOther;
});

const threshold = computed(() => Math.max(1, props.item.mismatch_count.threshold));
const current = computed(() => Math.max(0, props.item.mismatch_count.current));
const thresholdReached = computed(() => current.value >= threshold.value);
const segments = computed(() => Array.from({ length: threshold.value }, (_, index) => index + 1));

const villageLabel = computed(() => {
    const name = props.item.project.village_name;
    return name.startsWith('Desa ') ? name : `Desa ${name}`;
});

const inReviewHeadline = computed(() => {
    return props.item.bpd_review.in_review_since
        ? publicStatus.in_review(props.item.bpd_review.in_review_since)
        : 'Dalam tinjauan BPD';
});

const reviewedHeadline = computed(() => {
    const outcome = props.item.bpd_review.outcome;
    return outcome ? publicStatus.reviewed(outcome as ReviewOutcome) : 'Ditinjau BPD';
});
</script>

<template>
    <Card class="p-4 sm:p-6">
        <article class="divide-y divide-stroke">
            <!-- Header proyek & tanggal lapor (WITA) -->
            <header class="flex flex-col gap-2.5 pb-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex min-w-0 items-start gap-3">
                    <span
                        class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-subtle text-primary sm:flex"
                        aria-hidden="true"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                            />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <Link
                            :href="item.urls.project"
                            class="inline-block text-base font-semibold text-content transition-colors hover:text-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary sm:text-lg"
                        >
                            {{ item.project.name }}
                        </Link>
                        <p class="mt-0.5 text-sm text-content-secondary">
                            {{ villageLabel }} &middot; TA {{ item.project.year }}
                        </p>
                    </div>
                </div>

                <p
                    v-if="item.created_at"
                    class="inline-flex shrink-0 items-center gap-1.5 text-xs font-medium text-content-secondary tabular-nums sm:text-sm"
                >
                    <svg
                        class="h-4 w-4 shrink-0 text-content-tertiary"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                        aria-hidden="true"
                    >
                        <circle cx="12" cy="12" r="9" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 7v5l3 2" />
                    </svg>
                    <span>{{ text.card.reportedAt }} {{ formatDateTime(item.created_at) }}</span>
                </p>
            </header>

            <!-- Jenis, status netral laporan & catatan pribadi -->
            <div class="space-y-3 py-4">
                <div class="flex flex-wrap items-center gap-2">
                    <Badge :color="kindBadgeColor[item.kind]">
                        <svg
                            v-if="item.kind === 'match'"
                            class="h-3.5 w-3.5 shrink-0"
                            viewBox="0 0 16 16"
                            fill="none"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 8l3.5 3.5L13 4"
                            />
                        </svg>
                        <svg
                            v-else-if="item.kind === 'mismatch'"
                            class="h-3.5 w-3.5 shrink-0"
                            viewBox="0 0 16 16"
                            fill="none"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M8 2.5L1.5 13.5h13L8 2.5zm0 4v3m0 2h.01"
                            />
                        </svg>
                        <svg
                            v-else
                            class="h-3.5 w-3.5 shrink-0"
                            viewBox="0 0 16 16"
                            fill="none"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-width="2" d="M4 8h8" />
                        </svg>
                        <span>{{ reportType[item.kind] }}</span>
                    </Badge>

                    <Badge :color="statusBadgeColor[item.status]">
                        <svg
                            v-if="item.status === 'active'"
                            class="h-3.5 w-3.5 shrink-0"
                            viewBox="0 0 16 16"
                            fill="none"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <circle cx="8" cy="8" r="6" stroke-width="1.6" />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M5.5 8l1.8 1.8L10.8 6"
                            />
                        </svg>
                        <svg
                            v-else-if="item.status === 'superseded'"
                            class="h-3.5 w-3.5 shrink-0"
                            viewBox="0 0 16 16"
                            fill="none"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.6"
                                d="M13 7a5 5 0 00-9-2m-1 2v-3h3M3 9a5 5 0 009 2m1-2v3h-3"
                            />
                        </svg>
                        <svg
                            v-else
                            class="h-3.5 w-3.5 shrink-0"
                            viewBox="0 0 16 16"
                            fill="none"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <circle cx="8" cy="8" r="6" stroke-width="1.6" />
                            <path stroke-linecap="round" stroke-width="1.8" d="M5.5 8h5" />
                        </svg>
                        <span>{{ text.status[item.status] }}</span>
                    </Badge>
                </div>

                <p class="text-sm leading-relaxed text-content-secondary">
                    {{ statusHint }}
                </p>

                <!-- Catatan pribadi pelapor (tanpa kartu bersarang; pakai garis aksen kiri) -->
                <div v-if="item.note" class="border-l-2 border-stroke-strong pl-3">
                    <p class="text-xs font-medium text-content-secondary">{{ text.card.yourNote }}</p>
                    <p class="mt-0.5 text-sm leading-relaxed text-content">{{ item.note }}</p>
                </div>
            </div>

            <!-- Progres ambang proyek (x dari 3 warga) -->
            <div class="space-y-2.5 py-4">
                <div class="flex items-baseline justify-between gap-2">
                    <span class="text-sm font-medium text-content-secondary">
                        {{ text.card.thresholdTitle }}
                    </span>
                    <span
                        class="shrink-0 text-sm font-bold tabular-nums"
                        :class="thresholdReached || item.bpd_review.status !== 'none' ? 'text-info' : 'text-content'"
                    >
                        {{ text.card.thresholdFraction(current, threshold) }}
                    </span>
                </div>
                <div
                    class="grid gap-1.5"
                    :style="{ gridTemplateColumns: `repeat(${threshold}, minmax(0, 1fr))` }"
                    aria-hidden="true"
                >
                    <div
                        v-for="stepNumber in segments"
                        :key="stepNumber"
                        class="h-2 rounded-full transition-colors"
                        :class="
                            stepNumber <= current
                                ? thresholdReached || item.bpd_review.status !== 'none'
                                    ? 'bg-info-solid'
                                    : 'bg-warning-solid'
                                : 'bg-surface-muted'
                        "
                    />
                </div>
            </div>

            <!-- Status tindak lanjut BPD (sinkron M5) -->
            <div class="py-4">
                <Alert v-if="item.bpd_review.status === 'in_review'" color="warning" :title="inReviewHeadline">
                    {{ text.card.bpdInReviewBody }}
                </Alert>

                <div
                    v-else-if="item.bpd_review.status === 'reviewed'"
                    class="rounded-lg border border-info-border bg-info-subtle p-4 text-info"
                    role="status"
                >
                    <div class="flex gap-3">
                        <span class="mt-0.5 shrink-0" aria-hidden="true">
                            <svg
                                class="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z"
                                />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.5 12l2.5 2.5 4.5-5" />
                            </svg>
                        </span>

                        <div class="min-w-0 flex-1 space-y-2">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <p class="text-sm font-semibold sm:text-base">{{ reviewedHeadline }}</p>
                                <span v-if="item.bpd_review.reviewed_at" class="text-xs font-medium tabular-nums">
                                    {{ formatDate(item.bpd_review.reviewed_at) }}
                                </span>
                            </div>

                            <p v-if="item.bpd_review.note" class="text-sm leading-relaxed">
                                {{ item.bpd_review.note }}
                            </p>

                            <div v-if="item.bpd_review.notes.length > 0" class="border-t border-info-border/60 pt-2">
                                <p class="text-xs font-semibold">{{ text.card.bpdFollowUpTitle }}</p>
                                <ul class="mt-1.5 divide-y divide-info-border/40">
                                    <li
                                        v-for="(followUp, idx) in item.bpd_review.notes"
                                        :key="idx"
                                        class="py-2 text-sm text-content first:pt-0 last:pb-0"
                                    >
                                        <p class="leading-relaxed text-info">{{ followUp.note }}</p>
                                        <p v-if="followUp.created_at" class="mt-0.5 text-xs text-info/80 tabular-nums">
                                            {{ formatDate(followUp.created_at) }}
                                        </p>
                                    </li>
                                </ul>
                            </div>

                            <p v-if="item.bpd_review.submitted_after_review" class="text-xs leading-relaxed text-info">
                                {{ text.card.bpdAfterReviewHint }}
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="flex items-start gap-3 rounded-lg border border-stroke bg-surface-subtle p-4 text-content-secondary"
                    role="status"
                >
                    <svg
                        class="mt-0.5 h-5 w-5 shrink-0 text-content-tertiary"
                        viewBox="0 0 20 20"
                        fill="none"
                        stroke="currentColor"
                        aria-hidden="true"
                    >
                        <circle cx="10" cy="10" r="8" stroke-width="1.5" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10 9v5m0-7.5h.01" />
                    </svg>
                    <p class="text-sm leading-relaxed">
                        {{ text.card.bpdWaiting }}
                    </p>
                </div>
            </div>

            <!-- Aksi proyek -->
            <footer class="flex flex-col gap-2 pt-4 sm:flex-row sm:items-center">
                <Button :href="item.urls.project" variant="secondary" size="md" class="w-full sm:w-auto">
                    {{ text.card.viewProject }}
                </Button>
                <Button :href="item.urls.check" variant="ghost" size="md" class="w-full sm:w-auto">
                    {{ text.card.sendNew }}
                </Button>
            </footer>
        </article>
    </Card>
</template>
