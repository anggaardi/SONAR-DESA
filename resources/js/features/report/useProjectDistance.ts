// useProjectDistance: jarak ke proyek untuk layar lokasi (PRD Fitur Inti 5.1 step 3).
// Angka dihitung server (D-008): koordinat proyek tidak pernah dikirim ke browser, dan jaraknya dibulatkan.
// Ini hanya petunjuk di layar; jarak resmi dihitung ulang server saat laporan dikirim (M4-06).

import { useHttp } from '@inertiajs/vue3';
import { ref } from 'vue';

import type { GeoPosition } from './useGeolocation';

export interface DistanceHint {
    within_radius: boolean;
    /** Rounded up by the server; null when the visitor is too far away for a number to be shown. */
    distance_m: number | null;
}

export type DistanceStatus = 'idle' | 'loading' | 'ready' | 'failed';

export function useProjectDistance(url: string) {
    const http = useHttp<{ lat: number; lng: number }, DistanceHint>({ lat: 0, lng: 0 });

    const status = ref<DistanceStatus>('idle');
    const hint = ref<DistanceHint | null>(null);

    async function check(position: Pick<GeoPosition, 'lat' | 'lng'>): Promise<void> {
        status.value = 'loading';
        hint.value = null;
        http.lat = position.lat;
        http.lng = position.lng;

        try {
            await http.post(url, {
                onSuccess: (data) => {
                    hint.value = data;
                    status.value = 'ready';
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
    }

    return { status, hint, check };
}
