<script setup lang="ts">
// Konfirmasi Cek Spek (PRD Fitur Inti 5.1 step 9, C9). Jenis laporan dan "x dari 3" dihitung server.

import { Head } from '@inertiajs/vue3';

import Alert from '@/components/ui/Alert.vue';
import Badge from '@/components/ui/Badge.vue';
import Button from '@/components/ui/Button.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { reportSubmitted as text, reportType } from '@/lib/labels';
import type { SubmittedReportPage } from '@/types/report';

defineProps<{ submitted: SubmittedReportPage }>();
</script>

<template>
    <PublicLayout>
        <Head :title="`${text.title} · ${submitted.project.name}`" />

        <div class="space-y-5">
            <header>
                <h1 class="text-2xl font-bold text-content">{{ text.title }}</h1>
                <p class="text-base text-content-secondary">
                    {{ submitted.project.name }} · {{ submitted.project.village_name }}
                </p>
            </header>

            <Alert color="positive">{{ text.body }}</Alert>

            <p class="flex flex-wrap items-center gap-2 text-sm text-content-secondary">
                {{ text.kindLabel }}
                <Badge>{{ reportType[submitted.report.kind] }}</Badge>
            </p>

            <section class="space-y-1 rounded-2xl bg-surface-muted p-4" aria-live="polite">
                <p class="text-base font-semibold text-content tabular-nums">
                    {{ text.mismatchCount(submitted.mismatch_count.current, submitted.mismatch_count.threshold) }}
                </p>
                <p class="text-sm text-content-secondary">
                    {{ text.mismatchHint(submitted.mismatch_count.threshold) }}
                </p>
            </section>

            <p class="text-sm text-content-secondary">{{ text.replaceHint }}</p>

            <div class="flex flex-wrap gap-3">
                <Button variant="primary" :href="submitted.urls.project">{{ text.backToProject }}</Button>
                <Button variant="secondary" :href="submitted.urls.check">{{ text.sendAgain }}</Button>
            </div>
        </div>
    </PublicLayout>
</template>
