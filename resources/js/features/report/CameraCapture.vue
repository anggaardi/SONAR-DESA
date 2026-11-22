<script setup lang="ts">
// CameraCapture: foto Cek Spek dari kamera langsung, tanpa pilihan galeri (A15, C6).
// Kamera baru dibuka setelah warga menekan "Buka kamera", sesudah penjelasan lokasi dan izin (C1).
// Mengikuti standar INA Digital Design System (IDDS) dengan EmptyState bimbingan untuk state error kamera.

import { ref, watch } from 'vue';

import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import { camera } from '@/lib/labels';

import { DEFAULT_MAX_PHOTOS, MIN_PHOTOS, useCamera } from './useCamera';

interface Props {
    maxPhotos?: number;
    /** A ready-made photo given from outside (demo sample, M6-05); added once when set. */
    extraPhoto?: Blob | null;
}

const props = withDefaults(defineProps<Props>(), { maxPhotos: DEFAULT_MAX_PHOTOS, extraPhoto: null });

const emit = defineEmits<{ change: [blobs: Blob[]] }>();

const video = ref<HTMLVideoElement | null>(null);
const { status, stream, photos, canCapture, isFull, start, capture, add, remove } = useCamera({
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

watch(
    () => props.extraPhoto,
    (photo) => {
        if (photo) {
            add(photo);
        }
    },
);

async function takePhoto(): Promise<void> {
    if (video.value) {
        await capture(video.value);
    }
}

const failureText = { denied: camera.denied, unavailable: camera.unavailable, failed: camera.failed } as const;
</script>

<template>
    <section aria-labelledby="foto-lokasi" class="space-y-4">
        <!-- Header Seksi Foto -->
        <div>
            <div class="flex items-center justify-between gap-2">
                <h2 id="foto-lokasi" class="text-base font-bold text-content sm:text-lg">{{ camera.title }}</h2>
                <span class="text-xs font-semibold text-content-secondary tabular-nums" aria-live="polite">
                    {{ camera.counter(photos.length, maxPhotos) }}
                </span>
            </div>
            <p class="mt-1 text-xs text-content-secondary sm:text-sm">
                {{ camera.hint(MIN_PHOTOS, maxPhotos) }}
            </p>
        </div>

        <!-- Banner Pengingat Etika Privasi IDDS -->
        <div
            class="flex items-center gap-2.5 rounded-xl border border-warning-border bg-warning-subtle px-3.5 py-2.5 text-xs text-warning sm:text-sm"
        >
            <svg
                class="h-4 w-4 shrink-0"
                viewBox="0 0 20 20"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 3L2 17h16L10 3zm0 5v4m0 2.5h.01" />
            </svg>
            <span class="font-medium">{{ camera.faceReminder }}</span>
        </div>

        <!-- Error State: Kamera Ditolak / Gagal (EmptyState IDDS) -->
        <div
            v-if="status === 'denied' || status === 'unavailable' || status === 'failed'"
            class="rounded-2xl border border-stroke bg-surface p-4"
        >
            <EmptyState :title="failureText[status].title" :description="failureText[status].body">
                <template #illustration>
                    <!-- Slot Ilustrasi IDDS 4:3 -->
                    <div
                        class="flex aspect-4/3 w-full max-w-64 flex-col items-center justify-center rounded-2xl border-2 border-dashed border-warning-border bg-warning-subtle/40 p-4 text-center"
                    >
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-full bg-warning-subtle text-warning"
                            aria-hidden="true"
                        >
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path
                                    d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"
                                />
                                <circle cx="12" cy="13" r="4" />
                                <path d="M1 1l22 22" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <p class="mt-2 text-xs font-bold text-warning">[ Slot Ilustrasi: Kamera Ditolak ]</p>
                        <p class="text-[11px] text-content-tertiary">Proporsi 4:3 IDDS</p>
                    </div>
                </template>
                <Button variant="primary" @click="start">{{ camera.retry }}</Button>
            </EmptyState>
        </div>

        <!-- Jendela Video Kamera Langsung -->
        <div v-show="status === 'ready'" class="overflow-hidden rounded-2xl border border-stroke bg-content shadow-sm">
            <video ref="video" autoplay muted playsinline class="aspect-4/3 w-full object-cover" />
            <div class="flex justify-center border-t border-stroke bg-surface p-3">
                <Button variant="primary" :disabled="!canCapture" class="w-full sm:w-auto" @click="takePhoto">
                    <svg
                        class="h-4 w-4 shrink-0"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <circle cx="12" cy="12" r="9" />
                        <circle cx="12" cy="12" r="3" fill="currentColor" />
                    </svg>
                    {{ camera.capture }}
                </Button>
            </div>
        </div>

        <!-- Tombol Buka Kamera (Saat Belum Aktif & Belum Penuh) -->
        <div
            v-if="status !== 'ready' && !isFull && (status === 'idle' || status === 'starting')"
            class="flex flex-col gap-2"
        >
            <Button
                variant="secondary"
                :loading="status === 'starting'"
                class="h-12 w-full justify-center"
                @click="start"
            >
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" />
                    <circle cx="12" cy="13" r="4" />
                </svg>
                <span>{{ photos.length > 0 ? '+ Tambah Foto' : camera.open }}</span>
            </Button>
        </div>

        <p v-if="isFull" class="py-1 text-center text-xs text-content-secondary">
            {{ camera.limitReached }}
        </p>

        <!-- Galeri Foto Terambil (Termasuk Foto Demo) -->
        <div v-if="photos.length > 0" class="space-y-2">
            <p class="text-xs font-bold tracking-wider text-content uppercase">Foto Terlampir</p>
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <li
                    v-for="(photo, index) in photos"
                    :key="photo.id"
                    class="group relative overflow-hidden rounded-xl border border-stroke bg-surface shadow-xs"
                >
                    <img
                        :src="photo.previewUrl"
                        :alt="camera.photoAlt(index + 1)"
                        class="aspect-4/3 w-full object-cover"
                    />
                    <div class="flex items-center justify-between p-2 text-xs">
                        <span class="font-semibold text-content">Foto {{ index + 1 }}</span>
                        <button
                            type="button"
                            :aria-label="camera.removePhoto(index + 1)"
                            class="flex h-7 w-7 items-center justify-center rounded-lg bg-negative-subtle text-negative transition-colors hover:bg-negative-border focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary"
                            @click="remove(photo.id)"
                        >
                            <svg
                                class="h-3.5 w-3.5"
                                viewBox="0 0 20 20"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 5l10 10M15 5L5 15" />
                            </svg>
                        </button>
                    </div>
                </li>
            </ul>
        </div>
    </section>
</template>
