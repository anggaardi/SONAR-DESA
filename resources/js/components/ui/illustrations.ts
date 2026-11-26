import bpdEmpty from './illustrations/bpd-empty.svg';
import cameraError from './illustrations/ilustrasi-1-kamera.svg';
import documentError from './illustrations/ilustrasi-2-dokumen.svg';
import locationError from './illustrations/ilustrasi-3-lokasi.svg';
import notFound from './illustrations/not-found.svg';
import plangEmpty from './illustrations/plang-empty.svg';
import searchEmpty from './illustrations/search-empty.svg';
import successSeal from './illustrations/success-seal.svg';

export type IllustrationType =
    | 'plang-empty'
    | 'not-found'
    | 'search-empty'
    | 'success-seal'
    | 'bpd-empty'
    | 'camera-error'
    | 'document-error'
    | 'location-error';

export const illustrationSources: Record<IllustrationType, string> = {
    'plang-empty': plangEmpty,
    'not-found': notFound,
    'search-empty': searchEmpty,
    'success-seal': successSeal,
    'bpd-empty': bpdEmpty,
    'camera-error': cameraError,
    'document-error': documentError,
    'location-error': locationError,
};

// Keep decoded HTMLImageElement instances alive so large SVGs paint on first frame when error states mount.
const preloadedImages = new Map<string, HTMLImageElement>();

/**
 * Fetches and decodes the requested EmptyState SVG illustrations in the background before they are mounted.
 */
export function preloadIllustrations(types: readonly IllustrationType[]): void {
    if (typeof window === 'undefined' || typeof Image === 'undefined') {
        return;
    }

    for (const type of types) {
        const src = illustrationSources[type];
        if (preloadedImages.has(src)) {
            continue;
        }

        const image = new Image();
        image.decoding = 'sync';
        image.src = src;
        preloadedImages.set(src, image);
        void image.decode?.().catch(() => {
            // Fall back to standard browser <img> loading if offscreen decode fails or is unsupported.
        });
    }
}
