<script setup lang="ts">
// ProjectReviewsFab: Tombol sticky/FAB komentar warga & BPD (IDDS style).
// Menampilkan ulasan/laporan warga yang diterbitkan BPD serta catatan resmi BPD.
// Badge merah di kiri-atas tombol menampilkan jumlah ulasan & catatan yang terbit.

import { computed, onMounted, onUnmounted, ref } from 'vue';
import { formatDate } from '@/lib/format';
import { projectReviews, reportType, reviewOutcome, type ReviewOutcome } from '@/lib/labels';
import type { ProjectDetail } from '@/types/project';

interface Props {
    project: ProjectDetail;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'check-spec'): void;
}>();

const isOpen = ref(false);
type TabType = 'all' | 'bpd' | 'reports';
const activeTab = ref<TabType>('all');

const bpdNotesCount = computed(() => {
    let count = 0;
    if (props.project.review?.note) count++;
    if (props.project.review?.notes) count += props.project.review.notes.length;
    return count;
});

const publishedReportsCount = computed(() => {
    return props.project.published_reports?.length ?? 0;
});

const totalCount = computed(() => {
    return bpdNotesCount.value + publishedReportsCount.value;
});

const ariaLabel = computed(() => {
    return projectReviews.fabAriaLabel(totalCount.value);
});

const reviewedOutcomeLabel = computed(() => {
    const outcome = props.project.review?.outcome;
    if (!outcome) return null;
    return reviewOutcome[outcome as ReviewOutcome] ?? outcome;
});

function handleCheckSpecClick(): void {
    isOpen.value = false;
    emit('check-spec');
}

function onKeydown(e: KeyboardEvent): void {
    if (e.key === 'Escape' && isOpen.value) {
        isOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div>
        <!-- Sticky Floating Action Button (FAB) -->
        <div class="fixed right-4 bottom-20 z-40 sm:right-6 sm:bottom-24 lg:right-8 lg:bottom-8">
            <button
                type="button"
                class="relative flex h-14 w-14 items-center justify-center rounded-full bg-primary text-content-inverse shadow-sm transition-transform duration-150 hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary active:scale-95"
                :aria-label="ariaLabel"
                @click="isOpen = true"
            >
                <!-- Red Notification Badge on Top-Left (sesuai acuan desain) -->
                <span
                    v-if="totalCount > 0"
                    class="absolute -top-1.5 -left-1.5 flex h-6 min-w-6 items-center justify-center rounded-full bg-negative-solid px-1.5 text-xs font-bold text-content-inverse tabular-nums ring-2 ring-surface"
                >
                    {{ totalCount > 99 ? '99+' : totalCount }}
                </span>

                <!-- Chat Bubble Icon with 2 Lines Inside (sesuai acuan ikon komentar) -->
                <svg
                    class="h-6 w-6"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                    <line x1="8" y1="9" x2="16" y2="9" />
                    <line x1="8" y1="13" x2="13" y2="13" />
                </svg>
            </button>
        </div>

        <!-- Teleported Modal Dialog -->
        <Teleport to="body">
            <!-- Backdrop Overlay -->
            <div
                v-if="isOpen"
                class="fixed inset-0 z-50 bg-content/50 backdrop-blur-xs transition-opacity"
                aria-hidden="true"
                @click="isOpen = false"
            />

            <!-- Modal Window -->
            <div
                v-if="isOpen"
                role="dialog"
                aria-modal="true"
                :aria-label="projectReviews.dialogTitle"
                class="fixed top-1/2 left-1/2 z-50 flex max-h-[88vh] w-full max-w-lg -translate-x-1/2 -translate-y-1/2 flex-col rounded-lg border border-stroke bg-surface shadow-sm"
            >
                <!-- Header -->
                <div class="flex items-start justify-between border-b border-stroke px-5 py-4 sm:px-6">
                    <div class="min-w-0 pr-3">
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-semibold text-content sm:text-lg">
                                {{ projectReviews.dialogTitle }}
                            </h2>
                            <span
                                v-if="totalCount > 0"
                                class="inline-flex items-center rounded-full bg-primary-subtle px-2 py-0.5 text-xs font-semibold text-primary tabular-nums"
                            >
                                {{ totalCount }}
                            </span>
                        </div>
                        <p class="mt-0.5 text-xs text-content-tertiary">
                            {{ projectReviews.dialogSubtitle }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md p-1.5 text-content-secondary transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                        :aria-label="projectReviews.close"
                        @click="isOpen = false"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>

                <!-- Segmented Tabs (IDDS Filter Pills) -->
                <div class="border-b border-stroke bg-surface-subtle px-5 py-2.5 sm:px-6">
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded-full px-3 py-1.5 text-xs font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                            :class="
                                activeTab === 'all'
                                    ? 'bg-primary text-content-inverse'
                                    : 'bg-surface text-content-secondary hover:bg-surface-muted hover:text-content'
                            "
                            @click="activeTab = 'all'"
                        >
                            {{ projectReviews.tabs.all }} ({{ totalCount }})
                        </button>
                        <button
                            type="button"
                            class="rounded-full px-3 py-1.5 text-xs font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                            :class="
                                activeTab === 'bpd'
                                    ? 'bg-primary text-content-inverse'
                                    : 'bg-surface text-content-secondary hover:bg-surface-muted hover:text-content'
                            "
                            @click="activeTab = 'bpd'"
                        >
                            {{ projectReviews.tabs.bpd }} ({{ bpdNotesCount }})
                        </button>
                        <button
                            type="button"
                            class="rounded-full px-3 py-1.5 text-xs font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                            :class="
                                activeTab === 'reports'
                                    ? 'bg-primary text-content-inverse'
                                    : 'bg-surface text-content-secondary hover:bg-surface-muted hover:text-content'
                            "
                            @click="activeTab = 'reports'"
                        >
                            {{ projectReviews.tabs.reports }} ({{ publishedReportsCount }})
                        </button>
                    </div>
                </div>

                <!-- Scrollable Body Content -->
                <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4 sm:px-6">
                    <!-- 1. Catatan BPD Section -->
                    <div v-if="(activeTab === 'all' || activeTab === 'bpd') && bpdNotesCount > 0" class="space-y-3">
                        <div class="flex items-center gap-2">
                            <span
                                class="flex h-5 w-5 items-center justify-center rounded-full bg-info-subtle text-info"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                                    />
                                </svg>
                            </span>
                            <h3 class="text-xs font-semibold tracking-wider text-content-tertiary uppercase">
                                {{ projectReviews.bpdSection.title }}
                            </h3>
                        </div>

                        <!-- Main Decision Note -->
                        <div
                            v-if="project.review?.note"
                            class="space-y-2.5 rounded-lg border border-info-border bg-info-subtle p-4"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full bg-surface px-2.5 py-0.5 text-xs font-semibold text-info shadow-xs"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M5 13l4 4L19 7"
                                        />
                                    </svg>
                                    {{
                                        reviewedOutcomeLabel
                                            ? `Hasil: ${reviewedOutcomeLabel}`
                                            : projectReviews.bpdSection.verifiedBadge
                                    }}
                                </span>
                                <span v-if="project.review.reviewed_at" class="text-xs text-content-tertiary">
                                    {{ formatDate(project.review.reviewed_at) }}
                                </span>
                            </div>
                            <p class="text-sm leading-relaxed text-content">
                                {{ project.review.note }}
                            </p>
                            <div class="pt-1 text-xs font-medium text-info">
                                &mdash; {{ projectReviews.bpdSection.officialLabel }}
                            </div>
                        </div>

                        <!-- Append-Only Follow-Up Notes -->
                        <div v-if="project.review?.notes && project.review.notes.length > 0" class="space-y-2 pt-1">
                            <div
                                v-for="(noteItem, idx) in project.review.notes"
                                :key="idx"
                                class="space-y-1.5 rounded-lg border border-stroke bg-surface-subtle p-3.5"
                            >
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-semibold text-content">
                                        {{ projectReviews.bpdSection.followUpTitle }} #{{ idx + 1 }}
                                    </span>
                                    <span class="text-content-tertiary">
                                        {{ formatDate(noteItem.created_at) }}
                                    </span>
                                </div>
                                <p class="text-sm leading-relaxed text-content-secondary">
                                    {{ noteItem.note }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Ulasan Warga Diterbitkan Section -->
                    <div
                        v-if="(activeTab === 'all' || activeTab === 'reports') && publishedReportsCount > 0"
                        class="space-y-3"
                    >
                        <div class="flex items-center gap-2">
                            <span
                                class="flex h-5 w-5 items-center justify-center rounded-full bg-primary-subtle text-primary"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                    />
                                </svg>
                            </span>
                            <h3 class="text-xs font-semibold tracking-wider text-content-tertiary uppercase">
                                {{ projectReviews.citizenSection.title }}
                            </h3>
                        </div>

                        <!-- List of Published Citizen Reports -->
                        <div
                            v-for="(report, idx) in project.published_reports"
                            :key="report.id"
                            class="space-y-2.5 rounded-lg border border-stroke bg-surface p-4 transition-colors hover:border-stroke-strong/30"
                        >
                            <!-- Reporter Header & Kind Badge -->
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-content">
                                        {{
                                            report.reporter_label ||
                                            projectReviews.citizenSection.anonymousReporter(idx + 1)
                                        }}
                                    </p>
                                    <p class="text-xs text-content-tertiary">
                                        {{ formatDate(report.created_at) }}
                                    </p>
                                </div>

                                <!-- Kind Badge -->
                                <span
                                    v-if="report.kind === 'match'"
                                    class="inline-flex items-center gap-1 rounded-full border border-positive-border bg-positive-subtle px-2.5 py-0.5 text-xs font-medium text-positive"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M5 13l4 4L19 7"
                                        />
                                    </svg>
                                    {{ reportType.match }}
                                </span>
                                <span
                                    v-else-if="report.kind === 'mismatch'"
                                    class="inline-flex items-center gap-1 rounded-full border border-negative-border bg-negative-subtle px-2.5 py-0.5 text-xs font-medium text-negative"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                        />
                                    </svg>
                                    {{ reportType.mismatch }}
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1 rounded-full border border-stroke bg-surface-muted px-2.5 py-0.5 text-xs font-medium text-content-secondary"
                                >
                                    {{ reportType.unmeasurable }}
                                </span>
                            </div>

                            <!-- Citizen Note Text -->
                            <p v-if="report.note" class="text-sm leading-relaxed text-content-secondary">
                                "{{ report.note }}"
                            </p>

                            <!-- Measurements Checked (if any) -->
                            <div
                                v-if="report.answers && report.answers.length > 0"
                                class="space-y-1.5 rounded-md border border-stroke bg-surface-subtle p-2.5 text-xs"
                            >
                                <p class="font-medium text-content">Pemeriksaan fisik:</p>
                                <div
                                    v-for="ans in report.answers"
                                    :key="ans.id"
                                    class="flex items-center justify-between text-content-secondary"
                                >
                                    <span>{{ ans.measurement_name || 'Ukuran' }}</span>
                                    <span v-if="ans.measured_value" class="font-semibold text-content tabular-nums">
                                        {{ ans.measured_value }} {{ ans.measured_unit || '' }}
                                    </span>
                                    <span v-else class="font-medium text-content-tertiary">
                                        {{
                                            ans.answer === 'match'
                                                ? 'Sesuai'
                                                : ans.answer === 'mismatch'
                                                  ? 'Ada selisih'
                                                  : 'Tidak diukur'
                                        }}
                                    </span>
                                </div>
                            </div>

                            <!-- Verification Badge -->
                            <div class="flex items-center gap-1 pt-1 text-xs text-content-tertiary">
                                <svg
                                    class="h-3.5 w-3.5 text-positive"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M5 13l4 4L19 7"
                                    />
                                </svg>
                                <span>{{ projectReviews.citizenSection.verifiedBadge }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Empty State (When no items available) -->
                    <div
                        v-if="
                            (activeTab === 'all' && totalCount === 0) ||
                            (activeTab === 'bpd' && bpdNotesCount === 0) ||
                            (activeTab === 'reports' && publishedReportsCount === 0)
                        "
                        class="space-y-3 rounded-lg border border-dashed border-stroke p-6 text-center"
                    >
                        <div
                            class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-primary-subtle text-primary"
                        >
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                                />
                            </svg>
                        </div>
                        <div class="space-y-1">
                            <h4 class="text-sm font-semibold text-content">
                                {{ projectReviews.empty.title }}
                            </h4>
                            <p class="mx-auto max-w-sm text-xs leading-relaxed text-content-secondary">
                                {{ projectReviews.empty.body }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex h-9 items-center justify-center gap-1.5 rounded-md bg-primary px-3 text-xs font-semibold text-content-inverse hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                            @click="handleCheckSpecClick"
                        >
                            {{ projectReviews.empty.action }}
                        </button>
                    </div>
                </div>

                <!-- Footer -->
                <div class="border-t border-stroke bg-surface px-5 py-3.5 sm:px-6">
                    <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs text-content-tertiary">
                            {{ projectReviews.privacyNotice }}
                        </p>
                        <button
                            type="button"
                            class="h-9 rounded-md border border-stroke bg-surface px-4 text-xs font-medium text-content hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                            @click="isOpen = false"
                        >
                            {{ projectReviews.close }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
