<script setup lang="ts">
// Konfirmasi Cek Spek (PRD Fitur Inti 5.1 step 9, C9). Jenis laporan dan "x dari 3" dihitung server.

import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import successSeal from '@/components/ui/illustrations/success-seal.svg';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { reportSubmitted as text, reportType } from '@/lib/labels';
import type { ReportKind, SubmittedReportPage } from '@/types/report';

const props = defineProps<{ submitted: SubmittedReportPage }>();

const kindBadgeColor: Record<ReportKind, 'positive' | 'warning' | 'neutral'> = {
    match: 'positive',
    mismatch: 'warning',
    unmeasurable: 'neutral',
};

const threshold = computed(() => Math.max(1, props.submitted.mismatch_count.threshold));
const current = computed(() => Math.max(0, props.submitted.mismatch_count.current));
const thresholdReached = computed(() => current.value >= threshold.value);
const segments = computed(() => Array.from({ length: threshold.value }, (_, index) => index + 1));
</script>

<template>
    <PublicLayout>
        <Head :title="`${text.title} · ${submitted.project.name}`" />

        <div class="space-y-5">
            <!-- Hero Konfirmasi -->
            <header class="flex flex-col items-center pt-2 text-center">
                <div class="mb-3 aspect-4/3 w-full max-w-44 sm:max-w-48" aria-hidden="true">
                    <img :src="successSeal" alt="" width="280" height="210" class="h-full w-full object-contain" />
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-content">{{ text.title }}</h1>
                <p class="mt-1 text-sm font-medium text-content-secondary sm:text-base">
                    {{ submitted.project.name }} &middot; {{ submitted.project.village_name }}
                </p>
            </header>

            <!-- Jaminan Privasi -->
            <Alert color="positive">{{ text.body }}</Alert>

            <!-- Kartu Ringkasan Laporan & Ambang Tinjauan BPD -->
            <section
                class="divide-y divide-stroke rounded-2xl border border-stroke bg-surface p-4 shadow-2xs sm:p-5"
                aria-live="polite"
            >
                <div class="flex items-center justify-between gap-3 pb-3.5">
                    <span class="text-sm font-medium text-content-secondary">{{ text.kindLabel }}</span>
                    <Badge :color="kindBadgeColor[submitted.report.kind]">
                        {{ reportType[submitted.report.kind] }}
                    </Badge>
                </div>

                <div class="space-y-2.5 py-3.5">
                    <div class="flex items-baseline justify-between gap-2">
                        <p class="text-sm font-semibold text-content">{{ text.mismatchTitle }}</p>
                        <span
                            class="shrink-0 text-sm font-bold tabular-nums"
                            :class="thresholdReached ? 'text-info' : 'text-content'"
                        >
                            {{ text.mismatchFraction(current, threshold) }}
                        </span>
                    </div>

                    <!-- Progress bar segmen ambang pelapor -->
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
                                    ? thresholdReached
                                        ? 'bg-info-solid'
                                        : 'bg-warning-solid'
                                    : 'bg-surface-muted'
                            "
                        />
                    </div>

                    <p
                        class="text-xs leading-relaxed sm:text-sm"
                        :class="thresholdReached ? 'font-medium text-info' : 'text-content-secondary'"
                    >
                        {{ thresholdReached ? text.thresholdReached : text.mismatchHint(threshold) }}
                    </p>
                </div>

                <div class="flex items-start gap-2 pt-3.5 text-xs leading-relaxed text-content-secondary">
                    <svg
                        class="mt-0.5 h-4 w-4 shrink-0 text-content-tertiary"
                        viewBox="0 0 20 20"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        aria-hidden="true"
                    >
                        <circle cx="10" cy="10" r="8" />
                        <path stroke-linecap="round" d="M10 9v5m0-8h.01" />
                    </svg>
                    <p>{{ text.replaceHint }}</p>
                </div>
            </section>

            <!-- Aksi Lanjutan (Full-width stack agar rapi di mobile) -->
            <div class="space-y-2.5 pt-1">
                <Button variant="primary" :href="submitted.urls.project" class="w-full rounded-xl">
                    {{ text.backToProject }}
                </Button>
                <Button
                    variant="secondary"
                    :href="submitted.urls.my_reports ?? '/laporan-saya'"
                    class="w-full rounded-xl"
                >
                    {{ text.viewMyReports }}
                </Button>
                <Button variant="ghost" :href="submitted.urls.check" class="w-full rounded-xl">
                    {{ text.sendAgain }}
                </Button>
            </div>
        </div>
    </PublicLayout>
</template>
