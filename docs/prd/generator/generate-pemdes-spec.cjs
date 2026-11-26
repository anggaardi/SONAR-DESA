// Generator Feature Spec & PRD Fitur Pemerintah Desa (Pemdes) Sonar Desa
// Menggunakan docx-kit dari MEMSKILL (lean-feature-spec)
const path = require('path');
const kit = require('/home/kadekangga/Projects/MEMSKILL/feature-spec/scripts/lib/docx-kit');

const OUT = path.resolve(__dirname, '..', 'PRD-Fitur-Pemdes.docx');
const IMG = path.resolve(__dirname, 'img');

const k = kit.create({
  title: 'Sonar Desa - Spesifikasi Fitur Pemerintah Desa (Pemdes)',
  version: '1.2',
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
  eyebrow: 'SPESIFIKASI FITUR & PRD TEKNIS',
  heading: 'Portal Pemerintah Desa (Pemdes): Ingesti, Setup Spek, & Operasional Proyek',
  subtitle: 'Arsitektur Hulu Data Sonar Desa: Dari Ekstraksi Berkas RAB, Gatekeeper Baris Kuning, Geofencing Lapangan, Template Pengukuran Fisik, hingga Linimasa Progres Terbuka untuk Warga',
  meta: [
    ['Status', 'Revisi v1.2 (alur unggah RAB & status implementasi)'],
    ['Versi', '1.2'],
    ['Owner', 'Dev 2 (Lead Backend & Alur Pemdes)'],
    ['Tanggal', '26 November 2026'],
    ['Pelaksana', '2 Developer (Dev 1 & Dev 2)'],
    ['Sisa Estimasi', '±4 hari kerja (S6 Dev 1, S7 Dev 2)'],
    ['Repo', '`SONAR-DESA` (Laravel 13 + Inertia v3)'],
    ['Epic', 'SD-PEMDES - Portal & Setup Proyek Pemdes'],
  ],
}));

add(Callout('gray', 'Dokumen Acuan & Penyelaras Tata Kelola', [
  'Dokumen ini merupakan spesifikasi fungsional dan teknis mendalam untuk peran **Pemerintah Desa (Role::Desa)** pada platform Sonar Desa. Dokumen ini melengkapi **PRD Fitur Inti v1.1** (Bagian 3 & 4) dan **PRD Arsitektur v1.2**, menjawab akar masalah *asal-usul data*: bagaimana dokumen fisik APBDes/RAB bertransformasi menjadi parameter terukur di layar publik warga dan instrumen pengawasan BPD.',
]));

add(Callout('warn', 'Yang berubah di v1.2', [
  '- **Alur unggah RAB ditetapkan (D17–D19):** berkas selalu menjadi langkah pertama (`/audits/create`). Jalur AI dan Isi Manual bertemu di satu layar review `/audits/{id}`, lalu wizard terbit. Diagram alur ada di Bagian 6.2.',
  '- **Bagian 5 kini berupa status implementasi** per 26 Nov 2026: mana yang selesai, sebagian, dan belum.',
  '- **Story baru:** S6 (penyelesaian frontend & QA, Dev 1) dan S7 (sisa backend, Dev 2) di Bagian 9.',
  '- **Ringkasan v1.1** (tetap berlaku): prinsip pemilik data (6.5), hak per status (6.6), dan keputusan D8–D16 (satu pintu terbit, berkas wajib, tanpa unpublish, ukuran tidak bisa dihapus, pembekuan saat tinjauan BPD, daftar kolom CCO, label oleh kode, snapshot target, pemicu tinjauan BPD).',
]));

add(H3('Cara membaca dokumen ini'));
add(Tbl(['Peran', 'Wajib baca', 'Fokus implementasi'], [
  ['**Dev 2 (Lead Fitur Pemdes & Backend)**', 'Semua bagian (1–13)', 'Ingesti RAB, Logika Gatekeeper Baris Kuning, Wizard Setup, API Progress Photos, Model Changes, dan Pemicu Tinjauan BPD'],
  ['**Dev 1 (Frontend & QA Lead)**', 'Bagian 1, 5 (Status), 6.2 (Alur & Layar), 6.4–6.6, 9 (Story S6), 10', 'Story S6: perbaikan wizard, pensiun form lama, polling, tampilan label publik, dan QA end-to-end'],
  ['**Dewan Juri / Evaluator Lomba**', 'Bagian 1 (TL;DR), 2 (Metrik), 6.4–6.6 (Mapping & Kepemilikan Data), 9 (AC)', 'Memverifikasi integritas sistem: kenapa angka di hadapan warga dijamin sahih dan tidak dimanipulasi'],
], [25, 35, 40], { size: 17 }));

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
  'Risiko & mitigasi',
  'Lampiran: Checklist operasional',
]));
add(pageBreak());

// ==========================================
// 1. TL;DR
// ==========================================
add(H1('TL;DR'));
add(Callout('note', null, [
  '**Masalah Asal-Usul Data.** Selama ini perancangan platform berfokus pada sisi hilir: bagaimana warga membaca halaman proyek publik (/p/{slug}) dan bagaimana BPD menerima laporan selisih fisik. Namun tim sempat melewatkan sisi hulu: **siapa yang men-setup data tersebut ke sistem?** Tanpa modul input yang kokoh dan tervalidasi dari Pemerintah Desa (Kades & TPK), data anggaran dan titik geofence Cek Spek tidak akan pernah terisi secara sahih di database.',
  '**Solusi: Portal Pemerintah Desa Terpadu.** Sonar Desa menyediakan modul kerja khusus Pemdes (`Role::Desa`) yang mencakup 4 fase utuh: (1) Ingesti berkas asli RAB (PDF/foto berstempel) dengan ekstraksi OCR AI deterministik, (2) Gatekeeper Baris Kuning yang mengunci publikasi sebelum seluruh kejanggalan diverifikasi manusia, (3) Wizard Setup Proyek untuk mengunci titik GPS geofence (<= 300 m) dan memilih parameter ukuran Cek Spek dari template teknis baku, serta (4) Manajemen operasional fisik (update % progres, unggah foto lapangan bebas EXIF, dan riwayat addendum CCO).',
  '**Integritas & Anti-Manipulasi.** Sistem menjamin prinsip *"Numbers by code, words by AI"*. Data proyek yang sudah terbit memiliki sifat *append-only*: Pemdes tidak dapat mengedit data diam-diam tanpa meninggalkan rekam jejak publik (`project_changes`). Dokumen kertas asli berstempel selalu ditampilkan berdampingan agar warga dapat menguji sendiri keabsahan tabel digital vs fisik.',
  '**Prinsip Pemilik Data (v1.1).** Setiap data yang tampil di `/p/{slug}` wajib menjawab tiga pertanyaan: *siapa yang mengisi*, *kapan dikunci*, dan *jika berubah, jejaknya di mana*. Hanya ada **satu pintu terbit** (`PublishProject`), dan selama BPD meninjau, Pemdes tidak dapat mengubah tolok ukur yang sedang diuji warga.',
]));
add(Figure('d1-pemdes-lifecycle.png', 'Gambar 1 - Siklus Hidup Alur Kerja Pemerintah Desa (Pemdes) dari Ingesti hingga Pengawasan'));

// ==========================================
// 2. Latar belakang, tujuan & metrik
// ==========================================
add(H1('Latar belakang, tujuan & metrik'));
add(H2('Latar Belakang'));
add(Bullets([
  '**Kewajiban Hukum Positif Desa:** UU No. 6 Tahun 2014 Pasal 82 mewajibkan Pemerintah Desa menginformasikan perencanaan dan pelaksanaan APBDes kepada masyarakat melalui layanan informasi umum.',
  '**Beban Administratif Aparatur:** Menyusun papan proyek fisik manual memakan waktu lama dan hanya menampilkan angka gelondongan, menyulitkan transparansi rincian harga satuan dan volume pekerjaan.',
  '**Celah Manipulasi Spesifikasi:** Jika data spek fisik ditentukan bebas tanpa standar, ada risiko aparatur sengaja mengosongkan ukuran tebal jalan beton agar tidak bisa diuji warga di lapangan.',
]));

add(H2('Tujuan'));
add(Tbl(['#', 'Tujuan'], [
  ['**G1**', 'Menyediakan instrumen kerja digital bagi Pemdes untuk mengaudit kewajaran harga RAB terhadap Standar Satuan Harga (SSH) Gianyar dalam waktu <= 20 detik.'],
  ['**G2**', 'Mewajibkan berkas asli RAB, titik lokasi GPS, dan seluruh ukuran kunci dari template baku (ditambah maksimal 5 ukuran kustom) sebelum proyek dapat diterbitkan ke publik.'],
  ['**G3**', 'Menyediakan sarana otomatis cetak poster QR Code A4 untuk dipasang di plang fisik proyek desa.'],
  ['**G4**', 'Menjamin transparansi progres fisik lapangan berkala (%) dan catatan addendum perubahan anggaran (CCO) yang bisa dibaca publik secara real-time.'],
  ['**G5**', 'Menjamin satu pintu terbit dan menutup lingkaran pengawasan: laporan warga yang mencapai ambang otomatis membawa proyek ke status "Dalam Tinjauan BPD".'],
], [8, 92], { size: 17 }));

add(H2('Metrik Keberhasilan'));
add(Tbl(['Metrik', 'Target', 'Cara Mengukur'], [
  ['Kecepatan Ekstraksi RAB', '<= 20 detik', 'Waktu pemrosesan dokumen 40 baris di background queue'],
  ['Integritas Gatekeeper', '100% terkunci', 'Uji coba penerbitan saat masih ada baris berlabel kuning (NeedsCheck)'],
  ['Keberadaan Parameter Cek Spek', '100% proyek terbit', 'Setiap proyek terbit wajib memiliki relasi ke project_measurements'],
  ['Kepatuhan Privasi Foto', '100% bebas EXIF', 'Pemeriksaan metadata GPS/kamera pada file foto progres di disk storage'],
  ['Kelengkapan Proyek Terbit', '0 proyek tanpa berkas / GPS / item', 'Query proyek terbit dengan `audits.file_path`, `projects.lat/lng` kosong, atau tanpa `audit_items`'],
  ['Jejak Perubahan Pasca-Terbit', '100% tercatat', 'Setiap edit pasca-terbit menghasilkan baris `project_changes` dalam transaksi yang sama'],
], [30, 25, 45], { size: 17 }));

// ==========================================
// 3. Scope
// ==========================================
add(H1('Scope'));
add(Callout('warn', 'Batasan Scope Modul Pemdes (Jaga Scope MVP)', [
  'Modul ini berfokus pada alur input, verifikasi, penerbitan, dan update progres fisik proyek. Fitur integrasi dengan sistem keuangan daerah atau kuitansi SPJ tertutup berada di luar lingkup kompetisi.',
]));

add(scopeTable({
  inScope: [
    'Dashboard internal Pemdes (GET /desa) dengan kartu statistik proyek, status publik, dan daftar proyek desa.',
    'Alur unggah berkas asli RAB (PDF / JPG / PNG max 10 MB) dengan antrean background processing.',
    'Layar dual-view: penampil dokumen berstempel di kiri, tabel ekstraksi AI berlabel di kanan.',
    'Logika Gatekeeper Baris Kuning: validasi 0 baris kuning sebelum tombol "Terbitkan" aktif.',
    'Wizard Setup Publikasi: input metadata (Nama, Sumber Dana, Tahun), titik koordinat GPS, dan template Cek Spek.',
    'Generator poster A4 QR Code (GET /projects/{project}/qr) siap cetak.',
    'Panel kelola proyek: input persentase progres fisik ($0-100%$) dan unggah 1-5 foto lapangan (WebP max 1600 px).',
    'Pencatatan addendum perubahan fisik (CCO) yang terekam pada tabel project_changes.',
    'Jalur "Isi Manual": form proyek manual yang sudah ada dialihfungsikan sebagai fallback saat AI gagal, tetap wajib berkas asli dan tetap lewat gatekeeper + wizard.',
    'Pemicu otomatis status "Dalam Tinjauan BPD" saat laporan selisih valid mencapai ambang (`sonar.review.threshold`).',
  ],
  out: [
    'Dashboard BPD & penutupan siklus tinjauan (PRD Review terpisah, M5).',
    'Membatalkan terbit (unpublish) atau menghapus proyek yang sudah terbit.',
    'Integrasi otomatis ke software Siskeudes (Kemenkeu/BPKP).',
    'Pencatatan kuitansi belanja riil / SPJ internal desa.',
    'Multi-rekening kas desa atau buku kas umum.',
    'Form pendaftaran desa baru (desa dibuat via seeder / Superadmin).',
    'Editor pembuat RAB dari nol (Sistem mengaudit RAB yang sudah ada).',
  ],
  later: [
    'Ekspor laporan realisasi APBDes ke format resmi Kemendesa.',
    'Sinkronisasi API katalog harga lintas kabupaten di Provinsi Bali.',
    'Penilaian Indeks Transparansi Desa Antikorupsi KPK secara otomatis.',
  ],
}));

// ==========================================
// 4. Keputusan yang sudah disepakati
// ==========================================
add(H1('Keputusan yang sudah disepakati'));
add(Tbl(['#', 'Keputusan', 'Alasan Teknis & Produk', 'Sumber'], [
  ['**D1**', 'Gatekeeper Baris Kuning Terkunci 100%', 'Dokumen yang masih memiliki keraguan AI, beda satuan, atau deviasi hitung dilarang terbit ke publik sebelum disetujui manusia.', 'PRD Fitur v1.1'],
  ['**D2**', 'Template Ukuran Wajib Cek Spek (F19)', 'Ukuran teknis diambil dari template jenis proyek (mis. Rabat Beton: tebal, lebar, panjang). Pemdes dilarang menghapus ukuran kunci.', 'PRD Fitur v1.1'],
  ['**D3**', 'Titik Koordinat GPS Tunggal (F10)', 'Proyek fisik memiliki titik koordinat pusat (lat, lng) sebagai jangkar geofence <= 300 meter untuk pembuktian Cek Spek warga.', 'PRD Induk'],
  ['**D4**', 'Immutabilitas Berkas Asli Pasca-Terbit', 'Setelah proyek diterbitkan, berkas RAB asli dan rincian harga tidak boleh diedit diam-diam; revisi wajib masuk riwayat project_changes.', 'PRD Arsitektur'],
  ['**D5**', 'Sanitasi Foto Progres (A15 & UU PDP)', 'Foto lapangan otomatis dikonversi ke WebP (max 1600px) dan seluruh metadata sensitif EXIF/GPS dibersihkan di backend.', 'PRD v1.2'],
  ['**D6**', 'Bahasa Netral di Seluruh Antarmuka', 'Dilarang menggunakan istilah provokatif ("mark-up", "korupsi"). Gunakan "Di atas standar", "Perlu dicek", "Klarifikasi BPD".', 'PRD Induk'],
  ['**D7**', 'Poster QR Code Berformat Vektor A4', 'Dihasilkan secara dinamis via chillerlan/php-qrcode format SVG tanpa dependensi ekstensi GD eksternal.', 'PRD Arsitektur'],
  ['**D8**', 'Satu Pintu Terbit', 'Hanya `PublishProject` yang boleh mengisi `published_at`. Form proyek manual tidak lagi menerbitkan langsung; form itu menjadi jalur "Isi Manual" yang menghasilkan `audit_items`, lalu masuk gatekeeper dan wizard yang sama.', 'Revisi v1.1'],
  ['**D9**', 'Berkas Asli Wajib untuk Semua Proyek', 'Tanpa `audits.file_path`, warga tidak dapat menguji tabel digital terhadap kertas berstempel. Berlaku juga untuk jalur Isi Manual.', 'Revisi v1.1'],
  ['**D10**', 'Tidak Ada Unpublish / Hapus Pasca-Terbit', 'Proyek terbit tidak bisa ditarik kembali ke draf atau dihapus oleh Pemdes. Penghentian pekerjaan dicatat sebagai CCO di `project_changes`.', 'Revisi v1.1'],
  ['**D11**', 'Ukuran Cek Spek Tidak Bisa Dihapus Pasca-Terbit', '`report_answers.measurement_id` memakai cascade delete, sehingga menghapus ukuran ikut menghapus jawaban warga. Ukuran hanya boleh diubah nilainya lewat CCO.', 'Revisi v1.1'],
  ['**D12**', 'Pembekuan Saat Dalam Tinjauan BPD', 'Selama `public_status = in_review`, item RAB, nilai ukuran, dan GPS dibekukan agar Pemdes tidak dapat menggeser tolok ukur yang sedang diuji. Progres % dan foto tetap boleh.', 'Revisi v1.1'],
  ['**D13**', 'Daftar Kolom yang Boleh Diubah via CCO', 'Boleh: volume/harga/jumlah item RAB, nilai target ukuran, titik GPS, progres %. Terkunci: berkas asli, nama, jenis, sumber dana, tahun, label harga.', 'Revisi v1.1'],
  ['**D14**', 'Label Harga Dihitung oleh Kode', 'Pemdes hanya mengoreksi data hasil bacaan (nama, volume, satuan, harga) atau mengonfirmasi "Sudah Benar". Label selalu dihitung ulang oleh `PriceLabeler`, tidak pernah dipilih manusia.', 'Revisi v1.1'],
  ['**D15**', 'Snapshot Nilai Target di Jawaban Warga', 'Jawaban Cek Spek menyimpan nilai target yang berlaku saat laporan dikirim, sehingga CCO berikutnya tidak mengubah makna laporan lama. Koordinasi dengan pemilik modul Report.', 'Revisi v1.1'],
  ['**D16**', 'Pemicu Otomatis Tinjauan BPD', 'Saat jumlah pelapor selisih valid di siklus berjalan >= `sonar.review.threshold`, sistem membuka siklus tinjauan dan mengubah status publik ke `in_review`. Pemdes tidak bisa memicu atau menutupnya.', 'PRD Fitur v1.1 (B1)'],
  ['**D17**', 'Berkas Selalu Langkah Pertama, Satu Layar Review', 'Setiap audit dimulai dari unggah berkas di `/audits/create`. Hasil AI maupun Isi Manual (tambah baris di layar review) bertemu di `/audits/{id}`. Metadata, GPS, dan ukuran Cek Spek hanya diisi di wizard terbit. Halaman `/desa/projects/create` dipensiunkan.', 'Revisi v1.2'],
  ['**D18**', 'Pembacaan AI Asinkron dengan Batas Waktu Tunggal', 'Unggahan membuat audit `reading` dan menjalankan `ProcessRabDocumentJob` di antrean. Layar review melakukan polling. Batas tunggu UI, `$timeout` job, dan `retry_after` antrean diselaraskan dengan `sonar.rab_reader.timeout_seconds` (60 detik). Lewat batas, audit menjadi `read_failed`.', 'Revisi v1.2'],
  ['**D19**', 'Kuota AI & Contoh RAB untuk Demo', 'Desa demo dibatasi `sonar.demo.upload_limit` unggahan AI. Tombol "Uji Contoh RAB" memakai hasil fixture tanpa memanggil AI dan tidak memotong kuota. Anggaran harian global `sonar.ai_budget` ditegakkan sebelum job memanggil AI; jika habis, tawarkan Isi Manual.', 'Revisi v1.2'],
], [7, 30, 53, 10], { size: 17 }));

// ==========================================
// 5. Kondisi sekarang vs target
// ==========================================
add(H1('Kondisi sekarang vs target'));
add(P('Status implementasi hasil pemeriksaan kode di `main` (termasuk perubahan lokal yang belum di-commit) per 26 November 2026:'));
add(Tbl(['Area', 'Status', 'Kondisi Sekarang', 'Sisa Pekerjaan'], [
  ['**Unggah RAB** (`/audits/create`)', 'Selesai', '`AuditUploadController` + `ProcessRabDocumentJob`: berkas disimpan privat, audit `reading`, job di antrean. Ada Coba Lagi, Ganti Berkas, dan Hapus (ditolak bila sudah terbit).', '-'],
  ['**Layar Review & Gatekeeper**', 'Selesai', 'Dual-view, koreksi inline, "Sudah Benar", tambah baris manual, alasan baris kuning tersimpan di `doubt`, `publish_block_reason`, dan centang selisih total F3 (`/ack-total`). Edit/hapus item ditolak setelah terbit.', 'Batas polling 180 detik belum selaras dengan D18 (S6, S7)'],
  ['**Wizard Terbit**', 'Sebagian', '`PublishProject` memeriksa berkas, item, 0 baris kuning, F3, GPS, ukuran kunci, dan slug lintas desa.', 'Bug: wizard mengirim `origin: custom`, padahal backend hanya menerima `template`/`desa`. Ukuran kustom gagal divalidasi (S6)'],
  ['**Form Proyek Manual** (`/desa/projects/create`)', 'Sebagian', 'Masih ada dan masih bisa "terbitkan langsung" lewat `CreateDesaProject`. Tombol "Isi Manual" di dashboard mengarah ke sini. Ukuran template masih punya tombol Hapus.', 'Dipensiunkan sesuai D17 (S6 frontend, S7 backend)'],
  ['**Dashboard Pemdes**', 'Selesai', 'Kartu status audit (dibaca, perlu review, siap, gagal), daftar "Dokumen dalam proses" dengan polling, tombol Uji Contoh RAB.', '-'],
  ['**Kelola Proyek & CCO**', 'Selesai', 'CCO item, ukuran, GPS dengan kolom `reason` terpisah. Ganti acuan standar dan `how_to` tercatat. Dibekukan saat `in_review`. Ukuran tidak bisa dihapus pasca-terbit.', '-'],
  ['**Pemicu Tinjauan BPD**', 'Sebagian', 'Listener `TriggerReviewCycleOnThreshold` membuka siklus saat ambang tercapai. Dashboard BPD sudah ada.', 'Listener belum memakai aturan >= 3 perangkat berbeda (Adendum v1.2 5.2). Logika itu ada di `EvaluateReviewThreshold` yang tidak dipanggil (S7)'],
  ['**Kuota AI**', 'Sebagian', 'Kuota unggahan desa demo (`EnforceDemoUploadLimit`) dan contoh RAB berbasis fixture di `/demo` sudah ada.', '`sonar.ai_budget` belum ditegakkan. "Uji Contoh RAB" lewat `/audits` tetap memanggil AI bila driver `ai` (S7)'],
  ['**Snapshot target (D15)**', 'Belum', 'Jawaban warga belum menyimpan nilai target saat laporan dikirim.', 'S7, koordinasi dengan pemilik modul Report'],
  ['**Pratinjau dokumen**', 'Selesai', 'Caddy memakai `X-Frame-Options SAMEORIGIN`, sehingga iframe pratinjau PDF tampil.', '-'],
], [20, 10, 40, 30], { size: 15 }));
add(Figure('d2-data-mapping.png', 'Gambar 2 - Pemetaan Hulu-Hilir: Setup Pemdes -> Database -> Render Publik Warga'));

// ==========================================
// 6. Desain solusi
// ==========================================
add(H1('Desain solusi'));

add(H2('Skema Data & Relasi Modul Pemdes'));
add(P('Modul Pemdes mengelola dan menulis ke beberapa entitas utama di database PostgreSQL:'));
add(Code([
  '// Struktur Hubungan Model (Eloquent)',
  'Village (1) ──< User (role: Role::Desa)',
  'Village (1) ──< Audit ──< AuditItem',
  'Audit (1)   ── (1) Project',
  'Project (1) ──< ProjectMeasurement (Template & Nilai Spek)',
  'Project (1) ──< ProgressPhoto (Foto Lapangan WebP)',
  'Project (1) ──< ProjectChange (Audit Trail CCO)',
]));

add(H2('Spesifikasi Layar & Alur Antarmuka Pemdes'));
add(P('Alur utama (D17). Jalur AI dan Isi Manual bertemu di layar review, dan hanya ada satu pintu terbit:'));
add(Code([
  'Dashboard /desa ──[Unggah Dokumen RAB]──► ① Unggah /audits/create (berkas / contoh)',
  '                                              │ audit = reading, ProcessRabDocumentJob di antrean',
  '                                              ▼',
  '                                         ② Sedang dibaca /audits/{id}  (polling, batas 60 detik)',
  '                                              │ berhasil              │ gagal / kuota habis',
  '                                              ▼                       ▼',
  '                                              │              [Coba Lagi]  [Isi Manual: tambah baris]',
  '                                              ▼                       │',
  '                                         ③ Review & Gatekeeper /audits/{id}  ◄─┘',
  '                                              │ 0 baris kuning + centang F3 bila total berbeda',
  '                                              ▼',
  '                                         ④ Wizard Terbit (modal): metadata → GPS → ukuran Cek Spek',
  '                                              ▼  PublishProject',
  '                                         ⑤ Sukses + QR ──► Kelola Proyek /desa/projects/{id}',
]));
add(Bullets([
  '**1. Dashboard Utama (`GET /desa`):** Ringkasan metrik proyek, kartu status audit, daftar "Dokumen dalam proses" (audit yang belum terbit), filter proyek, dan tombol utama "Unggah Dokumen RAB".',
  '**2. Layar Unggah (`GET /audits/create`):** Drag-drop satu berkas PDF/JPG/PNG maks 10 MB, plus tombol "Uji Contoh RAB" untuk demo. Tidak ada isian lain. Setelah unggah, langsung diarahkan ke layar review dengan status sedang dibaca.',
  '**3. Layar Dual-View Audit & Review (`GET /audits/{id}`):** Saat `reading`: indikator baca dengan polling. Saat `read_failed`: "Coba Lagi" atau "Isi Manual" (tabel kosong, berkas tetap). Selebihnya: dokumen asli di kiri, tabel rincian di kanan dengan baris kuning, koreksi inline, tambah/hapus baris, dan bar konsistensi total F3.',
  '**4. Wizard Setup Publikasi (Modal Step-by-Step):** Validasi zero-baris-kuning -> Form Nama, Jenis Proyek, Sumber Dana -> Input Koordinat Geofence GPS -> Pengisian Nilai Target Template Spek Fisik (ukuran template tanpa tombol hapus, ukuran kustom `origin = desa` maks 5) -> Terbitkan.',
  '**5. Halaman Kelola Proyek (`GET /desa/projects/{id}`):** Form update % progres fisik, dropzone unggah foto lapangan (maks 5 foto), tombol Cetak Poster QR A4, form CCO, dan riwayat revisi. Saat proyek Dalam Tinjauan BPD, form CCO tampil terkunci dengan keterangan netral (D12).',
]));

add(H2('Logika Gatekeeper Baris Kuning'));
add(P('Sistem deterministik PHP mengevaluasi setiap item RAB sebelum mengizinkan penerbitan ke publik:'));
add(Figure('d3-yellow-gatekeeper.png', 'Gambar 3 - Mekanisme Gatekeeper Baris Kuning (Zero-Warning Policy)'));

add(H2('Matriks Pemetaan Hulu-Hilir (Pemdes ke Warga)'));
add(Tbl(['Data yang Diinput Pemdes', 'Kolom Database', 'Komponen Halaman Warga', 'Dampak Bagi Warga'], [
  ['Dokumen Asli Berstempel', 'audits.file_path', 'Tombol "Lihat Dokumen Asli"', 'Menguji keaslian tabel vs dokumen kertas resmi.'],
  ['Verifikasi Item RAB', 'audit_items.*', 'ProjectItems.vue (Tabel Harga)', 'Melihat harga wajar vs standar SSH Gianyar.'],
  ['Koordinat GPS Proyek', 'projects.lat, lng', 'LocationGate.vue (Geofence)', 'Mengunci Cek Spek hanya dalam radius <= 300 meter.'],
  ['Nilai Spek Template', 'project_measurements.*', 'ProjectMeasurements.vue', 'Instrumen ukur nyata warga di lapangan.'],
  ['Foto & % Progres', 'progress_photos.*', 'ProjectProgress.vue (Linimasa)', 'Melihat bukti visual pengerjaan proyek.'],
  ['Catatan Revisi / CCO', 'project_changes.*', 'ProjectChanges.vue (Riwayat)', 'Transparansi jika terjadi addendum di lapangan.'],
], [22, 20, 26, 32], { size: 16 }));

add(H2('Matriks Kepemilikan & Penguncian Data'));
add(P('Setiap data yang tampil di halaman warga harus punya satu pengisi, satu momen kunci, dan satu jejak perubahan. Data yang tidak bisa menjawab ketiganya dianggap celah integritas.'));
add(Tbl(['Data di halaman warga', 'Pengisi', 'Terkunci kapan', 'Jika berubah'], [
  ['Dokumen asli berstempel', 'Pemdes (unggah)', 'Saat terbit, permanen', 'Tidak bisa diganti (D9)'],
  ['Baris item RAB (nama, volume, satuan, harga)', 'AI membaca, Pemdes mengoreksi', 'Saat terbit', 'CCO, tercatat di `project_changes` (D13)'],
  ['Label harga & selisih standar', 'Kode (`PriceLabeler`)', 'Selalu dihitung ulang', 'Ikut berubah otomatis bila item berubah (D14)'],
  ['Total pagu', 'Kode (jumlah baris) + konfirmasi Pemdes bila beda', 'Saat terbit', 'Mengikuti CCO item'],
  ['Nama, jenis, sumber dana, tahun', 'Pemdes (wizard)', 'Saat terbit, permanen', 'Tidak bisa diubah (D13)'],
  ['Titik GPS geofence', 'Pemdes (wizard)', 'Saat terbit', 'CCO, dibekukan saat Dalam Tinjauan (D12)'],
  ['Ukuran Cek Spek', 'Template (kunci) + Pemdes (nilai & ukuran kustom)', 'Saat terbit', 'Nilai via CCO, tidak bisa dihapus, dibekukan saat Dalam Tinjauan (D11, D12)'],
  ['Progres % & foto lapangan', 'Pemdes (berkala)', 'Tidak dikunci, hanya bertambah', 'Penurunan progres tercatat di `project_changes`'],
  ['Riwayat perubahan', 'Sistem (otomatis)', 'Append-only', 'Tidak bisa diedit atau dihapus siapa pun'],
  ['Status Dalam Tinjauan / Ditinjau', 'Sistem (ambang laporan) lalu BPD', 'Diatur BPD', 'Pemdes tidak punya akses (D16)'],
], [26, 26, 20, 28], { size: 16 }));

add(H2('Hak & Larangan Pemdes per Status Proyek'));
add(Tbl(['Status', 'Pemdes boleh', 'Pemdes tidak boleh'], [
  ['**Audit `reading` / `read_failed`**', 'Menunggu, Coba Lagi, Isi Manual', 'Menerbitkan'],
  ['**Audit `needs_review`** (ada baris kuning)', 'Koreksi inline, "Sudah Benar", hapus baris duplikat/subtotal', 'Menerbitkan, mengubah label harga'],
  ['**Audit `ready`** (0 baris kuning)', 'Membuka wizard, mengisi metadata, GPS, ukuran', 'Menerbitkan tanpa berkas, tanpa GPS, atau tanpa ukuran kunci'],
  ['**Proyek terbit, `public_status = none`**', 'Progres & foto, CCO sesuai D13, cetak poster QR', 'Unpublish, hapus proyek, hapus ukuran, ganti berkas asli'],
  ['**Proyek `in_review`** (Dalam Tinjauan BPD)', 'Progres & foto, hadir dan memberi klarifikasi di musdes (di luar aplikasi)', 'CCO item, ukuran, dan GPS (D12), atau menutup tinjauan'],
  ['**Proyek `reviewed`** (Ditinjau BPD)', 'Progres & foto, CCO sesuai D13 (mis. tindak lanjut perbaikan)', 'Mengubah atau menghapus catatan BPD'],
], [28, 38, 34], { size: 16 }));

// ==========================================
// 7. Edge cases & aturan sistem
// ==========================================
add(H1('Edge cases & aturan sistem'));
add(Tbl(['Kondisi / Edge Case', 'Perilaku Sistem yang Diharapkan'], [
  ['AI gagal membaca dokumen (> 60 detik / error OCR)', 'Status audit menjadi `read_failed`. Sistem memunculkan alert ramah dengan opsi "Coba Lagi" atau "Isi Manual". Berkas asli tidak hilang.'],
  ['Operator mencoba menerbitkan audit dengan 1 baris kuning', 'Tombol "Terbitkan" terkunci disabled. Tooltip menampilkan: "Selesaikan 1 baris kuning yang belum diperiksa".'],
  ['Total penjumlahan baris tidak sama dengan total tertulis di RAB', 'Sistem menampilkan modal peringatan. Operator wajib mencentang checkbox persetujuan: "Saya sudah memeriksa ketidakcocokan total ini" (F3).'],
  ['Perangkat operator desa tidak memiliki GPS saat set koordinat', 'Operator dapat memilih opsi "Masukkan Koordinat Manual" dan menyalin titik dari Google Maps.'],
  ['Operator mengunggah foto progres dengan metadata EXIF kamera', 'Backend secara otomatis meresize foto (max 1600px), mengubah ke format WebP, dan membuang seluruh tag EXIF/GPS demi privasi (A15).'],
  ['Terjadi perubahan fisik (CCO) setelah proyek terbit', 'Data proyek diperbarui langsung, status tetap Published (tidak kembali ke draf), dan perubahan otomatis tercatat di project_changes.'],
  ['Operator memakai "Isi Manual" tanpa melampirkan berkas', 'Ditolak dengan pesan: "Lampirkan berkas RAB asli agar warga dapat mencocokkan tabel dengan dokumen". Tidak ada audit yang tersimpan (D9).'],
  ['Operator mencoba menerbitkan tanpa GPS atau dengan ukuran kunci yang dihapus', '`PublishProject` menolak, wizard menandai langkah yang belum lengkap. Proyek tetap berstatus siap terbit (D3, D2).'],
  ['Operator mencoba menghapus ukuran Cek Spek setelah terbit', 'Aksi tidak tersedia di UI dan ditolak di backend, sehingga jawaban warga yang merujuk ukuran tersebut tetap aman (D11).'],
  ['Operator mengubah nilai ukuran / item / GPS saat proyek Dalam Tinjauan BPD', 'Form CCO terkunci. Keterangan: "Perubahan data dibuka kembali setelah BPD menyelesaikan tinjauan" (D12).'],
  ['Operator menurunkan progres (mis. 70% ke 40%)', 'Diizinkan, tapi wajib alasan singkat dan tercatat di `project_changes` agar warga melihat penjelasannya.'],
  ['Operator ingin menarik proyek dari publik', 'Tidak ada tombol unpublish. Penghentian pekerjaan dicatat sebagai CCO dengan alasan, dan halaman tetap dapat diakses warga (D10).'],
  ['Laporan selisih valid ke-N mencapai ambang saat siklus tinjauan sudah terbuka', 'Tidak membuka siklus baru. Laporan masuk ke siklus berjalan (D16).'],
], [35, 65], { size: 16 }));

// ==========================================
// 8. Instruksi untuk Developer
// ==========================================
add(H1('Instruksi untuk Developer'));
add(Callout('note', 'Definition of Ready (DoR)', [
  'Sebelum coding dimulai, pastikan: (1) Branch git dibuat dengan format `feat/pemdes-workflow`, (2) Database migration `projects`, `audits`, `progress_photos`, dan `project_measurements` sudah ter-migrate, (3) Seeder Desa Melinggih dapat di-clone dengan bersih oleh VillageCloner.',
]));

add(Tbl(['Lakukan', 'Hindari'], [
  ['Gunakan Action class terisolasi (mis. PublishProjectAction, UpdateProjectProgressAction)', 'Menulis logika bisnis di controller atau closure route.'],
  ['Validasi kepemilikan desa melalui BelongsToVillage dan ProjectPolicy', 'Melakukan query desa manual tanpa tenant context check.'],
  ['Konversi foto lapangan ke WebP dan bersihkan EXIF sebelum simpan ke disk', 'Menyimpan file mentah dari input pengguna ke disk publik.'],
  ['Pakai label netral dari resources/js/lib/labels.ts', 'Menulis string hardcoded yang memojokkan di komponen Vue.'],
  ['Mengisi `published_at` hanya di `PublishProject` (D8)', 'Menerbitkan dari action lain, termasuk `CreateDesaProject`.'],
  ['Memanggil `RecordProjectChange` di transaksi yang sama dengan setiap edit pasca-terbit', 'Mengupdate model proyek/item/ukuran yang sudah terbit tanpa mencatat perubahan.'],
  ['Memeriksa `public_status` di policy sebelum CCO (D12)', 'Mengandalkan UI yang menyembunyikan tombol sebagai satu-satunya penjaga.'],
  ['Menolak penghapusan ukuran pasca-terbit di policy/action (D11)', 'Mengandalkan cascade delete pada `report_answers`.'],
], [50, 50], { size: 16 }));

// ==========================================
// 9. Epic & Story breakdown
// ==========================================
add(H1('Epic & Story breakdown'));

add(storyHeader('S1', 'Upload RAB, Isi Manual & Layar Review Audit', 'info', '4 hari kalender', 'M1-Standard'));
add(P('Sebagai aparatur desa, saya ingin mengunggah berkas RAB dan melihat perbandingan harga terhadap standar resmi, agar saya bisa memverifikasi kewajaran anggaran sebelum diterbitkan.'));
add(Tbl(['Area', 'Task', 'File Terkait'], [
  ['BE', 'Route & Controller Unggah Dokumen RAB', 'app/Modules/Audit/Http/Controllers/AuditUploadController.php'],
  ['BE', 'Action & Job Parsing RAB Asynchronous', 'app/Modules/Audit/Jobs/ProcessRabDocumentJob.php'],
  ['BE', 'Alihfungsi form manual menjadi jalur Isi Manual (wajib berkas, menghasilkan audit_items, tanpa publish)', 'app/Modules/Desa/Actions/CreateDesaProject.php, StoreProjectRequest.php'],
  ['FE', 'Komponen Upload Drag-and-Drop & Dual-Viewer', 'resources/js/pages/audit/Review.vue'],
], [15, 45, 40], { size: 16 }));
add(AC([
  ['PD-01', 'Aparatur desa di halaman upload', 'Mengunggah file PDF RAB valid (<= 10 MB)', 'Berkas disimpan di disk privat, job parsing berjalan di background, status reading'],
  ['PD-02', 'File non-PDF/JPG atau > 10 MB', 'Aparatur mencoba mengunggah', 'Sistem menolak dengan pesan validasi ramah di antarmuka'],
  ['PD-03', 'Proses parsing selesai', 'Aparatur membuka hasil audit', 'Tabel menampilkan rincian item lengkap dengan label harga deterministik'],
  ['PD-04', 'Terdapat baris kuning (NeedsCheck)', 'Aparatur mencoba menekan "Terbitkan"', 'Tombol terkunci (disabled) dan meminta penyelesaian baris kuning'],
  ['PD-11', 'AI gagal membaca (`read_failed`)', 'Aparatur memilih "Isi Manual" dan mengetik baris item', 'Audit yang sama (berkas tetap) berisi `audit_items` berlabel, lalu lanjut ke review. Tidak ada proyek yang terbit dari langkah ini.'],
  ['PD-12', 'Aparatur di form Isi Manual tanpa berkas', 'Menekan simpan', 'Ditolak dengan pesan meminta berkas asli (D9)'],
]));

add(storyHeader('S2', 'Wizard Publikasi Proyek & Geofencing', 'ok', '2 hari kalender', 'S1'));
add(P('Sebagai aparatur desa, saya ingin melengkapi metadata proyek, titik koordinat, dan template ukuran teknis, agar proyek dapat diterbitkan ke hadapan warga dengan instrumen Cek Spek yang valid.'));
add(Tbl(['Area', 'Task', 'File Terkait'], [
  ['BE', 'Action PublishProject: validasi 0 baris kuning & geofence', 'app/Modules/Project/Actions/PublishProject.php'],
  ['BE', 'Katalog Template Ukuran Fisik Proyek', 'config/sonar.php (measurement_templates)'],
  ['FE', 'Modal Wizard Setup Publikasi (Metadata, GPS, Spek)', 'resources/js/features/project/PublishWizardModal.vue'],
], [15, 45, 40], { size: 16 }));
add(AC([
  ['PD-05', 'Baris kuning telah 100% dikonfirmasi', 'Aparatur menekan "Terbitkan Proyek"', 'Modal Wizard Publikasi terbuka menampilkan form metadata dan template spek'],
  ['PD-06', 'Aparatur memilih jenis proyek (Rabat Beton)', 'Template ukuran dimuat', 'Tiga ukuran wajib (panjang, lebar, tebal) muncul dan wajib diisi nilai targetnya'],
  ['PD-07', 'Form Wizard terisi lengkap dengan GPS', 'Aparatur konfirmasi publikasi', 'Proyek berstatus published dan halaman publik live di /p/{slug}'],
  ['PD-13', 'Wizard tanpa GPS atau dengan ukuran kunci dihapus', 'Aparatur konfirmasi publikasi', '`PublishProject` menolak, proyek tidak terbit, langkah yang kurang ditandai'],
  ['PD-14', 'Proyek sudah terbit', 'Aparatur mencari aksi unpublish / hapus proyek', 'Aksi tidak tersedia, request langsung ke backend ditolak (D10)'],
]));

add(storyHeader('S3', 'Poster Cetak QR Code A4', 'warn', '1 hari kalender', 'S2'));
add(P('Sebagai aparatur desa, saya ingin mencetak poster A4 berisi QR Code proyek, agar saya bisa menempelkannya di papan plang proyek fisik di lapangan.'));
add(Tbl(['Area', 'Task', 'File Terkait'], [
  ['BE', 'Endpoint Cetak Poster QR (GET /projects/{project}/qr)', 'app/Modules/Project/Http/Controllers/ProjectQrController.php'],
  ['FE', 'Halaman Cetak A4 Print-Friendly', 'resources/js/pages/project/QrPoster.vue'],
], [15, 45, 40], { size: 16 }));
add(AC([
  ['PD-08', 'Proyek telah terbit', 'Aparatur membuka menu cetak poster QR', 'Sistem merender layout A4 dengan QR Code SVG tajam dan ajakan audit warga'],
]));

add(storyHeader('S4', 'Update Progres Fisik & Riwayat Perubahan (CCO)', 'note', '2 hari kalender', 'S2'));
add(P('Sebagai aparatur desa, saya ingin memperbarui persentase pengerjaan fisik, mengunggah foto lapangan, dan mencatat addendum perubahan, agar progres proyek terpantau transparan oleh warga.'));
add(Tbl(['Area', 'Task', 'File Terkait'], [
  ['BE', 'Action StoreProgressPhoto & Sanitasi WebP', 'app/Modules/Project/Actions/StoreProgressPhoto.php'],
  ['BE', 'Action RecordProjectChange untuk audit trail CCO', 'app/Modules/Project/Actions/RecordProjectChange.php'],
  ['FE', 'Panel Manajemen Proyek Berjalan di Dashboard', 'resources/js/pages/desa/ProjectManage.vue'],
], [15, 45, 40], { size: 16 }));
add(AC([
  ['PD-09', 'Proyek fisik berjalan', 'Aparatur upload foto dokumentasi lapangan', 'Foto dikonversi ke WebP, EXIF GPS kamera dibersihkan, progres % terupdate'],
  ['PD-10', 'Terjadi revisi spek/harga di lapangan', 'Aparatur memasukkan perubahan CCO', 'Rekaman tercatat di project_changes dan langsung tampil di linimasa publik'],
  ['PD-15', 'Proyek terbit memiliki ukuran Cek Spek', 'Aparatur mencoba menghapus salah satu ukuran', 'Ditolak, ukuran dan jawaban warga tetap utuh (D11)'],
  ['PD-16', 'Proyek berstatus Dalam Tinjauan BPD', 'Aparatur mengubah nilai ukuran, item, atau GPS', 'Ditolak (D12). Unggah foto & progres tetap berhasil.'],
]));

add(storyHeader('S5', 'Pemicu Otomatis Tinjauan BPD', 'danger', '1 hari kalender', 'S2'));
add(P('Sebagai warga, saya ingin laporan selisih yang sudah cukup banyak otomatis sampai ke BPD, agar proyek yang bermasalah tidak berhenti di laporan saja. Sebagai aparatur desa, saya ingin tahu kapan proyek saya sedang ditinjau, agar saya bisa menyiapkan klarifikasi.'));
add(Tbl(['Area', 'Task', 'File Terkait'], [
  ['BE', 'Action OpenReviewCycle: hitung pelapor selisih valid (`CountValidMismatchReporters`), buka siklus, isi `current_cycle_id`, `public_status = in_review`, `in_review_since`', 'app/Modules/Review/Actions/OpenReviewCycle.php'],
  ['BE', 'Listener `ReportSubmitted` yang memanggil OpenReviewCycle', 'app/Modules/Review/ (didaftarkan di provider modul)'],
  ['FE', 'Badge "Dalam Tinjauan BPD" di dashboard Pemdes & form CCO terkunci', 'resources/js/pages/desa/Dashboard.vue, ProjectManage.vue'],
], [15, 45, 40], { size: 16 }));
add(AC([
  ['PD-17', 'Proyek terbit dengan 2 pelapor selisih valid', 'Pelapor selisih valid ke-3 mengirim laporan', 'Siklus tinjauan terbuka, `public_status = in_review`, halaman warga menampilkan "Dalam Tinjauan BPD"'],
  ['PD-18', 'Siklus tinjauan sudah terbuka', 'Laporan selisih berikutnya masuk', 'Tidak ada siklus baru. Laporan tercatat pada siklus berjalan.'],
]));

add(H2('Status Story per 26 November 2026'));
add(Tbl(['Story', 'Status', 'Catatan'], [
  ['**S1** Upload RAB, Isi Manual & Review', 'Selesai (backend & UI)', 'Isi Manual sudah bisa lewat tambah baris di layar review. Form lama `/desa/projects/create` masih aktif, dipensiunkan di S6/S7.'],
  ['**S2** Wizard Publikasi & Geofencing', 'Sebagian', 'Backend lengkap. Bug `origin` ukuran kustom di wizard (S6).'],
  ['**S3** Poster QR A4', 'Selesai', 'Modal sukses terbit menampilkan QR dan tautan poster.'],
  ['**S4** Progres & CCO', 'Selesai', 'Kolom `reason` terpisah, pembekuan D12, larangan hapus D11.'],
  ['**S5** Pemicu Tinjauan BPD', 'Sebagian', 'Listener aktif, aturan perangkat berbeda belum diterapkan (S7).'],
], [30, 20, 50], { size: 16 }));

add(storyHeader('S6', 'Penyelesaian Alur Frontend & QA End-to-End (Dev 1)', 'info', '2–3 hari kalender', 'S1–S5'));
add(P('Sebagai tim, kami ingin alur Pemdes dari unggah RAB sampai tampil di halaman warga berjalan mulus tanpa jalan pintas, agar demo ke juri dan pemakaian nyata konsisten dengan keputusan D1–D19.'));
add(Tbl(['Prio', 'Task', 'File Terkait'], [
  ['P1', 'Perbaiki nilai `origin` ukuran kustom di wizard: kirim `desa` (bukan `custom`), dan saat memuat ulang ukuran tersimpan petakan `desa` ke kustom (bukan ke template)', 'resources/js/features/project/PublishWizardModal.vue'],
  ['P1', 'Pensiunkan form lama (D17): tombol "Isi Manual" di dashboard diarahkan ke `/audits/create` (unggah dulu), lalu mode tambah baris di layar review. Hapus tautan ke `/desa/projects/create`', 'resources/js/pages/desa/Dashboard.vue, ProjectCreate.vue'],
  ['P1', 'Selaraskan polling layar review dengan D18: batas tunggu 60 detik (bukan 180), lalu tampilkan status `read_failed` dari server. Pertimbangkan `usePoll` Inertia v3 menggantikan `setInterval`', 'resources/js/pages/audit/Review.vue'],
  ['P2', 'Tampilan publik baris yang dikonfirmasi "Sudah Benar" tetapi labelnya masih `needs_check` (mis. satuan beda dengan acuan): tampilkan sebagai "Tidak dapat dibandingkan" dengan bahasa netral (D6), bukan "Perlu dicek"', 'resources/js/features/project/ProjectItems.vue, components/ui/PriceLabel.vue'],
  ['P2', 'Saat kuota AI demo habis, layar unggah menampilkan pesan netral dan tombol "Uji Contoh RAB" / "Isi Manual" (D19)', 'resources/js/pages/audit/Create.vue'],
  ['QA', 'Jalankan TC-PD-01 s.d. TC-PD-15 di HP (viewport 360 px) dan desktop, termasuk cetak poster QR (Ctrl+P)', '-'],
], [8, 57, 35], { size: 16 }));
add(AC([
  ['PD-19', 'Pemdes menambah 1 ukuran kustom di wizard', 'Menekan "Terbitkan"', 'Proyek terbit, ukuran kustom tersimpan dengan `origin = desa` dan tampil di halaman warga'],
  ['PD-20', 'Pemdes di dashboard', 'Menekan "Isi Manual"', 'Diarahkan ke layar unggah. Tidak ada jalur membuat proyek tanpa berkas dan tanpa layar review'],
  ['PD-21', 'Audit berstatus `reading` lebih dari 60 detik', 'Pemdes menunggu di layar review', 'Layar berhenti polling dan menampilkan "Coba Lagi" / "Isi Manual" sesuai status server'],
]));

add(storyHeader('S7', 'Sisa Pekerjaan Backend (Dev 2)', 'note', '1–2 hari kalender', 'S1–S5'));
add(P('Sebagai pemilik backend, saya ingin menutup sisa celah integritas dan biaya agar keputusan D15, D16, D18, dan D19 benar-benar ditegakkan oleh kode.'));
add(Tbl(['Prio', 'Task', 'File Terkait'], [
  ['P1', 'Hapus jalur `is_published` di `CreateDesaProject` beserta route `desa.projects.create/store` setelah S6 memindahkan tombol "Isi Manual" (D8, D17)', 'app/Modules/Desa/Actions/CreateDesaProject.php, routes.php'],
  ['P1', 'Satukan logika ambang tinjauan: listener memakai aturan >= 3 akun **dan** >= 3 perangkat berbeda (Adendum v1.2 5.2). Hapus atau pakai `EvaluateReviewThreshold` agar tidak ada dua sumber kebenaran', 'app/Modules/Review/Listeners/TriggerReviewCycleOnThreshold.php, Actions/EvaluateReviewThreshold.php'],
  ['P1', 'Tegakkan `sonar.ai_budget` sebelum memanggil AI. "Uji Contoh RAB" di `/audits` memakai fixture (seperti `OpenSampleRab`), bukan AI', 'app/Modules/Audit/Jobs/ProcessRabDocumentJob.php, Http/Controllers/AuditUploadController.php'],
  ['P2', 'Selaraskan batas waktu (D18): `$timeout` job 180 detik, worker produksi `--timeout=75`, dan `retry_after` antrean 90 detik saat ini tidak konsisten', 'ProcessRabDocumentJob.php, compose.prod.yml, config/queue.php'],
  ['P2', 'Snapshot nilai target di jawaban warga (D15), koordinasi dengan pemilik modul Report', 'migration report_answers, app/Modules/Report/Actions/SubmitReport.php'],
  ['P3', 'Filter katalog standar harga per kabupaten & tahun di job AI dan layar review', 'ProcessRabDocumentJob.php, AuditReviewController.php'],
], [8, 57, 35], { size: 16 }));

// ==========================================
// 10. QA test cases per story
// ==========================================
add(H1('QA test cases per story'));
add(TC([
  ['TC-PD-01', 'Unggah berkas RAB PDF valid', '1. Buka /desa -> Klik "Unggah Dokumen RAB"\n2. Pilih sample-rab-melati.pdf\n3. Klik "Proses Dokumen"', 'Dokumen terbaca, tabel hasil audit tampil <= 20 detik.', 'P1'],
  ['TC-PD-02', 'Gatekeeper baris kuning mengunci publikasi', '1. Buka hasil audit yang memiliki baris kuning\n2. Cek status tombol "Terbitkan Proyek"', 'Tombol disabled, tooltip meminta pemeriksaan baris kuning.', 'P1'],
  ['TC-PD-03', 'Penerbitan proyek via Wizard', '1. Konfirmasi seluruh baris kuning\n2. Klik "Terbitkan"\n3. Lengkapi koordinat & spek rabat beton\n4. Klik "Konfirmasi Terbit"', 'Proyek berstatus Published, tautan /p/{slug} live di browser.', 'P1'],
  ['TC-PD-04', 'Cetak poster QR Code A4', '1. Buka menu Cetak QR pada proyek terbit\n2. Tekan Ctrl+P di browser', 'Poster A4 pas satu halaman, QR Code tajam dan dapat discan.', 'P2'],
  ['TC-PD-05', 'Sanitasi EXIF foto progres lapangan', '1. Unggah foto JPEG berkamera dengan geotag\n2. Cek file hasil upload di disk', 'File tersimpan dalam format WebP, koordinat GPS internal foto telah terhapus.', 'P1'],
  ['TC-PD-06', 'Isi Manual tetap lewat gatekeeper', '1. Pilih "Isi Manual" tanpa berkas\n2. Ulangi dengan berkas, isi baris item\n3. Buka /p/{slug} setelah terbit', 'Langkah 1 ditolak. Setelah terbit, tabel harga dan tombol "Lihat Dokumen Asli" tampil di halaman warga.', 'P1'],
  ['TC-PD-07', 'Wizard menolak proyek tanpa GPS', '1. Lengkapi wizard tanpa koordinat\n2. Klik "Konfirmasi Terbit"', 'Proyek tidak terbit, langkah GPS ditandai belum lengkap.', 'P1'],
  ['TC-PD-08', 'CCO tercatat dan tampil publik', '1. Ubah volume satu item pada proyek terbit\n2. Buka tab Riwayat di /p/{slug}', 'Nilai lama, nilai baru, dan waktu perubahan tampil. Label harga ikut dihitung ulang.', 'P1'],
  ['TC-PD-09', 'Pembekuan saat Dalam Tinjauan', '1. Kirim 3 laporan selisih valid dari 3 akun warga\n2. Login Pemdes, coba ubah nilai ukuran\n3. Unggah foto progres', 'Status proyek Dalam Tinjauan. Ubah ukuran ditolak, unggah foto berhasil.', 'P1'],
  ['TC-PD-10', 'Ukuran tidak bisa dihapus pasca-terbit', '1. Pada proyek terbit yang sudah punya laporan warga, coba hapus satu ukuran', 'Ditolak. Jawaban warga pada ukuran tersebut masih tampil.', 'P2'],
  ['TC-PD-11', 'Ukuran kustom di wizard', '1. Buka wizard dari audit siap terbit\n2. Tambah 1 ukuran kustom\n3. Terbitkan', 'Proyek terbit tanpa error validasi, ukuran kustom tampil di /p/{slug}.', 'P1'],
  ['TC-PD-12', 'AI gagal / waktu habis', '1. Unggah berkas yang tidak bisa dibaca (atau matikan worker antrean)\n2. Tunggu di layar review', 'Dalam ±60 detik muncul "Coba Lagi" dan "Isi Manual". Berkas tetap ada.', 'P1'],
  ['TC-PD-13', 'Isi Manual lewat layar review', '1. Dari audit `read_failed`, pilih Isi Manual\n2. Tambah 3 baris dengan acuan standar\n3. Terbitkan', 'Label harga terhitung otomatis. Halaman warga menampilkan tabel dan tombol "Lihat Dokumen Asli".', 'P1'],
  ['TC-PD-14', 'Centang selisih total (F3)', '1. Buka audit dengan total tertulis ≠ jumlah baris\n2. Coba terbitkan tanpa centang, lalu dengan centang', 'Tanpa centang ditolak, dengan centang lolos.', 'P1'],
  ['TC-PD-15', 'Kuota AI demo', '1. Di sesi demo, unggah berkas sampai melebihi batas\n2. Klik "Uji Contoh RAB"', 'Unggahan di atas batas ditolak dengan pesan netral. Contoh RAB tetap bisa dipakai.', 'P2'],
]));

// ==========================================
// 11. Rollout, rollback & monitoring
// ==========================================
add(H1('Rollout, rollback & monitoring'));
add(P('Penerapan fitur Pemdes dilakukan secara modular mengikuti prinsip zero-drift Docker:'));
add(Numbered([
  'Jalankan migrasi database via `php artisan migrate`.',
  'Pastikan konfigurasi disk storage lokal/S3 memiliki izin tulis direktori.',
  'Verifikasi template ukuran fisik di `config/sonar.php` termuat dengan benar.',
  'Periksa data lama: proyek terbit yang dibuat lewat form manual v1.0 (tanpa berkas, item, atau GPS) didaftar dan dilengkapi Pemdes lewat CCO, atau ditandai di dashboard sebagai "Belum lengkap".',
  'Jalankan pengujian end-to-end isolasi sesi demo via `make test ARGS="--filter=Desa"`.',
]));

// ==========================================
// 12. Risiko & mitigasi
// ==========================================
add(H1('Risiko & mitigasi'));
add(Tbl(['Risiko', 'Dampak', 'Rencana Mitigasi'], [
  ['AI salah memetakan item material unik desa', 'Sedang', 'Operator Pemdes memiliki tombol edit inline dan konfirmasi manual untuk membetulkan padanan.'],
  ['Operator kesulitan mencari koordinat GPS proyek', 'Rendah', 'Sistem menyediakan tombol 1-klik "Gunakan Lokasi Saya Saat Ini" via browser geolocation API.'],
  ['Pemdes enggan mengunggah dokumen karena takut diaudit', 'Tinggi', 'Sesuai PRD v1.2 F23, BPD memiliki hak penerbitan mandiri dari dokumen musdes jika desa menolak.'],
  ['Pemdes mengubah target spek untuk menggugurkan laporan warga', 'Tinggi', 'Pembekuan saat Dalam Tinjauan (D12), snapshot target di jawaban warga (D15), dan setiap CCO tampil publik.'],
  ['Pemdes menghapus ukuran sehingga jawaban warga ikut terhapus', 'Tinggi', 'Penghapusan ukuran pasca-terbit diblokir di backend (D11).'],
  ['Jalur Isi Manual dipakai untuk menghindari pembacaan AI', 'Sedang', 'Isi Manual tetap wajib berkas asli dan tetap melewati `PriceLabeler`. Warga bisa mencocokkan tabel dengan dokumen.'],
], [35, 15, 50], { size: 16 }));

// ==========================================
// 13. Lampiran: Checklist operasional
// ==========================================
add(H1('Lampiran: Checklist operasional'));
add(Checks([
  'Pastikan seeder aset dokumen RAB dan foto contoh tersedia di `database/seeders/assets`.',
  'Pastikan service background queue berjalan (`php artisan queue:listen`).',
  'Uji coba alur dari perspektif Pemdes di HP (viewport 360 px) dan desktop.',
  'Verifikasi tautan publik `/p/{slug}` dapat diakses tanpa sesi login (guest-friendly).',
  'Pastikan tidak ada proyek terbit tanpa `audits.file_path`, `projects.lat/lng`, atau `audit_items`.',
  'Uji siklus penuh: Pemdes terbit → 3 warga lapor selisih → status Dalam Tinjauan → form CCO Pemdes terkunci.',
]));

async function main() {
  console.log('Writing document to:', OUT);
  await k.save(OUT);
  console.log('Docx created successfully!');
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
