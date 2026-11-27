import { describe, expect, it } from 'vitest';

import {
    formatDate,
    formatDateTime,
    formatLength,
    formatNumber,
    formatPercentAbove,
    formatRupiah,
    formatRupiahShort,
} from './format';

describe('formatRupiah', () => {
    it.each([
        [85000, 'Rp85.000'],
        [0, 'Rp0'],
        [105000000, 'Rp105.000.000'],
        [-20000, '-Rp20.000'],
    ])('%d -> %s', (amount, expected) => {
        expect(formatRupiah(amount)).toBe(expected);
    });
});

describe('formatRupiahShort', () => {
    it.each([
        [4_200_000, 'Rp4,2 juta'],
        [12_100_000, 'Rp12,1 juta'],
        [1_000_000, 'Rp1 juta'],
        [999_999, 'Rp999.999'],
        [1_500_000_000, 'Rp1,5 miliar'],
    ])('%d -> %s', (amount, expected) => {
        expect(formatRupiahShort(amount)).toBe(expected);
    });
});

describe('formatPercentAbove', () => {
    it('prints an integer percent with a plus sign', () => {
        expect(formatPercentAbove(31)).toBe('+31%');
        expect(formatPercentAbove(1)).toBe('+1%');
    });
});

describe('WITA dates (UTC+8)', () => {
    it('formats date and time in WITA', () => {
        expect(formatDateTime('2026-12-14T01:15:00Z')).toBe('14 Des 2026, 09.15 WITA');
    });

    // PRD Fitur 9: saved 23.30 WIB (16.30 UTC) shows as next day 00.30 WITA.
    it('rolls over to the next day across the WIB/WITA boundary', () => {
        expect(formatDateTime('2026-12-14T16:30:00Z')).toBe('15 Des 2026, 00.30 WITA');
        expect(formatDate('2026-12-14T16:30:00Z')).toBe('15 Des 2026');
    });

    it('rejects invalid input instead of printing "Invalid Date"', () => {
        expect(() => formatDate('not-a-date')).toThrow(RangeError);
    });
});

describe('formatLength', () => {
    it.each([
        [350, '350 m'],
        [2.5, '2,5 m'],
        [2.25, '2,25 m'],
        [0.15, '15 cm'],
        [0.125, '12,5 cm'],
        [0, '0 m'],
    ])('%d -> %s', (meters, expected) => {
        expect(formatLength(meters)).toBe(expected);
    });
});

describe('formatNumber', () => {
    it.each([
        [12, '12'],
        ['12.000', '12'],
        ['3.5', '3,5'],
        ['0.15', '0,15'],
        ['0.085', '0,085'],
        ['1.5', '1,5'],
        ['8,5', '8,5'],
        ['4200', '4.200'],
        ['', ''],
    ])('%j -> %s', (input, expected) => {
        expect(formatNumber(input)).toBe(expected);
    });
});
