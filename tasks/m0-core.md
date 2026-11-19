# M0 Core (Infrastruktur & fondasi lintas modul)

Owner: Dev 1 · Jadwal: M0a 20–22 Nov, M0b 23–24 Nov
Spec: PRD Arsitektur 8, 13.1

## Task List

### M0-01 · Docker Compose + Makefile
- Status: done · Ukuran: M · Owner: Dev 1
- Spec: PRD Arsitektur 7.1, 7.2
- Scope: `compose.dev.yml`, `compose.prod.yml`, `Makefile` dengan target `setup`, `up`, `down`, `sh`, `migrate`, `fresh`, `test`, `lint`, `format`, `restart`, `logs`, `ps`.
- Files: `compose.dev.yml`, `compose.prod.yml`, `Makefile`, `docker/php/Dockerfile`

### M0-02 · Laravel baseline + Inertia v3 + Vue 3.5 + TS
- Status: done · Ukuran: M · Owner: Dev 1
- Spec: PRD Arsitektur 7.1, A13, A17, D-005
- Scope: Laravel 13 + PHP 8.4, Inertia v3 (SSR off, D-005), Vue 3.5 strict TypeScript, Tailwind v4, Pest, Vitest.
- Files: `composer.json`, `package.json`, `tsconfig.json`, `vite.config.ts`, `phpstan.neon`, `phpunit.xml`, `config/inertia.php`

### M0-03 · Village context + multi-tenancy guard
- Status: done · Ukuran: M · Owner: Dev 1
- Spec: PRD Arsitektur 6.3, A10; F0-5, F0-6
- Scope: `VillageContext`, `WithVillage` middleware, `BelongsToVillage` trait; isolasi desa di semua query, error `VillageContextMissing`.
- Files: `app/Modules/Core/Village/*`, `app/Http/Middleware/WithVillage.php`

### M0-04 · CI (GitHub Actions)
- Status: review (menunggu run pertama di GitHub; push masih dimatikan) · Ukuran: M · Owner: Dev 1
- Catatan implementasi:
  - 3 job paralel: `php` (Pint, Larastan, Pest di service Postgres 17), `js` (cek bahasa netral, ESLint,
    Prettier, vue-tsc, Vitest, `vite build`), `secrets` (gitleaks v8.30.1 CLI, seluruh history).
  - Larastan memakai level di `phpstan.neon` (level 6, M0a), bukan level 9 seperti rencana di atas.
  - Cek kata terlarang D9 dipindah ke `scripts/check-neutral-language.sh`, dipakai `make lint` dan CI.
  - `tests/TestCase.php` memanggil `withoutVite()`: checkout bersih tidak punya `public/build`, 9 test gagal tanpanya.
  - Diuji lokal: checkout bersih -> 41 Pest + 13 Vitest + build lulus; gitleaks 31 commit, tanpa temuan;
    actionlint bersih.
- Spec: PRD Arsitektur 7.3, A18
- Scope:
  - Workflow `.github/workflows/ci.yml` dipicu `push` dan `pull_request` ke `main`.
  - Job PHP: `vendor/bin/pint --test`, Larastan level 9, `php artisan test --compact` (Postgres 17 service).
  - Job JS: ESLint, `vue-tsc --noEmit`, Vitest.
  - Job secrets: gitleaks (scan history saat PR).
  - Cache: Composer dan npm.
- Files: `.github/workflows/ci.yml`
- Acceptance criteria:
  - [ ] `make test` dan `make lint` hijau sebelum pipeline pertama dijalankan
  - [ ] CI merah bila ada isu Pint, Larastan, atau test gagal
  - [ ] gitleaks berjalan dan scan termasuk history

### M0-05 · Deploy (VPS + Caddy + Laravel Cloud atau manual)
- Status: blocked · Ukuran: L · Owner: Dev 1
- Spec: PRD Arsitektur 7.4
- Blocker: **VPS dan domain dari owner proyek**
- Scope:
  - `DEPLOY.md`: langkah deploy manual ke VPS (Caddy 2, PHP-FPM Docker, Postgres) atau Laravel Cloud.
  - Script atau Makefile target `make deploy` (pull image + migrate + restart).
  - Env produksi: `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=warning`.
- Files: `DEPLOY.md`, `.github/workflows/deploy.yml` (opsional, CD)
- Acceptance criteria:
  - [ ] `http://localhost:8080/up` → 200 di server produksi
  - [ ] `make test` di server lulus (Postgres nyata)
  - [ ] HTTPS aktif, HTTP redirect ke HTTPS

### M0-06 · Seeders & demo clone
- Status: done · Ukuran: M · Owner: Dev 1
- Spec: PRD Arsitektur F0-5, F0-6; PRD Fitur 7.2
- Scope: `ProjectSeeder` dengan data Desa Contoh fiktif, `DemoCloneAction`, flag `is_demo`.
- Files: `database/seeders/*`, `app/Modules/Core/Demo/*`

### M0-07 · Login desa/BPD
- Status: done · Ukuran: M · Owner: Dev 1
- Catatan implementasi (berbeda dari rencana, mengikuti struktur modul A8 dan PRD Arsitektur 6.8):
  - File: `app/Modules/Core/Auth/Http/LoginController.php`, `LoginRequest.php`, `app/Modules/Core/routes.php`,
    `resources/js/pages/core/Login.vue`, `tests/Feature/Core/LoginTest.php`. Akun seeder sudah ada di `DatabaseSeeder`.
  - Throttle per email+IP lewat `RateLimiter`; percobaan ke-6 ditolak dengan pesan tunggu (422 + error field),
    bukan 429, supaya pesan tampil di form.
  - Login berhasil -> halaman tujuan (`intended`) atau `/` (dashboard belum ada; `/audits` datang di M2).
    Logout -> `/`. Akun warga ditolak dengan pesan yang sama seperti password salah.
  - Verify: `make test ARGS="--filter=Login"` (7 test).
- Spec: PRD Arsitektur 6.1, A9; PRD Fitur 8.1
- Scope:
  - Route `GET /login` → Inertia page `Auth/Login`.
  - Route `POST /login` → `AuthController@login`: email + password, throttle 5 permenit per IP.
  - Route `POST /logout`.
  - Middleware `auth` untuk semua route aplikasi desa/BPD.
  - Seeder akun: `desa@desa-contoh.test` (role `desa`) dan `bpd@desa-contoh.test` (role `bpd`), password `password`.
  - Halaman login: `PublicLayout`, form email + password, tombol primary satu-satunya, pesan error dari server.
- Files:
  - `app/Modules/Core/Http/Controllers/AuthController.php`
  - `resources/js/pages/auth/Login.vue`
  - `routes/web.php` (tambah `/login`, `/logout`)
  - `database/seeders/UserSeeder.php`
- Acceptance criteria:
  - [ ] `GET /login` tampil halaman login (200), terdaftar di `php artisan route:list`
  - [ ] Login berhasil → redirect ke `/dashboard` atau `/` sesuai role
  - [ ] Login gagal → 422, pesan error di bawah field
  - [ ] 6 percobaan per menit → 429 Too Many Requests
  - [ ] `POST /logout` → redirect ke `/login`
  - [ ] Akun seeder bisa login di environment lokal
- Verify: `make test ARGS="--filter=AuthController"`

### M0-08 · Layout + komponen UI
- Status: done · Ukuran: L · Owner: Dev 1 (commit 8488ccd, 01733c3; tambahan EmptyState, D-007)
- Spec: PRD Arsitektur A13; docs/DESIGN.md
- Scope:
  - Layouts: `PublicLayout`, `AppLayout`, `DemoBanner`.
  - Komponen `components/ui`: `Button`, `Badge`, `PriceLabel`, `Card`, `SummaryList`, `Alert`, `Dialog`, `Table`.
  - Setelah komponen siap: pecah `pages/project/Show.vue` (750 baris → ≤ 300) ke `features/project/`.
  - Semua komponen mengikuti DESIGN.md: token semantik, tanpa Tailwind default palette, aksesibel.
- Files:
  - `resources/js/layouts/PublicLayout.vue`
  - `resources/js/layouts/AppLayout.vue`
  - `resources/js/layouts/DemoBanner.vue`
  - `resources/js/components/ui/Button.vue`
  - `resources/js/components/ui/Badge.vue`
  - `resources/js/components/ui/PriceLabel.vue`
  - `resources/js/components/ui/Card.vue`
  - `resources/js/components/ui/SummaryList.vue`
  - `resources/js/components/ui/Alert.vue`
  - `resources/js/components/ui/Dialog.vue`
  - `resources/js/components/ui/Table.vue`
  - `resources/js/features/project/ProjectHeader.vue`
  - `resources/js/features/project/ProjectSummary.vue`
  - `resources/js/features/project/ProjectItemsTable.vue`
  - `resources/js/features/project/ProjectProgressPhotos.vue`
  - `resources/js/features/project/ProjectChanges.vue`
  - `resources/js/features/project/ProjectReviewStatus.vue`
- Acceptance criteria:
  - [ ] `grep -rnE "(bg|text|border)-(slate|gray|indigo|blue|rose|red|amber|green|emerald)-[0-9]" resources/js/components/ui resources/js/layouts resources/js/features` kosong
  - [ ] `grep -rnE "bg-gradient|shadow-(md|lg|xl|2xl)|rounded-(xl|2xl|3xl)" resources/js/components/ui resources/js/layouts resources/js/features` kosong
  - [ ] `Show.vue` ≤ 300 baris setelah dipecah
  - [ ] `make lint` lulus (ESLint + vue-tsc)
- Verify: `make lint`

## Aturan

- Setiap task menunjuk bagian spec yang dikerjakan.
- Penyimpangan dari spec dicatat sebagai entri D-xxx di `docs/DECISIONS.md`.
- Definition of Done dan Definition of Ready ada di `docs/ENGINEERING.md` bagian 8-9.
