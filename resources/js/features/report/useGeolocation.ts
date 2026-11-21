// useGeolocation: posisi + akurasi untuk Cek Spek (PRD Fitur Inti 5.1 steps 2-3, C2, C7).
// Komposable ini hanya membaca posisi dan menilai akurasi layar. Jarak ke proyek dan keputusan "dihitung atau
// tidak" ada di server (ReportClassifier); batas akurasi dioper dari config server, bukan angka di sini.

import { computed, onScopeDispose, ref } from 'vue';

export type LocationStatus = 'idle' | 'locating' | 'weak' | 'too_weak' | 'ready' | 'denied' | 'unavailable' | 'failed';

export interface GeoPosition {
    lat: number;
    lng: number;
    accuracyMeters: number;
}

export interface UseGeolocationOptions {
    /** Same limit as config('sonar.report.max_accuracy_m'), passed down by the server. */
    maxAccuracyMeters: number;
    timeoutMs?: number;
    /** How long start() keeps watching before it gives up on a fix that never gets accurate enough. */
    giveUpAfterMs?: number;
}

const DEFAULT_TIMEOUT_MS = 15_000;
const DEFAULT_GIVE_UP_AFTER_MS = 30_000;
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
    const giveUpAfterMs = options.giveUpAfterMs ?? DEFAULT_GIVE_UP_AFTER_MS;
    const geolocationOptions: PositionOptions = { enableHighAccuracy: true, timeout: timeoutMs, maximumAge: 0 };

    const status = ref<LocationStatus>('idle');
    const position = ref<GeoPosition | null>(null);
    let watchId: number | null = null;
    let giveUpTimer: ReturnType<typeof setTimeout> | null = null;

    const isReady = computed(() => status.value === 'ready');

    function clearWatch(): void {
        if (giveUpTimer !== null) {
            clearTimeout(giveUpTimer);
            giveUpTimer = null;
        }

        if (watchId !== null) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }
    }

    /**
     * Laptops without GPS can report a coarse fix once and never improve, and some browsers never call back at
     * all. Without this the screen would wait forever with no way to retry (C7).
     */
    function giveUp(): void {
        const wasWeak = status.value === 'weak';
        clearWatch();
        status.value = wasWeak ? 'too_weak' : 'failed';
    }

    function handleError(error: GeolocationPositionError): void {
        clearWatch();
        status.value = error.code === PERMISSION_DENIED ? 'denied' : 'failed';
    }

    /**
     * Watches until a fix is accurate enough, then stops (battery). While the fix is too coarse the status is
     * "weak", so the screen can say "tunggu sebentar" and the user sees it improve on its own (C7). After
     * giveUpAfterMs it stops: "too_weak" when only coarse fixes came in, "failed" when none came in.
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
        giveUpTimer = setTimeout(giveUp, giveUpAfterMs);
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
