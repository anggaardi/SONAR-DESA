// Script generator Feature Spec & Arsitektur Teknis Sonar Desa
// Menggunakan docx-kit dari lean-feature-spec
const path = require('path');
const kit = require('/home/kadekangga/Projects/MEMSKILL/feature-spec/scripts/lib/docx-kit');

const OUT = path.resolve(__dirname, 'FeatureSpec-Sonar-Desa-Arsitektur.docx');
const IMG = path.resolve(__dirname, 'img');

const k = kit.create({
  title: 'Sonar Desa - Arsitektur & Spesifikasi Modul',
  version: '1.0',
  imgDir: IMG,
});

const {
  THEME, add, P, Small, H1, H2, H3, Bullets, Numbered, Checks, Tbl, Callout, Code, Figure,
  cover, toc, scopeTable, storyHeader, AC, TC, pageBreak,
} = k;

// ==========================================
// COVER
// ==========================================
add(cover({
  eyebrow: 'FEATURE SPEC & ARSITEKTUR TEKNIS',
  heading: 'Sonar Desa: Fondasi Arsitektur & Spesifikasi Modul',
  subtitle: 'Arsitektur Laravel 11 + Vue 3 (Inertia.js), Lingkungan Docker Zero-Issue, Pipeline AI Deterministik, dan Spesifikasi Teknis 5 Modul untuk Techcomfest 2027',
  meta: [
    ['Status', 'Disepakati (Acuan Eksekusi)'], ['Versi', '1.0'],
    ['Owner', 'Dev 1 (Ketua Tim)'], ['Tanggal', '17 November 2026'],
    ['Pelaksana', '2 Developer (Dev 1, Dev 2)'], ['Estimasi', '32 hari kalender (5 story)'],
    ['Repo', '`SONAR-DESA` (Laravel 11 + Inertia Vue 3)'], ['Epic', 'SD - Sonar Desa MVP'],
  ],
}));

add(Callout('gray', 'Dokumen acuan implementasi teknis', [
  'Dokumen ini merupakan penjabaran teknis implementatif dari **PRD Sonar Desa v1.0** dan **PRD Fitur Inti v1.1**, dengan mengadopsi standar rekayasa software pemenang dari codebase acuan **MARGI AI**. Dokumen ini menjadi pedoman arsitektur sistem, struktur database, pipeline AI, pembagian kerja, dan acceptance criteria bagi Dev 1 dan Dev 2.',
]));

add(H3('Cara membaca dokumen ini'));
add(Tbl(['Peran', 'Wajib baca', 'Fokus implementasi'], [
  ['**Berdua (Dev 1 & Dev 2)**', 'Bagian 1, 2, 3, 4, 6.1 (Docker), 8 (Instruksi Dev), 11, 12', 'Paham arsitektur bersama, konvensi kode, & DoD'],
  ['**Dev 1 (Ketua Tim)**', 'Bagian 6.2 (Modul 2, 3, 5), Bagian 9 (S1, S3, S5), Bagian 10 (TC S1, S3, S5)', 'Frontend Vue, Geolocation, Kamera HP, Halaman Publik, Demo Juri'],
  ['**Dev 2 (Backend & AI)**', 'Bagian 6.2 (Modul 1, 4), Bagian 6.6 (AI Pure Engine), Bagian 9 (S2, S4), Bagian 10 (TC S2, S4)', 'Laravel Backend, OCR AI Queue Job, Math Engine, Dashboard BPD'],
], [22, 43, 35]));

add(toc([
  'TL;DR',
  'Latar belakang, tujuan & metrik',
  'Scope',
  'Keputusan yang disepakati',
  'Kondisi sekarang vs target',
  'Desain solusi',
  'Edge cases & aturan sistem',
  'Instruksi untuk Developer',
  'Epic & Story breakdown',
  'QA test cases per story',
  'Rollout, rollback & monitoring',
  'Risiko & open questions',
  'Lampiran: Setup Docker & checklist submit',
]));
add(pageBreak());

// ==========================================
// 1. TL;DR
// ==========================================
add(H1('TL;DR'));
add(Callout('note', null, [
  '**Masalah.** Penyelewengan anggaran dan proyek fiktif merupakan modus korupsi terbesar di Indonesia (ICW 2024: 187 kasus penyalahgunaan, 42 proyek fiktif, 33 mark-up). Akar masalah di tingkat desa adalah asimetri informasi: hanya penyelenggara desa yang mengetahui rincian anggaran, papan proyek fisik hanya menampilkan total dana gelondongan, dan BPD kekurangan instrumen audit independen.',
  '**Solusi.** Sonar Desa memposisikan diri untuk membuka janji tertulis (RAB) ke hadapan warga dan memberi warga instrumen terukur untuk membuktikan janji fisiknya langsung di lokasi proyek. Menggunakan AI Document Intelligence untuk mengekstrak RAB menjadi tabel terstruktur dalam hitungan detik, mencocokkan harga terhadap standar acuan resmi, dan menerbitkan rinciannya ke publik lewat QR di papan proyek. Warga dapat melakukan **Cek Spek** fisik (verifikasi GPS <= 300 m, kamera langsung, dan deteksi proyek fiktif), dan laporan masuk secara objektif ke **Dashboard BPD**.',
  '**Kenapa Scalable & Anti-Gagal.** Sistem menganut prinsip *"Numbers by code, words by AI"*: AI hanya membaca teks, sementara seluruh kalkulasi selisih, markup %, dan validasi integritas matematika dihitung 100% oleh kode PHP deterministik. Seluruh stack diikat menggunakan Docker Compose (PHP 8.3 FPM, Nginx, PostgreSQL 16, Redis 7, MinIO S3, Queue Worker) sehingga terbebas dari masalah "it works on my machine", serta dilengkapi Mode Demo terisolasi per sesi agar juri lomba dapat mencoba seluruh fitur tanpa login Google dan tanpa perlu berada di Bali.',
]));
add(Figure('d1-arsitektur-docker.png', 'Gambar 1 - Topologi Arsitektur Docker Compose (Local Development & VPS Deployment)'));

// ==========================================
// 2. Latar belakang, tujuan & metrik
// ==========================================
add(H1('Latar belakang, tujuan & metrik'));
add(H2('Masalah'));
add(Bullets([
  '**Penyelewengan anggaran & proyek fiktif dominan:** Rekap penindakan ICW 2024 mencatat 187 kasus penyalahgunaan anggaran, 42 proyek fiktif, dan 33 kasus mark-up harga di Indonesia. Sektor desa menjadi titik rawan karena dokumen rincian anggaran hanya dipegang segelintir aparatur desa.',
  '**Modus di proyek infrastruktur desa:** Spesifikasi fisik dikurangi (ketebalan jalan rabat beton berkurang dari RAB), proyek fiktif/tidak ditemukan di titik koordinat, atau harga satuan dinaikkan di atas acuan resmi.',
  '**Asimetri informasi publik:** Warga hanya melihat papan proyek bertuliskan "Pembangunan Jalan Desa - Rp105.000.000", tanpa tahu berapa harga satuan pekerjaan atau ketebalan volume beton yang dijanjikan dalam APBDes/RAB.',
  '**Ketiadaan instrumen pengawasan bagi BPD:** BPD memegang dokumen hasil musyawarah desa namun kesulitan memeriksa ratusan item harga secara manual terhadap dokumen acuan standar harga daerah.',
]));

add(H2('Tujuan'));
add(Tbl(['#', 'Tujuan'], [
  ['**G1**', 'Juri Techcomfest 2027 dapat mengevaluasi fitur andalan (Audit RAB) dalam 1 menit tanpa login akun pribadi.'],
  ['**G2**', 'Warga desa dapat melihat rincian harga wajar dan batas fisik ("Ukuran di RAB") langsung dari HP via scan QR papan proyek.'],
  ['**G3**', 'Kecurigaan warga diubah menjadi laporan berbukti fisik (foto kamera + koordinat lokasi) yang masuk ke BPD tanpa tuduhan publik liar.'],
  ['**G4**', 'Pengembangan selesai tepat waktu oleh 2 developer dengan lingkungan Docker zero-drift dan submit H-1 (17 Des 2026).'],
], [8, 92]));

add(H2('Metrik Keberhasilan'));
add(Tbl(['Metrik', 'Target', 'Cara Mengukur'], [
  ['Buka halaman audit contoh (demo)', '≤ 2 detik', 'Pengujian browser tanpa panggilan API AI'],
  ['Audit RAB unggahan baru (± 40 item)', '≤ 20 detik', 'Waktu proses job AI OCR di background queue'],
  ['Akurasi ekstraksi AI pada 3 RAB uji', '≥ 90% baris benar', 'Pengujian spike terhadap kunci jawaban spreadsheet'],
  ['First Contentful Paint Halaman Publik', '≤ 3 detik di 4G', 'Pengujian Lighthouse / Chrome DevTools throttling pada layar 360 px'],
  ['Zero Environment Mismatch', '100% identik', 'Dijalankan via skrip `docker compose up -d` di laptop Dev 1, Dev 2, dan VPS'],
], [35, 25, 40], { size: 17 }));

// ==========================================
// 3. Scope
// ==========================================
add(H1('Scope'));
add(Callout('warn', 'Jaga Scope — Pagar Pembatas Kompetisi', [
  'Tim hanya memiliki 32 hari kalender dengan 2 developer. Fitur di kolom **OUT** tidak boleh disentuh sama sekali sampai kompetisi selesai. Fitur di kolom **LATER** disimpan untuk roadmap pasca-lomba.',
]));

add(scopeTable({
  inScope: [
    'Fondasi Monolith Modern: Laravel 11 + Inertia.js v2 (Vue 3) + Tailwind CSS v4.',
    'Docker Compose: Nginx, PHP 8.3 FPM, Queue Worker, PostgreSQL 16, Redis 7, MinIO S3, Mailpit.',
    'Fitur 1: Audit RAB (Upload PDF/Scan, Gemini 1.5 Flash extraction, Pure Math Engine, Review Baris Kuning, Terbitkan).',
    'Fitur 2: Halaman Proyek Publik (Mobile 360 px, status netral, blok "Ukuran di RAB", viewer dokumen asli lazy-load, cetak poster A4 QR).',
    'Fitur 3: Cek Spek Warga (Google Socialite, Geolocation lock <= 300 m, Kamera HTML5, opsi "Proyek Tidak Ditemukan/Fiktif", proteksi 1 perangkat 1 pelapor via hashed ID).',
    'Fitur 4: Dashboard BPD (Daftar prioritas, review Pelapor A/B/C, eskalasi ambang 3 laporan valid, cluster alert laporan berdekatan 15m/20m, catatan append-only).',
    'Fitur 5: Mode Demo Juri (4 pintu masuk 1-klik, dataset Desa Contoh Gianyar, isolasi session Redis, reset demo, mode offline Semarang).',
    'Fitur 6: Penerbitan Mandiri BPD (Wewenang BPD menerbitkan RAB musdes berlabel "Diterbitkan BPD, bukan Pemerintah Desa").',
    'Fitur 7: Kepatuhan UU PDP (Layar persetujuan/consent, sanitasi EXIF foto, worker penghapusan koordinat GPS mentah pasca-siklus).',
    'Standar waktu terpusat WITA (Asia/Makassar) dan katalog Perbup Gianyar 2026 (30-50 item).',
  ],
  out: [
    'Integrasi otomatis ke Siskeudes Kemendagri / BPKP.',
    'Integrasi langsung API Jaga Desa Kejaksaan (belum ada API publik; BPD membawa laporan fisik).',
    'Verifikasi identitas dengan NIK / KTP warga.',
    'Notifikasi SMS / WhatsApp Gateway.',
    'Aplikasi mobile native (Android Java/Kotlin atau iOS Swift).',
    'Multi-kabupaten di luar Kabupaten Gianyar.',
    'Form pendaftaran mandiri desa umum (akun dibuat tim via seeder).',
    'Chatbot interaktif tanya-jawab AI.',
  ],
  later: [
    'Integrasi API resmi Jaga Desa Kejaksaan RI & Siskeudes.',
    'Kewajiban regulasi unggah RAB via Perbup / Surat Edaran Kejari.',
    'Ekspansi multi-kabupaten & penyesuaian inflasi tahunan.',
    'Dashboard analitik untuk Inspektorat Daerah & Kemendesa.',
    'Skor keterbukaan publik untuk indikator Desa Antikorupsi KPK.',
    'PWA offline-first dengan sinkronisasi background.',
  ],
}));

// ==========================================
// 4. Keputusan yang disepakati
// ==========================================
add(H1('Keputusan yang sudah disepakati'));
add(Tbl(['#', 'Keputusan', 'Alasan Teknis & Produk', 'Sumber'], [
  ['**D1**', 'Stack: Laravel 11 + Inertia.js v2 (Vue 3)', 'Eliminasi duplikasi API glue DTO, otentikasi session terintegrasi, delivery 2x lebih cepat untuk 2 dev.', 'Tim'],
  ['**D2**', 'Dockerisasi Penuh dari Hari Pertama', 'Mencegah dev-drift antara laptop Dev 1, Dev 2, dan server hosting juri.', 'Tim'],
  ['**D3**', 'Prinsip "Numbers by code, words by AI"', 'AI hanya untuk ekstraksi teks OCR. Perhitungan selisih, markup %, dan validasi baris dikerjakan 100% oleh Pure PHP.', 'Desain (MARGI)'],
  ['**D4**', 'Database PostgreSQL 16 + JSONB', 'Mendukung penyimpanan riwayat revisi dan parsed items secara fleksibel tanpa migrasi berulang.', 'Desain'],
  ['**D5**', 'MinIO sebagai Object Storage Lokal', 'Simulasi S3 lokal untuk dokumen RAB dan foto kamera; tidak bergantung path folder lokal host OS.', 'Desain'],
  ['**D6**', 'Google OAuth tanpa NIK/KTP', 'Menghilangkan rasa takut warga akan intimidasi aparat desa; email pelapor disamarkan jadi Pelapor A/B/C.', 'PRD Induk'],
  ['**D7**', 'Validasi Lokasi <= 300 m & Kamera Langsung', 'Mencegah laporan fiktif dari luar lokasi; tag capture="environment" menolak upload foto lama dari galeri.', 'PRD Induk'],
  ['**D8**', 'Gatekeeper Terbit (Kunci Baris Kuning)', 'Proyek tidak bisa diterbitkan jika masih ada baris berlabel "Perlu dicek" yang belum dikoreksi manusia.', 'PRD Fitur v1.1'],
  ['**D9**', 'Ukuran Cek Spek Wajib dari Template (F19)', 'Desa tidak boleh menghapus ukuran wajib proyek (contoh: tebal jalan beton) agar spesifikasi tidak dimanipulasi.', 'PRD Fitur v1.1'],
  ['**D10**', 'Dokumen Asli Tampil Publik (F20)', 'Masyarakat dan BPD dapat mencocokkan tabel digital dengan dokumen resmi berstempel dan bertandatangan.', 'PRD Fitur v1.1'],
  ['**D11**', 'Bahasa UI Wajib Netral', 'Kata "korupsi", "mark-up", atau "curang" dilarang di UI. Gunakan "di atas standar", "selisih spek", "perlu dicek".', 'PRD Induk'],
  ['**D12**', 'Waktu Standar WITA (Asia/Makassar)', 'Lokus proyek di Kabupaten Gianyar, Bali. Semua waktu disimpan UTC di DB dan diformat WITA di UI.', 'PRD Fitur v1.1'],
  ['**D13**', 'Opsi Deteksi Proyek Fiktif (F22)', 'Proyek fiktif adalah modus no. 2 ICW (42 kasus); warga di titik GPS membuktikan keberadaan fisik dengan foto & koordinat.', 'PRD v1.2'],
  ['**D14**', 'Hak Penerbitan Mandiri BPD (F23)', 'Mencegah bias seleksi jika Pemdes enggan unggah; diterbitkan dengan label transparan "Diterbitkan BPD, bukan Pemdes".', 'PRD v1.2'],
  ['**D15**', 'Anti-Sybil Device Hash & Cluster Alert (F24, F25)', 'Mencegah 1 orang membuat banyak akun atau rekayasa kelompok (alert jika < 15 menit & < 20 meter).', 'PRD v1.2'],
  ['**D16**', 'Kepatuhan UU PDP & Retensi Koordinat (F26)', 'Layar persetujuan, sanitasi EXIF foto, dan koordinat mentah dihapus permanen setelah siklus BPD ditutup (simpan jarak saja).', 'PRD v1.2'],
], [7, 28, 55, 10], { size: 17 }));

// ==========================================
// 5. Kondisi sekarang vs target
// ==========================================
add(H1('Kondisi sekarang vs target'));
add(Tbl(['Area', 'Kondisi Sekarang (Baseline)', 'Target Implementasi Sonar Desa'], [
  ['**Fondasi Arsitektur**', 'Repositori kosong, belum ada framework', 'Monorepo Laravel 11 + Inertia Vue 3 dengan TypeScript dan Tailwind CSS v4.'],
  ['**Lingkungan Eksekusi**', 'Manual install di mesin masing-masing', 'Satu perintah `docker compose up -d` menjalankan Nginx, PHP-FPM, Worker, Postgres, Redis, MinIO.'],
  ['**Pemeriksaan RAB**', 'Manual mencocokkan berkas cetak ke buku Perbup (memakan waktu 45-60 menit)', 'AI mengekstrak tabel RAB PDF dalam 10-20 detik, kalkulator otomatis mencocokkan standar harga Gianyar.'],
  ['**Akses Papan Proyek**', 'Papan triplek hanya menampilkan nama dan total anggaran', 'Poster A4 dengan QR Code menuju web responsive $\le$ 3s di HP, berisi rincian item wajar & ukuran fisik.'],
  ['**Partisipasi Warga**', 'Kecurigaan berakhir jadi gosip atau keributan media sosial', 'Jalur pengaduan terukur berbasis koordinat GPS nyata ($\le$ 300 m) dan foto kamera langsung ke BPD.'],
  ['**Pengawasan BPD**', 'Tidak ada data pembanding saat musdes', 'Dashboard prioritas dengan eskalasi otomatis (ambang 3 laporan valid) dan arsip audit bertanggal.'],
  ['**Akses Evaluator Lomba**', 'Juri harus mendaftar atau berada di Gianyar', 'Mode Demo 1-klik dengan 3 peran, 3 contoh RAB pre-cached, mock GPS/kamera, dan isolasi sesi di Redis.'],
], [22, 38, 40], { size: 17 }));
add(Figure('d2-peta-modul.png', 'Gambar 2 - Peta 5 Modul Fungsional Sonar Desa & Alur Pertukaran Data'));

// ==========================================
// 6. Desain solusi
// ==========================================
add(H1('Desain solusi'));

add(H2('6.1 Topologi Docker Compose & Konfigurasi'));
add(P('Stack dibangun menggunakan Docker Compose multi-container untuk memastikan keselarasan 100% antara development dan production:'));
add(Code([
  '# docker-compose.yml (Ringkasan Service)',
  'services:',
  '  web:',
  '    image: nginx:alpine',
  '    ports: ["8000:80"]',
  '    volumes: [".:/var/www/html", "./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf"]',
  '    depends_on: [app]',
  '  app:',
  '    build: { context: ., dockerfile: docker/php/Dockerfile }',
  '    volumes: [".:/var/www/html"]',
  '    environment: [APP_ENV=local, DB_CONNECTION=pgsql, DB_HOST=postgres, REDIS_HOST=redis]',
  '    depends_on: [postgres, redis]',
  '  worker:',
  '    build: { context: ., dockerfile: docker/php/Dockerfile }',
  '    command: ["php", "artisan", "queue:listen", "--tries=3", "--timeout=90"]',
  '    volumes: [".:/var/www/html"]',
  '    depends_on: [postgres, redis]',
  '  postgres:',
  '    image: postgres:16-alpine',
  '    environment: [POSTGRES_DB=sonardesa, POSTGRES_USER=sonar, POSTGRES_PASSWORD=secret]',
  '    volumes: ["pgdata:/var/lib/postgresql/data"]',
  '  redis:',
  '    image: redis:7-alpine',
  '    volumes: ["redisdata:/data"]',
  '  minio:',
  '    image: minio/minio:latest',
  '    command: ["server", "/data", "--console-address", ":9001"]',
  '    ports: ["9000:9000", "9001:9001"]',
  '    environment: [MINIO_ROOT_USER=minioadmin, MINIO_ROOT_PASSWORD=minioadmin]',
  '    volumes: ["miniodata:/data"]',
]));

add(H2('6.2 Spesifikasi 5 Modul Inti'));
add(P('Sistem dibagi menjadi 5 modul fungsional yang memiliki batas kepemilikan jelas:'));

add(H3('Modul 1: Audit RAB (Fitur Andalan) — Owner: Dev 2'));
add(Bullets([
  '**Input Dokumen:** File PDF / Gambar scan RAB ($\le$ 10 MB) diunggah oleh Pemdes atau BPD.',
  '**Asynchronous Processing:** File disimpan di MinIO S3, record audit dibuat dengan status `parsing`, dan `ProcessRabDocumentJob` dikirim ke antrean Redis.',
  '**Model AI:** Google Gemini 1.5 Flash via structured prompt mengekstrak: nama item, volume, satuan, harga satuan, dan total harga ke JSON schema.',
  '**Deterministic Math Engine (Pure PHP):** Mencocokkan nama item ke Perbup Standar Harga Gianyar menggunakan Levenshtein/fuzzy ratio. Menghitung markup persentase: `ceil(((RAB - Standar) / Standar) * 100)`. Memvalidasi `abs((vol * harga) - total) > 1000`.',
  '**Gatekeeper Baris Kuning:** Setiap baris dengan selisih hitung, beda satuan, atau AI ragu ditandai label `Perlu dicek`. Tombol "Terbitkan" terkunci sampai semua baris kuning dikoreksi atau disetujui manusia.',
]));

add(H3('Modul 2: Halaman Proyek Publik & Poster QR A4 — Owner: Dev 1'));
add(Bullets([
  '**Target Latensi:** Halaman proyek terbuka $\le$ 3 detik di jaringan 4G mobile pada viewport 360 px tanpa login.',
  '**Blok Informasi Wajib:** Nama proyek, tahun & sumber dana, total pagu, progres fisik, ringkasan label (Sesuai, Di atas standar, Tanpa acuan).',
  '**Ukuran di RAB (F21):** Menampilkan batas ukuran template teknis (contoh: Rabat beton = panjang 200 m, lebar 3 m, tebal 12 cm) langsung di halaman publik.',
  '**Dokumen Asli Berttd (F20):** Dokumen resmi berstempel dan bertandatangan dapat dibuka via tautan modal/viewer yang di-load secara *lazy* (tidak membebani first-load).',
  '**Generator Poster QR A4:** Menyediakan layout cetak A4 siap print berisi QR Code, tautan pendek, dan instruksi inspeksi warga.',
]));

add(H3('Modul 3: Cek Spek & Laporan Partisipatif Warga — Owner: Dev 1'));
add(Bullets([
  '**Pagar Lokasi Geospasial:** Membaca koordinat browser GPS; menghitung jarak ke titik proyek via rumus Haversine. Jika jarak $\le$ 300 m $\rightarrow$ Laporan Valid. Jika $>$ 300 m $\rightarrow$ Diterima bertanda "di luar radius" dan tidak dihitung ke ambang BPD.',
  '**Proteksi Kamera:** Menggunakan input HTML5 `capture="environment"`. Unggah dari galeri file ditolak. Backend membersihkan metadata EXIF demi privasi warga.',
  '**Otentikasi Aman:** Warga masuk menggunakan Google Socialite sebelum mengisi form (F11). Tidak meminta NIK atau foto KTP.',
  '**Form Checklist Template:** Menampilkan parameter ukuran wajib proyek. Jika warga memilih "Tidak Sesuai", form mewajibkan input angka riil hasil ukur fisik.',
]));

add(H3('Modul 4: Dashboard BPD & Manajemen Siklus Status — Owner: Dev 2'));
add(Bullets([
  '**Dashboard Prioritas:** Menampilkan proyek dengan urutan: status "Dalam Tinjauan", adanya laporan baru, dan jumlah item di atas standar.',
  '**Pelapor Anonim:** Identitas pelapor disamarkan menjadi `Pelapor A`, `Pelapor B`, `Pelapor C`. Foto dan catatan warga hanya bisa dilihat oleh BPD.',
  '**Ambang Eskalasi Otomatis (F12):** Status publik proyek otomatis berubah menjadi *"Dalam tinjauan BPD sejak <tanggal>"* saat laporan selisih valid mencapai $\ge 3$.',
  '**Penyimpanan Keputusan & Siklus Baru:** BPD menyimpan hasil tinjauan (`Ditindaklanjuti` / `Tidak ada selisih`) dengan catatan wajib $\ge$ 30 karakter. Setelah disimpan, status publik menampilkan keputusan, siklus lama diarsipkan, dan hitungan laporan siklus baru mulai dari 0.',
  '**Append-Only Log:** Keputusan BPD yang sudah disimpan tidak bisa diedit/dihapus; koreksi hanya bisa ditambahkan via "Catatan Susulan" bertanggal (F15).',
]));

add(H3('Modul 5: Mode Demo & Evaluasi Juri — Owner: Dev 1 & Dev 2'));
add(Bullets([
  '**4 Pintu Masuk 1-Klik:** Juri dapat menguji sistem sebagai Warga, BPD, Pemdes, atau melihat Contoh RAB 1/2/3 tanpa registrasi.',
  '**Sandbox Sesi Terisolasi:** Modifikasi data oleh Juri A disimpan di layer cache Redis ber-prefix `session_id`. Juri B di perangkat lain tetap melihat data awal Desa Contoh yang bersih.',
  '**Bypass GPS & Kamera Khusus Demo:** Tombol mock *"Pakai Foto & Lokasi Contoh"* memungkinkan juri menguji pengiriman laporan Cek Spek dari mana saja.',
  '**Reset Demo:** Tombol "Mulai Ulang Demo" mengembalikan seluruh data sesi ke kondisi seeder awal.',
]));

add(Figure('d3-ai-pipeline.png', 'Gambar 3 - Pipeline Ekstraksi AI Deterministik ("Numbers by Code, Words by AI")'));
add(Figure('d4-siklus-bpd.png', 'Gambar 4 - Logika Siklus Review BPD & Eskalasi Status Publik Proyek'));

add(H2('6.3 Schema Database Relasional (PostgreSQL 16)'));
add(P('Skema database dirancang modular dengan relasi bersih dan dukungan JSONB untuk audit trail:'));
add(Code([
  '// 1. Table audits (Proyek RAB)',
  'Schema::create("audits", function (Blueprint $table) {',
  '    $table->uuid("id")->primary();',
  '    $table->foreignId("user_id")->constrained();',
  '    $table->string("project_name");',
  '    $table->string("project_type"); // rabat_beton, saluran_irigasi, gedung, lampu_jalan, lainnya',
  '    $table->string("status")->default("draft"); // draft, parsing, need_review, ready, published',
  '    $table->decimal("latitude", 10, 7)->nullable();',
  '    $table->decimal("longitude", 10, 7)->nullable();',
  '    $table->bigInteger("original_total")->default(0);',
  '    $table->bigInteger("calculated_total")->default(0);',
  '    $table->boolean("total_mismatch_acknowledged")->default(false);',
  '    $table->string("document_path")->nullable();',
  '    $table->string("public_status")->default("none"); // none, under_review, reviewed',
  '    $table->integer("current_cycle_number")->default(1);',
  '    $table->jsonb("revision_history")->nullable(); // Perubahan pasca-terbit',
  '    $table->timestamp("published_at")->nullable();',
  '    $table->timestamps();',
  '});',
  '',
  '// 2. Table rab_items (Rincian Item RAB)',
  'Schema::create("rab_items", function (Blueprint $table) {',
  '    $table->uuid("id")->primary();',
  '    $table->foreignUuid("audit_id")->constrained()->cascadeOnDelete();',
  '    $table->integer("sort_order");',
  '    $table->string("raw_name");',
  '    $table->decimal("volume", 12, 2);',
  '    $table->string("unit");',
  '    $table->bigInteger("unit_price");',
  '    $table->bigInteger("total_price");',
  '    $table->string("standard_item_code")->nullable();',
  '    $table->string("price_label"); // sesuai, di_atas_standar, tanpa_acuan, perlu_dicek',
  '    $table->integer("markup_percentage")->default(0);',
  '    $table->bigInteger("price_difference")->default(0);',
  '    $table->boolean("is_manually_corrected")->default(false);',
  '    $table->timestamps();',
  '});',
  '',
  '// 3. Table citizen_reports (Laporan Cek Spek Warga)',
  'Schema::create("citizen_reports", function (Blueprint $table) {',
  '    $table->uuid("id")->primary();',
  '    $table->foreignUuid("audit_id")->constrained();',
  '    $table->foreignId("user_id")->constrained(); // Google User',
  '    $table->integer("cycle_number")->default(1);',
  '    $table->string("anonymous_alias"); // Pelapor A, Pelapor B, dll.',
  '    $table->jsonb("spec_answers"); // [{spec_name, answer, actual_value}]',
  '    $table->jsonb("photo_paths"); // Maks 3 foto',
  '    $table->decimal("submit_lat", 10, 7);',
  '    $table->decimal("submit_lng", 10, 7);',
  '    $table->decimal("distance_meters", 8, 2);',
  '    $table->string("report_type"); // selisih, sesuai, tidak_dapat_diukur',
  '    $table->boolean("is_valid")->default(true);',
  '    $table->string("invalid_reason")->nullable();',
  '    $table->boolean("is_superseded")->default(false);',
  '    $table->timestamps();',
  '});',
]));

add(H2('6.4 Keamanan & Privasi Data'));
add(Bullets([
  '**Zero PII Leakage:** Sistem tidak meminta atau menyimpan NIK/KTP warga. Email Google warga di-hash dan hanya digunakan untuk validasi akun unik.',
  '**Sanitasi Foto Otomatis:** Foto kamera di-strip metadata EXIF-nya (terutama tag geolokasi perangkat) menggunakan ekstensi PHP `exif` dan `gd` sebelum disimpan ke bucket S3.',
  '**Proteksi Anonimitas Pelapor:** Anggota BPD hanya dapat melihat label alias ("Pelapor A") untuk mencegah potensi intimidasi politik lokal di tingkat banjar/desa.',
  '**Integritas Sesi Demo:** Token API AI dan kredensial rahasia hanya tersimpan di file `.env` di level server dan tidak pernah di-expose ke frontend Vue.',
]));

// ==========================================
// 7. Edge cases & aturan sistem
// ==========================================
add(H1('Edge cases & aturan sistem'));
add(Tbl(['Kondisi Edge Case', 'Perilaku Sistem yang Benar (Sesuai Desain)'], [
  ['AI salah baca angka sub-total sebagai item pekerjaan', 'Pemdes dapat menghapus baris tersebut di layar koreksi; sistem menghitung ulang ringkasan total seketika.'],
  ['Markup harga sangat tipis (contoh: RAB Rp85.250 vs Standar Rp85.000 = +0,29%)', 'Sistem menampilkan label "Di atas standar +1%" (dibulatkan ke atas agar tidak pernah menampilkan "+0%").'],
  ['Laporan dikirim warga dari jarak 320 m (> 300 m)', 'Laporan tetap tersimpan bertanda "di luar radius", namun tidak dihitung dalam ambang 3 laporan valid BPD.'],
  ['Warga mengirim revisi laporan ke-2 di hari yang sama', 'Laporan baru otomatis menggantikan laporan lama; laporan lama ditandai "digantikan" dan tetap 1 akun unik.'],
  ['BPD menandai 1 laporan tidak valid sehingga laporan valid turun menjadi 2', 'Status publik proyek **tetap** "Dalam tinjauan BPD" sampai BPD menyelesaikan dan menyimpan keputusan resmi.'],
  ['Juri A mengirim laporan ke-3 di mode demo, Juri B membuka link di browser lain', 'Juri B tetap melihat status awal tanpa penanda karena setiap juri memiliki namespace sesi terisolasi.'],
  ['File RAB berukuran 10 MB dibuka oleh warga di HP', 'Halaman publik tetap terbuka cepat ($\le$ 3s); file dokumen asli hanya diunduh saat tombol "Lihat Dokumen Asli" diketuk.'],
  ['Koneksi jaringan internet warga putus saat klik "Kirim Laporan"', 'Isian checklist dan foto kamera tetap tersimpan di state Vue; tombol berubah menjadi "Kirim Ulang" tanpa reset.'],
], [42, 58], { size: 17 }));

// ==========================================
// 8. Instruksi untuk Developer
// ==========================================
add(H1('Instruksi untuk Developer'));
add(Callout('note', 'Aturan Kerja Bersama (Dev 1 & Dev 2)', [
  'Sebelum menulis kode, pastikan lingkungan Docker sudah berjalan via `./scripts/dev-up.sh`. Seluruh perubahan kode harus diverifikasi melalui pengujian fungsional dan linter.',
]));

add(H2('8.1 Definition of Ready (DoR)'));
add(Checks([
  'Spesifikasi teknis modul dan Acceptance Criteria sudah dipahami oleh penanggung jawab.',
  'Skema database dan migration terkait sudah disetujui bersama.',
  'Lingkungan Docker Compose berjalan bersih tanpa konflik port (8000, 5432, 6379, 9000).',
  'File kredensial `.env` telah memuat kunci API Gemini / OpenRouter uji coba.',
]));

add(H2('8.2 Panduan: Lakukan vs Hindari'));
add(Tbl(['Lakukan (Best Practice)', 'Hindari (Anti-Pattern)'], [
  ['Gunakan Pure Service untuk perhitungan matematika dan label', 'Menghitung markup atau selisih di dalam Vue component atau Controller'],
  ['Gunakan format zona waktu WITA (`Asia/Makassar`) untuk semua tanggal', 'Memanggil `new Date()` mentah di frontend tanpa konversi timezone'],
  ['Kunci tombol Terbitkan jika masih ada baris kuning', 'Mengizinkan publikasi RAB dengan status AI yang masih ragu'],
  ['Simpan foto kamera dengan sanitasi EXIF dan kompresi JPEG 80%', 'Menyimpan foto asli mentah dari kamera HP (> 8 MB) ke storage'],
  ['Gunakan terminology UI netral ("di atas standar", "perlu dicek")', 'Menulis kata "korupsi", "mark-up", atau "curang" di antarmuka'],
], [50, 50], { size: 17 }));

add(H2('8.3 Definition of Done (DoD)'));
add(Checks([
  'Semua Acceptance Criteria (AC) pada story yang bersangkutan lulus uji manual & otomatis.',
  'Halaman publik terverifikasi tidak memiliki horizontal scroll pada viewport mobile 360 px.',
  'Seluruh string tanggal dan waktu tampil konsisten dalam zona waktu WITA.',
  'Tidak ada pesan console error atau warning reaktivitas di browser.',
  'Kode di-commit dengan pesan Conventional Commits (contoh: `feat(audit): implement pure math calculation engine`).',
]));

// ==========================================
// 9. Epic & Story breakdown
// ==========================================
add(H1('Epic & Story breakdown'));
add(Figure('d5-roadmap-sprint.png', 'Gambar 5 - Rencana Kalender Eksekusi 32 Hari (16 Nov - 17 Des 2026)'));

// Story 1
add(storyHeader('STORY 1', 'Halaman Proyek Publik + Fondasi + Mode Demo', 'info', '4 hari (23 - 26 Nov)', 'Fondasi bersama'));
add(P('**Sebagai** warga, **saya ingin** membuka rincian anggaran proyek dari QR di papan proyek, **agar** saya mengetahui isi anggaran dan ukuran teknis tanpa perlu bertanya ke kantor desa.'));
add(Tbl(['Area', 'Task Implementasi', 'PIC'], [
  ['Fondasi & Docker', 'Setup Docker Compose, Laravel 11, Inertia Vue 3, Tailwind CSS v4, Postgres, MinIO', 'Dev 1'],
  ['Frontend UI', 'Layar publik mobile 360 px, ringkasan label harga, dan blok "Ukuran di RAB"', 'Dev 1'],
  ['Viewer Dokumen', 'Lazy-load viewer untuk dokumen asli bertandatangan (PDF/JPG)', 'Dev 1'],
  ['Poster QR', 'Generator poster A4 siap cetak dengan QR Code dan tautan pendek', 'Dev 1'],
], [22, 63, 15], { size: 17 }));
add(AC([
  ['S1-1', 'Proyek sudah terbit', 'Warga scan QR dengan HP', 'Halaman proyek terbuka tanpa login dalam ≤ 3 detik di 4G (wajib).'],
  ['S1-2', 'Lebar layar 360 px', 'Warga menggulir halaman', 'Tidak ada scroll horizontal; semua kolom dan label terbaca jelas.'],
  ['S1-3', 'Pengunjung di halaman depan', 'Klik "Coba sebagai Warga/BPD/Desa"', 'Masuk ke peran tersebut dalam 1 klik tanpa daftar akun (wajib demo).'],
  ['S1-4', 'Proyek punya ukuran Cek Spek', 'Halaman publik dibuka', 'Menampilkan blok "Ukuran di RAB" lengkap dengan nilai dan satuannya.'],
  ['S1-5', 'Proyek terbit', 'Desa klik "Cetak QR"', 'Tampil halaman cetak A4 berisi QR Code, nama proyek, dan ajakan scan.'],
]));
add(pageBreak());

// Story 2
add(storyHeader('STORY 2', 'Audit RAB Engine (Fitur Andalan)', 'ok', '9 hari (23 Nov - 1 Des)', 'Standar harga Gianyar'));
add(P('**Sebagai** Pemerintah Desa atau BPD, **saya ingin** mengunggah file RAB dan langsung melihat perbandingan terhadap standar harga, **agar** proses evaluasi anggaran selesai dalam hitungan detik.'));
add(Tbl(['Area', 'Task Implementasi', 'PIC'], [
  ['AI Integration', 'Setup Gemini 1.5 Flash structured parser via background queue worker', 'Dev 2'],
  ['Domain Engine', 'Pure PHP calculation engine: perbup matcher, markup %, vol*harga check', 'Dev 2'],
  ['Frontend Editor', 'Layar koreksi baris kuning, modal ubah sel, tombol hapus/tambah baris', 'Dev 2'],
  ['Publishing Gate', 'Logika kunci tombol terbitkan jika masih ada baris kuning atau file belum ada', 'Dev 2'],
], [22, 63, 15], { size: 17 }));
add(AC([
  ['S2-1', 'Pengunjung di demo', 'Klik "Coba contoh RAB 1"', 'Tabel dan ringkasan tampil ≤ 2 detik tanpa memanggil API AI (wajib demo).'],
  ['S2-2', 'File RAB PDF diunggah', 'Proses parsing berjalan', 'Tabel hasil tampil ≤ 20 detik dengan indikator proses di background queue.'],
  ['S2-3', 'Harga RAB > Standar Perbup', 'Tabel hasil tampil', 'Baris bertanda "Di atas standar +X%" dan selisih rupiah masuk ke ringkasan.'],
  ['S2-4', 'Ada selisih hitung baris > Rp1.000', 'Tabel hasil tampil', 'Baris bertanda kuning "Perlu dicek"; tombol Terbitkan terkunci (wajib).'],
  ['S2-5', 'Proyek sudah terbit', 'Desa mengedit satu nilai item', 'Perubahan tercatat publik: "Diubah <tanggal>: <item>, <lama> -> <baru>".'],
]));
add(pageBreak());

// Story 3
add(storyHeader('STORY 3', 'Cek Spek & Laporan Partisipatif Warga', 'warn', '7 hari (27 Nov - 3 Des)', 'Story 1 & Story 2'));
add(P('**Sebagai** warga di lokasi proyek, **saya ingin** mengecek kesesuaian ukuran fisik bangunan dan melapor berbukti foto, **agar** laporan saya sampai ke BPD secara objektif.'));
add(Tbl(['Area', 'Task Implementasi', 'PIC'], [
  ['Geolokasi', 'Implementasi pembacaan GPS browser & kalkulasi rumus Haversine radius 300 m', 'Dev 1'],
  ['Kamera Hardware', 'Integrasi HTML5 MediaDevices capture langsung & penolakan input galeri', 'Dev 1'],
  ['Autentikasi', 'Integrasi Google Socialite sebelum masuk form checklist', 'Dev 1'],
  ['Form Checklist', 'Komponen checklist ukuran template proyek & input angka hasil ukur', 'Dev 1'],
], [22, 63, 15], { size: 17 }));
add(AC([
  ['S3-1', 'Halaman proyek publik', 'Warga klik "Cek Spek di lokasi"', 'Tampil penjelasan singkat izin lokasi sebelum dialog GPS muncul.'],
  ['S3-2', 'Izin lokasi disetujui', 'GPS aktif dan akurat', 'Diminta login akun Google sebelum mengisi form checklist (wajib).'],
  ['S3-3', 'Warga mengambil foto', 'Membuka dialog kamera', 'Hanya kamera langsung yang aktif; opsi upload galeri tidak tersedia.'],
  ['S3-4', 'Lokasi warga > 300 m dari proyek', 'Laporan dikirim', 'Laporan diterima bertanda "di luar radius" dan tidak dihitung ke ambang BPD.'],
  ['S3-5', 'Mode demo juri', 'Klik "Pakai foto & lokasi contoh"', 'Laporan demo berhasil terkirim tanpa GPS riil dan tanpa login Google (wajib).'],
]));
add(pageBreak());

// Story 4
add(storyHeader('STORY 4', 'Dashboard BPD & Manajemen Siklus Status', 'note', '4 hari (2 - 5 Des)', 'Story 2 & Story 3'));
add(P('**Sebagai** anggota BPD, **saya ingin** meninjau proyek bermasalah dan laporan warga di satu dashboard, **agar** saya dapat menindaklanjuti temuan tersebut dengan bukti di Musyawarah Desa.'));
add(Tbl(['Area', 'Task Implementasi', 'PIC'], [
  ['Dashboard UI', 'Tampilan daftar proyek prioritas (status dalam tinjauan & selisih harga)', 'Dev 2'],
  ['Review Detail', 'Modal review laporan warga dengan foto, jarak, dan identitas Pelapor A/B/C', 'Dev 2'],
  ['Cycle Engine', 'Eskalasi status otomatis jika >= 3 laporan selisih valid dalam 1 siklus', 'Dev 2'],
  ['Tindak Lanjut', 'Form simpan hasil keputusan BPD (catatan >= 30 huruf) & catatan susulan', 'Dev 2'],
], [22, 63, 15], { size: 17 }));
add(AC([
  ['S4-1', 'BPD login', 'Membuka dashboard', 'Proyek dengan status "Dalam tinjauan" tampil di urutan teratas (wajib).'],
  ['S4-2', 'Proyek dipilih', 'BPD membuka detail laporan', 'Identitas warga tampil anonim sebagai "Pelapor A/B/C" beserta foto & hasil ukur.'],
  ['S4-3', 'Laporan selisih valid ke-3 masuk', 'Sistem memvalidasi laporan', 'Status publik proyek otomatis berubah menjadi "Dalam tinjauan BPD" (wajib).'],
  ['S4-4', 'Status "Dalam tinjauan"', 'BPD simpan hasil catatan < 30 char', 'Ditolak dengan pesan validasi minimal 30 karakter.'],
  ['S4-5', 'Keputusan BPD disimpan', 'Halaman publik dibuka', 'Tampil status "Ditinjau BPD: <Hasil>", laporan diarsipkan, siklus baru mulai dari 0.'],
]));
add(pageBreak());

// Story 5
add(storyHeader('STORY 5', 'Polishing, Mode Demo Juri, & Kesiapan Submit', 'ok', '6 hari (12 - 17 Des)', 'Semua Story'));
add(P('**Sebagai** tim pengembang, **kami ingin** aplikasi stabil di link hosting publik dengan panduan video jelas, **agar** juri lomba dapat menilai karya tanpa kendala teknis.'));
add(Tbl(['Area', 'Task Implementasi', 'PIC'], [
  ['Demo Isolation', 'Penyempurnaan isolasi sesi Redis untuk data Desa Contoh Gianyar', 'Dev 1 & Dev 2'],
  ['Testing & HP Check', 'Pengujian fungsional di 2 HP Android (Chrome mobile) dan 1 Laptop', 'Dev 1 & Dev 2'],
  ['Optimasi Docker', 'Setup production multi-stage Dockerfile di VPS publik (HTTPS Let\'s Encrypt)', 'Dev 2'],
  ['Video & Berkas', 'Perekaman video demonstrasi fitur dan penyusunan README monorepo', 'Dev 1'],
], [22, 63, 15], { size: 17 }));
add(AC([
  ['S5-1', 'Tautan hosting publik', 'Dibuka setelah 12 jam tidak aktif', 'Halaman depan terbuka ≤ 3 detik (hosting tidak mengalami cold-sleep) (wajib).'],
  ['S5-2', 'Juri A & B di perangkat berbeda', 'Juri A kirim laporan ke-3 Jl. Melati', 'Layar Juri B tetap menampilkan status normal tanpa penanda (wajib demo).'],
  ['S5-3', 'Data demo telah diubah juri', 'Klik tombol "Mulai ulang demo"', 'Seluruh data sesi juri kembali ke kondisi seeder awal.'],
  ['S5-4', 'Pengujian 2 orang luar tim', 'Menjalankan skenario 3 peran', '100% alur selesai tanpa error atau bantuan tim pengembang.'],
  ['S5-5', 'Pemeriksaan berkas submit', 'Dicek sebelum 17 Des 23:59', 'Semua link (GitHub repo, demo hosting, video drive) dapat diakses publik.'],
]));

// ==========================================
// 10. QA test cases per story
// ==========================================
add(H1('QA test cases per story'));
add(Callout('info', 'Prosedur Pengujian QA', [
  'Pengujian dilakukan pada lingkungan staging Docker lokal dan staging VPS publik. Uji mobile wajib menggunakan perangkat fisik Android dengan layar 360 px pada koneksi 4G riil.',
]));

add(H3('Test Cases: Story 1 (Halaman Proyek & QR)'));
add(TC([
  ['TC-S1-01', 'Buka via QR Code', 'Scan QR poster A4 di HP Android', 'Halaman terbuka ≤ 3 detik tanpa form login', 'P1'],
  ['TC-S1-02', 'Responsive Viewport 360 px', 'Buka Chrome DevTools resolusi 360x640', 'Bebas horizontal scrollbar, font dan label terbaca', 'P1'],
  ['TC-S1-03', 'Lazy-load Dokumen Asli', 'Klik tautan "Lihat Dokumen Asli"', 'Modal PDF/Scan terbuka tanpa memperlambat halaman utama', 'P2'],
]));

add(H3('Test Cases: Story 2 (Audit RAB Engine)'));
add(TC([
  ['TC-S2-01', 'Upload RAB PDF Standar', 'Unggah berkas RAB contoh 40 baris', 'Tabel terisi ≤ 20 detik, kalkulasi persentase akurat', 'P1'],
  ['TC-S2-02', 'Deteksi Baris Anomali', 'Unggah RAB dengan harga pekerjaan beton Rp820.000/m³ (standar Rp695.000/m³)', 'Label "Di atas standar +18%" (merah) tersemat otomatis', 'P1'],
  ['TC-S2-03', 'Gatekeeper Penerbitan', 'Coba klik Terbitkan saat ada baris kuning', 'Tombol terkunci, muncul peringatan periksa baris kuning', 'P1'],
]));

add(H3('Test Cases: Story 3 (Cek Spek Warga)'));
add(TC([
  ['TC-S3-01', 'Uji Radius Valid (≤ 300 m)', 'Kirim laporan dari jarak 50 m dari titik proyek', 'Laporan tercatat valid, masuk ke hitungan ambang BPD', 'P1'],
  ['TC-S3-02', 'Uji Radius Luar (> 300 m)', 'Kirim laporan dari jarak 450 m', 'Laporan tersimpan bertanda "di luar radius", tidak memicu BPD', 'P2'],
  ['TC-S3-03', 'Blokir Unggah Galeri', 'Buka opsi ambil foto di HP Android', 'Langsung membuka sensor kamera; opsi galeri tidak muncul', 'P1'],
]));

add(H3('Test Cases: Story 4 (Dashboard BPD)'));
add(TC([
  ['TC-S4-01', 'Eskalasi Otomatis Ambang 3', 'Kirim laporan selisih valid ke-3 dari akun berbeda', 'Status publik proyek seketika berubah jadi "Dalam tinjauan BPD"', 'P1'],
  ['TC-S4-02', 'Validasi Catatan BPD', 'BPD simpan keputusan dengan catatan 15 huruf', 'Sistem menolak; catatan wajib minimal 30 karakter', 'P2'],
  ['TC-S4-03', 'Append-Only Catatan Susulan', 'BPD coba mengedit keputusan lama', 'Tidak ada tombol edit; hanya tersedia tombol tambah catatan susulan', 'P1'],
]));

// ==========================================
// 11. Rollout, rollback & monitoring
// ==========================================
add(H1('Rollout, rollback & monitoring'));
add(H2('11.1 Prosedur Deployment VPS Produksi'));
add(Numbered([
  'Clone repositori di VPS Ubuntu: `git clone https://github.com/org/sonar-desa.git /var/www/sonar-desa`.',
  'Salin file environment: `cp .env.production .env` dan sesuaikan kredensial database serta API key AI.',
  'Jalankan container produksi: `docker compose -f docker-compose.prod.yml up -d --build`.',
  'Jalankan migrasi dan seeder awal: `docker compose exec app php artisan migrate --force && php artisan db:seed --class=GianyarDemoSeeder`.',
  'Konfigurasi SSL HTTPS otomatis via Let\'s Encrypt / Certbot pada container Nginx.',
]));

add(H2('11.2 Rollback Plan'));
add(Bullets([
  '**Rollback Cepat (Tanpa Downtime):** Jika terjadi kegagalan pada rilis terbaru, jalankan perintah `git checkout <commit-sebelumnya> && docker compose restart app worker`.',
  '**Rollback Database:** Skema migrasi bersifat aditif (*additive*). Rollback tidak menghapus data historis warga.',
]));

// ==========================================
// 12. Risiko & open questions
// ==========================================
add(H1('Risiko & open questions'));
add(H2('Risiko & Strategi Mitigasi'));
add(Tbl(['Risiko Teknis / Lomba', 'Dampak', 'Strategi Mitigasi Teruji'], [
  ['Akurasi OCR AI menurun pada dokumen foto miring/buram', 'Baris kuning membengkak', 'Sediakan opsi input manual cadangan; lakukan spike AI pada 18-22 Nov.'],
  ['Kuota token API AI habis saat periode penjurian (19-28 Des)', 'Fitur unggah gagal', 'Sediakan 3 Contoh RAB pre-cached (0 detik AI); batasi unggah mandiri 3 file/sesi.'],
  ['Hosting gratis mengalami cold-sleep (tertidur)', 'Loading pertama > 10 detik', 'Gunakan VPS Ubuntu aktif (tanpa sleep) atau cron healthcheck ping setiap 5 menit.'],
  ['Tuduhan fitnah politik antar-pendukung pilkades', 'Risiko hukum/etika', 'UI wajib netral; ambang 3 laporan valid dari akun berbeda; verifikasi manual BPD.'],
], [32, 22, 46], { size: 17 }));

// ==========================================
// 13. Lampiran
// ==========================================
add(H1('Lampiran: Setup Docker & checklist submit'));
add(H2('Quickstart Docker Development'));
add(Code([
  '# 1. Clone repositori & masuk direktori',
  'git clone <repo-url> && cd SONAR-DESA',
  '',
  '# 2. Inisialisasi otomatis via script',
  'chmod +x scripts/dev-up.sh',
  './scripts/dev-up.sh',
  '',
  '# 3. Akses aplikasi lokal',
  'Aplikasi Web:  http://localhost:8000',
  'MinIO Console: http://localhost:9001 (minioadmin / minioadmin)',
  'Mailpit Web:   http://localhost:8025',
]));

add(H2('Checklist Final Submission Techcomfest 2027'));
add(Checks([
  'Pendaftaran tim & verifikasi pembayaran Gelombang I terkonfirmasi (sebelum 18 Nov).',
  'Link public hosting web aktif, responsif, dan dapat dibuka tanpa login dari perangkat lain.',
  'Monorepo GitHub rapi, memiliki README komprehensif, dan lisensi open source.',
  'Video demonstrasi fitur berdurasi sesuai rulebook terunggah di Google Drive (akses publik/viewer).',
  'Tidak ada PII (data pribadi warga), nama desa asli, atau kunci API yang bocor di repository.',
  'Seluruh pengujian fungsional pada 2 HP Android fisik dan 1 laptop telah tuntas tanpa error.',
]));

// Simpan dokumen
(async () => {
  await k.save(OUT);
  console.log('✅ Feature Spec DOCX created at:', OUT);
})();
