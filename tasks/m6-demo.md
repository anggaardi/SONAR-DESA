# M6 Demo (Fitur 5: Mode demo untuk juri)

Owner: Dev 1 · Jadwal: M6a 25-26 Nov (pintu + clone), M6b 4-5 Des (data awal lengkap)
Spec: PRD Fitur Inti 7 (M1-M8); PRD Arsitektur 6.7, 6.8, M6 (F6-1..F6-4), A9

Cara kerja: setiap pengunjung yang menekan "Coba sebagai <peran>" mendapat salinan Desa Contoh sendiri
(satu baris `villages` dengan `is_demo = true`). Perubahan pengunjung hanya ada di salinannya, jadi juri
tidak saling mengganggu, dan kode fitur M1-M5 tidak pernah tahu sedang demo.

### M6-02 · VillageCloner
- Status: done · Ukuran: M · Owner: Dev 1 · Depends on: M0-06
- Spec: PRD Arsitektur 6.7, M6-02, F6-1, F6-2
- Files: `app/Modules/Demo/Actions/VillageCloner.php`, `tests/Feature/Demo/DemoSessionTest.php`
- Catatan:
  - Salin lewat query builder (tanpa global scope dan event model) dalam satu transaksi, dengan peta id
    lama -> baru. Urutan tabel ditulis eksplisit di `VillageCloner::TABLES`.
  - FK yang menunjuk ke depan atau ke tabel sendiri (`projects.current_cycle_id`, `reports.superseded_by`)
    diisi null dulu, lalu dipetakan setelah semua baris tersalin.
  - Slug proyek diberi akhiran `-d<village_id>` karena slug unik global.
  - File tidak disalin; baris kloning menunjuk file template.
  - Membuat akun demo desa + BPD tanpa password. Warga menjelajah tanpa login sampai M4.
  - Diukur di data Desa Contoh lokal: 42 ms (target F6-1: <= 1 dtk).
- Acceptance criteria
  - [x] F6-2: test membaca semua FK berantai ke `villages` dari information_schema; tabel yang tidak
        terdaftar di cloner membuat test gagal. Jumlah baris template = hasil clone per tabel.
  - [x] Semua FK hasil clone menunjuk ke baris clone, bukan template.

### M6-03 · StartDemoSession, SwitchDemoRole, ResetDemoSession + DemoBanner
- Status: done · Ukuran: M · Owner: Dev 1 · Depends on: M6-02, M0-07
- Spec: PRD Fitur Inti 7.2, 7.4 (M1, M2, M4, M5); PRD Arsitektur 6.7, 6.8
- Files: `app/Modules/Demo/Actions/DemoSession.php`, `Http/DemoController.php`, `routes.php`,
  `app/Modules/Core/Village/VillageVisibility.php`, `resources/js/layouts/{RootLayout,DemoBanner}.vue`,
  `resources/js/pages/core/Home.vue`
- Catatan:
  - `POST /demo/start/{role}` (10/mnt/IP), `/demo/switch/{role}`, `/demo/reset`. Satu village demo per sesi;
    "Mulai ulang" membuat clone baru lalu menghapus yang lama, peran tetap sama.
  - Banner tampil di semua halaman lewat `RootLayout` (layout default di `app.ts`), dibaca dari shared prop `demo`.
  - Cek "village demo hanya untuk sesi pemiliknya" dipindah dari 3 controller Project ke
    `Core\Village\VillageVisibility`, supaya modul fitur tidak menyebut demo (F6-4).
  - Semua peran mendarat di halaman desa demo. TODO(M2-06): desa -> `/audits`. TODO(M5-04): bpd -> `/bpd`.
  - Halaman depan: 3 pintu (Warga, Pemerintah Desa, BPD). Pintu ke-4 "Coba contoh RAB" datang dengan M2.
- Acceptance criteria
  - [x] M1: masuk dalam 1 klik tanpa daftar (browser: 226 ms sampai halaman desa).
  - [x] M2: banner + "Ganti peran" + "Mulai ulang demo" di semua layar demo.
  - [x] M4: pengunjung lain mendapat 404 untuk desa dan proyek demo milik sesi lain.
  - [x] M5: "Mulai ulang demo" mengembalikan data ke kondisi awal.
  - [ ] M3, M6, M8: menunggu M2 (contoh RAB, batas unggah) dan M4 (lokasi contoh).

### M6-04 · PurgeExpiredDemos
- Status: done · Ukuran: S · Owner: Dev 1
- Spec: PRD Arsitektur M6-04, F6-3
- Files: `app/Modules/Demo/Console/PurgeExpiredDemos.php`, `routes/console.php`
- Catatan: `demo:purge-expired` tiap jam (`withoutOverlapping`). Hapus village (cascade ke semua baris dan akun
  demo) + folder `storage/app/villages/<id>`. Kedaluwarsa = 24 jam sejak aktivitas demo terakhir.
  Command modul didaftarkan otomatis oleh `ModuleServiceProvider` dari `app/Modules/*/Console`.
- Acceptance criteria
  - [x] F6-3: village kedaluwarsa + foldernya terhapus; Desa Contoh dan village yang masih aktif utuh.

### M6-01 · DemoTemplateSeeder (data awal)
- Status: todo (M6b, 4-5 Des) · Depends on: M2-M5
- Saat ini Desa Contoh berisi 1 proyek dari `ProjectSeeder`. Data awal lengkap PRD Fitur 7.3 (4 proyek +
  Contoh RAB 1/2/3) menunggu M2-M5.

### M6-05 · Foto + lokasi contoh di Cek Spek; batas 3 unggah AI
- Status: todo · Depends on: M2 (unggah), M4 (Cek Spek)
