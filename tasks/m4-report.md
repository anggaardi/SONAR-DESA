# M4 Report (Fitur 3: Cek Spek & laporan warga)

Owner: Dev 2 (mengambil alih dari Dev 1, lihat tasks/README.md) · Jadwal: 27 Nov - 3 Des (M4-03, M4-04, M4-05 boleh mulai lebih awal)
Spec: PRD Fitur Inti v1.1 Bagian 5 (C1-C12), 8.3 (privasi); PRD Arsitektur v1.2 M4 (6.4, 6.8, 6.9, 8, F4-1..F4-4), 14.3-14.5

Cara kerja: warga membuka Cek Spek dari halaman proyek, mengizinkan lokasi, masuk dengan Google, mengisi
checklist ukuran, memotret langsung dari kamera, lalu mengirim. Server menghitung ulang jarak dan menentukan
jenis laporan (Selisih / Sesuai / Tidak bisa diukur). Setelah tersimpan, M4 mengirim event `ReportSubmitted`;
listener di M5 menghitung ulang ambang. M4 tidak pernah memanggil M5.

## Sudah ada dari M3-01

- Tabel `reports` + `Report` model (`OwnedThroughParent`), `ReportFactory`, enum `ReportKind`.
- `config/sonar.php`: `report.radius_m` (300), `report.max_accuracy_m` (100), `review.threshold` (3).
- `users.google_id`, `Role::Warga`. Tombol Cek Spek di halaman proyek masih nonaktif (diaktifkan di M4-07).

## Keputusan yang menunggu rapat 22 Nov (PRD Arsitektur 14.3-14.5)

Semua ini mengubah tabel `reports`. Migration yang sudah di-merge tidak boleh diedit (ENGINEERING 3), jadi
M4-02 menunggu keputusan dan memuat semua kolom final dalam satu migration baru. Hasil rapat dicatat sebagai
entri D-xxx di `docs/DECISIONS.md`, lalu bagian ini dihapus.

| ID | Usulan | Dampak ke M4 |
| --- | --- | --- |
| F25 | Laporan di luar radius ditolak (422 `REPORT_OUT_OF_RADIUS`), tidak tersimpan | Kolom `in_radius` dihapus; C8 dan F4-2 berubah; `ReportClassifier` tidak lagi menandai "di luar radius" |
| F30 | Isian banjar di laporan | Kolom `reports.banjar` (varchar 60, nullable), tidak tampil publik |
| Perangkat | 1 perangkat 1 pelapor | Kolom `device_hash` (HMAC-SHA256 cookie perangkat acak); unik aktif per (project, cycle, device_hash) |
| Klaster | Tanda laporan berdekatan (<= 15 m, <= 10 menit) | `ReportClusterDetector` + `cluster_flag`; hanya penanda untuk BPD |
| PDP | Layar persetujuan sebelum lokasi dan kamera | `users.consent_at`, `consent_version`, `POST /consent` |
| Retensi | Koordinat di-null-kan 30 hari setelah siklus ditutup | Job `PurgeReportCoordinates` |

Sampai ada keputusan, kerjakan hanya task yang tidak menyentuh skema: M4-03, M4-04, M4-05.

## Task List

### M4-03 · DistanceCalculator + ReportClassifier
- Status: review · Ukuran: M · Owner: Dev 2 · Depends on: -
- Spec: PRD Fitur Inti 5.3, 9 (edge "280 m, akurasi 90 m"); PRD Arsitektur M4-03, F4-1; ENGINEERING 2, 6
- Tujuan: jarak dan jenis laporan dihitung kelas PHP murni, tanpa Eloquent dan tanpa Laravel di dalamnya.
- Scope:
  - `DistanceCalculator::meters(lat1, lng1, lat2, lng2): float` (haversine).
  - `ReportClassifier::kind(array $answers): ReportKind`: minimal satu "Tidak" = Mismatch; tanpa "Tidak" dan
    minimal satu "Sesuai" = Match; semua "Tidak bisa ukur" = Unmeasurable.
  - Aturan radius dan akurasi: jarak <= `report.radius_m` dan akurasi <= `report.max_accuracy_m` dihitung
    (batas inklusif). Nilai dari `config('sonar.report.*')`, dioper lewat konstruktor, bukan angka di kode.
  - Jawaban memakai enum (mis. `MeasurementAnswer::Match|Mismatch|Unmeasurable`), bukan string bebas.
  - Input/output berupa DTO readonly, bukan array asosiatif antar lapisan.
- Out of scope: validasi request, penyimpanan, ambang tinjauan (M5 `ReviewThreshold`).
- Files:
  - `app/Modules/Report/Domain/DistanceCalculator.php`
  - `app/Modules/Report/Domain/ReportClassifier.php`
  - `app/Modules/Report/Enums/MeasurementAnswer.php`
  - `tests/Unit/Report/DistanceCalculatorTest.php`, `tests/Unit/Report/ReportClassifierTest.php`,
    `tests/Unit/Report/ReportClassifierBindingTest.php`; binding di `AppServiceProvider` (limit dari config)
- Acceptance criteria
  - [x] F4-1: dataset per aturan; semua cabang tercakup.
  - [x] Batas di kedua sisi: 300 m lulus, 300,1 m tidak; akurasi 100 lulus, 100,1 tidak.
  - [x] Kasus wajib: jarak 280 m + akurasi 90 m dihitung (PRD Fitur 9).
  - [x] Satu "Tidak" di antara banyak "Sesuai" tetap Mismatch (C9).
  - [x] Jarak dua titik yang sama = 0; hasil dicek terhadap pasangan koordinat yang diketahui (toleransi 1 m).
- Catatan: `isCounted()` = jarak <= radius **dan** akurasi <= batas. Bila F25 disetujui (22 Nov), `isWithinRadius()` berubah
  menjadi dasar penolakan 422 di M4-06; `ReportClassifier` tidak perlu diubah.
- Verify: `make test ARGS="--filter=Report"`

### M4-04 · useCamera (kamera langsung, tanpa galeri)
- Status: review · Ukuran: M · Owner: Dev 2 · Depends on: -
- Spec: PRD Fitur Inti 5.1 langkah 6, C6; PRD Arsitektur A15, M4-04, F4-3
- Scope:
  - Composable `useCamera`: `getUserMedia` + `<canvas>` -> `Blob` JPEG. Tidak ada `<input type="file">`.
  - 1-3 foto; tombol Kirim tetap nonaktif sampai ada minimal 1 foto (C6).
  - Pengingat "jangan memotret wajah orang" tampil di layar kamera (PRD Fitur 8.3).
  - Izin kamera ditolak: pesan dan cara mengaktifkan, isian lain tidak hilang.
  - Matikan stream saat komponen dilepas.
- Out of scope: unggah ke server (M4-06); pemilih galeri (dilarang A15).
- Files: `resources/js/features/report/useCamera.ts`, `resources/js/features/report/CameraCapture.vue`,
  `resources/js/features/report/useCamera.test.ts` (Vitest, `getUserMedia` di-mock); teks di `lib/labels.ts` (`camera`)
- Acceptance criteria
  - [x] C6: hanya kamera langsung; min 1, maks 3 foto. `hasMinimum` dipakai M4-07 untuk menonaktifkan Kirim.
  - [x] Teks UI diambil dari `lib/labels.ts`.
  - [ ] Dicek manual di HP Android lewat HTTPS (F4-3).
- Catatan: kamera tidak dibuka otomatis; warga menekan "Buka kamera" setelah penjelasan lokasi/izin (C1). Frame di-encode ulang
  lewat canvas (JPEG, sisi terpanjang <= 1600 px), jadi EXIF/GPS bawaan tidak ikut. Server tetap memvalidasi ulang (M4-06).

### M4-05 · useGeolocation + layar jarak
- Status: doing · Ukuran: M · Owner: Dev 2 · Depends on: M4-03 (aturan jarak untuk warna)
- Spec: PRD Fitur Inti 5.1 langkah 1-3, C1, C2, C7; PRD Arsitektur M4-05
- Scope:
  - Layar penjelasan sebelum izin diminta: kenapa lokasi diminta, laporan masuk ke BPD, nama tidak tampil (C1).
  - `useGeolocation`: ambil posisi + akurasi, tombol coba lagi. Akurasi > 100 m: "Sinyal lokasi lemah,
    tunggu sebentar" + coba lagi (C7).
  - Izin ditolak: tidak bisa lanjut + cara mengaktifkan lokasi (C2, wajib).
  - Tampilkan jarak ke titik proyek (hijau <= 300 m, kuning bila lebih). Jarak di layar hanya petunjuk;
    angka resmi dihitung ulang server saat kirim (M4-06). Titik proyek datang dari server, bukan dihitung di Vue.
  - Lokasi diambil ulang saat kirim.
- Files: `resources/js/features/report/useGeolocation.ts`, `LocationGate.vue`, `useGeolocation.test.ts`; teks di `lib/labels.ts` (`location`)
- Acceptance criteria
  - [x] C2 (wajib), C7: `useGeolocation` + `LocationGate` (penjelasan C1 ada di `LocationGate`, dipasang di halaman oleh M4-07).
  - [ ] Layar 360 px tanpa scroll horizontal (cek manual saat dipasang di M4-07).
  - [ ] Jarak + status radius dari server (lihat catatan).
- Catatan:
  - `LocationGate` tidak menghitung angka. Jarak dan "dalam radius" masuk lewat props `distanceMeters` dan
    `withinRadius` (ENGINEERING 2: angka hanya dari Domain). Sumbernya belum diputuskan: titik proyek tidak ada
    di `ProjectResource` (tidak publik), dan langkah lokasi terjadi sebelum login (F11). Opsi: endpoint publik
    ber-throttle yang memakai `DistanceCalculator`, atau mengirim titik proyek ke Vue untuk hitungan tampilan saja.
    Putusan dicatat di DECISIONS.md.
  - Lokasi dipantau dengan `watchPosition` sampai akurasi <= batas lalu berhenti; `readFresh()` dipakai M4-07 untuk
    mengambil ulang lokasi saat kirim. Batas akurasi dioper dari server (`config('sonar.report.max_accuracy_m')`).

### M4-02 · Migration report_answers + report_photos (+ kolom hasil rapat 22 Nov)
- Status: todo (menunggu keputusan 22 Nov) · Ukuran: M · Owner: Dev 2 · Depends on: D-xxx hasil rapat
- Spec: PRD Arsitektur 6.4, M4-02, F4-4; ENGINEERING 3 (migration baru, bukan edit)
- Scope:
  - Migration baru (satu file): tabel `report_answers` (`report_id`, `measurement_id`, `answer`,
    `measured_value`, `measured_unit`) dan `report_photos` (`report_id`, `path`, urutan).
  - Foto di disk privat; EXIF dibuang (resize + WebP, seperti `StoreProgressPhoto`).
  - Index unik laporan aktif per (project, cycle, user) lewat partial unique index
    (`WHERE superseded_by IS NULL`).
  - Kolom hasil keputusan F25/F30/device_hash/consent bila disetujui.
  - Model `ReportAnswer`, `ReportPhoto` (`OwnedThroughParent` lewat `Report`), factory, dan tambahkan
    ke `VillageCloner::TABLES` (test F6-2 akan gagal kalau terlewat).
- Files: `database/migrations/*`, `app/Modules/Report/Models/ReportAnswer.php`, `ReportPhoto.php`,
  `database/factories/*`, `app/Modules/Demo/Actions/VillageCloner.php`
- Acceptance criteria
  - [ ] Unique aktif per (project, cycle, user) ditegakkan di database, bukan hanya di kode.
  - [ ] Test F6-2 (clone) tetap hijau.

### M4-01 · Socialite Google, kembali ke checklist yang sama
- Status: todo · Ukuran: M · Owner: Dev 2 · Depends on: persetujuan dependency baru, Google OAuth client (26 Nov)
- Spec: PRD Fitur Inti 5.1 langkah 4, F11, C3; PRD Arsitektur 6.8, A4, M4-01
- Scope:
  - Tambah `laravel/socialite` (butuh persetujuan; alasan di pesan commit: login Google warga, A4).
  - `GET /auth/google` dan `/auth/google/callback`; buat/cari `User` dengan `Role::Warga` lewat `google_id`;
    kembali ke `url.intended` (checklist yang sama, isian tidak hilang).
  - Batal masuk atau Google gagal: pesan + coba lagi, tidak bisa lanjut tanpa login.
  - Tidak pernah meminta NIK/KTP (C3). Email hanya untuk membedakan akun, tidak tampil ke publik maupun BPD.
  - Batas waktu pada panggilan Google dan jalur gagal (ENGINEERING 2).
  - Di mode demo tidak dipakai: akun demo + lokasi contoh (M6-05).
- Files: `app/Modules/Report/Http/Controllers/GoogleAuthController.php` (atau di Core/Auth bila tim sepakat),
  `config/services.php`, `.env.example` (nama variabel saja), `tests/Feature/Report/GoogleLoginTest.php`
- Acceptance criteria
  - [ ] C3 (wajib): diminta masuk Google sebelum checklist; tidak ada NIK/KTP.
  - [ ] Test memakai Socialite fake; tidak ada request jaringan (`Http::preventStrayRequests()`).
  - [ ] Tidak ada secret di repo.

### M4-06 · SubmitReport (Action + FormRequest + Policy + event)
- Status: todo · Ukuran: L · Owner: Dev 2 · Depends on: M4-02, M4-03, M4-01
- Spec: PRD Fitur Inti 5.1 langkah 8, 5.3, C5, C8-C10; PRD Arsitektur 6.8, 6.9, M4-06, F4-2, F4-4
- Scope:
  - `POST /p/{slug}/reports` (auth warga). `SubmitReport` Action dalam **satu transaksi**:
    validasi ulang di server, hitung ulang jarak (koordinat dari klien tidak dipercaya), tentukan jenis lewat
    `ReportClassifier`, simpan jawaban + foto, ganti laporan lama akun yang sama (yang lama tetap terlihat BPD
    sebagai "digantikan"), kunci baris (`lockForUpdate`) agar dua kiriman bersamaan menghasilkan satu laporan aktif.
  - Aturan: jawaban "Tidak" wajib hasil ukur (angka + satuan sama dengan RAB) (C5); foto 1-3; catatan <= 280
    huruf; maks 3 kiriman per akun per proyek per hari (kiriman ke-4 ditolak, C10). Tambah
    `sonar.report.max_per_day`, `max_photos`, `max_note_length` ke `config/sonar.php`; tanpa angka ajaib.
  - Laporan di luar radius: sesuai hasil rapat 22 Nov (F25). Default PRD Fitur v1.1: diterima dengan tanda
    "di luar radius", tidak dihitung (C8).
  - Setelah commit, kirim event `ReportSubmitted` (berisi id, bukan model). Kontrak ke M5: listener sync.
  - Proyek draf atau milik desa lain: 404. Proyek diambil lewat slug publik; `scopeBindings` bila route bersarang.
  - Log terstruktur tanpa koordinat dan email.
- Files: `app/Modules/Report/Actions/SubmitReport.php`, `Http/Controllers/ReportController.php`,
  `Http/Requests/SubmitReportRequest.php`, `Policies/ReportPolicy.php`, `Events/ReportSubmitted.php`,
  `routes.php`, `tests/Feature/Report/SubmitReportTest.php`
- Acceptance criteria
  - [ ] Happy path: laporan tersimpan, jenis benar, event terkirim.
  - [ ] F4-2: koordinat palsu dari luar radius dihitung ulang server.
  - [ ] F4-4: dua kiriman bersamaan dari satu akun = satu laporan aktif.
  - [ ] C9, C10; 422 untuk validasi; 401/redirect belum login; 404 proyek draf/tidak ada.
  - [ ] Laporan lama menjadi "digantikan" dan tidak dihitung dua kali.

### M4-07 · Checklist Cek Spek + layar konfirmasi
- Status: todo · Ukuran: L · Owner: Dev 2 · Depends on: M4-04, M4-05, M4-06
- Spec: PRD Fitur Inti 5.1 langkah 5-9, 5.5, C4, C9, C11; PRD Arsitektur 6.8, M4-07
- Scope:
  - `GET /p/{slug}/cek`: checklist semua ukuran wajib dari template + tambahan desa, dengan nilai RAB dan
    cara ukur (C4). Per ukuran: Sesuai / Tidak / Tidak bisa ukur; "Tidak" membuka isian hasil ukur.
  - Foto (M4-04), catatan opsional 280 huruf, tombol Kirim; jaringan putus: isian tetap, tombol "Kirim lagi".
  - `GET /p/{slug}/reports/terkirim`: "Laporanmu masuk ke BPD", jenis laporan, "x dari 3" **dihitung server**.
  - Aktifkan lagi tombol Cek Spek di halaman proyek (sekarang nonaktif).
  - Props Inertia = data publik: tidak ada email, user_id, foto laporan, atau alasan tidak valid.
  - Form pakai `useForm`; halaman < 300 baris, logika di `features/report/`; `features/report` tidak meng-import
    isi `features/project`.
  - Semua teks dari `lib/labels.ts`; tanpa kata "korupsi", "mark-up", "curang", "penyelewengan".
- Files: `resources/js/pages/report/Check.vue`, `Submitted.vue`, `resources/js/features/report/*`,
  `resources/js/types/report.ts`, `app/Modules/Report/Http/Controllers/*`, `Http/Resources/*`,
  `tests/Feature/Report/CheckPageTest.php`
- Acceptance criteria
  - [ ] C4, C9, C11 (wajib: nama, foto, hasil laporan tidak tampil di halaman publik).
  - [ ] Layar 360 px tanpa scroll horizontal; `make lint` hijau.
  - [ ] Teks "x dari 3" berasal dari props server.

### M6-05 · Foto + lokasi contoh di Cek Spek (milik M6, bergantung ke M4)
- Status: todo · Depends on: M4-06, M4-07
- Spec: PRD Fitur Inti 5.6, C12; tasks/m6-demo.md M6-05
- Catatan: tombol "Pakai foto & lokasi contoh" melewati izin lokasi dan login Google; pengunjung demo dihitung
  satu akun per sesi. Kode M4 tidak boleh tahu sedang demo (ENGINEERING 2): bedanya ada di sumber lokasi/foto
  dan akun, bukan `if is_demo` di Action.

## Definition of Done per task

Lihat `docs/ENGINEERING.md` bagian 8-9. Ringkas: test hijau (`make test`), `make lint` hijau, `vendor/bin/pint`
dijalankan, tidak ada `console.log`, perubahan dari spec dicatat di `docs/DECISIONS.md`.
