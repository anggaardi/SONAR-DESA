import { beforeEach, describe, expect, it, vi } from 'vitest';

import type { DistanceHint } from './useProjectDistance';

interface FakeOptions {
    onSuccess: (data: DistanceHint) => void;
    onError: () => void;
    onHttpException: () => void;
    onNetworkError: () => void;
}

const fakeHttp = {
    lat: 0,
    lng: 0,
    post: vi.fn(),
};

vi.mock('@inertiajs/vue3', () => ({ useHttp: () => fakeHttp }));

const { useProjectDistance } = await import('./useProjectDistance');

const position = { lat: -8.5321, lng: 115.3214 };

beforeEach(() => {
    fakeHttp.lat = 0;
    fakeHttp.lng = 0;
    fakeHttp.post.mockReset();
});

describe('check', () => {
    it('posts the position to the given url and keeps the hint', async () => {
        const hint: DistanceHint = { within_radius: true, distance_m: 20 };
        fakeHttp.post.mockImplementation((_url: string, options: FakeOptions) => {
            options.onSuccess(hint);

            return Promise.resolve(hint);
        });
        const distance = useProjectDistance('/p/rabat-beton-melati/cek/jarak');

        await distance.check(position);

        expect(fakeHttp.post).toHaveBeenCalledWith('/p/rabat-beton-melati/cek/jarak', expect.any(Object));
        expect(fakeHttp.lat).toBe(-8.5321);
        expect(fakeHttp.lng).toBe(115.3214);
        expect(distance.status.value).toBe('ready');
        expect(distance.hint.value).toEqual(hint);
    });

    it('keeps a null distance for a visitor who is far away', async () => {
        const hint: DistanceHint = { within_radius: false, distance_m: null };
        fakeHttp.post.mockImplementation((_url: string, options: FakeOptions) => {
            options.onSuccess(hint);

            return Promise.resolve(hint);
        });
        const distance = useProjectDistance('/x');

        await distance.check(position);

        expect(distance.hint.value).toEqual({ within_radius: false, distance_m: null });
    });

    it.each(['onHttpException', 'onNetworkError', 'onError'] as const)('fails on %s', async (callback) => {
        fakeHttp.post.mockImplementation((_url: string, options: FakeOptions) => {
            options[callback]();

            return Promise.resolve(undefined);
        });
        const distance = useProjectDistance('/x');

        await distance.check(position);

        expect(distance.status.value).toBe('failed');
        expect(distance.hint.value).toBeNull();
    });

    it('fails when the request rejects', async () => {
        fakeHttp.post.mockRejectedValue(new Error('offline'));
        const distance = useProjectDistance('/x');

        await distance.check(position);

        expect(distance.status.value).toBe('failed');
    });

    it('can be retried after a failure and clears the old hint', async () => {
        fakeHttp.post
            .mockRejectedValueOnce(new Error('offline'))
            .mockImplementationOnce((_url: string, options: FakeOptions) => {
                options.onSuccess({ within_radius: true, distance_m: 0 });

                return Promise.resolve();
            });
        const distance = useProjectDistance('/x');

        await distance.check(position);
        await distance.check(position);

        expect(distance.status.value).toBe('ready');
        expect(distance.hint.value?.within_radius).toBe(true);
    });
});
