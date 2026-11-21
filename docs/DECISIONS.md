# DECISIONS

Keputusan dan penyimpangan dari spec. Entri terbaru menang (PRD Arsitektur 9.1, prioritas 1).
Aturan yang ingin diubah dicatat di sini dulu, tidak diubah diam-diam.

Format entri:

```
## D-xxx · Judul
- Date: · Status: proposed | accepted | superseded by D-yyy · Owner:
- Context:
- Decision:
- Consequences:
- Spec reference:
```

---

## D-001 · Feature Spec lama usang; PRD Arsitektur menjadi acuan teknis
- Date: 18 Nov 2026 · Status: accepted · Owner: Dev 1
- Context: ada dua dokumen arsitektur. Feature Spec memakai Laravel 11, Redis,
  MinIO, dan Gemini 1.5. PRD Arsitektur v1.2 memakai Laravel 13, Inertia v3,
  Postgres untuk queue/cache/session, disk lokal, dan model AI dari env.
- Decision: Feature Spec tidak berlaku untuk stack, struktur, infrastruktur,
  dan pilihan model. Acuan: PRD Fitur Inti v1.2 (produk) + PRD Arsitektur
  v1.2 (teknis). Bagian produk Feature Spec yang masih benar sudah ada di
  PRD Fitur Inti; selain itu diabaikan.
- Consequences: tidak ada Redis, MinIO, Mailpit, atau model AI yang dipatok.
  File Feature Spec dipindah ke docs/archive/ dengan banner "USANG, lihat D-001".
- Spec reference: PRD Arsitektur v1.2 Bagian 4 (A1-A22), 5, 7.

## D-002 · Keputusan arsitektur A1-A22 berlaku
- Date: 18 Nov 2026 · Status: accepted · Owner: Dev 1
- Context: keputusan arsitektur disepakati di PRD Arsitektur v1.2 Bagian 4. Disalin ke sini
  supaya satu tempat dibaca manusia dan AI agent.
- Decision:

| #   | Keputusan |
| --- | --- |
| A1  | Satu aplikasi Laravel 13 + Inertia v3 + Vue 3 (TypeScript). Tidak ada API JSON terpisah. Auth pakai session. |
| A2  | PostgreSQL 17. numeric dan timestamptz untuk uang dan waktu. |
| A3  | 1 VPS + Docker Compose, Caddy untuk HTTPS. Tidak "tidur", sama dengan dev. |
| A4  | Desa dan BPD login email + password dari seeder; warga login Google (Socialite). |
| A5  | Pembacaan RAB lewat interface RabReader: AiRabReader (laravel/ai, provider dari env) dan FixtureRabReader. |
| A6  | Aturan engineering di PRD Arsitektur Bagian 9, disalin ke docs/ENGINEERING.md. |
| A7  | Detail modul = fondasi teknis. AC fitur tetap di PRD Fitur Inti. |
| A8  | Modular monolith: app/Modules/<Nama>, tanpa package modul. |
| A9  | Mode demo = satu baris villages hasil kloning Desa Contoh per sesi; semua data desa ber-village_id dan difilter otomatis. |
| A10 | Queue, cache, session memakai driver database (Postgres). Tanpa Redis. Queue prod 3 proses worker. |
| A11 | Semua angka dihitung kelas Domain PHP murni. Rupiah integer, volume numeric, BcMath\Number. AI tidak pernah jadi sumber angka. |
| A12 | Satu Dockerfile multi-stage (dev, prod) dengan php.ini milik repo. |
| A13 | Tanpa starter kit UI atau library komponen. Tailwind CSS v4 + komponen sendiri di components/ui. |
| A14 | Waktu disimpan UTC (timestamptz), dikirim ISO 8601, ditampilkan WITA hanya lewat lib/format.ts. |
| A15 | Foto laporan diambil kamera di halaman (getUserMedia + canvas), bukan input type=file. |
| A16 | Fondasi M0a selesai 22 Nov, sebelum coding paralel 23 Nov. |
| A17 | Tanpa SSR. Halaman Inertia di-split per page; budget JS halaman publik 150 KB gzip. |
| A18 | CI adalah gerbang merge. Test memakai Postgres asli, bukan SQLite. |
| A19 | Feature Spec lama dinyatakan usang (D-001). |
| A20 | Tabel anak dicek lewat induk (Policy + scopeBindings); users tanpa global scope. |
| A21 | Kuota harian global AI dengan fallback otomatis ke Isi manual. |
| A22 | price_standards punya kolom regency sejak awal. |

- Consequences: perubahan pada salah satu baris di atas butuh entri D-xxx baru.
- Spec reference: PRD Arsitektur v1.2 Bagian 4.

## D-003 · TypeScript 5.9, bukan 7
- Date: 18 Nov 2026 · Status: accepted · Owner: Dev 1
- Context: TypeScript 7.0.2 sudah rilis (latest). vue-tsc 3.3 menerima typescript >=5.0,
  tetapi typescript-eslint 8.73 hanya mendukung typescript >=4.8.4 <6.1.0.
- Decision: kunci typescript ~5.9.3 (5.x terakhir) di package.json.
- Consequences: naik ke TypeScript 7 setelah typescript-eslint mendukungnya, dan tidak setelah 12 Des
  (larangan upgrade mayor, PRD Arsitektur 5).
- Spec reference: PRD Arsitektur 5 (Kualitas JS), 9.4.

## D-004 · Test env ditulis ke $_SERVER di phpunit.xml
- Date: 18 Nov 2026 · Status: accepted · Owner: Dev 1
- Context: compose `env_file: .env` mengisi environment container. Laravel membaca $_SERVER lebih dulu,
  sehingga `<env>` di phpunit.xml tidak menang dan test bisa memakai database dev atau APP_KEY kosong.
- Decision: kunci penting (APP_ENV, APP_KEY, DB_CONNECTION, DB_DATABASE, CACHE_STORE, QUEUE_CONNECTION,
  SESSION_DRIVER, RAB_READER) juga ditulis sebagai `<server>`. APP_KEY test berisi 32 byte nol, bukan secret.
- Consequences: `make test` selalu memakai database "testing", walau .env diubah.
- Spec reference: PRD Arsitektur 7.3 (make test), A18.

## D-005 · Inertia SSR dimatikan di config/inertia.php
- Date: 18 Nov 2026 · Status: accepted · Owner: Dev 1
- Context: default inertia-laravel 3.7 mengaktifkan SSR dan mencoba memanggil server SSR di setiap render.
- Decision: config/inertia.php dipublikasikan dengan `ssr.enabled = false`; plugin Vite `inertia({ ssr: false })`.
- Consequences: tidak ada service Node di runtime prod. Sesuai A17.
- Spec reference: PRD Arsitektur A17.

## D-006 · Design system: IDDS (mode terang) sebagai dasar visual
- Date: 19 Nov 2026 · Status: accepted · Owner: Dev 1
- Context: PRD Sonar Desa 6 menunda warna dan tipografi ke Q7. Tim memilih INA Digital Design System (IDDS)
  setelah membandingkan screenshot IDDS, GOV.UK, MYDS, SGDS, USWDS, NL Design System, Carbon, dan NerdWallet.
  A13 melarang library komponen, jadi paket `@idds/vue` tidak dipasang.
- Decision: token IDDS mode terang (tema inagov) ditulis sebagai token semantik Tailwind v4 di
  `resources/css/app.css`; aturan pakai di `docs/DESIGN.md`. Penyimpangan dari IDDS:
  `content-tertiary` memakai neutral-500 `#737373` (IDDS `#a3a3a3` hanya 2,5:1); teks status memakai
  shade 700 di atas latar 100 supaya lulus AA; warna `demo` (violet) khusus banner demo. Font Inter.
- Consequences: UI memakai kelas semantik (`bg-surface`, `text-content`, `bg-primary`, `text-negative`).
  Palet default Tailwind di-reset (`--color-*: initial`), jadi kelas seperti `bg-slate-50` tidak lagi ada;
  halaman M3 sudah dimigrasi. Inter dimuat lewat `@fontsource-variable/inter` (self-hosted, jalan offline
  untuk demo final F27; browser hanya mengunduh subset yang dipakai, latin ±48 KB).
- Spec reference: PRD Sonar Desa 6 / 6.1, PRD Fitur Inti 4.3, 7.2, A13, A17.

## D-007 · Spot ilustrasi humanis IDDS & komponen UI dasar
- Date: 19 Nov 2026 · Status: accepted · Owner: Dev 1
- Context: desain sistem murni netral tanpa ilustrasi terasa dingin/steril ("The Sterile Default"). IDDS resmi menyediakan pustaka ilustrasi bertema humanis, inklusif, dan ramah masyarakat Indonesia.
- Decision: adopsi pustaka ilustrasi spot IDDS berbasis SVG garis membulat (*rounded line-art*) dengan token semantik (`primary-subtle`, `stroke-strong`, `surface-subtle`) untuk titik sentuh krusial (Empty State, panduan Cek Spek, segel verifikasi desa). Komponen dasar di `resources/js/components/ui/` (`EmptyState`, `Button`, `Card`, `Badge`, `Alert`, `PriceLabel`, `Table`, `Dialog`, `SummaryList`) menjadi rujukan implementasi antarmuka yang humanis di mobile dan desktop.
- Consequences: UI memiliki kehangatan dan rasa manusiawi (*human-friendly*) seperti Gojek/IDDS tanpa melanggar WCAG 2.1 AA atau menambah ukuran bundle eksternal.
- Spec reference: D-006, docs/DESIGN.md, PRD Sonar Desa 6.1.

## D-008 · Jarak di layar Cek Spek dihitung server lewat endpoint publik
- Date: 21 Nov 2026 · Status: accepted · Owner: Dev 2
- Context: layar lokasi Cek Spek menampilkan jarak ke proyek (PRD Fitur Inti 5.1 step 3) sebelum warga login (F11).
  ENGINEERING 2 melarang Vue menjadi sumber angka, dan koordinat proyek tidak ada di props halaman publik. Warga
  tidak perlu tahu titik proyek: orang yang tidak ada di lokasi bisa mendatangi koordinat itu untuk melapor sembarangan.
- Decision: `POST /p/{slug}/cek/jarak` (publik, throttle `report-distance`, 30/menit per IP) menerima posisi
  pengunjung dan mengembalikan `{ within_radius, distance_m }`. Jarak dihitung `DistanceCalculator`, status radius
  oleh `ReportClassifier`. `distance_m` dibulatkan ke atas ke kelipatan 10 m dan bernilai `null` di atas 1000 m
  (`config('sonar.report.distance_hint_*')`). Respons `Cache-Control: no-store, private`. Proyek draf, tidak ada,
  tanpa lokasi, atau demo milik pengunjung lain menghasilkan 404 (aturan sama dengan P10).
- Consequences: koordinat proyek tidak pernah dikirim ke browser. Ini hanya petunjuk; jarak resmi tetap dihitung
  ulang dari lokasi segar saat laporan dikirim (M4-06). Risiko yang diterima: banyak kueri dari posisi palsu
  secara teori bisa memperkirakan titik proyek. Pembulatan, batas 1000 m, dan throttle membuatnya lambat dan
  tidak presisi; titik proyek memang bukan rahasia di lapangan (plang proyek ada di lokasi). Bila F25 disetujui
  (laporan luar radius ditolak), endpoint ini tidak berubah.
- Spec reference: PRD Fitur Inti 5.1 (langkah 2-3), F11, C8; PRD Arsitektur 6.9, ENGINEERING 2.
