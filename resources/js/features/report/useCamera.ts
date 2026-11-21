// useCamera: foto laporan hanya dari kamera langsung (A15, PRD Fitur Inti 5.1 step 6, C6).
// Frame digambar ke <canvas> lalu di-encode ulang menjadi JPEG, jadi EXIF dan GPS bawaan tidak ikut.
// Batas foto di sini hanya untuk layar; server memvalidasi ulang di SubmitReport (M4-06).

import { computed, onScopeDispose, ref, shallowRef } from 'vue';

export const MIN_PHOTOS = 1;
export const DEFAULT_MAX_PHOTOS = 3;
export const MAX_EDGE_PX = 1600;
export const JPEG_QUALITY = 0.85;

export type CameraStatus = 'idle' | 'starting' | 'ready' | 'denied' | 'unavailable' | 'failed';

export interface CapturedPhoto {
    id: number;
    blob: Blob;
    previewUrl: string;
}

export interface UseCameraOptions {
    maxPhotos?: number;
}

/** Scales down so the longer edge is at most maxEdge; never scales up. */
export function fitWithin(width: number, height: number, maxEdge: number): { width: number; height: number } {
    const longest = Math.max(width, height);

    if (longest <= maxEdge) {
        return { width, height };
    }

    const scale = maxEdge / longest;

    return { width: Math.round(width * scale), height: Math.round(height * scale) };
}

function statusForError(error: unknown): CameraStatus {
    const name = error instanceof DOMException ? error.name : '';

    if (name === 'NotAllowedError' || name === 'SecurityError') {
        return 'denied';
    }

    if (name === 'NotFoundError' || name === 'OverconstrainedError') {
        return 'unavailable';
    }

    return 'failed';
}

export function useCamera(options: UseCameraOptions = {}) {
    const maxPhotos = options.maxPhotos ?? DEFAULT_MAX_PHOTOS;

    const status = ref<CameraStatus>('idle');
    const stream = shallowRef<MediaStream | null>(null);
    const photos = ref<CapturedPhoto[]>([]);
    let nextId = 1;

    const canCapture = computed(() => status.value === 'ready' && photos.value.length < maxPhotos);
    const hasMinimum = computed(() => photos.value.length >= MIN_PHOTOS);
    const isFull = computed(() => photos.value.length >= maxPhotos);

    async function start(): Promise<void> {
        if (status.value === 'starting' || status.value === 'ready') {
            return;
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            status.value = 'unavailable';
            return;
        }

        status.value = 'starting';

        try {
            stream.value = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' } },
                audio: false,
            });
            status.value = 'ready';
        } catch (error) {
            stream.value = null;
            status.value = statusForError(error);
        }
    }

    function stop(): void {
        stream.value?.getTracks().forEach((track) => track.stop());
        stream.value = null;

        if (status.value === 'ready' || status.value === 'starting') {
            status.value = 'idle';
        }
    }

    /** Returns false when nothing was added (camera not ready, no frame yet, limit reached, or encode failed). */
    async function capture(video: HTMLVideoElement): Promise<boolean> {
        if (!canCapture.value || video.videoWidth === 0 || video.videoHeight === 0) {
            return false;
        }

        const size = fitWithin(video.videoWidth, video.videoHeight, MAX_EDGE_PX);
        const canvas = document.createElement('canvas');
        canvas.width = size.width;
        canvas.height = size.height;
        canvas.getContext('2d')?.drawImage(video, 0, 0, size.width, size.height);

        const blob = await new Promise<Blob | null>((resolve) => {
            canvas.toBlob(resolve, 'image/jpeg', JPEG_QUALITY);
        });

        // The limit is checked again: a second tap can resolve while the first encode is still running.
        if (blob === null || photos.value.length >= maxPhotos) {
            return false;
        }

        photos.value.push({ id: nextId++, blob, previewUrl: URL.createObjectURL(blob) });

        return true;
    }

    function remove(id: number): void {
        const photo = photos.value.find((candidate) => candidate.id === id);

        if (photo) {
            URL.revokeObjectURL(photo.previewUrl);
            photos.value = photos.value.filter((candidate) => candidate.id !== id);
        }
    }

    function release(): void {
        stop();
        photos.value.forEach((photo) => URL.revokeObjectURL(photo.previewUrl));
        photos.value = [];
    }

    onScopeDispose(release, true);

    return { status, stream, photos, maxPhotos, canCapture, hasMinimum, isFull, start, stop, capture, remove, release };
}
