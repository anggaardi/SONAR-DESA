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
    waitingSignal: 'Menunggu sinyal GPS yang lebih teliti...',
    weak: {
        title: 'Sinyal lokasi lemah',
        body: (accuracyMeters: number) =>
            `Tunggu sebentar. Ketelitian saat ini sekitar ${accuracyMeters} m. Coba keluar ke tempat terbuka.`,
    },
    tooWeak: {
        title: 'Lokasi belum cukup teliti',
        body: (accuracyMeters: number, maxAccuracyMeters: number) =>
            `Ketelitian terbaik sekitar ${accuracyMeters} m, perlu ${maxAccuracyMeters} m atau kurang. Nyalakan GPS dan keluar ke tempat terbuka, lalu coba lagi.`,
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
    farAway: (distance: string, radiusMeters: number) =>
        `Kamu berada ${distance} dari titik proyek. Cek Spek hanya bisa dikirim dalam ${radiusMeters} m dari proyek. Datangi lokasi proyek, lalu cek lokasi lagi.`,
    farAwayUnknown: (radiusMeters: number) =>
        `Kamu berada jauh dari titik proyek. Cek Spek hanya bisa dikirim dalam ${radiusMeters} m dari proyek. Datangi lokasi proyek, lalu cek lokasi lagi.`,
    recheck: 'Cek lokasi lagi',
    checkingDistance: 'Menghitung jarak ke proyek...',
    distanceFailed: {
        title: 'Jarak belum bisa dihitung',
        body: 'Periksa sinyal internetmu, lalu coba lagi. Isianmu tidak hilang.',
    },
} as const;

/** PRD Fitur Inti 5.1 step 4, C3, 8.3: Google sign-in for warga. Keys match the GoogleSignInFailure enum. */
export const googleSignIn = {
    heading: 'Masuk dengan Google',
    retry: 'Coba lagi',
    backHome: 'Kembali ke beranda',
    privacy: 'Email hanya dipakai untuk membedakan akun, tidak tampil ke publik maupun BPD. Tidak perlu NIK atau KTP.',
    reasons: {
        cancelled: {
            title: 'Kamu batal masuk',
            body: 'Cek Spek butuh akun Google supaya satu orang dihitung satu kali. Coba lagi bila ingin melanjutkan.',
        },
        failed: {
            title: 'Masuk dengan Google gagal',
            body: 'Periksa sinyal internetmu, lalu coba lagi.',
        },
        not_allowed: {
            title: 'Akun ini tidak bisa dipakai untuk Cek Spek',
            body: 'Email ini sudah terdaftar untuk akun lain, misalnya akun Pemerintah Desa atau BPD. Pakai akun Google pribadi.',
        },
    },
} as const;

export type GoogleSignInFailure = keyof typeof googleSignIn.reasons;

/** PRD Fitur Inti 5.1 steps 5-8, 5.5, C4-C6: the Cek Spek checklist page. */
export const checkSpec = {
    title: 'Cek Spek',
    start: 'Mulai Cek Spek',
    back: 'Kembali ke halaman proyek',
    step: (current: number, total: number, name: string) => `Langkah ${current} dari ${total} · ${name}`,
    steps: { location: 'Lokasi', measure: 'Ukur', photo: 'Foto & catatan' },
    answers: { match: 'Sesuai', mismatch: 'Tidak', unmeasurable: 'Tidak bisa ukur' },
    rabValue: (value: string, unit: string) => `RAB: ${value} ${unit}`,
    measuredLabel: (unit: string) => `Hasil ukur dalam ${unit} (wajib diisi)`,
    unanswered: 'Pilih salah satu jawaban.',
    measuredInvalid: 'Isi hasil ukur dengan angka lebih dari 0.',
    checklistIncomplete: 'Jawab semua ukuran dulu sebelum lanjut.',
    noMeasurements: 'Proyek ini belum punya ukuran yang bisa dicek warga.',
    next: 'Lanjut',
    previous: 'Kembali',
    noteLabel: (max: number) => `Catatan (opsional, maks ${max} huruf)`,
    notePlaceholder: 'contoh: beton retak di 3 titik',
    noteCounter: (count: number, max: number) => `${count}/${max}`,
    submit: 'Kirim laporan',
    submitNotice: 'Laporan masuk ke BPD, tidak langsung tampil di halaman publik.',
    resend: 'Kirim lagi',
    photoRequired: 'Ambil minimal 1 foto untuk mengirim laporan.',
    sendRefused: 'Laporan belum bisa dikirim',
    sendFailed: {
        title: 'Laporan belum terkirim',
        body: 'Periksa sinyal internetmu, lalu tekan Kirim lagi. Isianmu tidak hilang.',
    },
} as const;

/** PRD Fitur Inti 5.6, C12, M6-05: demo visitors are not at the project, so they use a sample. */
export const demoSample = {
    hint: 'Mode demo: kamu tidak perlu berada di lokasi. Pakai lokasi dan foto contoh untuk mencoba Cek Spek.',
    use: 'Pakai foto & lokasi contoh',
    failed: 'Lokasi contoh belum bisa dimuat. Coba lagi.',
    photoLabel: 'Foto contoh (demo)',
} as const;

/** PRD Fitur Inti 5.1 step 9, C9: confirmation after a Cek Spek report is saved. */
export const reportSubmitted = {
    title: 'Laporanmu masuk ke BPD',
    body: 'Laporan tidak langsung tampil di halaman publik, dan namamu tidak pernah ditampilkan.',
    kindLabel: 'Jenis laporan',
    mismatchCount: (current: number, threshold: number) =>
        `Laporan selisih di proyek ini: ${current} dari ${threshold}`,
    mismatchHint: (threshold: number) =>
        `Proyek masuk tinjauan BPD setelah ${threshold} warga berbeda mengirim laporan selisih dari lokasi.`,
    replaceHint: 'Mengirim lagi akan menggantikan laporanmu sebelumnya.',
    backToProject: 'Kembali ke halaman proyek',
    sendAgain: 'Kirim laporan baru',
} as const;

/** PRD Fitur Inti 6: Ulasan warga yang diterbitkan BPD & catatan resmi BPD. */
export const projectReviews = {
    fabAriaLabel: (count: number) => `Lihat ulasan warga dan catatan BPD (${count} diterbitkan)`,
    dialogTitle: 'Ulasan Warga & Catatan BPD',
    dialogSubtitle: 'Diterbitkan resmi oleh Badan Permusyawaratan Desa (BPD)',
    tabs: {
        all: 'Semua',
        bpd: 'Catatan BPD',
        reports: 'Ulasan Warga',
    },
    /** Village names are stored with their "Desa" prefix ("Desa Contoh"), so it is not added twice. */
    bpdName: (villageName: string | null | undefined) => {
        if (!villageName) {
            return 'BPD';
        }

        return /^desa\s/i.test(villageName) ? `BPD ${villageName}` : `BPD Desa ${villageName}`;
    },
    outcomePrefix: 'Hasil:',
    photos: {
        open: (title: string) => `Buka foto ${title}`,
        viewerCaption: 'Dokumentasi Cek Spek di lokasi',
        close: 'Tutup foto',
    },
    answer: {
        fallbackName: 'Ukuran fisik',
        match: 'Sesuai RAB',
        mismatch: 'Ada selisih',
        notMeasured: 'Tidak diukur',
    },
    bpdSection: {
        title: 'Tinjauan Resmi BPD',
        followUpTitle: 'Catatan Tindak Lanjut BPD',
        officialLabel: 'Badan Permusyawaratan Desa',
        verifiedBadge: 'Diterbitkan BPD',
    },
    citizenSection: {
        title: 'Ulasan & Laporan Warga',
        anonymousReporter: (index: number) => `Warga Pelapor ${index}`,
        meta: 'Laporan Cek Spek di lokasi',
        verifiedBadge: 'Diverifikasi BPD',
    },
    empty: {
        title: 'Belum Ada Ulasan Diterbitkan',
        body: 'BPD belum menerbitkan catatan pemeriksaan atau ulasan warga untuk proyek ini. Warga di sekitar lokasi proyek dapat berpartisipasi dengan Cek Spek.',
        action: 'Cek Spek di Lokasi',
    },
    emptyBpd: {
        title: 'Belum Ada Catatan BPD',
        body: 'BPD belum menerbitkan catatan pemeriksaan atau hasil musyawarah untuk proyek ini. Warga diajak aktif membantu pengawasan dengan Cek Spek.',
        action: 'Cek Spek di Lokasi',
    },
    emptyReports: {
        title: 'Belum Ada Laporan dari Warga',
        body: 'Belum ada warga yang mengirimkan laporan hasil cek spek lapangan. Warga diajak aktif memeriksa kesesuaian fisik proyek.',
        action: 'Mulai Cek Spek Sekarang',
    },
    callout: {
        title: 'Melihat Kejanggalan di Proyek Ini?',
        body: 'Bantu desa mengawasi volume & spesifikasi di lapangan. Laporan warga diverifikasi langsung oleh BPD untuk transparansi anggaran desa.',
        action: 'Mulai Cek Spek di Lokasi',
    },
    error: {
        title: 'Gagal Memuat Data Ulasan',
        body: 'Terjadi gangguan saat memuat data catatan BPD dan ulasan warga. Silakan coba beberapa saat lagi.',
        action: 'Coba Muat Ulang',
    },
    privacyNotice: 'Data pelapor dilindungi kerahasiaannya sesuai UU PDP dan UU No. 6/2014 tentang Desa.',
    close: 'Tutup',
} as const;
