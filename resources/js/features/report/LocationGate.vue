<script setup lang="ts">
// LocationGate: penjelasan lokasi (C1), izin (C2), sinyal lemah (C7), lalu jarak ke proyek.
// Jarak dan "dalam radius atau tidak" dihitung server (D-008); layar ini tidak menghitung angka (ENGINEERING 2).

import { watch } from 'vue';

import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import { formatLength } from '@/lib/format';
import { location } from '@/lib/labels';

import { useGeolocation, type GeoPosition } from './useGeolocation';
import { useProjectDistance } from './useProjectDistance';

interface Props {
    /** Same as config('sonar.report.radius_m'); only for the text. Inside or not comes from the server. */
    radiusMeters: number;
    maxAccuracyMeters: number;
    distanceUrl: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{ position: [position: GeoPosition]; continue: [] }>();

const { status, position, start } = useGeolocation({ maxAccuracyMeters: props.maxAccuracyMeters });
const { status: distanceStatus, hint, check } = useProjectDistance(props.distanceUrl);

watch(status, (current) => {
    if (current === 'ready' && position.value) {
        emit('position', position.value);
        void check(position.value);
    }
});

const failureText = { denied: location.denied, unavailable: location.unavailable, failed: location.failed } as const;
</script>

<template>
    <section aria-labelledby="lokasi-kamu" class="space-y-4">
        <h2 id="lokasi-kamu" class="text-lg font-semibold text-content">{{ location.title }}</h2>

        <div v-if="status === 'idle'" class="space-y-3 rounded-2xl bg-primary-subtle p-4">
            <h3 class="text-base font-semibold text-content">{{ location.why.heading }}</h3>
            <ul class="list-disc space-y-1 pl-5 text-sm text-content-secondary">
                <li v-for="point in location.why.points" :key="point">{{ point }}</li>
            </ul>
            <Button variant="primary" @click="start">{{ location.allow }}</Button>
        </div>

        <p v-else-if="status === 'locating'" class="text-sm text-content-secondary" role="status">
            {{ location.locating }}
        </p>

        <Alert v-else-if="status === 'weak'" color="warning" :title="location.weak.title">
            {{ location.weak.body(Math.round(position?.accuracyMeters ?? 0)) }}
        </Alert>

        <template v-else-if="status === 'too_weak'">
            <Alert color="warning" :title="location.tooWeak.title">
                {{ location.tooWeak.body(Math.round(position?.accuracyMeters ?? 0), maxAccuracyMeters) }}
            </Alert>
            <Button variant="secondary" @click="start">{{ location.retry }}</Button>
        </template>

        <template v-else-if="status === 'denied' || status === 'unavailable' || status === 'failed'">
            <Alert color="warning" :title="failureText[status].title">{{ failureText[status].body }}</Alert>
            <Button v-if="status !== 'unavailable'" variant="secondary" @click="start">{{ location.retry }}</Button>
        </template>

        <template v-else-if="status === 'ready'">
            <p
                v-if="distanceStatus === 'loading' || distanceStatus === 'idle'"
                class="text-sm text-content-secondary"
                role="status"
            >
                {{ location.checkingDistance }}
            </p>

            <template v-else-if="distanceStatus === 'failed'">
                <Alert color="warning" :title="location.distanceFailed.title">{{ location.distanceFailed.body }}</Alert>
                <Button variant="secondary" @click="position && check(position)">{{ location.retry }}</Button>
            </template>

            <!-- D-009: out of radius cannot go on; the server refuses such a send as well. -->
            <template v-else-if="hint && hint.within_radius">
                <Alert v-if="hint.distance_m !== null" color="positive">
                    {{ location.nearby(formatLength(hint.distance_m)) }}
                </Alert>
                <Button variant="primary" @click="emit('continue')">{{ location.continue }}</Button>
            </template>

            <template v-else-if="hint">
                <Alert color="warning">
                    {{
                        hint.distance_m !== null
                            ? location.farAway(formatLength(hint.distance_m), radiusMeters)
                            : location.farAwayUnknown(radiusMeters)
                    }}
                </Alert>
                <Button variant="secondary" @click="start">{{ location.recheck }}</Button>
            </template>
        </template>
    </section>
</template>
