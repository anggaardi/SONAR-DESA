import { afterEach, describe, expect, it, vi } from 'vitest';

import { illustrationSources, preloadIllustrations } from './illustrations';

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('preloadIllustrations', () => {
    it('is safe when window or Image is unavailable', () => {
        expect(() => preloadIllustrations(['location-error'])).not.toThrow();
    });

    it('creates and decodes one Image per unique illustration type', () => {
        const created: Array<{ src: string; decoding: string; decode: ReturnType<typeof vi.fn> }> = [];

        class MockImage {
            src = '';
            decoding = '';
            decode = vi.fn().mockResolvedValue(undefined);

            constructor() {
                created.push(this);
            }
        }

        vi.stubGlobal('window', {});
        vi.stubGlobal('Image', MockImage);

        preloadIllustrations(['location-error', 'camera-error', 'location-error']);

        expect(created).toHaveLength(2);
        expect(created[0]?.src).toBe(illustrationSources['location-error']);
        expect(created[0]?.decoding).toBe('sync');
        expect(created[0]?.decode).toHaveBeenCalledOnce();
        expect(created[1]?.src).toBe(illustrationSources['camera-error']);
        expect(created[1]?.decode).toHaveBeenCalledOnce();
    });
});
