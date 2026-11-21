import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { useGeolocation } from './useGeolocation';

type Success = (position: GeolocationPosition) => void;
type Failure = (error: GeolocationPositionError) => void;

let onSuccess: Success;
let onFailure: Failure;
let watchPosition: ReturnType<typeof vi.fn>;
let getCurrentPosition: ReturnType<typeof vi.fn>;
let clearWatch: ReturnType<typeof vi.fn>;

const fix = (accuracy: number, lat = -8.5321, lng = 115.3214) =>
    ({ coords: { latitude: lat, longitude: lng, accuracy } }) as GeolocationPosition;

const failure = (code: number) => ({ code }) as GeolocationPositionError;

function lowAccuracyThreshold() {
    return useGeolocation({ maxAccuracyMeters: 100 });
}

beforeEach(() => {
    watchPosition = vi.fn((success: Success, error: Failure) => {
        onSuccess = success;
        onFailure = error;

        return 7;
    });
    getCurrentPosition = vi.fn();
    clearWatch = vi.fn();
    vi.stubGlobal('navigator', { geolocation: { watchPosition, getCurrentPosition, clearWatch } });
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('start', () => {
    it('asks for a high accuracy position without using a cached one', () => {
        const geo = useGeolocation({ maxAccuracyMeters: 100, timeoutMs: 8000 });

        geo.start();

        expect(watchPosition).toHaveBeenCalledWith(expect.any(Function), expect.any(Function), {
            enableHighAccuracy: true,
            timeout: 8000,
            maximumAge: 0,
        });
        expect(geo.status.value).toBe('locating');
    });

    it('is ready and stops watching once the accuracy is good enough', () => {
        const geo = lowAccuracyThreshold();
        geo.start();

        onSuccess(fix(12));

        expect(geo.status.value).toBe('ready');
        expect(geo.isReady.value).toBe(true);
        expect(geo.position.value).toEqual({ lat: -8.5321, lng: 115.3214, accuracyMeters: 12 });
        expect(clearWatch).toHaveBeenCalledWith(7);
    });

    it('accepts an accuracy of exactly the limit', () => {
        const geo = lowAccuracyThreshold();
        geo.start();

        onSuccess(fix(100));

        expect(geo.status.value).toBe('ready');
    });

    it('stays weak and keeps watching while the accuracy is over the limit', () => {
        const geo = lowAccuracyThreshold();
        geo.start();

        onSuccess(fix(100.1));

        expect(geo.status.value).toBe('weak');
        expect(geo.position.value?.accuracyMeters).toBe(100.1);
        expect(clearWatch).not.toHaveBeenCalled();
    });

    it('becomes ready by itself when a weak fix improves', () => {
        const geo = lowAccuracyThreshold();
        geo.start();

        onSuccess(fix(250));
        onSuccess(fix(40));

        expect(geo.status.value).toBe('ready');
        expect(geo.position.value?.accuracyMeters).toBe(40);
    });

    it('does not start a second watch while one is running', () => {
        const geo = lowAccuracyThreshold();

        geo.start();
        geo.start();

        expect(watchPosition).toHaveBeenCalledOnce();
    });

    it('reports denied when the permission is refused', () => {
        const geo = lowAccuracyThreshold();
        geo.start();

        onFailure(failure(1));

        expect(geo.status.value).toBe('denied');
        expect(clearWatch).toHaveBeenCalledWith(7);
    });

    it.each([
        [2, 'position unavailable'],
        [3, 'timeout'],
    ])('reports failed on error code %d (%s)', (code) => {
        const geo = lowAccuracyThreshold();
        geo.start();

        onFailure(failure(code));

        expect(geo.status.value).toBe('failed');
    });

    it('can be retried after a failure and clears the old position', () => {
        const geo = lowAccuracyThreshold();
        geo.start();
        onSuccess(fix(300));
        onFailure(failure(3));

        geo.start();

        expect(geo.status.value).toBe('locating');
        expect(geo.position.value).toBeNull();
        expect(watchPosition).toHaveBeenCalledTimes(2);
    });

    it('is unavailable when the browser has no geolocation API', () => {
        vi.stubGlobal('navigator', {});
        const geo = lowAccuracyThreshold();

        geo.start();

        expect(geo.status.value).toBe('unavailable');
    });
});

describe('stop', () => {
    it('stops watching and goes back to idle while still searching', () => {
        const geo = lowAccuracyThreshold();
        geo.start();

        geo.stop();

        expect(clearWatch).toHaveBeenCalledWith(7);
        expect(geo.status.value).toBe('idle');
    });

    it('keeps a finished position after stop', () => {
        const geo = lowAccuracyThreshold();
        geo.start();
        onSuccess(fix(10));

        geo.stop();

        expect(geo.status.value).toBe('ready');
        expect(geo.position.value).not.toBeNull();
    });
});

describe('readFresh', () => {
    it('returns a new reading for submit time', async () => {
        getCurrentPosition.mockImplementation((success: Success) => success(fix(20, -8.53, 115.32)));
        const geo = lowAccuracyThreshold();

        const reading = await geo.readFresh();

        expect(reading).toEqual({ lat: -8.53, lng: 115.32, accuracyMeters: 20 });
        expect(getCurrentPosition).toHaveBeenCalledWith(expect.any(Function), expect.any(Function), {
            enableHighAccuracy: true,
            timeout: 15_000,
            maximumAge: 0,
        });
    });

    it('returns null when the position cannot be read', async () => {
        getCurrentPosition.mockImplementation((_success: Success, error: Failure) => error(failure(1)));
        const geo = lowAccuracyThreshold();

        expect(await geo.readFresh()).toBeNull();
    });

    it('returns null when the browser has no geolocation API', async () => {
        vi.stubGlobal('navigator', {});
        const geo = lowAccuracyThreshold();

        expect(await geo.readFresh()).toBeNull();
    });
});
