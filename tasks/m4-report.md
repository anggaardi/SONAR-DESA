# M4 Report (Fitur 3: Cek Spek & laporan warga)

Owner: Dev 1 · Jadwal: 27 Nov - 3 Des (M4-03, M4-04, M4-05 boleh mulai lebih awal)
Spec: PRD Fitur Inti v1.1 Bagian 5 (C1-C12), 8.3 (privasi); PRD Arsitektur v1.2 M4 (6.4, 6.8, 6.9, 8, F4-1..F4-4), 14.3-14.5

Cara kerja: warga membuka Cek Spek dari halaman proyek, mengizinkan lokasi, masuk dengan Google, mengisi
checklist ukuran, memotret langsung dari kamera, lalu mengirim. Server menghitung ulang jarak dan menentukan
jenis laporan (Selisih / Sesuai / Tidak bisa diukur). Setelah tersimpan, M4 mengirim event `ReportSubmitted`;
listener di M5 menghitung ulang ambang. M4 tidak pernah memanggil M5.

## Sudah ada dari M3-01

- Tabel `reports` + `Report` model (`OwnedThroughParent`), `ReportFactory`, enum `ReportKind`.
- `config/sonar.php`: `report.radius_m` (300), `report.max_accuracy_m` (100), `review.threshold` (3).
- `users.google_id`, `Role::Warga`. Tombol Cek Spek di halaman proyek masih nonaktif (diaktifkan di M4-07).

## Hasil rapat 22 Nov (PRD Arsitektur 14.3-14.5)

| ID | Keputusan | Dampak ke M4 |
| --- | --- | --- |
| F25 | **Diterima (D-009).** Di luar radius atau ketelitian > batas ditolak 422, tidak tersimpan. Radius dari sistem (`sonar.report.radius_m`), desa hanya mengisi titik | M4-06 menolak (sudah); `LocationGate` tanpa "Lanjut" bila jauh (sudah); kolom `in_radius` dihapus di M4-02 |
| F30 | **Tidak dipakai (D-011).** | Tidak ada kolom `banjar` |
| Perangkat | **Diterima (D-010).** 1 akun Google + 1 perangkat (`device_hash`); IP hanya rate limit, tidak disimpan | Kolom `device_hash` + cookie perangkat + partial unique index di M4-02; throttle `report-submit` per IP (sudah) |
| Klaster | Laporan berdekatan tetap diterima (D-010). Penanda untuk BPD: **belum diputuskan** | - |
| PDP | **Belum diputuskan** | - |
| Retensi | **Belum diputuskan** | - |

M4-02 sudah bisa dikerjakan (kolom final: `device_hash`, hapus `in_radius`). PDP dan Retensi menambah migration
terpisah bila disetujui nanti.

## Task List

### M4-03 · DistanceCalculator + ReportClassifier
- Status: review · Ukuran: M · Owner: Dev 1 · Depends on: -
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
- Status: review · Ukuran: M · Owner: Dev 1 · Depends on: -
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
- Status: review · Ukuran: M · Owner: Dev 1 · Depends on: M4-03 (aturan jarak untuk warna)
- Spec: PRD Fitur Inti 5.1 langkah 1-3, C1, C2, C7; PRD Arsitektur M4-05
- Scope:
  - Layar penjelasan sebelum izin diminta: kenapa lokasi diminta, laporan masuk ke BPD, nama tidak tampil (C1).
  - `useGeolocation`: ambil posisi + akurasi, tombol coba lagi. Akurasi > 100 m: "Sinyal lokasi lemah,
    tunggu sebentar" + coba lagi (C7).
  - Izin ditolak: tidak bisa lanjut + cara mengaktifkan lokasi (C2, wajib).
  - Tampilkan jarak ke titik proyek (hijau <= 300 m, kuning bila lebih). Jarak di layar hanya petunjuk;
    angka resmi dihitung ulang server saat kirim (M4-06). Titik proyek datang dari server, bukan dihitung di Vue.
  - Lokasi diambil ulang saat kirim.
- Files: `resources/js/features/report/useGeolocation.ts`, `LocationGate.vue`, `useGeolocation.test.ts`, `useProjectDistance.ts`, `app/Modules/Report/{routes.php,Actions/EstimateProjectDistance.php,
  Domain/DistanceHint.php,Http/*}`, `tests/Feature/Report/DistanceHintTest.php`; teks di `lib/labels.ts` (`location`)
- Acceptance criteria
  - [x] C2 (wajib), C7: `useGeolocation` + `LocationGate` (penjelasan C1 ada di `LocationGate`, dipasang di halaman oleh M4-07).
  - [ ] Layar 360 px tanpa scroll horizontal (cek manual saat dipasang di M4-07).
  - [x] Jarak + status radius dari server: `POST /p/{slug}/cek/jarak` (D-008), `useProjectDistance`.
- Catatan:
  - `LocationGate` tidak menghitung angka. Jarak dan "dalam radius" datang dari endpoint publik ber-throttle yang
    memakai `DistanceCalculator` (D-008): koordinat proyek tidak keluar dari server dan jarak dibulatkan ke atas
    (10 m, `null` di atas 1000 m). Hanya petunjuk; jarak resmi dihitung ulang saat kirim (M4-06).
  - Lokasi dipantau dengan `watchPosition` sampai akurasi <= batas lalu berhenti; `readFresh()` dipakai M4-07 untuk
    mengambil ulang lokasi saat kirim. Batas akurasi dioper dari server (`config('sonar.report.max_accuracy_m')`).
  - (21 Nov) Batas waktu `giveUpAfterMs` (30 dtk): laptop tanpa GPS bisa melapor posisi kasar sekali lalu diam,
    sehingga layar "Sinyal lemah, tunggu sebentar" macet tanpa tombol. Sekarang setelah 30 dtk status menjadi
    `too_weak` (pesan + Coba lagi) atau `failed` bila browser tidak menjawab sama sekali. Batas 100 m tetap (C7).
  - Tes lokal di laptop: Chrome DevTools > Sensors > Location, isi titik proyek seed (`-8.5412340, 115.3214560`)
    dan accuracy <= 100. HP butuh HTTPS (tunnel) karena lokasi dan kamera diblokir di `http://IP-LAN`.

### M4-02 · Migration report_answers + report_photos (+ kolom hasil rapat 22 Nov)
- Status: review (22 Nov) · migration `2026_11_21_171555_add_answers_photos_and_device_to_reports` · Ukuran: M · Owner: Dev 1 · Depends on: D-xxx hasil rapat
- Spec: PRD Arsitektur 6.4, M4-02, F4-4; ENGINEERING 3 (migration baru, bukan edit)
- Scope:
  - Migration baru (satu file): tabel `report_answers` (`report_id`, `measurement_id`, `answer`,
    `measured_value`, `measured_unit`) dan `report_photos` (`report_id`, `path`, urutan).
  - Foto di disk privat; EXIF dibuang (resize + WebP, seperti `StoreProgressPhoto`).
  - Index unik laporan aktif per (project, cycle, user) lewat partial unique index
    (`WHERE superseded_by IS NULL`).
  - Kolom hasil rapat: `reports.device_hash` (D-010; cookie perangkat acak, disimpan sebagai HMAC-SHA256 dengan
    kunci `APP_KEY`) + partial unique index aktif per (project, cycle, device_hash); hapus `reports.in_radius`
    (D-009) dan ubah kueri yang memakainya (`ProjectResource`, `VillageProjectController`,
    `CountValidMismatchReporters`). Tidak ada kolom `banjar` (D-011). Consent/retensi belum diputuskan.
  - Model `ReportAnswer`, `ReportPhoto` (`OwnedThroughParent` lewat `Report`), factory, dan tambahkan
    ke `VillageCloner::TABLES` (test F6-2 akan gagal kalau terlewat).
- Files: `database/migrations/*`, `app/Modules/Report/Models/ReportAnswer.php`, `ReportPhoto.php`,
  `database/factories/*`, `app/Modules/Demo/Actions/VillageCloner.php`
- Acceptance criteria
  - [x] Unique aktif per (project, cycle, user) dan per (project, cycle, device_hash) ditegakkan di database:
        partial unique index `NULLS NOT DISTINCT` (Postgres 15+), jadi laporan sebelum ada siklus juga terjaga.
  - [x] Test F6-2 (clone) tetap hijau.
- Catatan (22 Nov):
  - `report_answers.measurement_id` tanpa ON DELETE: ukuran yang sudah dijawab adalah bukti, hanya ikut terhapus
    bersama proyeknya. `measured_value` decimal(14,3) sama dengan `project_measurements.value`.
  - Foto: `Actions/StoreReportPhotos` (WebP, sisi terpanjang <= 1600 px, disk privat `reports/`). File ditulis
    sebelum transaksi dan dihapus lagi bila transaksi gagal.
  - Perangkat: `Http/Middleware/EnsureDeviceCookie` memberi cookie `sonar_device` (acak 40 karakter, HttpOnly,
    terenkripsi, 2 tahun) di halaman checklist dan saat kirim. Disimpan hanya `hash_hmac('sha256', token, APP_KEY)`.
  - `VillageCloner`: `report_answers` dan `report_photos` didaftarkan; kolom DEFERRED kini disisipkan dengan nilai
    template lalu dipetakan ulang (bukan null), supaya salinan laporan yang sudah digantikan tidak dianggap aktif
    dan tidak melanggar unique index. Perubahan kecil di kode M6.

### M4-01 · Socialite Google, kembali ke checklist yang sama
- Status: review · Ukuran: M · Owner: Dev 1 · Depends on: persetujuan dependency baru, Google OAuth client (26 Nov)
- Spec: PRD Fitur Inti 5.1 langkah 4, F11, C3; PRD Arsitektur 6.8, A4, M4-01
- Scope:
  - Tambah `laravel/socialite` (butuh persetujuan; alasan di pesan commit: login Google warga, A4).
  - `GET /auth/google` dan `/auth/google/callback`; buat/cari `User` dengan `Role::Warga` lewat `google_id`;
    kembali ke `url.intended` (checklist yang sama, isian tidak hilang).
  - Batal masuk atau Google gagal: pesan + coba lagi, tidak bisa lanjut tanpa login.
  - Tidak pernah meminta NIK/KTP (C3). Email hanya untuk membedakan akun, tidak tampil ke publik maupun BPD.
  - Batas waktu pada panggilan Google dan jalur gagal (ENGINEERING 2).
  - Di mode demo tidak dipakai: akun demo + lokasi contoh (M6-05).
- Files: `app/Modules/Report/Http/Controllers/GoogleAuthController.php`, `Actions/SignInCitizenWithGoogle.php`,
  `Enums/GoogleSignInFailure.php`, `routes.php`, `config/services.php`, `resources/js/pages/report/GoogleSignInFailed.vue`,
  `tests/Feature/Report/GoogleSignInTest.php`
- Acceptance criteria
  - [x] C3 (wajib): login Google tanpa NIK/KTP; kembali ke `url.intended`. Pengalihan tamu dari checklist ke Google
        dipasang bersama route `/p/{slug}/cek` di M4-07.
  - [x] Test memakai Socialite fake; tidak ada request jaringan (`Http::preventStrayRequests()`).
  - [x] Tidak ada secret di repo (`.env.example` hanya nama variabel).
  - [ ] Dicoba dengan OAuth client Google asli di lokal dan staging.
- Catatan:
  - Nama tampilan Google tidak disimpan (nama akun = "Warga"); hanya `google_id` dan email (PRD Fitur 8.3).
  - Email yang sudah dipakai akun lain (desa, BPD, atau warga dengan Google id lain) ditolak, tidak ditautkan.
    Ini mencegah login Google membuka akun desa/BPD.
  - Batal di Google (`?error=`), state tidak cocok, Google tidak terjangkau, atau `.env` kosong menampilkan halaman
    "Coba lagi"; `url.intended` tetap di sesi. Guzzle timeout 10 dtk (`config/services.php`).
  - Setelah login, halaman dimuat ulang: lokasi diminta lagi, tapi izin sudah diberikan sehingga cepat (M4-07).

### M4-06 · SubmitReport (Action + FormRequest + Policy + event)
- Status: review (22 Nov, termasuk simpan jawaban + foto dan 1 perangkat 1 pelapor) · Ukuran: L · Owner: Dev 1 · Depends on: M4-02, M4-03, M4-01
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
  - [x] Happy path: laporan tersimpan, jenis benar, event terkirim.
  - [x] F4-2: koordinat palsu dari luar radius dihitung ulang server.
  - [x] F4-4: dua kiriman bersamaan dari satu akun = satu laporan aktif.
  - [x] C9, C10; 422 untuk validasi; 401/redirect belum login; 404 proyek draf/tidak ada.
  - [x] Laporan lama menjadi "digantikan" dan tidak dihitung dua kali.
  - [x] Jawaban (`report_answers`, hasil ukur + satuan RAB hanya untuk "Tidak") dan foto (`report_photos`) tersimpan.
  - [x] D-010: perangkat yang sudah dipakai akun lain di siklus yang sama -> 422 `device`; akun yang sama di
        perangkat yang sama menggantikan laporannya sendiri.
- Progres (21 Nov, `tests/Feature/Report/SubmitReportTest.php`: 34 test hijau):
  - `POST /p/{slug}/reports` (`reports.store`, middleware `auth`) -> `ReportController::store` -> `SubmitReport`.
  - `SubmitReportRequest`: proyek tidak publik atau tanpa titik lokasi -> 404 (sebelum cek peran), selain warga -> 403
    (`ReportPolicy::create`). Semua ukuran proyek wajib dijawab tepat sekali (`size`, `distinct`, `in`); jawaban
    pakai enum `MeasurementAnswer`; "Tidak" wajib hasil ukur angka > 0 ("8,5" diterima), jawaban lain membuang
    hasil ukur (C5). Satuan tidak dikirim klien: satuan selalu dari ukuran RAB. Foto 1-`max_photos`, jpeg/png/webp
    <= 10 MB. Catatan <= `max_note_length`. lat/lng/accuracy_m wajib. Pesan validasi berbahasa Indonesia.
  - DTO readonly `Domain/ReportSubmission` + `Domain/SubmittedAnswer` antara request dan Action.
  - `SubmitReport`: jenis lewat `ReportClassifier::kind`; jarak dihitung ulang server dari posisi saat kirim (F4-2).
    Satu transaksi dengan baris proyek di-`lockForUpdate`: cek limit harian, buat laporan, laporan aktif lama di
    siklus yang sama diberi `superseded_by` (C10), lalu event. `cycle_id` = `projects.current_cycle_id`
    (boleh null; membuka siklus adalah tugas M5).
  - (22 Nov, D-009) Di luar radius -> 422 di field `location`, tidak tersimpan, event tidak dikirim. Ketelitian
    > `max_accuracy_m` -> 422 di `accuracy_m` ("Sinyal lokasi lemah"). Laporan baru selalu `in_radius = true`
    sampai kolom dihapus di M4-02. Edge "280 m, akurasi 90 m" tetap diterima.
  - (22 Nov, D-010) Throttle `report-submit` per IP (`sonar.report.submits_per_minute`, 20, longgar karena
    CGNAT). IP tidak disimpan dan tidak di-log. 1 perangkat 1 pelapor (`device_hash`) menunggu M4-02.
  - Limit C10: `config('sonar.report.max_per_day')` (3), dihitung per akun per proyek sejak tengah malam WITA
    (`sonar.display_timezone`), termasuk laporan yang sudah digantikan. Kiriman ke-4 -> 422 di field `report`.
  - Event `ReportSubmitted(reportId, projectId)` dikirim **di dalam** transaksi, bukan setelah commit: PRD
    Arsitektur 6.1 dan ENGINEERING 3 meminta listener M5 sync di transaksi yang sama (di bawah lock proyek).
    Listener M5 tidak boleh di-queue.
  - Log `report.submitted` hanya id, jenis, in_radius (tanpa koordinat, email, catatan).
  - Setelah kirim sementara redirect ke halaman proyek (`TODO(M4-07)`: halaman terkirim).
  - Sudah dites: happy path + event, jenis per jawaban (C9), di luar radius ditolak + klaim klien diabaikan
    (D-009, F4-2), batas radius 299,9/300,2 m, akurasi buruk ditolak, edge 280 m/90 m, throttle per IP, laporan
    lama digantikan (hanya akun + siklus
    yang sama), limit ke-4 ditolak, reset tengah malam WITA, limit per proyek, tamu -> login, desa 403, 404
    (tidak ada, draf, tanpa lokasi, demo lain), semua validasi + pesan.
  - Laporan lama diberi `superseded_by` sebelum laporan baru disisipkan (id baru diambil dulu lewat `nextval`),
    karena unique index aktif melarang dua laporan aktif sekaligus.
  - Sisa: tidak ada untuk M4-06. Listener M5 (`ReportSubmitted`) dikerjakan di M5-03.

### M4-07 · Checklist Cek Spek + layar konfirmasi
- Status: doing (halaman, kirim, dan konfirmasi selesai; cek 360 px + uji HP belum) · Ukuran: L · Owner: Dev 1 · Depends on: M4-04, M4-05, M4-06
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
  `tests/Feature/Report/CheckSpecPageTest.php`
- Acceptance criteria
  - [x] C4: checklist berisi semua ukuran, nilai RAB, dan cara ukur (`CheckSpecResource`, test di `CheckSpecPageTest`).
  - [x] C5 di layar: "Tidak" membuka isian hasil ukur dengan satuan RAB; Lanjut ditahan sampai terisi
        (`checkSpecRules.ts`). Server tetap memvalidasi ulang di M4-06.
  - [x] Tamu diarahkan ke Google lalu kembali ke `/p/{slug}/cek` (F11); desa/BPD 403; proyek tidak publik 404.
  - [x] C9: konfirmasi menampilkan jenis laporan + "x dari 3" dari server (`SubmittedReportPageTest`).
  - [x] C11 (wajib): setelah laporan terkirim, halaman proyek publik tidak memuat email, catatan, path foto, atau
        hasil ukur pelapor (`SubmitReportTest`).
  - [ ] Layar 360 px tanpa scroll horizontal; `make lint` hijau.
  - [x] Teks "x dari 3" berasal dari props server (`submitted.mismatch_count`).
- Catatan (bagian halaman, 21 Nov):
  - Tombol "Cek spek di lokasi" membuka dialog berisi penjelasan C1 (lokasi, laporan ke BPD, nama tidak tampil,
    login Google), lalu "Mulai Cek Spek" menuju `/p/{slug}/cek`. Penjelasan muncul sebelum login dan izin apa pun.
  - Urutan langkah di halaman: Lokasi -> Ukur -> Foto & catatan. Login Google terjadi sebelum halaman (route `auth`),
    jadi lokasi tidak perlu diminta ulang setelah redirect Google. Tetap memenuhi F11 (login sebelum checklist).
  - `FindPublicProject` (modul Project) dipakai `ProjectController`, `DistanceHintController`, `CheckSpecController`.
  - Batas `max_photos` dan `max_note_length` ditambahkan ke `config/sonar.php` dan dikirim lewat props.
  - (21 Nov) Tombol "Kirim laporan" tersambung ke `POST /p/{slug}/reports` (`checkSpec.urls.submit` dari server).
    Aktif bila lokasi ada, checklist lengkap, dan minimal 1 foto (`MIN_PHOTOS`). Saat kirim lokasi dibaca ulang
    (`readFresh`); bila gagal, posisi dari langkah Lokasi yang dikirim. Foto dikirim sebagai `File` JPEG
    (`useForm` -> FormData). Error validasi server tampil sebagai daftar; jaringan putus atau error server ->
    "Laporan belum terkirim" + tombol "Kirim lagi", isian tidak hilang. Setelah sukses ke halaman terkirim.
  - (21 Nov) `GET /p/{slug}/reports/terkirim` (`reports.submitted`, auth; tamu ke Google seperti checklist):
    laporan aktif terbaru akun ini di siklus berjalan; tanpa laporan -> kembali ke checklist; desa/BPD 403;
    proyek tidak publik 404. Props (`SubmittedReportResource`): nama proyek, jenis, `counted` (in_radius) untuk
    pesan "tidak dihitung", `mismatch_count {current, threshold}`, URL. Tanpa id, email, koordinat, catatan.
  - "x" dihitung `Actions/CountValidMismatchReporters`: akun berbeda dengan laporan Selisih yang dihitung
    (`in_radius`), tidak ditandai tidak valid, tidak digantikan, di `current_cycle_id`. Ini hanya hitungan;
    status publik tetap milik M5 (`ReviewThreshold`, M5-02), yang boleh memakai Action ini.
  - Celah demo: pengunjung demo peran warga belum login, jadi Cek Spek di mode demo mengarah ke Google.
    Harus ditutup M6-05 (akun warga demo) sebelum branch ini dipakai juri (M8).


### M6-05 · Foto + lokasi contoh di Cek Spek (milik M6, bergantung ke M4)
- Status: review (22 Nov, bagian Cek Spek; batas 3 unggah AI tetap menunggu M2) · Depends on: M4-06, M4-07
- Spec: PRD Fitur Inti 5.6, C12; tasks/m6-demo.md M6-05
- Catatan: tombol "Pakai foto & lokasi contoh" melewati izin lokasi dan login Google; pengunjung demo dihitung
  satu akun per sesi. Kode M4 tidak boleh tahu sedang demo (ENGINEERING 2): bedanya ada di sumber lokasi/foto
  dan akun, bukan `if is_demo` di Action.
- Selesai (22 Nov):
  - Akun warga demo per klon (`VillageCloner::ACCOUNT_ROLES` + Warga); `DemoSession` login untuk semua peran,
    jadi tidak ada Google di demo dan satu sesi = satu pelapor. Celah "warga demo diarahkan ke Google" tertutup.
  - `GET /demo/lokasi-contoh/{slug}` (`demo.sample-location`): titik 20 m di utara proyek, akurasi 10 m
    (`sonar.demo.sample_*`). Hanya untuk proyek desa demo sesi ini; proyek asli dan demo sesi lain 404.
  - `Check.vue`: blok "Pakai foto & lokasi contoh" hanya bila prop bersama `demo` ada. Lokasi masuk lewat
    `LocationGate.fixedPosition` (`useGeolocation.useFixed`), foto contoh digambar di canvas
    (`features/demo/useDemoSample.ts`) dan masuk lewat `CameraCapture.extraPhoto` (`useCamera.add`).
    Saat kirim lokasi tidak dibaca ulang dari GPS. Server memeriksa seperti laporan asli (radius, validasi).
  - C12 penuh (status publik jadi "Dalam tinjauan BPD") menunggu listener M5-03.

## Definition of Done per task

Lihat `docs/ENGINEERING.md` bagian 8-9. Ringkas: test hijau (`make test`), `make lint` hijau, `vendor/bin/pint`
dijalankan, tidak ada `console.log`, perubahan dari spec dicatat di `docs/DECISIONS.md`.
