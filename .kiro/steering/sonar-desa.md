# Sonar Desa: konteks project (selalu dibaca)

## Aturan kerja
- JANGAN push. Remote push sudah DISABLED. Commit lokal saja, Conventional Commits, scope = modul.
- Acuan: docs/prd/PRD-Arsitektur-Sonar-Desa.pdf (v1.2) untuk teknis, PRD Fitur Inti untuk produk.
- Feature Spec lama (Laravel 11, Redis, MinIO, Gemini 1.5) USANG (D-001).
- Semua perintah lewat Docker (make ...), bukan PHP/Node di host.

## Stack (sudah dipasang)
Laravel 13.43, PHP 8.4.27 (image sonar-desa-php:dev), Inertia Laravel 3.7, Pest 4, Larastan 3,
Postgres 17 (juga queue/cache/session), Caddy 2. Frontend belum dipasang.

## Sudah selesai (commit 962aea1, merge 44daeff)
- docker/php/Dockerfile (target dev/prod/web), php.ini (upload 12M/16M, UTC), Caddyfile,
  compose.dev.yml, compose.prod.yml (queue 3 replika), Makefile, .env.example, init DB "testing".
- app/Modules/Core: CurrentVillage (gagal keras tanpa konteks), BelongsToVillage, WithVillage,
  VillageOwnership + OwnedThroughParent (tabel anak, C1), User tanpa global scope + Role enum (C3),
  SetCurrentVillage middleware (alias "village").
- ModuleServiceProvider, AppServiceProvider (CurrentVillage scoped, cek env prod), config/sonar.php.
- Migration villages + users (role, village_id nullable), VillageFactory, UserFactory, seeder idempoten.

## Belum jalan (disengaja)
App belum bisa boot: bootstrap/app.php memanggil App\Modules\Core\Http\Middleware\HandleInertiaRequests
yang belum dibuat.

## Langkah berikutnya (M0a, urut)
1. Cek isi docs/ dan references/ (untracked). Tambah "references" ke .dockerignore.
2. HandleInertiaRequests + root view app.blade.php; hapus welcome.blade.php.
3. Frontend: vue 3.5, @inertiajs/vue3 + @inertiajs/vite 3.11, @vitejs/plugin-vue, Tailwind v4,
   ESLint + Prettier, Vitest, vue-tsc. CEK dulu: vue-tsc dan typescript-eslint sudah mendukung
   TypeScript 7? Kalau belum, pakai TypeScript 5.x terakhir.
4. resources/js: pages/, features/, components/ui/, layouts/, lib/format.ts (Rp, WITA), lib/labels.ts.
5. Folder kosong modul: Standard, Audit, Project, Report, Review, Demo.
6. phpunit.xml -> pgsql DB "testing"; pest init; phpstan.neon (level 6); test CurrentVillage + isolasi.
7. docs/DECISIONS.md (D-001, D-002 = A1-A22), docs/ENGINEERING.md (Bagian 9 PRD), tasks/README.md.
8. Verifikasi: make setup, make up, /up = 200, halaman Inertia tampil di :8080, make test, make lint.

## Catatan teknis
- "docker compose build" pernah macet tanpa output; "docker build --target dev -t sonar-desa-php:dev
  -f docker/php/Dockerfile ." berhasil. Cek "make setup" benar-benar jalan.
- Di bash, UID readonly: jangan pakai "UID=1000 cmd". Makefile meng-export UID/GID.
