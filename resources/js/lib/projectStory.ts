// Menerjemahkan data proyek ke bahasa warga untuk halaman rincian proyek (project/Show.vue):
// kalimat pembuka, panjang dalam meter, dan pengelompokan baris RAB.

import type { ProjectBudgetItem, ProjectDetail, ProjectMeasurementItem } from '@/types/project';

const STORY_SUBJECT: Record<string, string> = {
    rabat_beton: 'Jalan rabat beton',
    saluran_irigasi: 'Saluran irigasi',
    gedung: 'Bangunan',
    lampu_jalan: 'Lampu jalan',
};

const METERS_PER_UNIT: Record<string, number> = {
    m: 1,
    meter: 1,
    metre: 1,
    cm: 0.01,
    km: 1000,
};

/** Ukuran pertama yang namanya memuat kata kunci, mis. "panjang". */
export function findMeasurement(
    measurements: ProjectMeasurementItem[],
    keyword: string,
): ProjectMeasurementItem | undefined {
    return measurements.find((m) => m.name.toLowerCase().includes(keyword));
}

/** Nilai ukuran dalam meter, atau null bila satuannya bukan satuan panjang. */
export function measurementInMeters(measurement: ProjectMeasurementItem | undefined): number | null {
    if (!measurement) {
        return null;
    }
    const factor = METERS_PER_UNIT[measurement.unit.trim().toLowerCase()];
    const value = Number(measurement.value);
    if (factor === undefined || Number.isNaN(value)) {
        return null;
    }
    return value * factor;
}

export function progressPhrase(progressPct: number): string {
    if (progressPct <= 0) {
        return 'Pekerjaan belum dimulai.';
    }
    if (progressPct >= 100) {
        return 'Pekerjaan sudah selesai.';
    }
    return `Pekerjaan sudah berjalan ${progressPct}%.`;
}

/** "Dana Desa 2026" tetap; "Dana Desa" menjadi "Dana Desa 2026". */
export function fundingLabel(fundSource: string, year: number): string {
    return fundSource.includes(String(year)) ? fundSource : `${fundSource} ${year}`;
}

/** Kalimat pembuka: apa yang dibangun, seberapa panjang, dari dana apa, sudah sejauh apa. */
export function projectStory(
    project: Pick<ProjectDetail, 'type' | 'fund_source' | 'year' | 'progress_pct' | 'measurements'>,
): string {
    const subject = STORY_SUBJECT[project.type] ?? 'Pembangunan';
    const length = measurementInMeters(findMeasurement(project.measurements, 'panjang'));
    const lengthText = length ? ` sepanjang ${new Intl.NumberFormat('id-ID').format(length)} meter` : '';

    return `${subject}${lengthText}, dibiayai ${fundingLabel(project.fund_source, project.year)}. ${progressPhrase(project.progress_pct)}`;
}

export type BudgetGroupKey = 'material' | 'labor' | 'equipment';

export interface BudgetGroup {
    key: BudgetGroupKey;
    label: string;
    itemNames: string[];
    amountRp: number;
    percent: number;
}

const GROUP_LABELS: Record<BudgetGroupKey, string> = {
    material: 'Bahan',
    labor: 'Upah',
    equipment: 'Sewa alat',
};

const LABOR_UNITS = ['hok', 'oh', 'ob', 'orang hari', 'org'];
const LABOR_NAME = /^(upah|tenaga|mandor|tukang|pekerja)/i;
const EQUIPMENT_NAME = /^sewa\b|\balat\b/i;

/** Kelompok baris RAB dari nama dan satuannya. Bukan data resmi: halaman menyebutnya "dikelompokkan otomatis". */
export function budgetGroupOf(item: Pick<ProjectBudgetItem, 'name' | 'unit'>): BudgetGroupKey {
    const unit = (item.unit ?? '').trim().toLowerCase();
    const name = (item.name ?? '').trim();
    if (LABOR_UNITS.includes(unit) || LABOR_NAME.test(name)) {
        return 'labor';
    }
    if (EQUIPMENT_NAME.test(name)) {
        return 'equipment';
    }
    return 'material';
}

/** Persen bulat yang jumlahnya tepat 100 (metode sisa terbesar). */
function wholePercents(amounts: number[]): number[] {
    const total = amounts.reduce((sum, a) => sum + a, 0);
    if (total <= 0) {
        return amounts.map(() => 0);
    }
    const parts = amounts.map((a, i) => {
        const raw = (a / total) * 100;
        return { i, whole: Math.floor(raw), rest: raw - Math.floor(raw) };
    });
    let remaining = 100 - parts.reduce((sum, p) => sum + p.whole, 0);
    for (const part of [...parts].sort((a, b) => b.rest - a.rest)) {
        if (remaining <= 0) {
            break;
        }
        part.whole += 1;
        remaining -= 1;
    }
    const floors = parts.map((p) => p.whole);
    return floors;
}

/** Kelompok anggaran berurutan bahan, upah, sewa alat; kelompok tanpa nilai tidak ditampilkan. */
export function groupBudget(items: Pick<ProjectBudgetItem, 'name' | 'unit' | 'amount_rp'>[]): BudgetGroup[] {
    const order: BudgetGroupKey[] = ['material', 'labor', 'equipment'];
    const groups = order
        .map((key) => {
            const members = items.filter((item) => budgetGroupOf(item) === key && (item.amount_rp ?? 0) > 0);
            return {
                key,
                label: GROUP_LABELS[key],
                itemNames: members.map((m) => m.name),
                amountRp: members.reduce((sum, m) => sum + (m.amount_rp ?? 0), 0),
                percent: 0,
            };
        })
        .filter((g) => g.amountRp > 0);

    const percents = wholePercents(groups.map((g) => g.amountRp));
    return groups.map((g, i) => ({ ...g, percent: percents[i] ?? 0 }));
}
