/**
 * Parse a QR payload or manual plang input into a safe same-app path (/p/{slug} or /d/{slug}).
 */
export function parsePlangQrTarget(rawInput: string, currentOrigin?: string): string | null {
    const trimmed = rawInput.trim();
    if (!trimmed) {
        return null;
    }

    const validPath = /^\/(p|d)\/([a-zA-Z0-9][a-zA-Z0-9_-]*)\/?$/;

    // Direct relative path (/p/slug or /d/village-slug)
    if (trimmed.startsWith('/')) {
        const cleanPath = trimmed.split(/[?#]/)[0] ?? '';
        const match = cleanPath.match(validPath);
        return match ? `/${match[1]}/${match[2]}` : null;
    }

    // Full URL (must match currentOrigin when provided, or http(s) URL carrying /p/... or /d/...)
    if (/^https?:\/\//i.test(trimmed)) {
        try {
            const parsed = new URL(trimmed);
            if (currentOrigin && parsed.origin !== currentOrigin) {
                return null;
            }
            const match = parsed.pathname.match(validPath);
            return match ? `/${match[1]}/${match[2]}` : null;
        } catch {
            return null;
        }
    }

    // Bare project slug (e.g. "rabat-beton-jl-melati-rt-02-03")
    if (/^[a-zA-Z0-9][a-zA-Z0-9_-]{1,120}$/.test(trimmed)) {
        return `/p/${trimmed}`;
    }

    return null;
}
