# M2 Audit (Fitur 1: Ingesti, Review RAB & Gatekeeper Integritas Anggaran)

Owner: Dev 2 (Lead Backend & Alur Pemdes) · Jadwal: 23 Nov - 1 Des
Spec: PRD Fitur Inti v1.1 Bagian 3 (A1-A15); PRD Arsitektur v1.2 M2 (6.4, 6.5, 6.6, 9.2, 9.3); PRD Fitur Pemdes v1.1 (PD-01..PD-18); docs/audit_rab/m2-audit.md

Cara kerja: Operator Pemdes mengunggah berkas PDF/foto RAB asli melalui portal desa (`/audits/create`). Sistem menjalankan pembacaan via background worker queue (`ProcessRabDocumentJob`), mencocokkan uraian dengan standar acuan harga (SSH Gianyar) secara deterministik menggunakan `PriceLabeler`, dan menguji keseimbangan total RAB via `TotalChecker`. Operator memverifikasi baris berlabel kuning (`NeedsCheck`) di `/audits/{id}` sebelum dapat membuka wizard penerbitan proyek publik (`/projects`).

## Invarian Wajib Modul
1. **Numbers by Code, Words by LLM (D-002, A11):** Seluruh kalkulasi selisih rupiah (`diff_rp`), persentase deviasi (`over_pct`), label harga (`PriceLabel`), dan validasi total dihitung kelas PHP murni di lapisan `Domain/`.
2. **Presisi Finansial (Zero Float):** Nilai rupiah integer (`bigint`), volume string presisi (`numeric(14,3)`), komputasi memakai `bcmath`.
3. **Yellow-Row Gatekeeper (D-003, PD-02):** Tombol terbitkan proyek terkunci mutlak (`disabled`) selama masih terdapat baris belanja berlabel kuning (`NeedsCheck`) yang belum diselesaikan atau dikonfirmasi.
4. **Kunci Mutlak Pasca-Terbit (D-004, D-012, D-013, PD-14):** Item audit pada proyek yang sudah berstatus `published` tidak dapat diubah atau dihapus secara langsung; perbaikan spesifikasi wajib melalui instrumen CCO (`project_changes`).
5. **Bahasa Netral di Kode & UI (D-009):** Tidak menggunakan istilah penuduh ("mark-up", "korupsi"). Gunakan padanan resmi: *Di atas standar*, *Perlu dicek*, *Tanpa acuan*, dan *Sesuai standar*.

---

## Task List

### M2-01 · PriceLabeler + TotalChecker (Pure PHP Domain)
- Status: done · Ukuran: M · Owner: Dev 2 · Depends on: M1-04
- Spec: PRD Fitur Inti 3.4, 9; PRD Arsitektur 6.5, 6.6, 9.2; docs/audit_rab/m2-audit.md Bagian 1, 3
- Tujuan: Kalkulasi deterministik zero-float (bcmath) untuk label harga, persentase deviasi, selisih rupiah, dan rekonsiliasi total RAB tanpa dependensi framework.
- Scope:
  - `PriceLabeler::label(...)`: Pelabelan `BelowOrEqual`, `AboveStandard` (toleransi $\le 5\%$, deviasi $> 5\%$), `NoReference`, dan `NeedsCheck` (keraguan OCR/satuan/harga).
  - `TotalChecker::check(...)`: Validasi jumlah baris belanja terhadap nilai total tertulis kertas fisik (`written_total_rp`).
  - Unit tests lengkap mencakup skenario deviasi harga, keraguan OCR, dan ketidakcocokan total.
- Files:
  - `app/Modules/Audit/Domain/PriceLabeler.php`
  - `app/Modules/Audit/Domain/TotalChecker.php`
  - `tests/Unit/Audit/PriceLabelerTest.php`
  - `tests/Unit/Audit/TotalCheckerTest.php`
- Verify:
  - `make test ARGS="--filter=PriceLabelerTest"`
  - `make test ARGS="--filter=TotalCheckerTest"`

### M2-02 · Audit Review Page & Yellow-Row Gatekeeper (`GET /audits/{id}`)
- Status: done · Ukuran: L · Owner: Dev 2 · Depends on: M2-01
- Spec: PRD Fitur Inti 3.3, 3.4; PRD Fitur Pemdes 4.2, Bagian 6 (PD-02, PD-03); docs/audit_rab/m2-audit.md Bagian 4
- Scope:
  - `AuditReviewController@show`: Memberikan data audit, daftar `AuditItem`, ringkasan selisih total, dan status kesiapan publikasi (`canPublish`).
  - Aksi `AuditItemController@confirm`: Konfirmasi baris kuning menjadi "Sudah Benar" (`corrected = true`).
  - Aksi `AuditItemController@update` & `destroy`: Koreksi inline volume, satuan, harga satuan, dan standar acuan dengan rekalkulasi instan.
  - Aksi `AuditReviewController@ackTotal`: Konfirmasi selisih total jika terdapat perbedaan antara penjumlahan rincian dan kertas fisik.
  - Vue view `resources/js/pages/audit/Review.vue`: Tabel rincian belanja, badge status, filter baris kuning, inline editor, dan drawer dokumen asli.
- Files:
  - `app/Modules/Audit/Http/Controllers/AuditReviewController.php`
  - `app/Modules/Audit/Http/Controllers/AuditItemController.php`
  - `resources/js/pages/audit/Review.vue`
  - `tests/Feature/Audit/AuditReviewTest.php`
- Verify:
  - `make test ARGS="tests/Feature/Audit/AuditReviewTest.php"`

### M2-03 · Ingesti Dokumen RAB & Background Processing Worker
- Status: done · Ukuran: L · Owner: Dev 2 · Depends on: M2-02
- Spec: PRD Fitur Inti 3.1, 3.2 (A1-A5); PRD Fitur Pemdes 4.1, 4.2 (PD-01, PD-11, PD-12)
- Scope:
  - `AuditUploadController@create` & `@store`: Form unggah file RAB (PDF, JPG, PNG) dengan penyimpanan storage aman dan isolasi multi-desa.
  - Dukungan tombol instan sampel berkas demo (`sample-rab-melati.pdf`) untuk onboarding dan testing cepat.
  - Queue Job `ProcessRabDocumentJob`: Mengelola transisi status lifecycle (`reading` -> `needs_review` / `ready` / `read_failed`), ekstraksi data teks/tabel, pelabelan harga awal melalui `PriceLabeler`, dan penanganan error/timeout.
  - Endpoint retry extraction `POST /audits/{audit}/retry`: Mengizinkan operator mencoba kembali ekstraksi berkas ketika terjadi kegagalan parser.
  - UI `resources/js/pages/audit/Create.vue`: Layar pengunggahan berkas terintegrasi progress bar, drag-and-drop, dan panduan format RAB.
- Files:
  - `app/Modules/Audit/Http/Controllers/AuditUploadController.php`
  - `app/Modules/Audit/Jobs/ProcessRabDocumentJob.php`
  - `resources/js/pages/audit/Create.vue`
  - `app/Modules/Audit/routes.php`
  - `tests/Feature/Audit/AuditUploadAndJobTest.php`
- Verify:
  - `make test ARGS="tests/Feature/Audit/AuditUploadAndJobTest.php"`

### M2-04 · Entri Tambah Baris Belanja Manual
- Status: done · Ukuran: M · Owner: Dev 2 · Depends on: M2-02
- Spec: PRD Fitur Pemdes Bagian 6, 8, AC PD-11, PD-12
- Scope:
  - Endpoint `POST /audits/{audit}/items` via `AuditItemController@store`: Memungkinkan operator menambahkan baris belanja baru secara manual langsung di layar audit review.
  - Validasi ketat nama item, volume numerik, satuan, harga satuan, dan standar acuan opsional.
  - Komputasi otomatis `diff_rp`, `over_pct`, dan `label` melalui `PriceLabeler`.
  - Auto-update status audit jika penambahan/penyesuaian menyelesaikan semua baris kuning (`updateAuditStatusIfResolved`).
  - Modal form terintegrasi di `resources/js/pages/audit/Review.vue` dengan kalkulasi live total rupiah.
- Files:
  - `app/Modules/Audit/Http/Controllers/AuditItemController.php`
  - `resources/js/pages/audit/Review.vue`
  - `app/Modules/Audit/routes.php`
  - `tests/Feature/Audit/AuditUploadAndJobTest.php`
- Verify:
  - `make test ARGS="tests/Feature/Audit/AuditUploadAndJobTest.php"`

### M2-05 · Diagnostik Blokir Publikasi & Gatekeeper Banner
- Status: done · Ukuran: S · Owner: Dev 2 · Depends on: M2-02, M2-04
- Spec: PRD Fitur Pemdes 4.2, Bagian 6, AC PD-02, PD-13
- Scope:
  - Identifikasi alasan pemblokiran penerbitan proyek (`publishBlockReason`) di `AuditReviewController@show`:
    - `already_published`: Proyek audit sudah terbit.
    - `reading`: Dokumen masih dalam proses antrean ekstraksi.
    - `read_failed`: Dokumen gagal diekstraksi.
    - `missing_file`: Audit tidak memiliki berkas dokumen fisik asli.
    - `no_items`: Tabel audit tidak memiliki baris rincian belanja.
    - `has_yellow_rows`: Masih terdapat baris belanja berstatus `NeedsCheck`.
    - `unacknowledged_diff`: Terdapat selisih total yang belum dikonfirmasi operator.
  - Komponen banner visual informatif di `Review.vue` dengan aksi koreksi langsung bagi pengguna.
- Files:
  - `app/Modules/Audit/Http/Controllers/AuditReviewController.php`
  - `resources/js/pages/audit/Review.vue`
  - `tests/Feature/Audit/AuditReviewTest.php`
- Verify:
  - `make test ARGS="tests/Feature/Audit/AuditReviewTest.php"`

### M2-06 · Monitoring Audit Berjalan di Ruang Kerja Pemdes (`GET /desa`)
- Status: done · Ukuran: M · Owner: Dev 2 · Depends on: M2-03
- Spec: PRD Fitur Pemdes 4.1 (Layar 1: Dashboard Utama Pemdes)
- Scope:
  - `DesaDashboardController@index`: Menghitung dan menyediakan koleksi `in_progress_audits` (audit non-published) beserta indikator baris kuning dan item count.
  - Kartu statistik audit: `audits_reading`, `audits_needs_review`, `audits_ready`, `audits_read_failed`.
  - Tampilan tabel & kartu audit berjalan di `resources/js/pages/desa/Dashboard.vue` dengan tautan langsung ke `/audits/{id}` atau `/audits/create`.
  - Penyesuaian rute `/audits`: Redirect menuju `/audits/create` untuk mempermudah operator memulai audit baru.
  - Penyelarasan navigasi sidebar di `resources/js/pages/desa/ProjectCreate.vue` dan `ProjectManage.vue`.
- Files:
  - `app/Modules/Desa/Http/Controllers/DesaDashboardController.php`
  - `resources/js/pages/desa/Dashboard.vue`
  - `app/Modules/Desa/routes.php`
  - `resources/js/pages/desa/ProjectCreate.vue`
  - `resources/js/pages/desa/ProjectManage.vue`
  - `tests/Feature/Desa/DesaDashboardTest.php`
- Verify:
  - `make test ARGS="tests/Feature/Desa/DesaDashboardTest.php"`

### M2-07 · Integritas Audit Pasca-Terbit & Perlindungan Data
- Status: done · Ukuran: M · Owner: Dev 2 · Depends on: M2-02
- Spec: PRD Fitur Pemdes Bagian 8, 9, AC PD-14, PD-15, PD-16; Invarian 4
- Scope:
  - Pencegahan modifikasi atau penghapusan item audit pasca-terbit (`ensureEditable`).
  - Pemblokiran aksi unpublish pada level rute dan controller.
  - Perlindungan IDOR dan isolasi data per desa melalui `CurrentVillage`.
  - Test suite komprehensif untuk pengujian edge-case integritas audit dan perbaikan review.
- Files:
  - `app/Modules/Audit/Http/Controllers/AuditItemController.php`
  - `app/Modules/Audit/Http/Controllers/AuditReviewController.php`
  - `tests/Feature/Audit/AuditIntegrityAndReviewFixesTest.php`
- Verify:
  - `make test ARGS="tests/Feature/Audit/AuditIntegrityAndReviewFixesTest.php"`
