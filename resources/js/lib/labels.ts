// UI text that must match PRD Fitur Inti 8.2 exactly. All screens read from here (PRD Arsitektur 9.2).
// Neutral language only: words like "korupsi" or "mark-up" never appear (D9, checked in CI).
// Keys mirror the backend enum values so Resources can send the value and Vue picks the text.

import { formatDate, formatPercentAbove } from './format';

export const priceLabel = {
    within_standard: 'Sesuai',
    above_standard: (percent: number) => `Di atas standar ${formatPercentAbove(percent)}`,
    no_reference: 'Tanpa acuan',
    needs_check: 'Perlu dicek',
} as const;

export const auditStatus = {
    draft: 'Draf',
    reading: 'Membaca',
    needs_review: 'Perlu diperiksa',
    ready: 'Siap terbit',
    published: 'Terbit',
    read_failed: 'Gagal baca',
} as const;

export const reportType = {
    mismatch: 'Selisih',
    match: 'Sesuai',
    unmeasurable: 'Tidak bisa diukur',
} as const;

export const reviewOutcome = {
    followed_up: 'Ditindaklanjuti',
    no_mismatch: 'Tidak ada selisih',
} as const;

export type ReviewOutcome = keyof typeof reviewOutcome;

/** Status publik. "none" deliberately has no text: no badge, never green (PRD Fitur 4.3). */
export const publicStatus = {
    none: null,
    in_review: (sinceIso: string) => `Dalam tinjauan BPD sejak ${formatDate(sinceIso)}`,
    reviewed: (outcome: ReviewOutcome) => `Ditinjau BPD: ${reviewOutcome[outcome]}`,
} as const;

export const projectType = {
    rabat_beton: 'Rabat beton',
    saluran_irigasi: 'Saluran / irigasi',
    gedung: 'Gedung',
    lampu_jalan: 'Lampu jalan',
    lainnya: 'Lainnya',
} as const;

export const citizenCount = (n: number) => `Dicek warga di lokasi: ${n} orang`;

export interface Measurement {
    name: string;
    value: string;
    unit: string;
}

/** PRD Fitur 8.2: "Ukuran di RAB: panjang 200 m, lebar 3 m, tebal 12 cm". */
export const measurementsInRab = (items: Measurement[]) =>
    `Ukuran di RAB: ${items.map((m) => `${m.name} ${m.value} ${m.unit}`).join(', ')}`;

export const document = {
    viewOriginal: 'Lihat dokumen asli',
    readFrom: 'Tabel di halaman ini dibaca dari dokumen ini',
} as const;

export const demoBanner = 'Data demo - desa & angka fiktif';

export const priceStandardSource = (year: number) => `Standar harga Kab. Gianyar ${year}`;
