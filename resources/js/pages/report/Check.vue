<script setup lang="ts">
// Cek Spek (PRD Fitur Inti 5.1, 5.5): lokasi -> ukur -> foto & catatan. Hanya menyusun; logika di features/report.
// Warga sudah masuk Google sebelum halaman ini (F11). Angka jarak dan batas datang dari server.

import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import { useDemoSample } from '@/features/demo/useDemoSample';
import { isChecklistComplete, emptyAnswers } from '@/features/report/checkSpecRules';
import CameraCapture from '@/features/report/CameraCapture.vue';
import LocationGate from '@/features/report/LocationGate.vue';
import MeasurementChecklist from '@/features/report/MeasurementChecklist.vue';
import { MIN_PHOTOS } from '@/features/report/useCamera';
import { useGeolocation, type GeoPosition } from '@/features/report/useGeolocation';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { checkSpec as text, demoSample } from '@/lib/labels';
import type { CheckSpecPage } from '@/types/report';

const props = defineProps<{ checkSpec: CheckSpecPage }>();

type Step = 'location' | 'measure' | 'photo';
const steps: Step[] = ['location', 'measure', 'photo'];
const step = ref<Step>('location');
const showChecklistErrors = ref(false);

// The server re-checks everything and recomputes the distance (SubmitReportRequest, SubmitReport).
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

// Demo only (PRD Fitur Inti 5.6, M6-05): sample position + photo instead of the GPS and camera. The send is
// the same request and the server checks it the same way.
const isDemo = computed(() => usePage().props.demo !== null);
const {
    status: sampleStatus,
    position: samplePosition,
    photo: samplePhoto,
    load: loadSample,
} = useDemoSample(props.checkSpec.project.slug, demoSample.photoLabel);

// Only for the fresh reading at send time; LocationGate owns the watch on the first step.
const { readFresh } = useGeolocation({ maxAccuracyMeters: props.checkSpec.limits.max_accuracy_m });
const isReadingLocation = ref(false);
const sendFailed = ref(false);

const isSending = computed(() => isReadingLocation.value || form.processing);
const canSend = computed(
    () =>
        !isSending.value && form.lat !== null && form.photos.length >= MIN_PHOTOS && isChecklistComplete(form.answers),
);
const serverErrors = computed(() => [...new Set(Object.values(form.errors))]);

/** Network or server failure: the answers, photos, and note stay, and the button becomes "Kirim lagi". */
function markSendFailed(): boolean {
    sendFailed.value = true;

    return false;
}

async function send(): Promise<void> {
    if (!canSend.value) {
        return;
    }

    sendFailed.value = false;
    isReadingLocation.value = true;

    // "Lokasi diambil ulang saat kirim" (PRD Fitur Inti 5.1). If this reading fails, the earlier fix is sent.
    // Not with the demo sample: the visitor's real GPS is far from the demo project.
    const fresh = sampleStatus.value === 'ready' ? null : await readFresh();
    isReadingLocation.value = false;

    if (fresh) {
        rememberPosition(fresh);
    }

    form.transform((data) => ({
        ...data,
        photos: data.photos.map(
            (blob, index) => new File([blob], `foto-${index + 1}.jpg`, { type: blob.type || 'image/jpeg' }),
        ),
    })).post(props.checkSpec.urls.submit, {
        preserveScroll: true,
        onNetworkError: markSendFailed,
        onHttpException: markSendFailed,
    });
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
            <div v-show="step === 'location'" class="space-y-4">
                <div v-if="isDemo" class="space-y-2 rounded-2xl bg-demo-subtle p-4">
                    <p class="text-sm text-content">{{ demoSample.hint }}</p>
                    <Button
                        variant="secondary"
                        :loading="sampleStatus === 'loading'"
                        :disabled="sampleStatus === 'ready'"
                        @click="loadSample"
                    >
                        {{ demoSample.use }}
                    </Button>
                    <p v-if="sampleStatus === 'failed'" class="text-sm text-warning" role="alert">
                        {{ demoSample.failed }}
                    </p>
                </div>
                <LocationGate
                    :radius-meters="checkSpec.limits.radius_m"
                    :max-accuracy-meters="checkSpec.limits.max_accuracy_m"
                    :distance-url="checkSpec.urls.distance"
                    :fixed-position="samplePosition"
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
                <CameraCapture
                    :max-photos="checkSpec.limits.max_photos"
                    :extra-photo="samplePhoto"
                    @change="(blobs) => (form.photos = blobs)"
                />

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
                <p v-if="form.photos.length < MIN_PHOTOS" class="text-sm text-content-secondary">
                    {{ text.photoRequired }}
                </p>

                <Alert v-if="sendFailed" color="warning" :title="text.sendFailed.title">
                    {{ text.sendFailed.body }}
                </Alert>
                <Alert v-else-if="serverErrors.length > 0" color="warning" :title="text.sendRefused">
                    <ul class="list-disc space-y-1 pl-5">
                        <li v-for="message in serverErrors" :key="message">{{ message }}</li>
                    </ul>
                </Alert>

                <div class="flex gap-3">
                    <Button variant="secondary" :disabled="isSending" @click="step = 'measure'">
                        {{ text.previous }}
                    </Button>
                    <Button variant="primary" :disabled="!canSend" :loading="isSending" @click="send">
                        {{ sendFailed ? text.resend : text.submit }}
                    </Button>
                </div>
            </section>
        </div>
    </PublicLayout>
</template>
