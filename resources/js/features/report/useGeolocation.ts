// useGeolocation: posisi + akurasi untuk Cek Spek (PRD Fitur Inti 5.1 steps 2-3, C2, C7).
// Komposable ini hanya membaca posisi dan menilai akurasi layar. Jarak ke proyek dan keputusan "dihitung atau
// tidak" ada di server (ReportClassifier); batas akurasi dioper dari config server, bukan angka di sini.

import { computed, onScopeDispose, ref } from 'vue';

export type LocationStatus = 'idle' | 'locating' | 'weak' | 'ready' | 'denied' | 'unavailable' | 'failed';

export interface GeoPosition {
    lat: number;
    lng: number;
    accuracyMeters: number;
}

export interface UseGeolocationOptions {
    /** Same limit as config('sonar.report.max_accuracy_m'), passed down by the server. */
    maxAccuracyMeters: number;
    timeoutMs?: number;
}

const DEFAULT_TIMEOUT_MS = 15_000;
const PERMISSION_DENIED = 1;

function toGeoPosition(position: GeolocationPosition): GeoPosition {
    return {
        lat: position.coords.latitude,
        lng: position.coords.longitude,
        accuracyMeters: position.coords.accuracy,
    };
}

export function useGeolocation(options: UseGeolocationOptions) {
    const timeoutMs = options.timeoutMs ?? DEFAULT_TIMEOUT_MS;
    const geolocationOptions: PositionOptions = { enableHighAccuracy: true, timeout: timeoutMs, maximumAge: 0 };

    const status = ref<LocationStatus>('idle');
    const position = ref<GeoPosition | null>(null);
    let watchId: number | null = null;

    const isReady = computed(() => status.value === 'ready');

    function clearWatch(): void {
        if (watchId !== null) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }
    }

    function handleError(error: GeolocationPositionError): void {
        clearWatch();
        status.value = error.code === PERMISSION_DENIED ? 'denied' : 'failed';
    }

    /**
     * Watches until a fix is accurate enough, then stops (battery). While the fix is too coarse the status is
     * "weak", so the screen can say "tunggu sebentar" and the user sees it improve on its own (C7).
     */
    function start(): void {
        if (status.value === 'locating' || status.value === 'weak') {
            return;
        }

        if (!('geolocation' in navigator)) {
            status.value = 'unavailable';
            return;
        }

        clearWatch();
        position.value = null;
        status.value = 'locating';

        watchId = navigator.geolocation.watchPosition(
            (raw) => {
                const fix = toGeoPosition(raw);
                position.value = fix;

                if (fix.accuracyMeters > options.maxAccuracyMeters) {
                    status.value = 'weak';
                    return;
                }

                status.value = 'ready';
                clearWatch();
            },
            handleError,
            geolocationOptions,
        );
    }

    function stop(): void {
        if (!('geolocation' in navigator)) {
            return;
        }

        clearWatch();

        if (status.value === 'locating' || status.value === 'weak') {
            status.value = 'idle';
        }
    }

    /** One fresh reading for submit time ("lokasi diambil ulang saat kirim"). Null when it cannot be read. */
    function readFresh(): Promise<GeoPosition | null> {
        if (!('geolocation' in navigator)) {
            return Promise.resolve(null);
        }

        return new Promise((resolve) => {
            navigator.geolocation.getCurrentPosition(
                (raw) => resolve(toGeoPosition(raw)),
                () => resolve(null),
                geolocationOptions,
            );
        });
    }

    onScopeDispose(stop, true);

    return { status, position, isReady, start, stop, readFresh };
}
