import { formatDate, formatNumber, formatPercentAbove } from './format';

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
    `Ukuran di RAB: ${items.map((m) => `${m.name} ${formatNumber(m.value)} ${m.unit}`).join(', ')}`;

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
    locationHint: (radiusMeters: number) => `Lokasi dicek saat melapor (radius ${radiusMeters} m)`,
    back: 'Kembali ke halaman proyek',
    step: (current: number, total: number, name: string) => `Langkah ${current} dari ${total} · ${name}`,
    steps: { location: 'Lokasi', report: 'Foto & temuan' },
    answers: { match: 'Sesuai', mismatch: 'Tidak', unmeasurable: 'Tidak bisa ukur' },
    recommendations: {
        toggleTitle: (count: number) => `Lihat panduan ukuran di RAB (${count} item)`,
        toggleHint: 'Opsional — buka jika ingin melihat target ukuran & cara cek di lapangan.',
        checkedLabel: 'Sudah dicek',
        howToPrefix: 'Cara cek:',
    },
    finding: {
        question: 'Apa yang kamu temukan?',
        requiredBadge: 'Wajib pilih satu',
        options: {
            match: {
                title: 'Sesuai RAB',
                description: 'Kondisi fisik di lokasi sesuai dengan ukuran di RAB.',
            },
            mismatch: {
                title: 'Ada yang tidak sesuai',
                description: 'Ada ukuran atau kondisi fisik yang berbeda dari RAB.',
            },
            unmeasurable: {
                title: 'Belum bisa menilai',
                description: 'Belum sempat mengukur langsung, tetapi ingin melampirkan foto atau catatan.',
            },
        },
        mismatchPrompt: 'Pilih item yang tidak sesuai',
        mismatchHint: 'Pilih minimal 1 item. Angka temuan bersifat opsional.',
        optionalMeasuredLabel: (unit: string) => `Angka temuan di lapangan dalam ${unit} (opsional)`,
        missingOverall: 'Pilih salah satu kesimpulan temuan sebelum lanjut.',
        missingMismatchItems: 'Pilih minimal satu item yang tidak sesuai.',
        measuredInvalidOptional: 'Isi angka lebih dari 0 (contoh: 8 atau 8,5), atau kosongkan jika tidak mengukur.',
    },
    rabValue: (value: string | number, unit: string) => `RAB: ${formatNumber(value)} ${unit}`,
    measuredLabel: (unit: string) => `Hasil ukur dalam ${unit} (opsional)`,
    unanswered: 'Pilih salah satu jawaban.',
    measuredInvalid: 'Isi hasil ukur dengan angka lebih dari 0.',
    checklistIncomplete: 'Lengkapi jawaban "Apa yang kamu temukan?" dulu sebelum lanjut.',
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
    mismatchTitle: 'Laporan selisih di proyek ini',
    mismatchFraction: (current: number, threshold: number) => `${current} dari ${threshold} warga`,
    mismatchCount: (current: number, threshold: number) =>
        `Laporan selisih di proyek ini: ${current} dari ${threshold}`,
    mismatchHint: (threshold: number) =>
        `Proyek masuk tinjauan BPD setelah ${threshold} warga berbeda mengirim laporan selisih dari lokasi.`,
    thresholdReached: 'Ambang tercapai — proyek ini masuk daftar tinjauan BPD.',
    replaceHint: 'Mengirim lagi akan menggantikan laporanmu sebelumnya pada proyek ini.',
    backToProject: 'Kembali ke halaman proyek',
    sendAgain: 'Kirim laporan baru',
    viewMyReports: 'Lihat di Laporan Saya',
} as const;

/** PRD Fitur Laporan Saya (/laporan-saya): menutup siklus pengawasan warga. */
export const myReports = {
    navLabel: 'Laporan Saya',
    title: 'Laporan Saya',
    subtitle: 'Pantau status laporan Cek Spek dan tindak lanjut BPD pada proyek yang kamu awasi.',
    eyebrow: 'Pengawasan Partisipatif Warga',
    eyebrowVillage: (villageName: string) =>
        `Pengawasan Warga · ${villageName.startsWith('Desa ') ? villageName : `Desa ${villageName}`}`,
    backHome: 'Beranda',
    viewVillageProjects: 'Lihat daftar proyek desa',
    listHeading: (count: number) => `Riwayat laporan (${count})`,
    filters: {
        all: 'Semua',
        active: 'Aktif',
        bpd: 'Dalam & Ditinjau BPD',
    },
    summary: {
        total: 'Total laporan',
        active: 'Aktif dihitung',
        bpd: 'Tindak lanjut BPD',
    },
    summaryHelpers: {
        total: 'Riwayat Cek Spek terkirim',
        active: 'Masuk hitungan siklus aktif',
        bpd: 'Dalam tinjauan atau selesai',
    },
    guide: {
        title: 'Alur tinjauan laporan BPD',
        items: [
            {
                term: 'Aktif dihitung',
                detail: 'Laporan valid terbarumu pada siklus proyek ini masuk hitungan ambang.',
            },
            {
                term: 'Ambang 3 warga',
                detail: 'Tinjauan BPD dimulai otomatis saat 3 warga berbeda mengirim laporan selisih dari lokasi.',
            },
            {
                term: 'Putusan & catatan BPD',
                detail: 'Hasil tinjauan serta catatan lanjutan resmi BPD tampil di sini dan di halaman proyek.',
            },
        ],
        privacy:
            'Identitas dan email pelapor dijaga kerahasiaannya serta tidak pernah ditampilkan ke publik maupun BPD.',
    },
    status: {
        active: 'Aktif dihitung',
        superseded: 'Digantikan',
        not_counted: 'Tidak dihitung',
    },
    statusHints: {
        activeMismatch: 'Masuk hitungan progres ambang tinjauan BPD.',
        activeOther: 'Laporan tercatat aktif; tinjauan BPD dipicu oleh laporan selisih.',
        superseded: 'Digantikan oleh laporan terbarumu pada siklus proyek ini.',
        notCounted: 'Tidak masuk hitungan ambang tinjauan oleh BPD.',
    },
    card: {
        reportedAt: 'Dikirim',
        kindLabel: 'Jenis laporan',
        statusLabel: 'Status laporan',
        yourNote: 'Catatanmu',
        thresholdTitle: 'Progres ambang proyek',
        thresholdFraction: (current: number, threshold: number) => `${current} dari ${threshold} warga`,
        bpdSectionTitle: 'Status Tindak Lanjut BPD',
        bpdWaiting:
            'Belum masuk tinjauan BPD. Tinjauan dimulai otomatis bila terdapat 3 pelapor selisih valid dari lokasi.',
        bpdInReviewBody: 'Laporan warga pada proyek ini sedang diperiksa oleh BPD.',
        bpdFollowUpTitle: 'Catatan lanjutan resmi BPD',
        bpdAfterReviewHint: 'Laporan ini dikirim pada siklus setelah putusan di atas dicatat.',
        viewProject: 'Lihat rincian proyek',
        sendNew: 'Kirim laporan baru',
    },
    session: {
        googleTitle: 'Masuk dengan Google',
        googleHint: 'Akun Google dipakai agar setiap warga dihitung satu kali. Namamu tidak pernah tampil ke publik.',
        logout: 'Keluar',
        loggingOut: 'Keluar...',
    },
    guest: {
        title: 'Masuk untuk melihat riwayat laporanmu',
        description:
            'Masuk dengan akun Google yang sama saat kamu mengirim Cek Spek untuk memantau progres ambang dan hasil tinjauan BPD.',
        privacy: 'Tanpa NIK atau KTP. Email hanya dipakai membedakan akun.',
        loginGoogle: 'Masuk dengan Google',
        tryDemo: 'Coba lewat Mode Demo',
    },
    empty: {
        title: 'Kamu belum mengirim laporan Cek Spek',
        description:
            'Ikut awasi pembangunan fisik desa langsung dari lokasi. Setiap laporan yang kamu kirim dapat dipantau tindak lanjut BPD-nya di sini.',
        demoCta: (projectName: string) => `Coba Cek Spek: ${projectName}`,
        demoVillageCta: 'Lihat daftar proyek desa',
        exploreCta: 'Jelajahi proyek desa',
    },
    emptyFiltered: {
        title: 'Tidak ada laporan di kategori ini',
        description: 'Pilih filter "Semua" untuk melihat seluruh riwayat laporanmu.',
        reset: 'Tampilkan semua',
    },
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
