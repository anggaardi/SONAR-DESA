<script setup lang="ts">
// CameraCapture: foto Cek Spek dari kamera langsung, tanpa pilihan galeri (A15, C6).
// Kamera baru dibuka setelah warga menekan "Buka kamera", sesudah penjelasan lokasi dan izin (C1).

import { ref, watch } from 'vue';

import Alert from '@/components/ui/Alert.vue';
import Button from '@/components/ui/Button.vue';
import { camera } from '@/lib/labels';

import { DEFAULT_MAX_PHOTOS, MIN_PHOTOS, useCamera } from './useCamera';

interface Props {
    maxPhotos?: number;
}

const props = withDefaults(defineProps<Props>(), { maxPhotos: DEFAULT_MAX_PHOTOS });

const emit = defineEmits<{ change: [blobs: Blob[]] }>();

const video = ref<HTMLVideoElement | null>(null);
const { status, stream, photos, canCapture, isFull, start, capture, remove } = useCamera({
    maxPhotos: props.maxPhotos,
});

watch(
    stream,
    (activeStream) => {
        if (video.value) {
            video.value.srcObject = activeStream;
        }
    },
    { flush: 'post' },
);

watch(
    photos,
    (items) =>
        emit(
            'change',
            items.map((photo) => photo.blob),
        ),
    { deep: true },
);

async function takePhoto(): Promise<void> {
    if (video.value) {
        await capture(video.value);
    }
}

const failureText = { denied: camera.denied, unavailable: camera.unavailable, failed: camera.failed } as const;
</script>

<template>
    <section aria-labelledby="foto-lokasi" class="space-y-3">
        <div>
            <h2 id="foto-lokasi" class="text-lg font-semibold text-content">{{ camera.title }}</h2>
            <p class="text-sm text-content-secondary">{{ camera.hint(MIN_PHOTOS, maxPhotos) }}</p>
        </div>

        <p
            class="flex items-center gap-2 rounded-lg border border-warning-border bg-warning-subtle px-3 py-2 text-sm text-warning"
        >
            <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor" aria-hidden="true">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.5"
                    d="M10 3L2 17h16L10 3zm0 5v4m0 2.5h.01"
                />
            </svg>
            {{ camera.faceReminder }}
        </p>

        <Alert
            v-if="status === 'denied' || status === 'unavailable' || status === 'failed'"
            color="warning"
            :title="failureText[status].title"
        >
            {{ failureText[status].body }}
        </Alert>

        <div v-show="status === 'ready'" class="overflow-hidden rounded-2xl bg-content">
            <video ref="video" autoplay muted playsinline class="aspect-4/3 w-full object-cover" />
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <Button v-if="status !== 'ready'" variant="secondary" :loading="status === 'starting'" @click="start">
                {{ status === 'denied' || status === 'failed' ? camera.retry : camera.open }}
            </Button>
            <Button v-else variant="primary" :disabled="!canCapture" @click="takePhoto">
                {{ camera.capture }}
            </Button>
            <p class="text-sm text-content-secondary tabular-nums" aria-live="polite">
                {{ camera.counter(photos.length, maxPhotos) }}
            </p>
        </div>

        <p v-if="isFull" class="text-sm text-content-secondary">{{ camera.limitReached }}</p>

        <ul v-if="photos.length > 0" class="grid grid-cols-3 gap-2">
            <li v-for="(photo, index) in photos" :key="photo.id" class="relative">
                <img
                    :src="photo.previewUrl"
                    :alt="camera.photoAlt(index + 1)"
                    class="aspect-square w-full rounded-lg object-cover"
                />
                <button
                    type="button"
                    :aria-label="camera.removePhoto(index + 1)"
                    class="absolute top-1 right-1 flex h-8 w-8 items-center justify-center rounded-full bg-surface text-content shadow focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                    @click="remove(photo.id)"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-width="1.5" d="M5 5l10 10M15 5L5 15" />
                    </svg>
                </button>
            </li>
        </ul>
    </section>
</template>
