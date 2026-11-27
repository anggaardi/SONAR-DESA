import { describe, expect, it } from 'vitest';

import type { ProjectMeasurementItem } from '@/types/project';

import {
    budgetGroupOf,
    fundingLabel,
    groupBudget,
    measurementInMeters,
    progressPhrase,
    projectStory,
} from './projectStory';

const measurement = (name: string, value: string, unit: string): ProjectMeasurementItem => ({
    id: 1,
    name,
    value,
    unit,
    how_to: '',
    origin: 'rab',
});

describe('measurementInMeters', () => {
    it.each([
        ['350', 'meter', 350],
        ['15', 'cm', 0.15],
        ['1.2', 'km', 1200],
        ['2,5', 'meter', 2.5],
        ['3', 'M', 3],
    ])('%s %s -> %d', (value, unit, expected) => {
        expect(measurementInMeters(measurement('Panjang', value, unit))).toBe(expected);
    });

    it('returns null for units that are not lengths', () => {
        expect(measurementInMeters(measurement('Jumlah', '12', 'titik'))).toBeNull();
        expect(measurementInMeters(undefined)).toBeNull();
    });
});

describe('progressPhrase', () => {
    it.each([
        [0, 'Pekerjaan belum dimulai.'],
        [65, 'Pekerjaan sudah berjalan 65%.'],
        [100, 'Pekerjaan sudah selesai.'],
    ])('%d%% -> %s', (pct, expected) => {
        expect(progressPhrase(pct)).toBe(expected);
    });
});

describe('fundingLabel', () => {
    it('does not repeat a year that is already in the fund source', () => {
        expect(fundingLabel('Dana Desa 2026', 2026)).toBe('Dana Desa 2026');
        expect(fundingLabel('ADD', 2026)).toBe('ADD 2026');
    });
});

describe('projectStory', () => {
    it('tells what, how long, which fund, and how far along', () => {
        expect(
            projectStory({
                type: 'rabat_beton',
                fund_source: 'Dana Desa 2026',
                year: 2026,
                progress_pct: 65,
                measurements: [measurement('Panjang', '350', 'meter')],
            }),
        ).toBe('Jalan rabat beton sepanjang 350 meter, dibiayai Dana Desa 2026. Pekerjaan sudah berjalan 65%.');
    });

    it('leaves out the length when there is no length measurement', () => {
        expect(
            projectStory({ type: 'gedung', fund_source: 'ADD', year: 2026, progress_pct: 0, measurements: [] }),
        ).toBe('Bangunan, dibiayai ADD 2026. Pekerjaan belum dimulai.');
    });
});

describe('budgetGroupOf', () => {
    it.each([
        ['Semen Gresik 50kg', 'sak', 'material'],
        ['Upah Tukang & Pekerja', 'HOK', 'labor'],
        ['Mandor', 'OH', 'labor'],
        ['Sewa Molen Beton', 'hari', 'equipment'],
    ])('%s (%s) -> %s', (name, unit, expected) => {
        expect(budgetGroupOf({ name, unit })).toBe(expected);
    });
});

describe('groupBudget', () => {
    it('sums each group and returns whole percents that add up to 100', () => {
        const groups = groupBudget([
            { name: 'Semen', unit: 'sak', amount_rp: 32_300_000 },
            { name: 'Pasir', unit: 'm3', amount_rp: 12_600_000 },
            { name: 'Batu Split', unit: 'm3', amount_rp: 17_400_000 },
            { name: 'Upah Tukang & Pekerja', unit: 'HOK', amount_rp: 33_600_000 },
            { name: 'Sewa Molen Beton', unit: 'hari', amount_rp: 9_100_000 },
        ]);

        expect(groups.map((g) => [g.key, g.amountRp, g.percent])).toEqual([
            ['material', 62_300_000, 59],
            ['labor', 33_600_000, 32],
            ['equipment', 9_100_000, 9],
        ]);
    });

    it('drops groups without any amount', () => {
        expect(groupBudget([{ name: 'Semen', unit: 'sak', amount_rp: 1000 }]).map((g) => g.key)).toEqual(['material']);
    });
});
