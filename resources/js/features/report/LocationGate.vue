<script setup lang="ts">
// LocationGate: penjelasan lokasi (C1), izin (C2), sinyal lemah (C7), lalu jarak ke proyek.
// Jarak dan "dalam radius atau tidak" datang dari server lewat props; layar ini tidak menghitung angka (ENGINEERING 2).

import { watch } from 'vue';

import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import { formatLength } from '@/lib/format';
import { location } from '@/lib/labels';

import { useGeolocation, type GeoPosition } from './useGeolocation';

interface Props {
    maxAccuracyMeters: number;
    distanceMeters?: number | null;
    withinRadius?: boolean | null;
}

const props = withDefaults(defineProps<Props>(), { distanceMeters: null, withinRadius: null });

const emit = defineEmits<{ position: [position: GeoPosition]; continue: [] }>();

const { status, position, start } = useGeolocation({ maxAccuracyMeters: props.maxAccuracyMeters });

watch(status, (current) => {
    if (current === 'ready' && position.value) {
        emit('position', position.value);
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

        <template v-else-if="status === 'denied' || status === 'unavailable' || status === 'failed'">
            <Alert color="warning" :title="failureText[status].title">{{ failureText[status].body }}</Alert>
            <Button v-if="status !== 'unavailable'" variant="secondary" @click="start">{{ location.retry }}</Button>
        </template>

        <template v-else-if="status === 'ready'">
            <Alert v-if="distanceMeters !== null && withinRadius === false" color="warning">
                {{ location.farAway(formatLength(Math.round(distanceMeters))) }}
            </Alert>
            <Alert v-else-if="distanceMeters !== null" color="positive">
                {{ location.nearby(formatLength(Math.round(distanceMeters))) }}
            </Alert>
            <p v-else class="text-sm text-content-secondary" role="status">{{ location.locating }}</p>

            <Button variant="primary" :disabled="distanceMeters === null" @click="emit('continue')">
                {{ location.continue }}
            </Button>
        </template>
    </section>
</template>
