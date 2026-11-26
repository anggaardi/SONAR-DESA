import { describe, expect, it } from 'vitest';
import { getVisibleTiles, latLngToWorldPx, metersToPixels, parseCoordinateText, worldPxToLatLng } from './mapTileMath';

describe('parseCoordinateText', () => {
    it('parses comma-separated coordinates', () => {
        expect(parseCoordinateText('-8.412400, 115.248200')).toEqual({
            lat: -8.4124,
            lng: 115.2482,
        });
    });

    it('parses space-separated coordinates', () => {
        expect(parseCoordinateText('-8.5069 115.2625')).toEqual({
            lat: -8.5069,
            lng: 115.2625,
        });
    });

    it('extracts coordinates from Google Maps URLs', () => {
        expect(parseCoordinateText('https://www.google.com/maps/place/Ubud/@-8.506912,115.262511,17z')).toEqual({
            lat: -8.506912,
            lng: 115.262511,
        });

        expect(parseCoordinateText('https://maps.google.com/?q=-8.4124,115.2482')).toEqual({
            lat: -8.4124,
            lng: 115.2482,
        });
    });

    it('rejects out-of-range or malformed strings', () => {
        expect(parseCoordinateText('')).toBeNull();
        expect(parseCoordinateText('-91.0, 115.2')).toBeNull();
        expect(parseCoordinateText('-8.4, 181.0')).toBeNull();
        expect(parseCoordinateText('Jalan Melati')).toBeNull();
    });
});

describe('Web Mercator conversions', () => {
    it('round-trips lat/lng through world pixels accurately', () => {
        const lat = -8.4124;
        const lng = 115.2482;
        const zoom = 16;

        const px = latLngToWorldPx(lat, lng, zoom);
        const result = worldPxToLatLng(px.x, px.y, zoom);

        expect(result.lat).toBeCloseTo(lat, 6);
        expect(result.lng).toBeCloseTo(lng, 6);
    });

    it('computes positive pixel radius for 300m geofence', () => {
        const radiusPx16 = metersToPixels(300, -8.4124, 16);
        const radiusPx15 = metersToPixels(300, -8.4124, 15);

        expect(radiusPx16).toBeGreaterThan(100);
        expect(radiusPx16).toBeCloseTo(radiusPx15 * 2, 4);
    });

    it('builds visible tile specs covering viewport', () => {
        const tiles = getVisibleTiles(-8.4124, 115.2482, 16, 600, 256);

        expect(tiles.length).toBeGreaterThanOrEqual(4);
        expect(tiles[0]?.url).toMatch(/^https:\/\/tile\.openstreetmap\.org\/16\/\d+\/\d+\.png$/);
    });
});
