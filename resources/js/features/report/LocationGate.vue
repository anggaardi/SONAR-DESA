<script setup lang="ts">
// LocationGate: penjelasan lokasi (C1), izin (C2), sinyal lemah (C7), lalu jarak ke proyek.
// Jarak dan "dalam radius atau tidak" dihitung server (D-008); layar ini tidak menghitung angka (ENGINEERING 2).
// Sesuai IDDS: state error menggunakan tata letak EmptyState dengan slot ilustrasi 4:3 terarah.

import { onMounted, watch } from 'vue';

import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import { preloadIllustrations } from '@/components/ui/illustrations';
import { formatLength } from '@/lib/format';
import { location } from '@/lib/labels';

import { useGeolocation, type GeoPosition } from './useGeolocation';
import { useProjectDistance } from './useProjectDistance';

interface Props {
    /** Same as config('sonar.report.radius_m'); only for the text. Inside or not comes from the server. */
    radiusMeters: number;
    maxAccuracyMeters: number;
    /** A position given from outside (demo sample, M6-05); used instead of the GPS when set. */
    fixedPosition?: GeoPosition | null;
    distanceUrl: string;
}

const props = withDefaults(defineProps<Props>(), { fixedPosition: null });

const emit = defineEmits<{ position: [position: GeoPosition]; continue: [] }>();

const { status, position, start, useFixed } = useGeolocation({ maxAccuracyMeters: props.maxAccuracyMeters });
const { status: distanceStatus, hint, check } = useProjectDistance(props.distanceUrl);

watch(status, (current) => {
    if (current === 'ready' && position.value) {
        emit('position', position.value);
        void check(position.value);
    }
});

watch(
    () => props.fixedPosition,
    (fixed) => {
        if (!fixed) {
            return;
        }

        // Already "ready" from the GPS: the status watcher will not fire, so check the new position here.
        const wasReady = status.value === 'ready';
        useFixed(fixed);

        if (wasReady) {
            emit('position', fixed);
            void check(fixed);
        }
    },
);

const failureText = { denied: location.denied, unavailable: location.unavailable, failed: location.failed } as const;

onMounted(() => {
    preloadIllustrations(['location-error']);
});
</script>

<template>
    <section aria-labelledby="lokasi-kamu" class="space-y-4">
        <h2 id="lokasi-kamu" class="sr-only">{{ location.title }}</h2>

        <!-- State 1: Awal / Meminta Izin (IDDS Trust Card) -->
        <div v-if="status === 'idle'" class="space-y-4 rounded-2xl border border-stroke bg-surface p-5 sm:p-6">
            <div class="flex items-start gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-subtle text-primary"
                    aria-hidden="true"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path
                            d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                        <circle cx="12" cy="9.5" r="2.5" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-content sm:text-lg">{{ location.why.heading }}</h3>
                    <p class="text-xs text-content-secondary sm:text-sm">
                        Verifikasi lokasi memastikan keaslian pengecekan warga di sekitar fisik proyek.
                    </p>
                </div>
            </div>

            <!-- 3 Poin Jaminan IDDS -->
            <div class="space-y-2.5 rounded-xl bg-surface-subtle p-3.5">
                <div
                    v-for="(point, idx) in location.why.points"
                    :key="point"
                    class="flex items-start gap-2.5 text-xs text-content-secondary sm:text-sm"
                >
                    <span
                        class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-primary/10 text-[10px] font-bold text-primary"
                    >
                        {{ idx + 1 }}
                    </span>
                    <span>{{ point }}</span>
                </div>
            </div>

            <Button variant="primary" class="w-full sm:w-auto" @click="start">
                <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path
                        d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                    <circle cx="12" cy="9.5" r="2.5" />
                </svg>
                {{ location.allow }}
            </Button>
        </div>

        <!-- State 2: Sedang Mencari Lokasi -->
        <div
            v-else-if="status === 'locating'"
            class="flex items-center gap-3 rounded-2xl border border-stroke bg-surface p-5 text-content-secondary"
            role="status"
        >
            <svg class="h-5 w-5 animate-spin text-primary" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
            </svg>
            <span class="text-sm font-medium text-content">{{ location.locating }}</span>
        </div>

        <!-- State 3 & 4: Sinyal Lemah / Terlalu Lemah (EmptyState IDDS) -->
        <div
            v-else-if="status === 'weak' || status === 'too_weak'"
            class="rounded-2xl border border-stroke bg-surface p-4"
            :role="status === 'weak' ? 'status' : undefined"
        >
            <EmptyState
                illustration="location-error"
                :title="status === 'weak' ? location.weak.title : location.tooWeak.title"
                :description="
                    status === 'weak'
                        ? location.weak.body(Math.round(position?.accuracyMeters ?? 0))
                        : location.tooWeak.body(Math.round(position?.accuracyMeters ?? 0), maxAccuracyMeters)
                "
            >
                <div
                    v-if="status === 'weak'"
                    class="inline-flex items-center gap-2 rounded-xl border border-warning-border bg-warning-subtle px-3.5 py-2 text-xs font-medium text-warning sm:text-sm"
                >
                    <svg class="h-4 w-4 shrink-0 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>
                    <span>{{ location.waitingSignal }}</span>
                </div>
                <Button v-else variant="primary" @click="start">{{ location.retry }}</Button>
            </EmptyState>
        </div>

        <!-- State 5: Akses Ditolak / Tidak Tersedia (EmptyState IDDS) -->
        <div
            v-else-if="status === 'denied' || status === 'unavailable' || status === 'failed'"
            class="rounded-2xl border border-stroke bg-surface p-4"
        >
            <EmptyState
                illustration="location-error"
                :title="failureText[status].title"
                :description="failureText[status].body"
            >
                <Button v-if="status !== 'unavailable'" variant="primary" @click="start">{{ location.retry }}</Button>
            </EmptyState>
        </div>

        <!-- State 6: Lokasi Ditemukan (Memeriksa Jarak atau Siap) -->
        <template v-else-if="status === 'ready'">
            <div
                v-if="distanceStatus === 'loading' || distanceStatus === 'idle'"
                class="flex items-center gap-3 rounded-2xl border border-stroke bg-surface p-5 text-content-secondary"
                role="status"
            >
                <svg class="h-5 w-5 animate-spin text-primary" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                <span class="text-sm font-medium text-content">{{ location.checkingDistance }}</span>
            </div>

            <!-- Gagal menghitung jarak -->
            <template v-else-if="distanceStatus === 'failed'">
                <Alert color="warning" :title="location.distanceFailed.title">{{ location.distanceFailed.body }}</Alert>
                <Button variant="secondary" @click="position && check(position)">{{ location.retry }}</Button>
            </template>

            <!-- BERHASIL: Di dalam radius (Tervalidasi) -->
            <div
                v-else-if="hint && hint.within_radius"
                class="space-y-4 rounded-2xl border border-positive-border bg-positive-subtle p-5"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-positive text-content-inverse"
                        aria-hidden="true"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 10l3.5 3.5L15 6" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-positive sm:text-base">
                            {{
                                hint.distance_m !== null
                                    ? location.nearby(formatLength(hint.distance_m))
                                    : 'Lokasi Terverifikasi di Area Proyek'
                            }}
                        </p>
                        <p class="text-xs text-content-secondary">
                            Kamu berada dalam batas jangkauan pemeriksaan fisik proyek.
                        </p>
                    </div>
                </div>

                <Button variant="primary" class="w-full sm:w-auto" @click="emit('continue')">
                    {{ location.continue }} &rarr;
                </Button>
            </div>

            <!-- ERROR: Di luar radius (EmptyState IDDS) -->
            <div v-else-if="hint" class="rounded-2xl border border-stroke bg-surface p-4">
                <EmptyState
                    illustration="location-error"
                    title="Kamu Berada di Luar Lokasi Proyek"
                    :description="
                        hint.distance_m !== null
                            ? location.farAway(formatLength(hint.distance_m), radiusMeters)
                            : location.farAwayUnknown(radiusMeters)
                    "
                >
                    <Button variant="primary" @click="start">
                        <svg
                            class="h-4 w-4 shrink-0"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path
                                d="M20 11a8 8 0 0 0-14.6-4.5L4 8m0-4v4h4m12 5a8 8 0 0 1-14.6 4.5L4 16m16 4v-4h-4"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                        {{ location.recheck }}
                    </Button>
                </EmptyState>
            </div>
        </template>
    </section>
</template>
