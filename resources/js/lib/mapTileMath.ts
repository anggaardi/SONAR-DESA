export interface Coordinates {
    lat: number;
    lng: number;
}

export interface TileSpec {
    x: number;
    y: number;
    z: number;
    leftPx: number;
    topPx: number;
    url: string;
}

const TILE_SIZE = 256;
const EARTH_CIRCUMFERENCE_METERS_AT_EQUATOR = 40075016.68557849;
const MAX_MERCATOR_LAT = 85.05112878;

export function clampLatitude(lat: number): number {
    return Math.max(-MAX_MERCATOR_LAT, Math.min(MAX_MERCATOR_LAT, lat));
}

export function normalizeLongitude(lng: number): number {
    const wrapped = ((((lng + 180) % 360) + 360) % 360) - 180;
    return wrapped === -180 && lng > 0 ? 180 : wrapped;
}

export function isValidCoordinates(lat: number, lng: number): boolean {
    return Number.isFinite(lat) && Number.isFinite(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180;
}

/**
 * Parses coordinate inputs such as "-8.4124, 115.2482", "-8.4124 115.2482",
 * or Google Maps URLs containing "@-8.4124,115.2482" or "q=-8.4124,115.2482".
 */
export function parseCoordinateText(input: string): Coordinates | null {
    const trimmed = input.trim();
    if (!trimmed) {
        return null;
    }

    const urlMatch = trimmed.match(/(?:@|[?&](?:q|ll)=)(-?\d+(?:\.\d+)?)[,\s]+(-?\d+(?:\.\d+)?)/i);
    const pairMatch = urlMatch ?? trimmed.match(/^(-?\d+(?:\.\d+)?)\s*[,\s;]\s*(-?\d+(?:\.\d+)?)$/);
    if (!pairMatch) {
        return null;
    }

    const lat = Number(pairMatch[1]);
    const lng = Number(pairMatch[2]);
    if (!isValidCoordinates(lat, lng)) {
        return null;
    }

    return { lat, lng };
}

export function latLngToWorldPx(lat: number, lng: number, zoom: number): { x: number; y: number } {
    const scale = TILE_SIZE * Math.pow(2, zoom);
    const clampedLat = clampLatitude(lat);
    const sinLat = Math.sin((clampedLat * Math.PI) / 180);

    const x = ((lng + 180) / 360) * scale;
    const y = (0.5 - Math.log((1 + sinLat) / (1 - sinLat)) / (4 * Math.PI)) * scale;

    return { x, y };
}

export function worldPxToLatLng(x: number, y: number, zoom: number): Coordinates {
    const scale = TILE_SIZE * Math.pow(2, zoom);
    const rawLng = (x / scale) * 360 - 180;
    const n = Math.PI - (2 * Math.PI * y) / scale;
    const lat = (180 / Math.PI) * Math.atan(0.5 * (Math.exp(n) - Math.exp(-n)));

    return {
        lat: Math.max(-90, Math.min(90, lat)),
        lng: normalizeLongitude(rawLng),
    };
}

export function metersToPixels(meters: number, lat: number, zoom: number): number {
    const scale = TILE_SIZE * Math.pow(2, zoom);
    const metersPerPixel =
        (EARTH_CIRCUMFERENCE_METERS_AT_EQUATOR * Math.cos((clampLatitude(lat) * Math.PI) / 180)) / scale;

    if (metersPerPixel <= 0) {
        return 0;
    }

    return meters / metersPerPixel;
}

export function getVisibleTiles(
    centerLat: number,
    centerLng: number,
    zoom: number,
    viewportWidth: number,
    viewportHeight: number,
): TileSpec[] {
    const maxTileIndex = Math.pow(2, zoom);
    const centerPx = latLngToWorldPx(centerLat, centerLng, zoom);
    const topLeftX = centerPx.x - viewportWidth / 2;
    const topLeftY = centerPx.y - viewportHeight / 2;

    const minTileX = Math.floor(topLeftX / TILE_SIZE);
    const maxTileX = Math.floor((topLeftX + viewportWidth) / TILE_SIZE);
    const minTileY = Math.max(0, Math.floor(topLeftY / TILE_SIZE));
    const maxTileY = Math.min(maxTileIndex - 1, Math.floor((topLeftY + viewportHeight) / TILE_SIZE));

    const tiles: TileSpec[] = [];
    for (let tx = minTileX; tx <= maxTileX; tx++) {
        const wrappedX = ((tx % maxTileIndex) + maxTileIndex) % maxTileIndex;
        for (let ty = minTileY; ty <= maxTileY; ty++) {
            tiles.push({
                x: wrappedX,
                y: ty,
                z: zoom,
                leftPx: tx * TILE_SIZE - topLeftX,
                topPx: ty * TILE_SIZE - topLeftY,
                url: `https://tile.openstreetmap.org/${zoom}/${wrappedX}/${ty}.png`,
            });
        }
    }

    return tiles;
}
