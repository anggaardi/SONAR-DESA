// Generator Feature Spec Peran Pemda (Pemerintah Kabupaten) Sonar Desa.
// Menggunakan docx-kit dari MEMSKILL (lean-feature-spec), sama seperti generate-pemdes-spec.cjs.
// Diagram: jalankan generate-pemda-diagrams.cjs dulu.
const path = require('path');
const kit = require('/home/kadekangga/Projects/MEMSKILL/feature-spec/scripts/lib/docx-kit');

const OUT = process.argv[2] || path.resolve(__dirname, '..', 'PRD-Fitur-Pemda.docx');
const IMG = path.resolve(__dirname, 'img');

const k = kit.create({
  title: 'Sonar Desa - Peran Pemda',
  version: '1.0',
  imgDir: IMG,
});

const {
  add, P, H1, H2, H3, Bullets, Numbered, Checks, Tbl, Callout, Code, Figure,
  cover, toc, scopeTable, storyHeader, AC, TC, pageBreak,
} = k;

// ==========================================
// COVER
// ==========================================
add(cover({
  eyebrow: 'FEATURE SPEC',
  heading: 'Peran Pemda: Acuan Harga & Rekap Kabupaten',
  subtitle: 'Kabupaten menjadi pemilik resmi acuan harga audit RAB desa dan menerima umpan balik dari semua desanya',
  meta: [
    ['Status', 'Disepakati'],
    ['Versi', '1.0'],
    ['Owner', 'Dev 2'],
    ['Tanggal', '26 November 2026'],
    ['Pelaksana', 'Dev 2 (BE), Dev 1 (FE, M2, QA)'],
    ['Estimasi', 'Dev 2 9 hr, Dev 1 10,5 hr + QA 3 hr'],
    ['Target', '12 Des 2026'],
    ['Epic', 'SD-PEMDA'],
  ],
}));

add(Callout('gray', 'Posisi dokumen ini', [
  'Melengkapi **PRD Fitur Pemdes v1.2** dan menjawab kritik juri di **Adendum v1.2 bagian 4.1** (acuan harga diisi tim dev dan usang). D1–D19 di PRD Pemdes tetap berlaku. Keputusan baru memakai awalan **DP**.',
]));

add(H3('Cara membaca dokumen ini'));
add(Tbl(['Peran', 'Wajib baca', 'Opsional'], [
  ['**Dev 2** (backend)', '1, 4, 5, 6.1, 6.4–6.8, 7, 8, 9 (S1, S3–S6 bagian BE)', '10'],
  ['**Dev 1** (frontend, M2, QA)', '1, 4, 5, 6.3, 6.6, 7, 8, 9 (S2, S3–S6 bagian FE), 10', '6.5'],
  ['**Juri / reviewer**', '1, 2, 4', '6.3'],
], [26, 52, 22], { size: 17 }));

add(toc([
  'TL;DR',
  'Latar belakang, tujuan & metrik',
  'Scope',
  'Keputusan yang disepakati',
  'Kondisi sekarang vs target',
  'Desain solusi',
  'Edge cases & aturan',
  'Instruksi untuk Developer',
  'Epic & Story breakdown',
  'QA test cases per story',
  'Rollout, rollback & monitoring',
  'Risiko & open questions',
  'Lampiran: checklist operasional',
]));
add(pageBreak());

// ==========================================
// 1. TL;DR
// ==========================================
add(H1('TL;DR'));
add(Callout('note', null, [
  '**Masalah.** Acuan harga yang dipakai audit RAB (`price_standards`) sekarang diisi tim dev lewat `ProjectSeeder`. Tidak ada pihak berwenang yang memilikinya. Item RAB yang tidak punya acuan berhenti sebagai label abu-abu "Tanpa acuan" dan tidak ada yang menindaklanjuti. Kalau satu harga diubah, angka di halaman warga yang sudah terbit ikut bergeser, karena harga acuan dibaca ulang setiap kali halaman dibuka.',
  '**Solusi.** Tambah role `pemda` yang terikat ke satu kabupaten, dengan tiga layar di `/pemda`: **Rekap kabupaten** (per kecamatan dan per desa, diurutkan sebagai prioritas pendampingan), **Katalog acuan** (kelola harga per tahun anggaran), dan **Antrean "Tanpa Acuan"** (item tanpa acuan dari semua desa, dikelompokkan, lalu dijadikan acuan dengan satu tombol). Audit RAB menyimpan salinan harga acuan per item (`standard_price_rp`), jadi perubahan dari Pemda hanya berlaku untuk pelabelan berikutnya.',
  '**Kenapa ini kuat untuk lomba.** Sonar Desa menjadi satu siklus tata kelola yang utuh: Pemda menetapkan acuan, Pemdes mengaudit, warga mengecek di lapangan, BPD meninjau, lalu hasilnya kembali ke Pemda. Tidak ada tabel baru: hanya tiga kolom tambahan dan satu modul `Pemda`.',
]));
add(Figure('pm1-asis-tobe.png', 'Gambar 1 - Sekarang vs target: siapa pemilik acuan harga'));
add(Figure('pm2-siklus.png', 'Gambar 2 - Siklus tata kelola empat peran setelah Pemda masuk'));

// ==========================================
// 2. Latar belakang, tujuan & metrik
// ==========================================
add(H1('Latar belakang, tujuan & metrik'));
add(H2('Masalah'));
add(Bullets([
  '**Acuan tidak punya pemilik.** 8 harga acuan ditulis tangan di `database/seeders/ProjectSeeder.php:179` (`seedStandards`). Juri sudah mempertanyakan sumber dan keberlakuannya (Adendum v1.2 4.1).',
  '**Item tanpa acuan tidak menghasilkan tindakan.** Label `NoReference` hanya berarti "tidak dinilai". Tidak ada yang mengumpulkan item itu dari banyak desa untuk ditetapkan acuannya.',
  '**Data terbit tidak stabil.** `ProjectResource` membaca `standard->price_rp` setiap kali halaman dibuka. Kalau harga acuan diubah, harga acuan yang tampil berubah tetapi `over_pct` yang tersimpan tidak, sehingga angka di halaman warga saling bertentangan.',
  '**Rantai pengawasan berhenti di desa.** Rantainya berhenti di BPD. Belum ada pandangan tingkat kabupaten, padahal pembinaan dan pengawasan keuangan desa memang tugas kabupaten (evaluasi APBDes, Inspektorat).',
]));
add(H2('Tujuan'));
add(Tbl(['#', 'Tujuan'], [
  ['**G1**', 'Acuan harga per kabupaten dan tahun dikelola oleh akun Pemda, lengkap dengan sumber (`source_ref`), tanpa campur tangan tim dev.'],
  ['**G2**', 'Item "Tanpa acuan" dari semua desa di satu kabupaten terkumpul di satu antrean dan bisa dijadikan acuan dengan satu alur.'],
  ['**G3**', 'Pemda melihat rekap per kecamatan dan per desa dengan bahasa netral: siapa yang belum mengunggah RAB, berapa item di atas acuan, dan proyek mana yang sedang ditinjau BPD.'],
  ['**G4**', 'Perubahan acuan tidak pernah mengubah angka di halaman yang sudah terbit.'],
], [8, 92], { size: 17 }));
add(H2('Metrik keberhasilan'));
add(Tbl(['Metrik', 'Target', 'Cara mengukur'], [
  ['Stabilitas halaman terbit', '0 angka berubah', 'Feature test: ubah `price_rp`, bandingkan respons `/p/{slug}` sebelum dan sesudah'],
  ['Kelengkapan antrean', '100% item `no_reference` muncul', 'Feature test dengan item dari 3 desa contoh'],
  ['Isolasi kabupaten', '0 desa kabupaten lain terlihat', 'Feature test: akun Pemda "Bangli" membuka `/pemda`'],
  ['Demo aman', '0 baris `price_standards` berubah', 'Feature test: akun demo Pemda POST/PATCH/DELETE ke `/pemda/acuan`'],
  ['Alur demo juri', '≤ 3 klik dari `/demo` ke antrean', 'Uji manual saat QA (S6)'],
  ['Kecepatan rekap', '< 1 detik untuk 20 desa', 'Uji manual dengan data seed + hitung query di feature test'],
], [30, 22, 48], { size: 17 }));

// ==========================================
// 3. Scope
// ==========================================
add(H1('Scope'));
add(scopeTable({
  inScope: [
    'Role `pemda` + kolom `users.regency`, akun Pemda di seeder.',
    'Kolom `villages.district` (kecamatan) + 5 desa contoh di Gianyar.',
    'Snapshot `audit_items.standard_price_rp` dan semua titik bacanya.',
    'Katalog acuan: daftar per tahun, tambah, ubah, hapus (jika belum dipakai), salin ke tahun baru.',
    'Antrean "Tanpa Acuan" + tombol "Jadikan acuan".',
    'Median antar-desa di antrean (prioritas P2, "kalau sempat").',
    'Rekap kabupaten per kecamatan dan per desa.',
    'Pintu "Pemda" di `/demo`, hanya baca.',
  ],
  out: [
    'Teks "Kabupaten Gianyar" yang di-hardcode di penjelasan label (`ProjectResource.php:156-157`). Tiket terpisah.',
    'Pelabelan ulang otomatis audit draf atau terbit saat acuan baru dibuat.',
    'Pemda mengubah data desa, proyek, atau laporan warga.',
    'Satu akun Pemda untuk lebih dari satu kabupaten.',
  ],
  later: [
    'Peta sebaran (Leaflet, perlu persetujuan dependency).',
    'Impor Perbup/SSH dari PDF memakai AI.',
    'Eskalasi temuan ke Inspektorat / Jaga Desa.',
    'Notifikasi ke desa saat acuan baru tersedia.',
    'Ekspor rekap ke PDF.',
  ],
}));
add(Callout('warn', 'Jaga scope', [
  'Waktu tinggal ±3 minggu sampai penyisihan ditutup. Kalau ada ide tambahan saat mengerjakan, catat di kolom LATER dan sebut di video sebagai roadmap. Jangan diselipkan ke story.',
]));

// ==========================================
// 4. Keputusan yang disepakati
// ==========================================
add(H1('Keputusan yang disepakati'));
add(Tbl(['#', 'Keputusan', 'Alasan', 'Sumber'], [
  ['**DP1**', 'Role baru `pemda`, ditautkan ke kabupaten lewat kolom `users.regency` (nullable, diisi hanya untuk pemda). `Role::belongsToVillage()` mengembalikan `false` untuk pemda.', 'Paling ringan dan cocok dengan `villages.regency` serta `price_standards.regency` yang sudah ada. Tidak perlu tabel `regencies`.', 'Jawaban #1'],
  ['**DP2**', 'Halaman Pemda tidak memakai middleware `village`. Akses lintas desa hanya lewat satu Action `RegencyVillages`, satu-satunya tempat yang melepas global scope `village`.', '`CurrentVillage::id()` sengaja melempar error tanpa konteks. Melepas scope di banyak tempat berisiko membocorkan data antar-kabupaten.', 'Desain'],
  ['**DP3**', 'Simpan salinan `standard_price_rp` di `audit_items` setiap kali `PriceLabeler` dijalankan. Semua tampilan harga acuan membaca salinan ini.', 'Perubahan dari Pemda tidak boleh mengubah halaman terbit. Sekaligus menutup bug integritas yang sudah ada di `ProjectResource`.', 'Jawaban #2'],
  ['**DP4**', 'Harga acuan boleh diubah kapan saja. Acuan yang sudah dipakai item RAB tidak bisa dihapus. Pergantian tahun memakai "Salin katalog ke tahun baru".', 'Berkat DP3, mengubah harga aman. Menghapus akan mengosongkan `standard_id` (`nullOnDelete`) di audit lama.', 'Desain'],
  ['**DP5**', 'Tambah `villages.district`. Seed 5 desa contoh bernama **"Desa Contoh <Kecamatan>"** di kecamatan asli Gianyar, dengan data fiktif.', 'Nama desa asli + data fiktif yang tampil publik di `/d/{slug}` bisa disangka temuan nyata. Ini penyesuaian dari jawaban #3.', 'Jawaban #3 (disesuaikan)'],
  ['**DP6**', 'Tanpa peta. Rekap berupa tabel per kecamatan dan per desa.', 'Belum ada library peta di `package.json`, dan menambah dependency perlu persetujuan.', 'Jawaban #4'],
  ['**DP7**', 'Antrean mengelompokkan item `no_reference` berdasarkan nama ternormalisasi (huruf kecil, spasi dirapikan), tanpa AI. Grup pindah ke tab "Sudah ada acuan" jika ada acuan dengan nama ternormalisasi yang sama di katalog tahun itu.', 'Deterministik, mudah diuji, tidak perlu kolom penghubung.', 'Jawaban #5'],
  ['**DP8**', 'Acuan baru tidak melabel ulang audit apa pun secara otomatis. Untuk audit draf, acuan baru muncul di pilihan acuan layar review (Pemdes memilih sendiri). Audit terbit tidak berubah.', 'Mencocokkan nama secara otomatis bisa salah. Pemilihan tetap di tangan manusia, sesuai D14 di PRD Pemdes.', 'Jawaban #5 (diperjelas)'],
  ['**DP9**', 'Median antar-desa hanya tampil jika ada ≥ 3 desa berbeda dengan satuan yang sama, hanya di layar Pemda, dengan label "Pembanding antar-desa, bukan standar resmi".', 'Mencegah pembanding dianggap acuan resmi dan mencegah satu desa menentukan angka.', 'Jawaban #7'],
  ['**DP10**', 'Pemda di demo hanya bisa membaca. Akun demo Pemda memakai `village_id` milik klon (supaya ikut terhapus) dan `regency` yang sama. Yang terlihat: desa contoh non-demo + desa demo milik sesi itu.', '`price_standards` tidak dibatasi per desa, jadi tulisan dari demo akan mengubah katalog semua desa asli.', 'Jawaban #6'],
  ['**DP11**', 'Bahasa netral: "prioritas pendampingan", urutan tanpa skor atau peringkat, tidak ada kata "korupsi" atau "mark-up".', 'Konsisten dengan aturan bahasa netral di `docs/audit_rab/m2-audit.md`.', 'Desain'],
  ['**DP12**', 'Pembagian kerja: Dev 2 mengerjakan backend. Dev 1 mengerjakan frontend, S2 (modul Audit M2 miliknya), dan QA. Target 12 Des.', 'Mengikuti kepemilikan modul yang berlaku sekarang.', 'Jawaban #8'],
  ['**DP13**', 'Kontrak dulu: di hari pertama tiap story, Dev 2 push route + controller yang mengirim props sesuai Bagian 6.6 (boleh data kosong).', 'Supaya Dev 1 bisa mulai halaman Vue di hari yang sama tanpa menunggu query selesai.', 'Desain'],
], [7, 38, 42, 13], { size: 15 }));

// ==========================================
// 5. Kondisi sekarang vs target
// ==========================================
add(H1('Kondisi sekarang vs target'));
add(Tbl(['Area', 'Kondisi sekarang', 'Titik kode', 'Target'], [
  ['Role', 'Hanya `desa`, `bpd`, `warga`', '`app/Modules/Core/Auth/Role.php:7`', '+ `pemda`, `belongsToVillage()` = false'],
  ['Akun', 'Desa/BPD terikat `village_id`. Tidak ada kolom kabupaten', '`database/migrations/0001_01_01_000000_create_users_table.php`', '+ `users.regency`'],
  ['Wilayah', '`villages.regency` (string). Tidak ada kecamatan. Seed hanya 1 desa', '`DatabaseSeeder.php:21`', '+ `villages.district`, 5 desa contoh'],
  ['Isolasi data', 'Global scope `village` di setiap model desa. Tanpa konteks, error', '`Core/Village/BelongsToVillage.php:23`, `CurrentVillage.php:37`', 'Dilepas hanya di `RegencyVillages`'],
  ['Katalog', 'Per `regency` + `year`, fallback ke tahun terakhir', '`Standard/Models/PriceStandard.php:45`', 'Dikelola Pemda, tanpa kolom baru'],
  ['Pelabelan', '`PriceLabeler` dipanggil di 4 tempat', '`ProcessRabDocumentJob.php:149`, `CreateAuditFromFixture.php:79`, `AuditItemController.php:217`, `ApplyProjectCco.php:97`', 'Semua menyimpan `standard_price_rp`'],
  ['Tampilan acuan', 'Harga acuan dibaca dari relasi `standard`', '`ProjectResource.php:147-171`, `AuditReviewController.php:116-121`, `DesaProjectManageController.php:71-76`', 'Membaca `standard_price_rp`'],
  ['Konfirmasi baris', '`confirm()` menghitung label sendiri dari `standard->price_rp`', '`AuditItemController.php:39-56`', 'Ikut menyimpan snapshot'],
  ['Login & demo', 'Redirect hanya untuk desa/bpd. Pintu demo: warga, desa, bpd', '`LoginController.php:70`, `DemoController.php:83`, `pages/demo/*.vue:13`', '+ cabang pemda'],
  ['Modul', 'Daftar modul ditulis manual', '`app/Providers/ModuleServiceProvider.php:17`', '+ `Pemda`'],
], [14, 30, 34, 22], { size: 15 }));

add(Callout('danger', 'Penting: hal yang diam-diam salah kalau dikerjakan dengan cara naif', [
  '- **Ubah harga = halaman terbit bergeser.** `ProjectResource` mengambil `standard->price_rp` saat halaman dirender, sedangkan `over_pct` dan `diff_rp` disimpan saat pelabelan. Tanpa snapshot (S2), Pemda yang mengedit harga langsung membuat angka di halaman warga saling bertentangan.',
  '- **Demo Pemda menulis ke katalog semua desa.** `price_standards` adalah tabel global. Pengunjung demo yang menekan "Simpan" akan mengubah acuan Desa Contoh dan desa asli. Maka tulisan dari akun demo harus ditolak di backend, bukan hanya disembunyikan di UI.',
  '- **Pemda tidak boleh lewat middleware `village`.** Untuk akun demo Pemda (`village_id` terisi), middleware itu diam-diam mengunci query ke satu desa. Untuk akun asli (`village_id` null), query langsung error.',
  '- **`users.regency` harus sama persis dengan `villages.regency`.** Kolomnya string bebas. Salah ketik ("gianyar", "Kab. Gianyar") membuat Pemda melihat 0 desa tanpa error apa pun. Pakai satu nilai dari seeder dan uji di S1.',
]));

// ==========================================
// 6. Desain solusi
// ==========================================
add(H1('Desain solusi'));
add(H2('Arsitektur'));
add(Figure('pm3-arsitektur.png', 'Gambar 3 - Komponen baru dan yang disentuh'));
add(Tbl(['Komponen', 'Lokasi', 'Story'], [
  ['`Role::Pemda`, `User::regency`', '`app/Modules/Core/Auth/`', 'S1'],
  ['Migration `users.regency`, `villages.district`', '`database/migrations/`', 'S1'],
  ['Modul `Pemda` (`routes.php`, `README.md`)', '`app/Modules/Pemda/`, daftar di `ModuleServiceProvider::MODULES`', 'S1'],
  ['`RegencyVillages` (Action)', '`app/Modules/Pemda/Actions/`', 'S1'],
  ['`BlockDemoPemdaWrites` (alias `pemda.write`)', '`app/Modules/Pemda/Http/Middleware/`', 'S1'],
  ['`PemdaSampleSeeder`', '`database/seeders/`', 'S1'],
  ['Migration + backfill `audit_items.standard_price_rp`', '`database/migrations/`', 'S2'],
  ['`PemdaStandardController`, `CopyCatalogToYear`, Form Request', '`app/Modules/Pemda/`', 'S3'],
  ['`PemdaQueueController`, `BuildNoReferenceQueue`', '`app/Modules/Pemda/`', 'S4'],
  ['`PemdaRecapController`, `BuildRegencyRecap`', '`app/Modules/Pemda/`', 'S5'],
  ['`PemdaLayout.vue`, `pages/pemda/{Recap,Standards,Queue}.vue`, `types/pemda.ts`', '`resources/js/`', 'S3–S5'],
], [45, 43, 12], { size: 16 }));
add(Callout('info', null, [
  'Nama layout: `PemdesLayout.vue` (sudah ada, untuk desa) berbeda dengan `PemdaLayout.vue` (baru, untuk kabupaten). Sebut "Pemdes" untuk desa dan "Pemda" untuk kabupaten secara konsisten di kode dan UI.',
]));

add(H2('Alur pengguna'));
add(Numbered([
  'Pemda login dan diarahkan ke `/pemda` (Rekap). Pemda memilih tahun anggaran (default: tahun berjalan, zona `sonar.display_timezone`).',
  'Dari menu, Pemda membuka **Tanpa Acuan**. Tab "Belum ada acuan" menampilkan grup item, jumlah desa, jumlah baris, dan median (jika ≥ 3 desa).',
  'Pemda menekan **Jadikan acuan**. Modal terisi nama dan satuan terbanyak dari grup. Pemda mengisi kode, harga, dan sumber, lalu menyimpan ke katalog tahun yang dipilih.',
  'Grup itu pindah ke tab "Sudah ada acuan". Audit RAB berikutnya yang memuat item serupa akan dicocokkan AI ke acuan baru dan dilabel hijau atau merah.',
  'Di **Acuan**, Pemda bisa mengubah harga, menghapus acuan yang belum dipakai, atau menyalin seluruh katalog ke tahun berikutnya.',
]));

add(H2('Wireframe & aturan UI'));
add(Figure('pm4-wireframe.png', 'Gambar 4 - Wireframe Rekap kabupaten dan Antrean "Tanpa Acuan"'));
add(Bullets([
  'Ikuti `docs/DESIGN.md` (IDDS, mode terang). Pakai komponen `components/ui` yang sudah ada. Jangan pakai `rounded-xl`, bayangan di kartu, gradien, atau teks di bawah 12 px (lihat Perbaikan-UI-Pemdes).',
  'Teks untuk pegawai kabupaten, bukan untuk pembaca PRD. Jangan tampilkan kode keputusan (DP1, D14) atau istilah teknis ("snapshot", "regency").',
  'Rupiah memakai pemformat yang sama dengan halaman Pemdes. Kolom angka rata kanan.',
  'Empty state wajib ada: "Belum ada desa yang mengunggah RAB tahun ini", "Semua item sudah punya acuan", "Katalog 2027 belum ada. Audit memakai katalog 2026."',
  'Mode demo: tombol tulis nonaktif dengan keterangan "Mode demo: katalog hanya bisa dilihat". Prop `can_write` dari server menentukannya.',
]));

add(H2('Model data'));
add(Figure('pm5-erd.png', 'Gambar 5 - Perubahan skema: tiga kolom baru, tanpa tabel baru'));
add(Code([
  "// S1: add_pemda_columns",
  "Schema::table('users', fn (Blueprint $t) => $t->string('regency')->nullable()->index());",
  "Schema::table('villages', fn (Blueprint $t) => $t->string('district')->nullable()->index());",
  '',
  "// S2: add_standard_price_snapshot_to_audit_items",
  "Schema::table('audit_items', fn (Blueprint $t) => $t->bigInteger('standard_price_rp')->nullable());",
  "// backfill di up(): salin price_standards.price_rp untuk baris yang standard_id-nya terisi",
  "DB::statement('UPDATE audit_items ai SET standard_price_rp = ps.price_rp",
  "  FROM price_standards ps WHERE ai.standard_id = ps.id');",
]));

add(pageBreak());
add(H2('Logika inti'));
add(H3('RegencyVillages'));
add(Code([
  'final class RegencyVillages',
  '{',
  '    /** @return Builder<Village> */',
  '    public function for(User $pemda): Builder',
  '    {',
  '        return Village::query()',
  "            ->where('regency', $pemda->regency)",
  '            ->where(fn ($q) => $q',
  "                ->where('is_demo', false)",
  "                ->when($pemda->village_id, fn ($q, $id) => $q->orWhere('id', $id)));",
  '    }',
  '}',
  '// Query lintas desa: Audit/Project::withoutGlobalScope(\'village\')',
  '//   ->whereIn(\'village_id\', $regencyVillages->for($user)->select(\'id\'))',
]));
add(H3('Antrean "Tanpa Acuan"'));
add(Code([
  "items = audit_items JOIN audits WHERE audits.village_id IN RegencyVillages",
  "  AND audits.status IN (needs_review, ready, published)",
  "  AND label = 'no_reference' AND standard_id IS NULL",
  'key(item) = lower(trim(collapse_spaces(raw_name)))',
  'groups = items.groupBy(key) -> {',
  '  sample_name: raw_name terbanyak, units: daftar satuan,',
  '  village_count: count(distinct village_id), item_count,',
  '  median_unit_price_rp: median(unit_price_rp) untuk satuan terbanyak',
  '                        HANYA jika >= 3 desa dengan satuan itu, selain itu null,',
  '  has_standard: ada price_standards(regency, year) dengan key(name) == key',
  '}',
  'urut: village_count desc, item_count desc',
]));
add(P('Pengelompokan dilakukan di PHP (Collection). Volume satu kabupaten kecil (puluhan desa, ratusan baris). Jika suatu saat besar, pindahkan normalisasi ke SQL. Ini dicatat sebagai risiko, bukan dikerjakan sekarang.'));
add(H3('Rekap kabupaten'));
add(Bullets([
  'Hanya proyek **terbit** dengan `projects.year` = tahun terpilih.',
  'Per desa: `projects_published`, `items_above_standard`, `items_no_reference`, `valid_mismatch_reporters` (distinct `user_id`, `kind = mismatch`, `invalid_reason` null, `superseded_by` null), `projects_in_review`, `has_any_audit`.',
  'Per kecamatan: penjumlahan baris desa, ditambah `villages_total` dan `villages_with_published`.',
  'Urutan desa (prioritas pendampingan): `projects_in_review` desc → `items_above_standard` desc → belum pernah unggah (`has_any_audit` false) → nama.',
  'Hindari N+1: ambil semua angka dengan `withCount` / subquery per desa dalam satu query, bukan loop per proyek.',
]));

add(pageBreak());
add(H2('Kontrak halaman (Inertia props)'));
add(Tbl(['Method', 'Path', 'Nama route', 'Middleware'], [
  ['GET', '`/pemda`', '`pemda.recap`', '`auth`, `role:pemda`'],
  ['GET', '`/pemda/acuan?year=`', '`pemda.standards.index`', '`auth`, `role:pemda`'],
  ['POST', '`/pemda/acuan`', '`pemda.standards.store`', '+ `pemda.write`'],
  ['PATCH', '`/pemda/acuan/{standard}`', '`pemda.standards.update`', '+ `pemda.write`'],
  ['DELETE', '`/pemda/acuan/{standard}`', '`pemda.standards.destroy`', '+ `pemda.write`'],
  ['POST', '`/pemda/acuan/salin-tahun`', '`pemda.standards.copy_year`', '+ `pemda.write`'],
  ['GET', '`/pemda/tanpa-acuan?year=&tab=`', '`pemda.queue`', '`auth`, `role:pemda`'],
], [10, 34, 30, 26], { size: 16 }));
add(Code([
  '// resources/js/types/pemda.ts',
  "type PemdaBase = { regency: string; year: number; years: number[]; can_write: boolean };",
  '',
  'type RecapProps = PemdaBase & {',
  '  summary: { villages_total: number; villages_with_published: number;',
  '    items_above_standard: number; items_no_reference: number; projects_in_review: number };',
  '  districts: { name: string; villages_total: number; villages_with_published: number;',
  '    items_above_standard: number; projects_in_review: number }[];',
  '  villages: { id: number; name: string; slug: string; district: string | null;',
  '    projects_published: number; items_above_standard: number; items_no_reference: number;',
  '    valid_mismatch_reporters: number; projects_in_review: number; has_any_audit: boolean }[];',
  '};',
  '',
  'type StandardsProps = PemdaBase & {',
  '  fallback_year: number | null; // terisi jika katalog tahun ini kosong',
  '  standards: { id: number; code: string; name: string; unit: string; price_rp: number;',
  '    source_ref: string; used_count: number }[];',
  '};',
  '',
  'type QueueProps = PemdaBase & {',
  "  tab: 'open' | 'covered'; counts: { open: number; covered: number };",
  '  groups: { key: string; sample_name: string; units: string[]; village_count: number;',
  '    item_count: number; median_unit_price_rp: number | null; median_unit: string | null;',
  '    village_names: string[] }[];',
  '};',
]));
add(Tbl(['Field form', 'Aturan validasi'], [
  ['`code`', 'wajib, string ≤ 32, unik per (`regency`, `code`, `year`)'],
  ['`name`', 'wajib, string ≤ 255'],
  ['`unit`', 'wajib, string ≤ 32'],
  ['`price_rp`', 'wajib, integer, 1 s.d. 10.000.000.000'],
  ['`year`', 'wajib, integer, 2020 s.d. tahun berjalan + 1'],
  ['`source_ref`', 'wajib, string ≤ 255 (mis. nomor Perbup / SSH)'],
  ['`regency`', '**tidak diterima dari form**. Selalu diambil dari `user->regency`'],
  ['salin tahun: `from_year`, `to_year`', '`to_year` = `from_year` + 1, katalog `to_year` harus kosong'],
], [30, 70], { size: 16 }));

add(H2('Alur runtime: dari item tanpa acuan sampai audit berikutnya'));
add(Figure('pm6-seq-antrean.png', 'Gambar 6 - Item tanpa acuan, dijadikan acuan, lalu dipakai audit berikutnya'));

add(H2('Aturan keamanan'));
add(Bullets([
  '`regency` milik Pemda hanya berasal dari `user->regency`, tidak pernah dari request.',
  '`update` / `destroy` acuan: tolak 404 jika `standard->regency !== user->regency` (pola sama dengan `VillageOwnership`: tidak membocorkan keberadaan data).',
  '`pemda.write` menolak semua akun Pemda yang `village->is_demo === true` (redirect back + flash). Penjaganya di backend, bukan hanya `can_write` di UI.',
  'Pemda tidak boleh membuka route `desa`, `bpd`, atau `audits` (sudah dijaga `role:` yang ada). Desa dan BPD tidak boleh membuka `/pemda`.',
  'Rekap dan antrean tidak menampilkan identitas warga pelapor, hanya jumlah.',
]));

// ==========================================
// 7. Edge cases & aturan
// ==========================================
add(H1('Edge cases & aturan'));
add(Tbl(['Kondisi', 'Perilaku sistem'], [
  ['Pemda mengubah harga acuan yang sudah dipakai proyek terbit', 'Halaman terbit tidak berubah (snapshot). Audit baru dan koreksi baris di audit draf memakai harga baru.'],
  ['Desa melakukan CCO pada item proyek terbit setelah harga acuan berubah', '`ApplyProjectCco` melabel ulang dengan harga acuan **terbaru** dan memperbarui snapshot. Ini perilaku benar: CCO adalah pelabelan baru dan tercatat di `project_changes`.'],
  ['Pemda menghapus acuan yang dipakai ≥ 1 baris RAB', 'Ditolak dengan pesan "Acuan ini dipakai N baris RAB, tidak bisa dihapus". Tombol Hapus nonaktif jika `used_count > 0`.'],
  ['Kode acuan sudah ada di tahun yang sama', 'Validasi gagal: "Kode sudah dipakai di katalog {tahun}".'],
  ['Katalog tahun proyek belum ada', 'Audit memakai katalog tahun terakhir (perilaku `catalogForRegencyAndYear` yang sudah ada). Layar Acuan menampilkan banner `fallback_year`.'],
  ['Salin ke tahun yang sudah berisi katalog', 'Ditolak. Pemda harus mengubah acuan satu per satu di tahun itu.'],
  ['Satu nama item, satuan berbeda antar desa (sak vs kg)', 'Tetap satu grup, kolom satuan menampilkan semuanya. Median hanya untuk satuan terbanyak dan hanya jika ≥ 3 desa.'],
  ['Item tanpa harga satuan (`unit_price_rp` null)', 'Tetap dihitung di jumlah baris, tidak ikut median.'],
  ['Pemda memberi nama acuan berbeda dari nama grup', 'Grup tetap di tab "Belum ada acuan" karena kunci namanya tidak cocok. Ini perilaku benar (DP7). Pemda bisa memakai nama dari grup.'],
  ['Audit masih `reading` atau `read_failed`', 'Tidak masuk antrean (belum ada baris yang bisa dipercaya).'],
  ['Akun Pemda dengan `regency` yang tidak cocok dengan desa mana pun', 'Rekap kosong dengan empty state, tanpa error. Dicegah lewat seeder dan test S1.'],
  ['Pengunjung demo A (Pemda) dan B (Desa) bersamaan', 'Pemda A melihat desa contoh + klon A saja, tidak pernah klon B.'],
  ['Akun demo Pemda POST ke `/pemda/acuan` langsung (tanpa UI)', 'Ditolak oleh `pemda.write`, katalog tidak berubah.'],
  ['Desa contoh tampil di halaman publik `/d/{slug}`', 'Diterima: namanya jelas "Desa Contoh ...", sama seperti Desa Contoh yang sudah ada.'],
], [40, 60], { size: 16 }));

// ==========================================
// 8. Instruksi untuk Developer
// ==========================================
add(H1('Instruksi untuk Developer'));
add(Callout('note', null, [
  'Baca Bagian 4 dan callout **Penting** di Bagian 5 sebelum menulis kode. Hampir semua bug yang mungkin muncul ada di sana: snapshot, demo, dan scope `village`.',
]));
add(H2('Definition of Ready'));
add(Checks([
  'Branch per story dari `main`: `feat/pemda-s1-fondasi`, `feat/pemda-s2-snapshot`, dst.',
  'Baca `.ai/rules` (jika ada) dan `docs/DESIGN.md` untuk story yang menyentuh UI.',
  '`make fresh` berjalan bersih dan seeder menghasilkan akun `pemda@gianyar.test` (password hanya di lokal).',
  'Kontrak props di Bagian 6.6 sudah dipush Dev 2 sebelum Dev 1 mulai halaman Vue (DP13).',
  'Kalau ada keputusan yang belum jelas, tanya di grup tim. Jangan menebak lalu menulis keputusan baru di kode.',
]));
add(H2('Lakukan / Hindari'));
add(Tbl(['Lakukan', 'Hindari'], [
  ['Ambil daftar desa lintas wilayah hanya lewat `RegencyVillages`', 'Memanggil `withoutGlobalScope(\'village\')` tanpa filter `RegencyVillages`'],
  ['Ambil `regency` dari `$request->user()->regency`', 'Menerima `regency` dari input form atau query string'],
  ['Menolak tulisan demo di middleware `pemda.write`', 'Hanya menyembunyikan tombol di Vue'],
  ['Mengisi `standard_price_rp` di setiap pemanggilan `PriceLabeler` (4 tempat + `confirm()`)', 'Membaca `$item->standard->price_rp` untuk tampilan'],
  ['Menghitung rekap dengan `withCount` / subquery', 'Loop per desa lalu per proyek (N+1)'],
  ['Teks netral: "Di atas acuan", "Prioritas pendampingan"', 'Skor, peringkat, "desa terburuk", "korupsi"'],
  ['Satu test per aturan di Bagian 7 yang berisiko (snapshot, isolasi, demo)', 'Test untuk hal yang tidak berubah'],
], [50, 50], { size: 16 }));
add(H2('Definition of Done'));
add(Checks([
  'Semua AC story lulus, dan test case P1 di Bagian 10 lulus di lokal.',
  'Feature test baru lulus: `php artisan test --compact tests/Feature/Pemda` dan test yang disentuh.',
  'Migration punya `down()` yang benar.',
  '`vendor/bin/pint --dirty --format agent` dan `npm run build` bersih.',
  'Tampilan dicek di desktop 1280 px. Layar Pemda tidak wajib mobile, tapi tidak boleh rusak di 768 px.',
  'Tidak ada teks UI yang menyebut kode keputusan atau istilah teknis.',
]));

// ==========================================
// 9. Epic & Story breakdown
// ==========================================
add(H1('Epic & Story breakdown'));
add(Figure('pm7-epic-timeline.png', 'Gambar 7 - Epic, story, dan rencana harian per developer'));
add(Tbl(['Story', 'Pelaksana', 'Estimasi', 'Tanggal', 'Butuh'], [
  ['**S1** Fondasi role Pemda & wilayah', 'Dev 2', '2 hari', '27–28 Nov', '-'],
  ['**S2** Snapshot harga acuan di audit', 'Dev 1', '2 hari', '27–28 Nov', '-'],
  ['**S3** Katalog acuan', 'Dev 2 (BE), Dev 1 (FE)', '2 + 2,5 hari', '29 Nov–1 Des', 'S1, S2'],
  ['**S4** Antrean "Tanpa Acuan"', 'Dev 2 (BE), Dev 1 (FE)', '2 + 2 hari', '1–3 Des', 'S1, S3'],
  ['**S5** Rekap kabupaten', 'Dev 2 (BE), Dev 1 (FE)', '2 + 3 hari', '3–6 Des', 'S1'],
  ['**S6** Demo Pemda hanya baca', 'Dev 2 (BE), Dev 1 (FE)', '1 + 1 hari', '5–7 Des', 'S1, S3'],
  ['QA end-to-end + perbaikan', 'Dev 1 (QA), Dev 2 (fix)', '3 hari', '7–10 Des', 'S1–S6'],
  ['Deploy produksi', 'Dev 2', '2 hari', '11–12 Des', 'QA'],
], [32, 24, 14, 16, 14], { size: 16 }));

// S1
add(pageBreak());
add(storyHeader('S1', 'Fondasi role Pemda & wilayah (+ fondasi)', 'info', '2 hari (Dev 2)', '-'));
add(P('**Sebagai** pegawai kabupaten, **saya ingin** masuk dengan akun Pemda dan hanya melihat desa di kabupaten saya, **agar** saya bisa mengawasi tanpa melihat data kabupaten lain.'));
add(Tbl(['Area', 'Task', 'File'], [
  ['BE - Fondasi', '`Role::Pemda`, `belongsToVillage()` false. `User`: fillable + `@property regency`', '`Core/Auth/Role.php`, `User.php`'],
  ['BE - Fondasi', 'Migration `users.regency`, `villages.district` (nullable, index)', '`database/migrations/`'],
  ['BE - Fondasi', 'Modul `Pemda`: `routes.php` (grup `auth` + `role:pemda`, prefix `pemda`), `README.md` (Owner: Dev 2), daftarkan di `MODULES`', '`app/Modules/Pemda/`, `ModuleServiceProvider.php:17`'],
  ['BE - Fondasi', 'Action `RegencyVillages` (Bagian 6.5) + middleware `BlockDemoPemdaWrites` (alias `pemda.write`)', '`Pemda/Actions/`, `Pemda/Http/Middleware/`, `bootstrap/app.php`'],
  ['BE - Auth', 'Redirect login dan landing demo untuk Pemda ke `pemda.recap`', '`LoginController.php:70`, `DemoController.php:83`'],
  ['BE - Data', '`PemdaSampleSeeder`: `district` untuk Desa Contoh, 5 desa "Desa Contoh <Kecamatan>" dengan proyek terbit, item hijau/merah/tanpa acuan (≥ 2 nama item muncul di ≥ 3 desa), 1 proyek `in_review`, 1 desa tanpa audit. Akun `pemda@gianyar.test`', '`database/seeders/`'],
  ['FE', 'Tambah `pemda` ke tipe `Role` di frontend, buat `PemdaLayout.vue` (menu Rekap, Acuan, Tanpa Acuan)', '`resources/js/types/`, `resources/js/layouts/`'],
  ['Test', 'Akses role, isolasi kabupaten, desa demo milik sesi lain tidak terlihat', '`tests/Feature/Pemda/PemdaAccessTest.php`'],
], [16, 56, 28], { size: 15 }));
add(H3('Acceptance Criteria'));
add(AC([
  ['1', 'Akun `pemda@gianyar.test`', 'Login', 'Diarahkan ke `/pemda` (200)'],
  ['2', 'Akun desa atau BPD', 'Membuka `/pemda`', '403 "Akses ditolak."'],
  ['3', 'Akun Pemda', 'Membuka `/desa`, `/bpd`, `/audits/create`', '403'],
  ['4', 'Pemda Gianyar dan desa di kabupaten "Bangli"', '`RegencyVillages` dipanggil', 'Hanya desa Gianyar non-demo yang kembali'],
  ['5', 'Dua klon demo di Gianyar, akun demo Pemda milik klon A', '`RegencyVillages` dipanggil', 'Desa contoh + klon A, bukan klon B'],
  ['6', '`make fresh`', 'Seeder dijalankan dua kali', 'Jumlah desa, akun, dan proyek sama (idempoten)'],
  ['7', 'Alur desa dan BPD yang sudah ada', 'Test suite lama dijalankan', 'Semua tetap lulus'],
]));

// S2
add(pageBreak());
add(storyHeader('S2', 'Snapshot harga acuan di audit RAB', 'ok', '2 hari (Dev 1)', '-'));
add(P('**Sebagai** warga, **saya ingin** angka di halaman proyek tidak berubah diam-diam, **agar** perbandingan harga yang saya baca hari ini sama dengan yang saya laporkan besok.'));
add(Tbl(['Area', 'Task', 'File'], [
  ['BE - Data', 'Migration `audit_items.standard_price_rp` + backfill (Bagian 6.4). Tambah ke `#[Fillable]` dan casts', '`database/migrations/`, `Audit/Models/AuditItem.php`'],
  ['BE - Tulis', 'Isi `standard_price_rp` di setiap pelabelan', '`ProcessRabDocumentJob.php:149`, `CreateAuditFromFixture.php:79`, `AuditItemController.php` (`confirm`, `store`, `update`), `ApplyProjectCco.php:97`'],
  ['BE - Baca', 'Ganti `standard->price_rp` menjadi `standard_price_rp` untuk tampilan. `ProjectResource` tidak lagi menghitung label dari harga terbaru', '`ProjectResource.php:147-171`, `AuditReviewController.php:116-121`, `DesaProjectManageController.php:71-76`'],
  ['BE - Demo', 'Pastikan klon demo ikut menyalin kolom baru (cloner menyalin semua kolom, cek test F6-2)', '`Demo/Actions/VillageCloner.php`'],
  ['Test', 'Ubah `price_rp` setelah terbit, respons `/p/{slug}` tetap sama. CCO memakai harga baru', '`tests/Feature/Project/`, `tests/Feature/Audit/`'],
], [16, 50, 34], { size: 15 }));
add(H3('Acceptance Criteria'));
add(AC([
  ['1', 'Database lama dengan item yang punya `standard_id`', '`php artisan migrate`', 'Semua item tersebut punya `standard_price_rp` = `price_rp` acuannya'],
  ['2', 'Proyek terbit dengan item di atas acuan', '`price_rp` acuannya diubah', '`/p/{slug}` menampilkan harga acuan, selisih, dan persen yang sama seperti sebelumnya'],
  ['3', 'Audit baru diproses setelah harga diubah', 'Job selesai', 'Item memakai harga baru, `standard_price_rp` = harga baru'],
  ['4', 'Desa melakukan CCO harga satu item terbit', 'CCO disimpan', 'Label dihitung ulang dengan harga acuan terbaru, snapshot diperbarui, `project_changes` tercatat'],
  ['5', 'Baris `NeedsCheck` dikonfirmasi "Sudah Benar"', '`confirm()`', 'Snapshot terisi sesuai acuan saat itu'],
  ['6', 'Sesi demo baru', 'Klon dibuat', 'Item klon membawa `standard_price_rp` yang sama dengan template'],
]));

// S3
add(pageBreak());
add(storyHeader('S3', 'Katalog acuan harga', 'warn', 'BE 2 hari (Dev 2) + FE 2,5 hari (Dev 1)', 'S1, S2'));
add(P('**Sebagai** Pemda, **saya ingin** mengelola daftar harga acuan per tahun anggaran beserta sumbernya, **agar** audit RAB semua desa memakai acuan resmi yang terbaru.'));
add(Tbl(['Area', 'Task', 'File'], [
  ['BE - API', 'Hari 1: route + `PemdaStandardController@index` dengan props lengkap (DP13)', '`Pemda/routes.php`, `Pemda/Http/Controllers/`'],
  ['BE - API', '`store`, `update`, `destroy` (tolak jika dipakai), `copyYear`. Form Request sesuai tabel validasi 6.6', '`Pemda/Http/Requests/`, `Pemda/Actions/CopyCatalogToYear.php`'],
  ['BE - API', '`used_count` via `withCount` relasi item. `fallback_year` dari logika `catalogForRegencyAndYear`', '`Standard/Models/PriceStandard.php` (tambah relasi `items()`)'],
  ['FE', 'Halaman `pemda/Standards.vue`: pilih tahun, tabel, modal tambah/ubah, konfirmasi hapus, tombol "Salin ke {tahun+1}"', '`resources/js/pages/pemda/`'],
  ['FE', 'Banner `fallback_year`, keadaan `can_write = false`', 'idem'],
  ['Test', 'CRUD, unik per tahun, tolak hapus jika dipakai, isolasi kabupaten pada update/destroy', '`tests/Feature/Pemda/PemdaStandardTest.php`'],
], [14, 56, 30], { size: 15 }));
add(H3('Acceptance Criteria'));
add(AC([
  ['1', 'Pemda di `/pemda/acuan?year=2026`', 'Halaman dibuka', 'Hanya acuan Gianyar 2026 tampil, dengan `used_count`'],
  ['2', 'Form tambah lengkap', 'Simpan', 'Baris baru dengan `regency` = Gianyar, apa pun yang dikirim di request'],
  ['3', 'Kode `MAT-SMN-01` sudah ada di 2026', 'Tambah kode yang sama di 2026', 'Validasi gagal dengan pesan kode sudah dipakai'],
  ['4', 'Acuan dengan `used_count` 3', 'DELETE', 'Ditolak, baris tetap ada'],
  ['5', 'Acuan milik kabupaten lain', 'PATCH / DELETE dengan ID-nya', '404'],
  ['6', 'Katalog 2027 kosong', 'Salin 2026 ke 2027', 'Semua baris tersalin dengan `year` = 2027. Salin ulang ditolak'],
  ['7', 'Katalog 2027 kosong', 'Buka `?year=2027`', 'Banner menyebut audit memakai katalog 2026'],
]));

// S4
add(pageBreak());
add(storyHeader('S4', 'Antrean "Tanpa Acuan"', 'note', 'BE 2 hari (Dev 2) + FE 2 hari (Dev 1)', 'S1, S3'));
add(P('**Sebagai** Pemda, **saya ingin** melihat item RAB yang belum punya acuan dari semua desa dan menjadikannya acuan, **agar** katalog makin lengkap mengikuti kebutuhan nyata desa.'));
add(Tbl(['Prio', 'Area', 'Task', 'File'], [
  ['P1', 'BE', 'Hari 1: route + `PemdaQueueController` dengan props lengkap (DP13)', '`Pemda/Http/Controllers/`'],
  ['P1', 'BE', '`BuildNoReferenceQueue`: filter, normalisasi kunci, grup, `has_standard`, tab, urutan (Bagian 6.5)', '`Pemda/Actions/`'],
  ['P2', 'BE', 'Median antar-desa sesuai DP9', 'idem'],
  ['P1', 'FE', 'Halaman `pemda/Queue.vue`: tab, tabel grup, daftar desa (tooltip/expand)', '`resources/js/pages/pemda/`'],
  ['P1', 'FE', 'Modal "Jadikan acuan" memakai form S3 (prefill nama + satuan), POST ke `pemda.standards.store`, lalu kembali ke antrean', 'pakai ulang komponen form dari S3'],
  ['P2', 'FE', 'Kolom median dengan label "Pembanding antar-desa, bukan standar resmi" dan petunjuk harga di modal', 'idem'],
  ['P1', 'Test', 'Grup + hitungan, isolasi kabupaten, audit `reading` tidak ikut, grup pindah tab setelah acuan dibuat, median null jika < 3 desa', '`tests/Feature/Pemda/PemdaQueueTest.php`'],
], [8, 10, 54, 28], { size: 15 }));
add(H3('Acceptance Criteria'));
add(AC([
  ['1', '"Paving Block 8 cm" (desa A) dan "paving  block 8 cm" (desa B, C)', 'Buka antrean', 'Satu grup, 3 desa'],
  ['2', 'Grup dengan 3 desa satuan m2', 'Buka antrean', 'Median tampil dengan label pembanding'],
  ['3', 'Grup dengan 2 desa', 'Buka antrean', 'Median "-" dengan keterangan "< 3 desa"'],
  ['4', 'Pemda menekan "Jadikan acuan" dan menyimpan dengan nama grup', 'Kembali ke antrean', 'Grup ada di tab "Sudah ada acuan", hitungan tab berubah'],
  ['5', 'Audit desa Bangli punya item tanpa acuan', 'Pemda Gianyar membuka antrean', 'Item itu tidak muncul'],
  ['6', 'Audit berstatus `reading`', 'Buka antrean', 'Itemnya tidak muncul'],
  ['7', 'Audit draf desa memuat item yang baru dijadikan acuan', 'Pemdes membuka layar review', 'Acuan baru ada di pilihan acuan. Label item belum berubah sampai Pemdes memilihnya (DP8)'],
]));

// S5
add(pageBreak());
add(storyHeader('S5', 'Rekap kabupaten', 'info', 'BE 2 hari (Dev 2) + FE 3 hari (Dev 1)', 'S1'));
add(P('**Sebagai** Pemda, **saya ingin** melihat ringkasan per kecamatan dan per desa, **agar** saya tahu desa mana yang perlu didampingi lebih dulu.'));
add(Tbl(['Area', 'Task', 'File'], [
  ['BE', 'Hari 1: route `pemda.recap` + `PemdaRecapController` dengan props lengkap (DP13)', '`Pemda/Http/Controllers/`'],
  ['BE', '`BuildRegencyRecap`: angka per desa dalam satu query (subquery/withCount), agregasi per kecamatan, urutan prioritas (Bagian 6.5)', '`Pemda/Actions/`'],
  ['FE', 'Halaman `pemda/Recap.vue`: pilih tahun, 4 kartu ringkasan, tabel kecamatan, tabel desa dengan badge status (Ditinjau, Terbit, Belum unggah), baris desa menaut ke `/d/{slug}`', '`resources/js/pages/pemda/`'],
  ['FE', 'Empty state dan teks netral sesuai DP11', 'idem'],
  ['Test', 'Hitungan per desa dan kecamatan, hanya proyek terbit di tahun terpilih, urutan, jumlah query tidak tumbuh per desa', '`tests/Feature/Pemda/PemdaRecapTest.php`'],
], [14, 58, 28], { size: 15 }));
add(H3('Acceptance Criteria'));
add(AC([
  ['1', 'Seed contoh', 'Buka `/pemda`', 'Kartu ringkasan sesuai data seed. Desa dengan proyek `in_review` ada di urutan teratas'],
  ['2', 'Desa tanpa audit', 'Buka `/pemda`', 'Badge "Belum unggah", tanpa angka nol yang menyesatkan ("-")'],
  ['3', 'Proyek terbit tahun 2025', 'Pilih tahun 2026', 'Proyek 2025 tidak dihitung'],
  ['4', 'Laporan warga yang ditandai tidak valid atau digantikan', 'Buka `/pemda`', 'Tidak dihitung di `valid_mismatch_reporters`'],
  ['5', '5 desa vs 10 desa', 'Hitung query', 'Jumlah query sama (tanpa N+1)'],
  ['6', 'Klik baris desa', '-', 'Membuka `/d/{slug}` desa itu'],
]));

// S6
add(pageBreak());
add(storyHeader('S6', 'Demo Pemda hanya baca', 'danger', 'BE 1 hari (Dev 2) + FE 1 hari (Dev 1)', 'S1, S3'));
add(P('**Sebagai** juri yang mencoba demo, **saya ingin** masuk sebagai Pemda dan melihat item RAB yang baru saya unggah sebagai Pemdes muncul di antrean, **agar** saya paham siklus lengkapnya tanpa merusak data orang lain.'));
add(Tbl(['Area', 'Task', 'File'], [
  ['BE', '`VillageCloner::createAccount` untuk Pemda: `village_id` = klon, `regency` = `village->regency`. Tambah ke `ACCOUNT_ROLES` atau buat saat dibutuhkan', '`Demo/Actions/VillageCloner.php`, `DemoSession.php:74`'],
  ['BE', '`pemda.write` aktif di semua route tulis. Flash "Mode demo: katalog hanya bisa dilihat"', '`Pemda/routes.php`'],
  ['BE', '`can_write` = `! user->village?->is_demo` di props semua halaman Pemda', 'controller Pemda'],
  ['FE', 'Pintu "Pemda" di `/demo` + teks deskripsi. "Ganti peran" di `DemoBanner` menyertakan Pemda', '`pages/demo/*.vue`, `layouts/DemoBanner.vue`'],
  ['FE', 'Tombol tulis nonaktif + keterangan saat `can_write` false', 'halaman Pemda'],
  ['Test', 'Demo Pemda tidak bisa tulis, melihat klon sendiri, klon ikut terhapus saat purge', '`tests/Feature/Demo/DemoPemdaTest.php`'],
], [10, 58, 32], { size: 15 }));
add(H3('Acceptance Criteria'));
add(AC([
  ['1', 'Pengunjung di `/demo`', 'Pilih "Pemda"', 'Masuk ke `/pemda` dengan banner demo'],
  ['2', 'Demo: unggah "Uji Contoh RAB" sebagai Desa, lalu ganti peran ke Pemda', 'Buka Tanpa Acuan', 'Item tanpa acuan dari klon sendiri ikut muncul'],
  ['3', 'Akun demo Pemda', 'POST/PATCH/DELETE ke `/pemda/acuan`', 'Ditolak, `price_standards` tidak berubah'],
  ['4', 'Klon kedaluwarsa', '`PurgeExpiredDemos` jalan', 'Akun demo Pemda ikut terhapus'],
  ['5', 'Tombol tulis', 'Dilihat di demo', 'Nonaktif dengan keterangan mode demo'],
]));

// ==========================================
// 10. QA test cases
// ==========================================
add(pageBreak());
add(H1('QA test cases per story'));
add(Callout('info', 'Persiapan QA (Dev 1)', [
  '- Lingkungan: lokal setelah `make fresh`, lalu staging/produksi setelah deploy 11–12 Des.',
  '- Akun: `pemda@gianyar.test`, `desa@desa-contoh.test`, `bpd@desa-contoh.test` (password lokal dari seeder), dan sesi `/demo`.',
  '- Perangkat: desktop 1280 px (wajib), tablet 768 px (tidak boleh rusak).',
  '- Prioritas: **P1** = harus lulus sebelum rekam video, **P2** = harus lulus sebelum 12 Des, **P3** = kalau sempat.',
]));
add(H3('S1 - Fondasi'));
add(TC([
  ['S1-01', 'Login Pemda', 'Login `pemda@gianyar.test`', 'Masuk ke Rekap `/pemda`', 'P1'],
  ['S1-02', 'Desa tidak bisa ke Pemda', 'Login desa, buka `/pemda`', 'Halaman 403', 'P1'],
  ['S1-03', 'Pemda tidak bisa ke halaman desa', 'Login Pemda, buka `/desa` dan `/bpd`', 'Halaman 403', 'P1'],
  ['S1-04', 'Regresi desa & BPD', 'Jalankan alur unggah → terbit → laporan warga → BPD', 'Tidak ada perubahan perilaku', 'P1'],
]));
add(H3('S2 - Snapshot'));
add(TC([
  ['S2-01', 'Halaman terbit stabil', '1. Catat harga acuan & selisih satu item di `/p/{slug}`\n2. Ubah harga acuan itu di katalog\n3. Muat ulang `/p/{slug}`', 'Angka sama persis dengan langkah 1', 'P1'],
  ['S2-02', 'Audit baru pakai harga baru', 'Setelah S2-01, unggah RAB dengan item yang sama', 'Item baru memakai harga baru', 'P1'],
  ['S2-03', 'CCO memakai harga terbaru', 'CCO harga item yang sama di proyek terbit', 'Label dihitung dengan harga terbaru, tercatat di Riwayat', 'P2'],
]));
add(H3('S3 - Katalog'));
add(TC([
  ['S3-01', 'Tambah acuan', 'Isi form lengkap, simpan', 'Muncul di tabel tahun yang dipilih', 'P1'],
  ['S3-02', 'Kode ganda', 'Tambah kode yang sudah ada di tahun sama', 'Pesan validasi, tidak tersimpan', 'P2'],
  ['S3-03', 'Hapus acuan terpakai', 'Tekan Hapus pada acuan dengan "dipakai > 0"', 'Tombol nonaktif / ditolak dengan pesan', 'P1'],
  ['S3-04', 'Salin ke tahun baru', 'Salin 2026 ke 2027, buka 2027', 'Semua acuan ada di 2027. Salin ulang ditolak', 'P2'],
  ['S3-05', 'Banner fallback', 'Buka tahun tanpa katalog', 'Banner menyebut tahun katalog yang dipakai', 'P3'],
]));
add(H3('S4 - Antrean'));
add(TC([
  ['S4-01', 'Grup lintas desa', 'Buka Tanpa Acuan pada data seed', 'Item dengan nama sama dari beberapa desa jadi satu grup', 'P1'],
  ['S4-02', 'Jadikan acuan', 'Tekan "Jadikan acuan", isi kode & harga, simpan', 'Grup pindah ke tab "Sudah ada acuan"', 'P1'],
  ['S4-03', 'Acuan dipakai audit berikutnya', 'Sebagai desa, unggah RAB dengan item itu', 'Item dilabel hijau/merah, bukan abu-abu', 'P1'],
  ['S4-04', 'Median', 'Lihat grup dengan ≥ 3 desa dan grup dengan 2 desa', 'Median tampil hanya di grup pertama, dengan label pembanding', 'P2'],
]));
add(H3('S5 - Rekap'));
add(TC([
  ['S5-01', 'Angka ringkasan', 'Buka `/pemda`, bandingkan dengan data seed', 'Angka cocok', 'P1'],
  ['S5-02', 'Urutan prioritas', 'Lihat tabel desa', 'Desa dengan proyek ditinjau BPD di atas, desa belum unggah ditandai', 'P1'],
  ['S5-03', 'Filter tahun', 'Ganti tahun', 'Angka berubah sesuai tahun proyek', 'P2'],
  ['S5-04', 'Bahasa netral', 'Baca semua teks layar Pemda', 'Tidak ada skor, peringkat, "korupsi", kode keputusan', 'P1'],
]));
add(H3('S6 - Demo'));
add(TC([
  ['S6-01', 'Alur demo juri', '1. `/demo` → Desa → Uji Contoh RAB\n2. Ganti peran → Pemda\n3. Buka Tanpa Acuan', 'Item dari langkah 1 terlihat dalam ≤ 3 klik', 'P1'],
  ['S6-02', 'Demo tidak bisa tulis', 'Di demo Pemda, coba tambah / ubah / hapus acuan', 'Tombol nonaktif. Request langsung ditolak', 'P1'],
  ['S6-03', 'Isolasi sesi', 'Dua browser demo berbeda, keduanya Pemda', 'Masing-masing hanya melihat klon sendiri + desa contoh', 'P2'],
]));

// ==========================================
// 11. Rollout
// ==========================================
add(H1('Rollout, rollback & monitoring'));
add(Numbered([
  'Merge story ke `main` berurutan: S1 → S2 → S3 → S4 → S5 → S6. Setiap merge menjalankan seluruh test suite.',
  'Deploy produksi (11–12 Des): `php artisan migrate --force` (backfill S2 berjalan di sini), lalu `php artisan db:seed --force` untuk desa contoh dan akun Pemda.',
  'Akun Pemda produksi tidak punya password dari seeder (pola sama dengan akun desa). Tim mengatur password lewat langkah yang biasa dipakai untuk akun desa.',
  'Cek manual di produksi: `/demo` → Pemda, `/pemda` angka masuk akal, satu halaman `/p/{slug}` angka tidak berubah dibanding sebelum deploy.',
]));
add(P('**Rollback:** setiap migration punya `down()`. Menghapus kolom `standard_price_rp` mengembalikan perilaku lama (tampilan membaca relasi). Jika S3–S6 bermasalah saat demo, hapus `Pemda` dari `ModuleServiceProvider::MODULES` untuk mematikan semua route `/pemda` tanpa menyentuh data.'));
add(P('**Monitoring:** log error Laravel untuk `VillageContextMissing` (tanda ada query Pemda yang lupa lewat `RegencyVillages`), dan jumlah baris `price_standards` sebelum/sesudah sesi demo (harus sama).'));

// ==========================================
// 12. Risiko & open questions
// ==========================================
add(H1('Risiko & open questions'));
add(Tbl(['Risiko', 'Dampak', 'Mitigasi'], [
  ['Waktu habis sebelum S5/S6 selesai', 'Tinggi', 'Urutan story sudah memprioritaskan nilai demo. Median (P2) dan banner fallback (P3) dilepas lebih dulu.'],
  ['Juri bertanya "apakah Pemda sungguhan memakai ini?"', 'Sedang', 'Jawab dengan dasar tugas kabupaten (evaluasi APBDes, pembinaan, Inspektorat). Cek pasal Permendagri 20/2018 sebelum disebut di video.'],
  ['Antrean lambat jika data besar', 'Rendah', 'Volume lomba kecil. Jalur pindah ke SQL sudah dicatat di Bagian 6.5.'],
  ['Konflik merge di modul Audit (S2 Dev 1 vs pekerjaan lain)', 'Sedang', 'S2 dikerjakan pertama dan di-merge sebelum S3 dimulai.'],
  ['Data contoh disangka data asli', 'Sedang', 'Nama "Desa Contoh <Kecamatan>" (DP5).'],
], [38, 12, 50], { size: 16 }));
add(H3('Open questions (tidak memblokir)'));
add(Tbl(['Pertanyaan', 'Owner', 'Batas'], [
  ['Nomor Perbup/SSH Gianyar untuk `source_ref` katalog produksi (lanjutan jadwal 20 Nov di Adendum)', 'Dev 2', '10 Des'],
  ['Apakah Pemda perlu dicantumkan di halaman publik ("Acuan ditetapkan oleh Pemkab Gianyar")?', 'Dev 1 & Dev 2', '5 Des'],
  ['Teks naskah video untuk bagian Pemda dan roadmap (peta, impor Perbup, eskalasi)', 'Dev 1 & Dev 2', '13 Des'],
], [60, 20, 20], { size: 16 }));

// ==========================================
// 13. Lampiran
// ==========================================
add(H1('Lampiran: checklist operasional'));
add(H3('Skenario video (±60 detik untuk bagian Pemda)'));
add(Checks([
  'Buka `/demo` → Desa → "Uji Contoh RAB": tunjukkan satu item abu-abu "Tanpa acuan".',
  'Ganti peran → Pemda: Rekap kabupaten, sebut "prioritas pendampingan".',
  'Tanpa Acuan: tunjukkan grup lintas desa + pembanding antar-desa.',
  'Akun asli (bukan demo): "Jadikan acuan" → simpan.',
  'Kembali ke Desa: unggah ulang, item kini berlabel hijau/merah.',
  'Tutup dengan siklus Pemda → Pemdes → Warga → BPD (Gambar 2) dan roadmap.',
]));
add(H3('Sebelum deploy 12 Des'));
add(Checks([
  'Semua test P1 lulus di lokal dan staging.',
  'Backfill `standard_price_rp` sudah dicek dengan query: 0 item dengan `standard_id` terisi tetapi snapshot kosong.',
  'Akun Pemda produksi sudah bisa login.',
  'Tidak ada teks "Kabupaten Gianyar" baru yang di-hardcode di kode Pemda (ambil dari `regency`).',
]));

async function main() {
  await k.save(OUT, { creator: 'Sonar Desa' });
  console.log('Docx created:', OUT);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
