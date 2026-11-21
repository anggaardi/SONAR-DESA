import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { fitWithin, MAX_EDGE_PX, useCamera } from './useCamera';

interface FakeCanvas {
    width: number;
    height: number;
    drawn: boolean;
    encodedAs: { type: string; quality: number } | null;
}

let canvases: FakeCanvas[];
let revoked: string[];
let urlCounter: number;

function fakeTrack() {
    return { stop: vi.fn() };
}

function fakeStream(track = fakeTrack()) {
    return { getTracks: () => [track] } as unknown as MediaStream;
}

function fakeVideo(width: number, height: number) {
    return { videoWidth: width, videoHeight: height } as HTMLVideoElement;
}

function stubCamera(getUserMedia: () => Promise<MediaStream>) {
    vi.stubGlobal('navigator', { mediaDevices: { getUserMedia } });
}

beforeEach(() => {
    canvases = [];
    revoked = [];
    urlCounter = 0;

    vi.stubGlobal('document', {
        createElement: () => {
            const canvas: FakeCanvas = { width: 0, height: 0, drawn: false, encodedAs: null };
            canvases.push(canvas);

            return {
                get width() {
                    return canvas.width;
                },
                set width(value: number) {
                    canvas.width = value;
                },
                get height() {
                    return canvas.height;
                },
                set height(value: number) {
                    canvas.height = value;
                },
                getContext: () => ({
                    drawImage: () => {
                        canvas.drawn = true;
                    },
                }),
                toBlob: (callback: (blob: Blob | null) => void, type: string, quality: number) => {
                    canvas.encodedAs = { type, quality };
                    callback(new Blob(['frame'], { type }));
                },
            };
        },
    });
    vi.stubGlobal('URL', {
        createObjectURL: () => `blob:photo-${++urlCounter}`,
        revokeObjectURL: (url: string) => revoked.push(url),
    });
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('fitWithin', () => {
    it.each([
        [4000, 3000, 1600, { width: 1600, height: 1200 }],
        [3000, 4000, 1600, { width: 1200, height: 1600 }],
        [1280, 720, 1600, { width: 1280, height: 720 }],
        [1600, 1600, 1600, { width: 1600, height: 1600 }],
    ])('%d x %d within %d', (width, height, maxEdge, expected) => {
        expect(fitWithin(width, height, maxEdge)).toEqual(expected);
    });
});

describe('start', () => {
    it('asks for the rear camera without audio', async () => {
        const getUserMedia = vi.fn().mockResolvedValue(fakeStream());
        stubCamera(getUserMedia);
        const camera = useCamera();

        await camera.start();

        expect(getUserMedia).toHaveBeenCalledWith({ video: { facingMode: { ideal: 'environment' } }, audio: false });
        expect(camera.status.value).toBe('ready');
        expect(camera.stream.value).not.toBeNull();
    });

    it('is unavailable when the browser has no camera API', async () => {
        vi.stubGlobal('navigator', {});
        const camera = useCamera();

        await camera.start();

        expect(camera.status.value).toBe('unavailable');
    });

    it.each([
        ['NotAllowedError', 'denied'],
        ['SecurityError', 'denied'],
        ['NotFoundError', 'unavailable'],
        ['NotReadableError', 'failed'],
    ])('maps %s to %s', async (errorName, expected) => {
        stubCamera(() => Promise.reject(new DOMException('nope', errorName)));
        const camera = useCamera();

        await camera.start();

        expect(camera.status.value).toBe(expected);
        expect(camera.stream.value).toBeNull();
    });

    it('can be retried after the permission was denied', async () => {
        const getUserMedia = vi
            .fn()
            .mockRejectedValueOnce(new DOMException('nope', 'NotAllowedError'))
            .mockResolvedValueOnce(fakeStream());
        stubCamera(getUserMedia);
        const camera = useCamera();

        await camera.start();
        await camera.start();

        expect(camera.status.value).toBe('ready');
    });
});

describe('capture', () => {
    async function readyCamera(maxPhotos?: number) {
        stubCamera(() => Promise.resolve(fakeStream()));
        const camera = useCamera({ maxPhotos });
        await camera.start();

        return camera;
    }

    it('adds a JPEG photo scaled to the maximum edge', async () => {
        const camera = await readyCamera();

        const added = await camera.capture(fakeVideo(4000, 3000));

        expect(added).toBe(true);
        expect(camera.photos.value).toHaveLength(1);
        expect(camera.photos.value[0]?.blob.type).toBe('image/jpeg');
        expect(canvases[0]).toMatchObject({ width: MAX_EDGE_PX, height: 1200, drawn: true });
        expect(canvases[0]?.encodedAs?.type).toBe('image/jpeg');
    });

    it('requires at least one photo before the form may be sent', async () => {
        const camera = await readyCamera();
        expect(camera.hasMinimum.value).toBe(false);

        await camera.capture(fakeVideo(640, 480));

        expect(camera.hasMinimum.value).toBe(true);
    });

    it('stops accepting photos at the limit', async () => {
        const camera = await readyCamera(3);

        for (let shot = 0; shot < 3; shot++) {
            await camera.capture(fakeVideo(640, 480));
        }
        const fourth = await camera.capture(fakeVideo(640, 480));

        expect(fourth).toBe(false);
        expect(camera.photos.value).toHaveLength(3);
        expect(camera.isFull.value).toBe(true);
        expect(camera.canCapture.value).toBe(false);
    });

    it('does not exceed the limit when two captures overlap', async () => {
        const camera = await readyCamera(1);

        const results = await Promise.all([camera.capture(fakeVideo(640, 480)), camera.capture(fakeVideo(640, 480))]);

        expect(results.filter(Boolean)).toHaveLength(1);
        expect(camera.photos.value).toHaveLength(1);
    });

    it('ignores a capture before the camera is ready', async () => {
        stubCamera(() => Promise.resolve(fakeStream()));
        const camera = useCamera();

        const added = await camera.capture(fakeVideo(640, 480));

        expect(added).toBe(false);
        expect(camera.photos.value).toHaveLength(0);
    });

    it('ignores a capture while the video has no frame yet', async () => {
        const camera = await readyCamera();

        const added = await camera.capture(fakeVideo(0, 0));

        expect(added).toBe(false);
    });
});

describe('remove and release', () => {
    it('frees the preview of a removed photo and allows another shot', async () => {
        stubCamera(() => Promise.resolve(fakeStream()));
        const camera = useCamera({ maxPhotos: 1 });
        await camera.start();
        await camera.capture(fakeVideo(640, 480));
        const photo = camera.photos.value[0]!;

        camera.remove(photo.id);

        expect(revoked).toEqual([photo.previewUrl]);
        expect(camera.photos.value).toHaveLength(0);
        expect(camera.canCapture.value).toBe(true);
    });

    it('stops the camera tracks but keeps the photos', async () => {
        const track = fakeTrack();
        stubCamera(() => Promise.resolve(fakeStream(track)));
        const camera = useCamera();
        await camera.start();
        await camera.capture(fakeVideo(640, 480));

        camera.stop();

        expect(track.stop).toHaveBeenCalledOnce();
        expect(camera.stream.value).toBeNull();
        expect(camera.status.value).toBe('idle');
        expect(camera.photos.value).toHaveLength(1);
    });

    it('releases the camera and every preview on release', async () => {
        const track = fakeTrack();
        stubCamera(() => Promise.resolve(fakeStream(track)));
        const camera = useCamera();
        await camera.start();
        await camera.capture(fakeVideo(640, 480));
        await camera.capture(fakeVideo(640, 480));

        camera.release();

        expect(track.stop).toHaveBeenCalled();
        expect(revoked).toHaveLength(2);
        expect(camera.photos.value).toHaveLength(0);
    });
});

describe('add', () => {
    it('adds a ready-made photo without the camera, within the same limit', () => {
        const camera = useCamera({ maxPhotos: 1 });

        expect(camera.add(new Blob(['sample'], { type: 'image/jpeg' }))).toBe(true);
        expect(camera.add(new Blob(['second'], { type: 'image/jpeg' }))).toBe(false);

        expect(camera.photos.value).toHaveLength(1);
        expect(camera.hasMinimum.value).toBe(true);
        expect(camera.status.value).toBe('idle');
    });
});
