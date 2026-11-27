import { describe, expect, it } from 'vitest';
import { parsePlangQrTarget } from './plangQr';

describe('parsePlangQrTarget', () => {
    it('extracts project path from same-origin URL', () => {
        expect(
            parsePlangQrTarget('https://sonardesa.id/p/rabat-beton-jl-melati-rt-02-03', 'https://sonardesa.id'),
        ).toBe('/p/rabat-beton-jl-melati-rt-02-03');
    });

    it('extracts village path from URL without origin constraint', () => {
        expect(parsePlangQrTarget('http://localhost:8080/d/desa-contoh-template')).toBe('/d/desa-contoh-template');
    });

    it('rejects external origin URLs when currentOrigin is specified', () => {
        expect(parsePlangQrTarget('https://evil.example/p/rabat-beton', 'https://sonardesa.id')).toBeNull();
    });

    it('accepts relative project and village paths', () => {
        expect(parsePlangQrTarget('/p/saluran-irigasi-subak-uma-dalem/')).toBe('/p/saluran-irigasi-subak-uma-dalem');
        expect(parsePlangQrTarget('/d/demo-abc123?foo=bar')).toBe('/d/demo-abc123');
    });

    it('converts bare project slug to /p/{slug}', () => {
        expect(parsePlangQrTarget('lampu-jalan-tenaga-surya')).toBe('/p/lampu-jalan-tenaga-surya');
    });

    it('rejects empty, javascript, or invalid inputs', () => {
        expect(parsePlangQrTarget('')).toBeNull();
        expect(parsePlangQrTarget('   ')).toBeNull();
        expect(parsePlangQrTarget('javascript:alert(1)')).toBeNull();
        expect(parsePlangQrTarget('/admin/users')).toBeNull();
    });
});
