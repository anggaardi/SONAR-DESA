<script setup lang="ts">
// Cek Spek (PRD Fitur Inti 5.1, 5.5): lokasi -> ukur -> foto & catatan. Hanya menyusun; logika di features/report.
// Warga sudah masuk Google sebelum halaman ini (F11). Angka jarak dan batas datang dari server.

import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import { isChecklistComplete, emptyAnswers } from '@/features/report/checkSpecRules';
import CameraCapture from '@/features/report/CameraCapture.vue';
import LocationGate from '@/features/report/LocationGate.vue';
import MeasurementChecklist from '@/features/report/MeasurementChecklist.vue';
import type { GeoPosition } from '@/features/report/useGeolocation';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { checkSpec as text } from '@/lib/labels';
import type { CheckSpecPage } from '@/types/report';

const props = defineProps<{ checkSpec: CheckSpecPage }>();

type Step = 'location' | 'measure' | 'photo';
const steps: Step[] = ['location', 'measure', 'photo'];
const step = ref<Step>('location');
const showChecklistErrors = ref(false);

// TODO(M4-06): submit to POST /p/{slug}/reports; the location is read again at submit time (useGeolocation.readFresh).
const form = useForm({
    answers: emptyAnswers(props.checkSpec.measurements.map((measurement) => measurement.id)),
    photos: [] as Blob[],
    note: '',
    lat: null as number | null,
    lng: null as number | null,
    accuracy_m: null as number | null,
});

function rememberPosition(position: GeoPosition): void {
    form.lat = position.lat;
    form.lng = position.lng;
    form.accuracy_m = position.accuracyMeters;
}

function continueToPhotos(): void {
    if (!isChecklistComplete(form.answers)) {
        showChecklistErrors.value = true;
        return;
    }

    step.value = 'photo';
}
</script>

<template>
    <PublicLayout>
        <Head :title="`${text.title} · ${checkSpec.project.name}`" />

        <div class="space-y-5">
            <Link
                :href="checkSpec.urls.project"
                class="inline-flex min-h-11 items-center text-sm font-medium text-primary hover:text-primary-hover"
            >
                &larr; {{ text.back }}
            </Link>

            <header>
                <p class="text-sm text-content-secondary" aria-live="polite">
                    {{ text.step(steps.indexOf(step) + 1, steps.length, text.steps[step]) }}
                </p>
                <h1 class="mt-1 text-2xl font-bold text-content">{{ text.title }}</h1>
                <p class="text-base text-content-secondary">
                    {{ checkSpec.project.name }} · {{ checkSpec.project.village_name }}
                </p>
            </header>

            <Alert v-if="checkSpec.measurements.length === 0" color="info">{{ text.noMeasurements }}</Alert>

            <!-- Kept mounted (v-show) so the position and camera state survive moving between steps. -->
            <div v-show="step === 'location'">
                <LocationGate
                    :max-accuracy-meters="checkSpec.limits.max_accuracy_m"
                    :distance-url="checkSpec.urls.distance"
                    @position="rememberPosition"
                    @continue="step = 'measure'"
                />
            </div>

            <section v-show="step === 'measure'" class="space-y-4" aria-labelledby="langkah-ukur">
                <h2 id="langkah-ukur" class="sr-only">{{ text.steps.measure }}</h2>
                <MeasurementChecklist
                    v-model="form.answers"
                    :measurements="checkSpec.measurements"
                    :show-errors="showChecklistErrors"
                />
                <Alert v-if="showChecklistErrors && !isChecklistComplete(form.answers)" color="warning">
                    {{ text.checklistIncomplete }}
                </Alert>
                <div class="flex gap-3">
                    <Button variant="secondary" @click="step = 'location'">{{ text.previous }}</Button>
                    <Button variant="primary" :disabled="checkSpec.measurements.length === 0" @click="continueToPhotos">
                        {{ text.next }}
                    </Button>
                </div>
            </section>

            <section v-show="step === 'photo'" class="space-y-5" aria-labelledby="langkah-foto">
                <h2 id="langkah-foto" class="sr-only">{{ text.steps.photo }}</h2>
                <CameraCapture :max-photos="checkSpec.limits.max_photos" @change="(blobs) => (form.photos = blobs)" />

                <div>
                    <label for="catatan" class="text-sm font-medium text-content">
                        {{ text.noteLabel(checkSpec.limits.max_note_length) }}
                    </label>
                    <textarea
                        id="catatan"
                        v-model="form.note"
                        rows="3"
                        :maxlength="checkSpec.limits.max_note_length"
                        :placeholder="text.notePlaceholder"
                        class="mt-1 w-full rounded-md border border-stroke-strong bg-surface px-3 py-2 text-base text-content focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                    />
                    <p class="text-right text-xs text-content-tertiary tabular-nums">
                        {{ text.noteCounter(form.note.length, checkSpec.limits.max_note_length) }}
                    </p>
                </div>

                <p class="text-sm text-content-secondary">{{ text.submitNotice }}</p>
                <Alert color="info">{{ text.submitPending }}</Alert>
                <div class="flex gap-3">
                    <Button variant="secondary" @click="step = 'measure'">{{ text.previous }}</Button>
                    <Button variant="primary" disabled>{{ text.submit }}</Button>
                </div>
            </section>
        </div>
    </PublicLayout>
</template>
