<script setup lang="ts">
// Cek Spek (PRD Fitur Inti 5.1, 5.5): lokasi -> ukur -> foto & catatan. Hanya menyusun; logika di features/report.
// Warga sudah masuk Google sebelum halaman ini (F11). Angka jarak dan batas datang dari server.
// Redesain mobile sesuai standar INA Digital Design System (IDDS) dengan Visual Stepper dan tata letak humanis.

import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import { preloadIllustrations } from '@/components/ui/illustrations';
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

const stepTitles: Record<Step, string> = {
    location: text.steps.location,
    measure: text.steps.measure,
    photo: text.steps.photo,
};

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

onMounted(() => {
    preloadIllustrations(['location-error', 'camera-error']);
});
</script>

<template>
    <PublicLayout>
        <Head :title="`${text.title} · ${checkSpec.project.name}`" />

        <div class="space-y-5">
            <!-- Navigasi Atas & Kembali -->
            <div class="flex items-center justify-between">
                <Link
                    :href="checkSpec.urls.project"
                    class="inline-flex min-h-11 items-center gap-1.5 text-sm font-semibold text-primary transition-colors hover:text-primary-hover"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15l-5-5 5-5" />
                    </svg>
                    <span>{{ text.back }}</span>
                </Link>

                <span class="text-xs font-semibold tracking-wider text-content-secondary uppercase">
                    {{ text.title }}
                </span>
            </div>

            <!-- Kartu Konteks Proyek (Ringkas & Informatif ala IDDS) -->
            <div
                class="flex items-center justify-between gap-3 rounded-2xl border border-stroke bg-surface p-4 shadow-2xs"
            >
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-primary">Proyek Fisik Desa</p>
                    <h1 class="truncate text-base font-bold text-content sm:text-lg">
                        {{ checkSpec.project.name }}
                    </h1>
                    <p class="truncate text-xs text-content-secondary">
                        {{ checkSpec.project.village_name }}
                    </p>
                </div>
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-subtle text-primary"
                    aria-hidden="true"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3.5 15.5 15.5 3.5l5 5-12 12z" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M7.5 11.5l2 2m3-5l2 2m3-5l2 2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
            </div>

            <!-- Visual Stepper IDDS (3 Langkah) -->
            <div
                class="space-y-2 rounded-2xl border border-stroke bg-surface-subtle p-3.5"
                aria-label="Tahapan Cek Spek"
            >
                <div class="flex items-center justify-between text-xs sm:text-sm">
                    <p class="font-bold text-content">
                        {{ text.step(steps.indexOf(step) + 1, steps.length, stepTitles[step]) }}
                    </p>
                    <span class="text-xs font-semibold text-content-secondary tabular-nums">
                        {{ Math.round(((steps.indexOf(step) + 1) / steps.length) * 100) }}% Selesai
                    </span>
                </div>

                <!-- 3 Segmen Progress Bar -->
                <div class="grid grid-cols-3 gap-2" aria-hidden="true">
                    <div
                        v-for="(st, idx) in steps"
                        :key="st"
                        class="h-2 rounded-full transition-colors"
                        :class="
                            idx < steps.indexOf(step)
                                ? 'bg-positive'
                                : idx === steps.indexOf(step)
                                  ? 'bg-primary'
                                  : 'bg-stroke-strong/30'
                        "
                    />
                </div>
            </div>

            <div
                v-if="checkSpec.measurements.length === 0"
                class="rounded-lg border border-stroke bg-surface px-4"
                role="status"
            >
                <EmptyState
                    illustration="plang-empty"
                    compact
                    title="Ukuran fisik belum tersedia"
                    :description="text.noMeasurements"
                />
            </div>

            <!-- Mode Demo Juri Card (IDDS Sandbox Styling) -->
            <div v-if="isDemo" class="space-y-3 rounded-2xl border border-[#c9bfff] bg-demo-subtle p-4 sm:p-5">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <svg
                            class="h-5 w-5 text-demo"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path d="M12 13a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z" />
                            <path
                                d="M8.5 15a5 5 0 0 1 0-7m7 0a5 5 0 0 1 0 7m-9.9 10a9 9 0 0 1 0-13m12.8 0a9 9 0 0 1 0 13"
                            />
                        </svg>
                        <p class="text-xs font-bold tracking-wider text-demo uppercase sm:text-sm">Mode Demo Juri</p>
                    </div>
                    <span
                        class="rounded-full border border-[#c9bfff] bg-surface px-2.5 py-0.5 text-xs font-bold text-demo"
                    >
                        Sandbox
                    </span>
                </div>

                <p class="text-xs leading-relaxed text-content sm:text-sm">
                    {{ demoSample.hint }}
                </p>

                <!-- Status Sample Ready vs Tombol Pakai Sample -->
                <div
                    v-if="sampleStatus === 'ready'"
                    class="flex items-center justify-between gap-3 rounded-xl border border-[#c9bfff] bg-surface/80 p-3"
                >
                    <div class="flex items-center gap-2 text-xs font-bold text-positive sm:text-sm">
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                            <path
                                fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd"
                            />
                        </svg>
                        <span>Foto & lokasi contoh aktif</span>
                    </div>
                    <button type="button" class="text-xs font-semibold text-demo hover:underline" @click="loadSample">
                        Muat Ulang
                    </button>
                </div>
                <div v-else class="flex flex-wrap items-center gap-3">
                    <Button
                        variant="secondary"
                        :loading="sampleStatus === 'loading'"
                        :disabled="sampleStatus === 'loading'"
                        class="border-[#c9bfff] bg-surface text-demo hover:bg-[#ede8ff]"
                        @click="loadSample"
                    >
                        <svg
                            class="h-4 w-4 shrink-0"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                            <polyline points="17 8 12 3 7 8" stroke-linecap="round" stroke-linejoin="round" />
                            <line x1="12" y1="3" x2="12" y2="15" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        {{ demoSample.use }}
                    </Button>
                </div>

                <p v-if="sampleStatus === 'failed'" class="text-xs font-semibold text-warning sm:text-sm" role="alert">
                    {{ demoSample.failed }}
                </p>
            </div>

            <!-- LANGKAH 1: LOKASI (Kept mounted via v-show agar koordinat bertahan) -->
            <div v-show="step === 'location'" class="space-y-4">
                <LocationGate
                    :radius-meters="checkSpec.limits.radius_m"
                    :max-accuracy-meters="checkSpec.limits.max_accuracy_m"
                    :distance-url="checkSpec.urls.distance"
                    :fixed-position="samplePosition"
                    @position="rememberPosition"
                    @continue="step = 'measure'"
                />
            </div>

            <!-- LANGKAH 2: UKUR (Form Checklist RAB) -->
            <section v-show="step === 'measure'" class="space-y-5" aria-labelledby="langkah-ukur">
                <h2 id="langkah-ukur" class="sr-only">{{ text.steps.measure }}</h2>

                <!-- Panduan Langkah Ukur -->
                <div class="flex items-start gap-3 rounded-2xl border border-primary/20 bg-primary-subtle p-4">
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary text-content-inverse"
                        aria-hidden="true"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="12" y1="16" x2="12" y2="12" />
                            <line x1="12" y1="8" x2="12.01" y2="8" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-info sm:text-sm">Bandingkan Kondisi Lapangan dengan RAB</p>
                        <p class="mt-0.5 text-xs text-content-secondary">
                            Jika fisik tidak sesuai dokumen, masukkan angka riil yang kamu temukan di lapangan.
                        </p>
                    </div>
                </div>

                <MeasurementChecklist
                    v-model="form.answers"
                    :measurements="checkSpec.measurements"
                    :show-errors="showChecklistErrors"
                />

                <Alert v-if="showChecklistErrors && !isChecklistComplete(form.answers)" color="warning">
                    {{ text.checklistIncomplete }}
                </Alert>

                <!-- Tombol Navigasi Bawah Langkah Ukur (Sticky/Fixed di HP) -->
                <div class="flex gap-3 pt-2">
                    <Button variant="secondary" class="flex-1 sm:flex-initial" @click="step = 'location'">
                        {{ text.previous }}
                    </Button>
                    <Button
                        variant="primary"
                        :disabled="checkSpec.measurements.length === 0"
                        class="flex-2 sm:flex-initial"
                        @click="continueToPhotos"
                    >
                        {{ text.next }} &rarr;
                    </Button>
                </div>
            </section>

            <!-- LANGKAH 3: FOTO & CATATAN -->
            <section v-show="step === 'photo'" class="space-y-5" aria-labelledby="langkah-foto">
                <h2 id="langkah-foto" class="sr-only">{{ text.steps.photo }}</h2>

                <CameraCapture
                    :max-photos="checkSpec.limits.max_photos"
                    :extra-photo="samplePhoto"
                    @change="(blobs) => (form.photos = blobs)"
                />

                <!-- Form Catatan Tambahan -->
                <div class="space-y-1.5 rounded-2xl border border-stroke bg-surface p-4 sm:p-5">
                    <div class="flex items-center justify-between">
                        <label for="catatan" class="text-xs font-bold text-content sm:text-sm">
                            {{ text.noteLabel(checkSpec.limits.max_note_length) }}
                        </label>
                        <span class="text-xs text-content-tertiary tabular-nums">
                            {{ text.noteCounter(form.note.length, checkSpec.limits.max_note_length) }}
                        </span>
                    </div>

                    <textarea
                        id="catatan"
                        v-model="form.note"
                        rows="3"
                        :maxlength="checkSpec.limits.max_note_length"
                        :placeholder="text.notePlaceholder"
                        class="mt-1 w-full rounded-xl border border-stroke-strong bg-surface p-3 text-sm leading-relaxed text-content transition-all focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary sm:text-base"
                    />
                    <p class="text-xs text-content-secondary">
                        Catatan ini akan diteruskan ke Badan Permusyawaratan Desa (BPD) bersama laporan ukuran.
                    </p>
                </div>

                <!-- Jaminan Keamanan & Privasi Laporan Warga IDDS -->
                <div class="flex items-start gap-3 rounded-xl border border-primary/20 bg-primary-subtle p-3.5">
                    <svg
                        class="mt-0.5 h-5 w-5 shrink-0 text-primary"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                    <div>
                        <p class="text-xs font-bold text-info sm:text-sm">Kerahasiaan Pelapor Terjamin</p>
                        <p class="mt-0.5 text-xs leading-relaxed text-content-secondary">
                            {{ text.submitNotice }}
                        </p>
                    </div>
                </div>

                <p v-if="form.photos.length < MIN_PHOTOS" class="text-xs font-medium text-warning sm:text-sm">
                    ⚠️ {{ text.photoRequired }}
                </p>

                <!-- Error saat Kirim -->
                <Alert v-if="sendFailed" color="warning" :title="text.sendFailed.title">
                    {{ text.sendFailed.body }}
                </Alert>
                <Alert v-else-if="serverErrors.length > 0" color="warning" :title="text.sendRefused">
                    <ul class="list-disc space-y-1 pl-5 text-xs sm:text-sm">
                        <li v-for="message in serverErrors" :key="message">{{ message }}</li>
                    </ul>
                </Alert>

                <!-- Tombol Navigasi Bawah Langkah Terakhir -->
                <div class="flex gap-3 pt-2">
                    <Button
                        variant="secondary"
                        :disabled="isSending"
                        class="flex-1 sm:flex-initial"
                        @click="step = 'measure'"
                    >
                        {{ text.previous }}
                    </Button>
                    <Button
                        variant="primary"
                        :disabled="!canSend"
                        :loading="isSending"
                        class="flex-2 sm:flex-initial"
                        @click="send"
                    >
                        {{ sendFailed ? text.resend : text.submit }}
                    </Button>
                </div>
            </section>
        </div>
    </PublicLayout>
</template>
