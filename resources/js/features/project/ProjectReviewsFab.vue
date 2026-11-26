<script setup lang="ts">
// ProjectReviewsFab: bottom sheet (mobile) / dialog (desktop) with the BPD review notes and the citizen
// reports BPD published. Photos are loaded from reviewed citizen reports via the server endpoint.

import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import Badge from '@/components/ui/Badge.vue';
import bpdEmpty from '@/components/ui/illustrations/bpd-empty.svg';
import notFound from '@/components/ui/illustrations/not-found.svg';
import plangEmpty from '@/components/ui/illustrations/plang-empty.svg';
import searchEmpty from '@/components/ui/illustrations/search-empty.svg';
import { formatDate } from '@/lib/format';
import { projectReviews, reportType, reviewOutcome, type ReviewOutcome } from '@/lib/labels';
import type { ProjectDetail, ProjectPublishedReport, ProjectPublishedReportPhoto } from '@/types/project';
import ReviewPhotoGrid from './ReviewPhotoGrid.vue';

interface Props {
    project: ProjectDetail;
    hasError?: boolean;
    canCheckSpec?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    hasError: false,
    canCheckSpec: true,
});

const emit = defineEmits<{
    (e: 'check-spec'): void;
    (e: 'retry'): void;
}>();

type TabType = 'all' | 'bpd' | 'reports';
type ReportKind = ProjectPublishedReport['kind'];

interface ActivePhoto {
    url: string;
    title: string;
    subtitle: string;
}

const isOpen = ref(false);
const isFullscreen = ref(false);
const activeTab = ref<TabType>('all');
const activePhoto = ref<ActivePhoto | null>(null);

function openPhoto(report: ProjectPublishedReport, photo: ProjectPublishedReportPhoto): void {
    activePhoto.value = {
        url: photo.url,
        title: `${report.reporter_label} — Foto ${photo.position}`,
        subtitle: `${reportType[report.kind]} · ${formatDate(report.created_at)}`,
    };
}

function closePhoto(): void {
    activePhoto.value = null;
}

const kindBadgeColor: Record<ReportKind, 'positive' | 'negative' | 'neutral'> = {
    match: 'positive',
    mismatch: 'negative',
    unmeasurable: 'neutral',
};

function openSheet(): void {
    isFullscreen.value = false;
    isOpen.value = true;
}

function closeSheet(): void {
    isOpen.value = false;
    isFullscreen.value = false;
}

function toggleFullscreen(): void {
    isFullscreen.value = !isFullscreen.value;
}

// Touch drag: swipe up for fullscreen, swipe down to shrink or close.
const dragHeight = ref<number | null>(null);
const isDragging = ref(false);
let touchStartY = 0;
let touchCurrentY = 0;
let hasDragged = false;

function handleTouchStart(e: TouchEvent): void {
    const touch = e.touches[0];
    if (touch) {
        touchStartY = touch.clientY;
        touchCurrentY = touch.clientY;
        dragHeight.value = null;
        isDragging.value = true;
        hasDragged = false;
    }
}

function handleTouchMove(e: TouchEvent): void {
    if (!isDragging.value) {
        return;
    }
    const touch = e.touches[0];
    if (!touch) {
        return;
    }
    touchCurrentY = touch.clientY;
    const deltaY = touchCurrentY - touchStartY;

    if (Math.abs(deltaY) > 8) {
        hasDragged = true;
    }

    if (typeof window !== 'undefined') {
        const vh = window.innerHeight;
        const baseHeight = isFullscreen.value ? vh : vh * 0.75;
        // Drag ke atas (deltaY < 0) menambah tinggi; drag ke bawah (deltaY > 0) mengurangi tinggi
        const targetHeight = baseHeight - deltaY;
        dragHeight.value = Math.max(0, Math.min(vh, targetHeight));
    }

    if (e.cancelable) {
        e.preventDefault();
    }
}

function handleTouchEnd(): void {
    if (!isDragging.value) {
        return;
    }
    isDragging.value = false;

    const deltaY = touchCurrentY - touchStartY;
    const threshold = 60;

    if (isFullscreen.value) {
        if (deltaY > threshold) {
            // Drag ke bawah dari layar penuh -> kembali ke ukuran normal
            isFullscreen.value = false;
        }
    } else {
        if (deltaY < -threshold) {
            // Drag ke atas -> buka layar penuh
            isFullscreen.value = true;
        } else if (deltaY > threshold) {
            // Drag ke bawah -> tutup sheet
            closeSheet();
        }
    }

    dragHeight.value = null;
    touchStartY = 0;
    touchCurrentY = 0;
}

function handleHandleClick(): void {
    if (hasDragged) {
        hasDragged = false;
        return;
    }
    toggleFullscreen();
}

const dragStyle = computed(() => {
    if (isDragging.value && dragHeight.value !== null) {
        return {
            height: `${dragHeight.value}px`,
            maxHeight: `${dragHeight.value}px`,
            transition: 'none',
        };
    }
    return undefined;
});

const bpdNotesCount = computed(() => {
    let count = 0;
    if (props.project.review?.note) {
        count++;
    }
    if (props.project.review?.notes) {
        count += props.project.review.notes.length;
    }
    return count;
});

const publishedReportsCount = computed(() => props.project.published_reports?.length ?? 0);

const totalCount = computed(() => bpdNotesCount.value + publishedReportsCount.value);

const ariaLabel = computed(() => projectReviews.fabAriaLabel(totalCount.value));

const bpdName = computed(() => projectReviews.bpdName(props.project.village?.name));

const reviewedOutcomeLabel = computed(() => {
    const outcome = props.project.review?.outcome;
    if (!outcome) {
        return null;
    }
    return reviewOutcome[outcome as ReviewOutcome] ?? outcome;
});

function answerLabel(answer: string): string {
    if (answer === 'match') {
        return projectReviews.answer.match;
    }
    if (answer === 'mismatch') {
        return projectReviews.answer.mismatch;
    }
    return projectReviews.answer.notMeasured;
}

function answerClass(answer: string): string {
    if (answer === 'match') {
        return 'text-positive';
    }
    if (answer === 'mismatch') {
        return 'font-semibold text-negative';
    }
    return 'text-content-secondary';
}

function handleCheckSpecClick(): void {
    isOpen.value = false;
    emit('check-spec');
}

function onKeydown(e: KeyboardEvent): void {
    if (e.key !== 'Escape') {
        return;
    }
    if (activePhoto.value) {
        closePhoto();
    } else if (isOpen.value) {
        closeSheet();
    }
}

watch(isOpen, (open) => {
    if (typeof document === 'undefined') {
        return;
    }
    if (open) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
});

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
});
</script>

<template>
    <div>
        <!-- Sticky Floating Action Button (FAB) - disembunyikan saat sheet terbuka agar tidak tembus pandang -->
        <div v-if="!isOpen" class="fixed right-4 bottom-20 z-40 sm:right-6 sm:bottom-24 lg:right-8 lg:bottom-8">
            <button
                type="button"
                class="relative flex h-14 w-14 items-center justify-center rounded-full bg-primary text-content-inverse shadow-sm transition-transform duration-150 hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary active:scale-95"
                :aria-label="ariaLabel"
                @click="openSheet"
            >
                <span
                    v-if="totalCount > 0"
                    class="absolute -top-1.5 -left-1.5 flex h-6 min-w-6 items-center justify-center rounded-full bg-negative-solid px-1.5 text-xs font-bold text-content-inverse tabular-nums ring-2 ring-surface"
                >
                    {{ totalCount > 99 ? '99+' : totalCount }}
                </span>

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

        <Teleport to="body">
            <!-- Backdrop Overlay -->
            <div
                v-if="isOpen"
                class="fixed inset-0 z-50 bg-content/50 backdrop-blur-xs transition-opacity"
                aria-hidden="true"
                @click="closeSheet"
                @touchmove.prevent
                @wheel.prevent
            />

            <!-- Container: Bottom Sheet di Mobile (<640px), Dialog Modal di Desktop (≥640px) -->
            <div
                v-if="isOpen"
                role="dialog"
                aria-modal="true"
                :aria-label="projectReviews.dialogTitle"
                class="fixed z-50 flex flex-col bg-surface shadow-2xl transition-[height,max-height,border-radius,transform] duration-300 ease-out"
                :class="
                    isFullscreen
                        ? 'inset-x-0 bottom-0 h-[100dvh] max-h-[100dvh] w-full rounded-t-none border-t-0 sm:inset-0 sm:top-0 sm:left-0 sm:h-full sm:max-h-none sm:max-w-none sm:translate-x-0 sm:translate-y-0 sm:rounded-none sm:border-0'
                        : 'inset-x-0 bottom-0 h-[75dvh] max-h-[75dvh] w-full rounded-t-3xl border-t border-stroke sm:inset-auto sm:top-1/2 sm:left-1/2 sm:h-auto sm:max-h-[88vh] sm:max-w-lg sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-2xl sm:border sm:border-stroke sm:shadow-lg'
                "
                :style="dragStyle"
            >
                <!-- Mobile Drag/Swipe Indicator Handle -->
                <div
                    class="flex cursor-grab touch-none justify-center pt-3 pb-1.5 select-none active:cursor-grabbing sm:hidden"
                    role="button"
                    tabindex="0"
                    :aria-label="isFullscreen ? 'Kecilkan sheet' : 'Seret ke atas untuk layar penuh'"
                    @click="handleHandleClick"
                    @touchstart="handleTouchStart"
                    @touchmove="handleTouchMove"
                    @touchend="handleTouchEnd"
                >
                    <div
                        class="h-1.5 rounded-full transition-all duration-300"
                        :class="
                            isFullscreen ? 'w-14 bg-primary/40' : 'w-10 bg-stroke-strong/30 hover:bg-stroke-strong/50'
                        "
                    />
                </div>

                <!-- Header Sheet -->
                <div
                    class="flex items-start justify-between border-b border-stroke px-5 py-3 select-none sm:px-6 sm:py-4"
                    @touchstart="handleTouchStart"
                    @touchmove="handleTouchMove"
                    @touchend="handleTouchEnd"
                    @dblclick="toggleFullscreen"
                >
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
                        class="rounded-full p-1.5 text-content-secondary transition-colors hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                        :aria-label="projectReviews.close"
                        @click="closeSheet"
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

                <!-- Filter Chips -->
                <div class="border-b border-stroke bg-surface-subtle px-5 py-2.5 sm:px-6" @touchmove.stop>
                    <div class="no-scrollbar flex gap-2 overflow-x-auto">
                        <button
                            type="button"
                            class="shrink-0 rounded-full px-3.5 py-1.5 text-xs font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                            :class="
                                activeTab === 'all'
                                    ? 'bg-primary text-content-inverse'
                                    : 'border border-stroke bg-surface text-content-secondary hover:bg-surface-muted hover:text-content'
                            "
                            @click="activeTab = 'all'"
                        >
                            {{ projectReviews.tabs.all }} ({{ totalCount }})
                        </button>
                        <button
                            type="button"
                            class="shrink-0 rounded-full px-3.5 py-1.5 text-xs font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                            :class="
                                activeTab === 'bpd'
                                    ? 'bg-primary text-content-inverse'
                                    : 'border border-stroke bg-surface text-content-secondary hover:bg-surface-muted hover:text-content'
                            "
                            @click="activeTab = 'bpd'"
                        >
                            {{ projectReviews.tabs.bpd }} ({{ bpdNotesCount }})
                        </button>
                        <button
                            type="button"
                            class="shrink-0 rounded-full px-3.5 py-1.5 text-xs font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                            :class="
                                activeTab === 'reports'
                                    ? 'bg-primary text-content-inverse'
                                    : 'border border-stroke bg-surface text-content-secondary hover:bg-surface-muted hover:text-content'
                            "
                            @click="activeTab = 'reports'"
                        >
                            {{ projectReviews.tabs.reports }} ({{ publishedReportsCount }})
                        </button>
                    </div>
                </div>

                <!-- Scrollable Body Content -->
                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-3 sm:px-6 sm:py-4">
                    <!-- Error State (IDDS Fallback Illustration) -->
                    <div v-if="hasError" class="flex h-full flex-col items-center justify-center py-6 text-center">
                        <div class="mb-4 aspect-4/3 w-full max-w-44 sm:max-w-48" aria-hidden="true">
                            <img :src="notFound" alt="" width="280" height="210" class="h-full w-full object-contain" />
                        </div>
                        <div class="space-y-1">
                            <h4 class="text-sm font-semibold text-content sm:text-base">
                                {{ projectReviews.error.title }}
                            </h4>
                            <p class="mx-auto max-w-sm text-xs leading-relaxed text-content-secondary sm:text-sm">
                                {{ projectReviews.error.body }}
                            </p>
                        </div>
                        <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                            <button
                                type="button"
                                class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg bg-primary px-4 text-xs font-semibold text-content-inverse transition-colors hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary active:bg-primary-hover"
                                @click="emit('retry')"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                    />
                                </svg>
                                <span>{{ projectReviews.error.action }}</span>
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-9 items-center justify-center rounded-lg border border-stroke bg-surface px-4 text-xs font-medium text-content hover:bg-surface-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                                @click="closeSheet"
                            >
                                {{ projectReviews.close }}
                            </button>
                        </div>
                    </div>

                    <template v-else>
                        <div class="divide-y divide-stroke">
                            <!-- 1. Catatan Resmi BPD -->
                            <article
                                v-if="(activeTab === 'all' || activeTab === 'bpd') && project.review?.note"
                                class="space-y-3 py-4 first:pt-0"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="relative shrink-0">
                                        <div
                                            class="flex h-10 w-10 items-center justify-center rounded-full bg-info text-warning-solid"
                                            aria-hidden="true"
                                        >
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                                                <path
                                                    d="M12 2L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-3zm0 4a3 3 0 110 6 3 3 0 010-6zm0 14.93c-3.87-1.12-6.8-4.73-6.98-8.93H19c-.2 4.2-3.12 7.81-7 8.93z"
                                                />
                                            </svg>
                                        </div>
                                        <span
                                            class="absolute -right-0.5 -bottom-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-primary text-content-inverse ring-2 ring-surface"
                                            aria-hidden="true"
                                        >
                                            <svg
                                                class="h-2.5 w-2.5"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                                stroke-width="3"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M5 13l4 4L19 7"
                                                />
                                            </svg>
                                        </span>
                                    </div>
                                    <div class="min-w-0">
                                        <h4 class="text-sm leading-tight font-semibold text-content">
                                            {{ bpdName }}
                                        </h4>
                                        <p class="text-xs text-content-tertiary">
                                            {{ projectReviews.bpdSection.officialLabel }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                                    <Badge v-if="reviewedOutcomeLabel" color="info">
                                        {{ projectReviews.outcomePrefix }} {{ reviewedOutcomeLabel }}
                                    </Badge>
                                    <span v-if="project.review.reviewed_at" class="text-xs text-content-tertiary">
                                        {{ formatDate(project.review.reviewed_at) }}
                                    </span>
                                </div>

                                <p class="text-sm leading-relaxed text-content">
                                    {{ project.review.note }}
                                </p>
                            </article>

                            <!-- Catatan Tindak Lanjut BPD -->
                            <article
                                v-for="(noteItem, idx) in project.review?.notes || []"
                                v-show="activeTab === 'all' || activeTab === 'bpd'"
                                :key="`bpd-followup-${idx}`"
                                class="space-y-2 py-4 first:pt-0"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-info-subtle text-xs font-bold text-info"
                                        aria-hidden="true"
                                    >
                                        #{{ idx + 1 }}
                                    </div>
                                    <div>
                                        <h5 class="text-sm font-semibold text-content">
                                            {{ projectReviews.bpdSection.followUpTitle }} #{{ idx + 1 }}
                                        </h5>
                                        <p class="text-xs text-content-tertiary">
                                            {{ formatDate(noteItem.created_at) }}
                                        </p>
                                    </div>
                                </div>
                                <p class="pl-12 text-sm leading-relaxed text-content-secondary">
                                    {{ noteItem.note }}
                                </p>
                            </article>

                            <!-- 2. Laporan Warga yang diterbitkan BPD -->
                            <article
                                v-for="(report, idx) in project.published_reports || []"
                                v-show="activeTab === 'all' || activeTab === 'reports'"
                                :key="report.id"
                                class="space-y-3 py-4 first:pt-0"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-surface-muted text-content-secondary"
                                        aria-hidden="true"
                                    >
                                        <svg
                                            class="h-5 w-5"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="1.8"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.118a7.5 7.5 0 0115 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.5-1.632z"
                                            />
                                        </svg>
                                    </div>
                                    <div class="min-w-0">
                                        <h4 class="text-sm leading-tight font-semibold text-content">
                                            {{
                                                report.reporter_label ||
                                                projectReviews.citizenSection.anonymousReporter(idx + 1)
                                            }}
                                        </h4>
                                        <p class="text-xs text-content-tertiary">
                                            {{ projectReviews.citizenSection.meta }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                                    <Badge :color="kindBadgeColor[report.kind]">
                                        {{ reportType[report.kind] }}
                                    </Badge>
                                    <span class="text-xs text-content-tertiary">
                                        {{ formatDate(report.created_at) }}
                                    </span>
                                </div>

                                <p v-if="report.note" class="text-sm leading-relaxed text-content">
                                    {{ report.note }}
                                </p>

                                <ReviewPhotoGrid
                                    v-if="report.photos && report.photos.length > 0"
                                    :photos="report.photos"
                                    :reporter-label="report.reporter_label"
                                    @open="(photo) => openPhoto(report, photo)"
                                />

                                <!-- Hasil ukur per spesifikasi -->
                                <ul v-if="report.answers && report.answers.length > 0" class="flex flex-wrap gap-1.5">
                                    <li
                                        v-for="ans in report.answers"
                                        :key="ans.id"
                                        class="inline-flex flex-wrap items-center gap-1.5 rounded-md border border-stroke bg-surface-subtle px-2.5 py-1 text-sm text-content-secondary"
                                    >
                                        <span class="font-medium text-content">
                                            {{ ans.measurement_name || projectReviews.answer.fallbackName }}:
                                        </span>
                                        <span v-if="ans.measured_value" class="font-semibold text-content tabular-nums">
                                            {{ ans.measured_value }} {{ ans.measured_unit || '' }}
                                        </span>
                                        <span :class="answerClass(ans.answer)">({{ answerLabel(ans.answer) }})</span>
                                    </li>
                                </ul>
                            </article>
                        </div>

                        <!-- 3. Empty State dengan Ilustrasi IDDS -->
                        <div
                            v-if="
                                (activeTab === 'all' && totalCount === 0) ||
                                (activeTab === 'bpd' && bpdNotesCount === 0) ||
                                (activeTab === 'reports' && publishedReportsCount === 0)
                            "
                            class="flex h-full flex-col items-center justify-center py-6 text-center select-none"
                            @touchstart="handleTouchStart"
                            @touchmove="handleTouchMove"
                            @touchend="handleTouchEnd"
                        >
                            <div class="mb-3 aspect-4/3 w-full max-w-36 sm:max-w-44" aria-hidden="true">
                                <img
                                    :src="
                                        activeTab === 'bpd'
                                            ? bpdEmpty
                                            : activeTab === 'reports'
                                              ? searchEmpty
                                              : plangEmpty
                                    "
                                    alt=""
                                    width="280"
                                    height="210"
                                    class="h-full w-full object-contain"
                                />
                            </div>
                            <div class="space-y-1">
                                <h4 class="text-sm font-semibold text-content sm:text-base">
                                    {{
                                        activeTab === 'bpd'
                                            ? projectReviews.emptyBpd.title
                                            : activeTab === 'reports'
                                              ? projectReviews.emptyReports.title
                                              : projectReviews.empty.title
                                    }}
                                </h4>
                                <p class="mx-auto max-w-sm text-xs leading-relaxed text-content-secondary sm:text-sm">
                                    {{
                                        activeTab === 'bpd'
                                            ? projectReviews.emptyBpd.body
                                            : activeTab === 'reports'
                                              ? projectReviews.emptyReports.body
                                              : projectReviews.empty.body
                                    }}
                                </p>
                            </div>
                            <button
                                v-if="canCheckSpec"
                                type="button"
                                class="mt-4 inline-flex h-9 items-center justify-center gap-1.5 rounded-lg bg-primary px-4 text-xs font-semibold text-content-inverse transition-colors hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary active:bg-primary-hover"
                                @click="handleCheckSpecClick"
                                @touchstart.stop
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M3.5 15.5 15.5 3.5l5 5-12 12zM7.5 11.5l2 2M10.5 8.5l2 2M13.5 5.5l2 2"
                                    />
                                </svg>
                                <span>
                                    {{
                                        activeTab === 'bpd'
                                            ? projectReviews.emptyBpd.action
                                            : activeTab === 'reports'
                                              ? projectReviews.emptyReports.action
                                              : projectReviews.empty.action
                                    }}
                                </span>
                            </button>
                        </div>
                    </template>
                </div>

                <!-- Floating CTA di Mode Layar Penuh -->
                <div
                    v-if="canCheckSpec && isFullscreen && totalCount > 0"
                    class="sticky bottom-0 z-10 border-t border-stroke bg-surface px-5 py-2.5 sm:px-6"
                    @touchmove.stop
                >
                    <button
                        type="button"
                        class="flex w-full items-center justify-center gap-2 rounded-full bg-primary px-4 py-2.5 text-xs font-semibold text-content-inverse shadow-sm transition-transform hover:bg-primary-hover active:scale-98"
                        @click="handleCheckSpecClick"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"
                            />
                        </svg>
                        <span>Tulis Ulasan & Cek Spek</span>
                    </button>
                </div>

                <!-- Footer Tetap di Bawah (Privacy Notice) -->
                <div
                    class="shrink-0 px-5 pt-1.5 pb-3 text-center select-none sm:px-6 sm:pb-3.5"
                    @touchmove.stop
                    @touchstart="handleTouchStart"
                    @touchmove="handleTouchMove"
                    @touchend="handleTouchEnd"
                >
                    <p class="text-xs text-content-tertiary">
                        {{ projectReviews.privacyNotice }}
                    </p>
                </div>
            </div>

            <!-- Lightbox foto lapangan -->
            <div
                v-if="activePhoto"
                class="fixed inset-0 z-60 flex items-center justify-center bg-black/80 p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
                :aria-label="activePhoto.title"
                @click.self="closePhoto"
                @touchmove.prevent
                @wheel.prevent
            >
                <div class="relative w-full max-w-lg overflow-hidden rounded-lg bg-surface shadow-sm">
                    <div class="flex items-start justify-between gap-3 border-b border-stroke px-4 py-3">
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-content">{{ activePhoto.title }}</h3>
                            <p class="text-sm text-content-secondary">{{ activePhoto.subtitle }}</p>
                        </div>
                        <button
                            type="button"
                            class="rounded-full p-1.5 text-content-secondary hover:bg-surface-muted hover:text-content"
                            :aria-label="projectReviews.photos.close"
                            @click="closePhoto"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>

                    <div class="flex max-h-[75vh] items-center justify-center bg-black p-2">
                        <img
                            :src="activePhoto.url"
                            :alt="activePhoto.title"
                            class="max-h-[70vh] w-auto max-w-full object-contain"
                        />
                    </div>

                    <p class="bg-surface-subtle px-4 py-3 text-xs text-content-secondary">
                        {{ projectReviews.photos.viewerCaption }}
                    </p>
                </div>
            </div>
        </Teleport>
    </div>
</template>
