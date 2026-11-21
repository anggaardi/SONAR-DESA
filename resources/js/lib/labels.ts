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

/** PRD Fitur Inti 7: demo doors and banner actions. */
export const demo = {
    banner: demoBanner,
    switchRole: 'Ganti peran',
    restart: 'Mulai ulang demo',
    roles: {
        warga: 'Warga',
        desa: 'Pemerintah Desa',
        bpd: 'BPD',
    },
    tryAs: (role: 'warga' | 'desa' | 'bpd') => `Coba sebagai ${demo.roles[role]}`,
    playingAs: (role: 'warga' | 'desa' | 'bpd') => `Kamu sebagai ${demo.roles[role]}`,
} as const;

export const priceStandardSource = (year: number) => `Standar harga Kab. Gianyar ${year}`;

/** PRD Fitur Inti 5.1 step 6 and 8.3: live camera only, with a reminder not to photograph people. */
export const camera = {
    title: 'Foto lokasi',
    hint: (min: number, max: number) => `Ambil ${min}-${max} foto langsung dari kamera.`,
    faceReminder: 'Jangan memotret wajah orang.',
    open: 'Buka kamera',
    capture: 'Ambil foto',
    retry: 'Coba lagi',
    counter: (count: number, max: number) => `${count} dari ${max} foto`,
    limitReached: 'Batas foto tercapai. Hapus satu foto untuk mengambil ulang.',
    removePhoto: (position: number) => `Hapus foto ${position}`,
    photoAlt: (position: number) => `Foto ${position}`,
    denied: {
        title: 'Kamera belum diizinkan',
        body: 'Aktifkan izin kamera untuk situs ini di pengaturan browser, lalu coba lagi. Isian lainnya tidak hilang.',
    },
    unavailable: {
        title: 'Kamera tidak tersedia',
        body: 'Buka halaman ini di HP yang memiliki kamera dan pastikan alamatnya diawali https.',
    },
    failed: {
        title: 'Kamera belum bisa dibuka',
        body: 'Tutup aplikasi lain yang memakai kamera, lalu coba lagi.',
    },
} as const;

/** PRD Fitur Inti 5.1 steps 1-3 and C1, C2, C7: why location is asked, weak signal, distance hint. */
export const location = {
    title: 'Lokasi kamu',
    why: {
        heading: 'Kenapa lokasi diminta?',
        points: [
            'Untuk memastikan kamu berada di dekat proyek saat mengecek.',
            'Laporanmu masuk ke BPD, tidak langsung tampil di halaman publik.',
            'Namamu tidak ditampilkan ke publik.',
        ],
    },
    allow: 'Izinkan lokasi',
    retry: 'Coba lagi',
    continue: 'Lanjut',
    locating: 'Mencari lokasimu...',
    weak: {
        title: 'Sinyal lokasi lemah',
        body: (accuracyMeters: number) =>
            `Tunggu sebentar. Ketelitian saat ini sekitar ${accuracyMeters} m. Coba keluar ke tempat terbuka.`,
    },
    denied: {
        title: 'Lokasi belum diizinkan',
        body: 'Aktifkan izin lokasi untuk situs ini di pengaturan browser atau HP, lalu coba lagi. Cek Spek tidak bisa dilanjutkan tanpa lokasi.',
    },
    unavailable: {
        title: 'Lokasi tidak tersedia',
        body: 'Buka halaman ini di HP dengan GPS aktif dan pastikan alamatnya diawali https.',
    },
    failed: {
        title: 'Lokasi belum ditemukan',
        body: 'Pastikan GPS aktif, lalu coba lagi.',
    },
    nearby: (distance: string) => `Lokasi aktif: ${distance} dari titik proyek`,
    farAway: (distance: string) =>
        `Kamu berada ${distance} dari titik proyek. Laporan tetap bisa dikirim, tetapi tidak akan dihitung.`,
    farAwayUnknown: 'Kamu berada jauh dari titik proyek. Laporan tetap bisa dikirim, tetapi tidak akan dihitung.',
    checkingDistance: 'Menghitung jarak ke proyek...',
    distanceFailed: {
        title: 'Jarak belum bisa dihitung',
        body: 'Periksa sinyal internetmu, lalu coba lagi. Isianmu tidak hilang.',
    },
} as const;
