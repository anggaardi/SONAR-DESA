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

## M0a selesai (commit db1b1d2..fe52815, terverifikasi 18 Nov)
- HandleInertiaRequests + app.blade.php; Vue 3.5 + Inertia v3 + Tailwind v4, TypeScript 5.9.
- resources/js (pages, features, components/ui, layouts, lib/format.ts, lib/labels.ts).
- Folder modul kosong: Standard, Audit, Project, Report, Review, Demo.
- Pest di Postgres "testing", Larastan level 6, tes isolasi desa.
- docs/DECISIONS.md (D-001..D-005), docs/ENGINEERING.md, tasks/README.md.
- Verifikasi: make setup jalan sampai selesai, /up = 200, halaman Inertia core/Home di :8080,
  make test (17 Pest + 13 Vitest) lulus, make lint (Pint, Larastan, ESLint, Prettier, vue-tsc) lulus.

## Langkah berikutnya
- M0b (23-24 Nov, Dev 1): tulis tasks/m0-core.md dulu sesuai template di tasks/README.md,
  lalu kerjakan. Paralel: Dev 2 mulai M1 Standard (tasks/m1-standard.md).

## Catatan teknis
- "docker compose build" pernah macet tanpa output; "docker build --target dev -t sonar-desa-php:dev
  -f docker/php/Dockerfile ." berhasil. Cek "make setup" benar-benar jalan.
- Di bash, UID readonly: jangan pakai "UID=1000 cmd". Makefile meng-export UID/GID.
