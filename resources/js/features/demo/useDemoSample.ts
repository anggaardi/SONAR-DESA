// useDemoSample: "Pakai foto & lokasi contoh" di Cek Spek mode demo (PRD Fitur Inti 5.6, C12, M6-05).
// Lokasi contoh datang dari server (hanya untuk proyek desa demo sesi ini); foto contoh digambar di <canvas>.
// Laporan tetap dikirim ke endpoint yang sama dan diperiksa server seperti laporan asli.

import { useHttp } from '@inertiajs/vue3';
import { ref } from 'vue';

import type { GeoPosition } from '@/features/report/useGeolocation';

interface SampleLocation {
    lat: number;
    lng: number;
    accuracy_m: number;
}

export type DemoSampleStatus = 'idle' | 'loading' | 'ready' | 'failed';

const PHOTO_WIDTH = 800;
const PHOTO_HEIGHT = 600;

/** A plain JPEG that is clearly a sample, so BPD in the demo never mistakes it for a real photo. */
export function drawSamplePhoto(label: string): Promise<Blob | null> {
    const canvas = document.createElement('canvas');
    canvas.width = PHOTO_WIDTH;
    canvas.height = PHOTO_HEIGHT;
    const context = canvas.getContext('2d');

    if (context) {
        context.fillStyle = '#9ca3af';
        context.fillRect(0, 0, PHOTO_WIDTH, PHOTO_HEIGHT);
        context.fillStyle = '#6b7280';
        context.fillRect(0, PHOTO_HEIGHT * 0.6, PHOTO_WIDTH, PHOTO_HEIGHT * 0.4);
        context.fillStyle = '#ffffff';
        context.font = 'bold 40px sans-serif';
        context.textAlign = 'center';
        context.fillText(label, PHOTO_WIDTH / 2, PHOTO_HEIGHT / 2);
    }

    return new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85));
}

export function useDemoSample(projectSlug: string, photoLabel: string) {
    const http = useHttp<Record<string, never>, SampleLocation>({});

    const status = ref<DemoSampleStatus>('idle');
    const position = ref<GeoPosition | null>(null);
    const photo = ref<Blob | null>(null);

    async function load(): Promise<void> {
        status.value = 'loading';

        try {
            await http.get(`/demo/lokasi-contoh/${encodeURIComponent(projectSlug)}`, {
                onSuccess: (data) => {
                    position.value = { lat: data.lat, lng: data.lng, accuracyMeters: data.accuracy_m };
                },
                onError: () => {
                    status.value = 'failed';
                },
                onHttpException: () => {
                    status.value = 'failed';
                },
                onNetworkError: () => {
                    status.value = 'failed';
                },
            });
        } catch {
            status.value = 'failed';
        }

        if (position.value === null) {
            status.value = 'failed';
            return;
        }

        photo.value = await drawSamplePhoto(photoLabel);
        status.value = 'ready';
    }

    return { status, position, photo, load };
}
