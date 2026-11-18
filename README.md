# Sonar Desa

Label komposisi untuk proyek desa. Web app transparansi RAB proyek Dana Desa untuk Techcomfest 2027
(Web Application, Sub-tema 1). Audit RAB terhadap standar harga Kab. Gianyar, halaman proyek publik + QR,
Cek Spek warga, dan dashboard BPD.

Stack: Laravel 13 (PHP 8.4), Inertia v3, Vue 3.5 + TypeScript, Tailwind v4, Postgres 17, Caddy 2.
Semuanya jalan di Docker, jadi laptop tidak perlu PHP, Composer, Node, atau Postgres.

## Prasyarat

- Docker dengan Compose v2 (`docker compose version`)
- `make` dan `git`
- Port kosong: `8080` (app), `5173` (Vite), `5432` (Postgres)
- Windows: jalankan dari WSL2 (Ubuntu), clone repo di dalam filesystem WSL (`~/...`), bukan di `/mnt/c`

## Mulai (sekali setelah clone)

```bash
git clone <url-repo> SONAR-DESA
cd SONAR-DESA
make setup
make up
```

`make setup` membuat `.env` dari `.env.example`, build image PHP, `composer install`, `npm ci`,
membuat app key, lalu migrate + seed. Pertama kali butuh beberapa menit untuk download image.

Cek hasilnya:

- http://localhost:8080 menampilkan halaman depan
- http://localhost:8080/up mengembalikan 200
- `make test` dan `make lint` lulus

## Database

Tidak perlu diatur. Postgres jalan sebagai service `db`, dan nilai di `.env.example` sudah cocok:

| | Nilai |
| --- | --- |
| Host (dari container) | `db:5432` |
| Host (dari laptop, mis. DBeaver/TablePlus) | `127.0.0.1:5432` |
| Database | `sonar` (app), `testing` (Pest, dibuat otomatis) |
| User / password | `sonar` / `sonar_dev_only` (hanya lokal) |

Akun lokal hasil seed (login belum dibuat, datang di M0b): `desa@desa-contoh.test` dan
`bpd@desa-contoh.test`, password `password`.

## Perintah harian

| Perintah | Fungsi |
| --- | --- |
| `make up` / `make down` | Nyalakan / matikan semua service |
| `make ps` / `make logs` | Status / log service |
| `make sh` | Shell di container app (untuk `php artisan ...`) |
| `make migrate` | Jalankan migration baru (setelah `git pull`) |
| `make fresh` | Bangun ulang database + seed |
| `make test` | Pest + Vitest. Filter: `make test-php ARGS="--filter=Village"` |
| `make lint` | Pint, Larastan, ESLint, Prettier, vue-tsc |
| `make format` | Rapikan format PHP dan JS otomatis |
| `make restart` | Restart PHP setelah mengubah `.env` |

Jangan jalankan `php`, `composer`, atau `npm` langsung di laptop. Lewat `make`, atau `make sh` lalu
jalankan di dalam container. Paket npm baru:
`docker compose -f compose.dev.yml run --rm --no-deps vite npm install <paket>`.

Setelah `git pull` yang mengubah `composer.lock`, `package-lock.json`, atau Dockerfile: jalankan `make setup`
lagi. Aman diulang, data database tidak hilang.

## Masalah umum

| Gejala | Penyebab / solusi |
| --- | --- |
| `port is already allocated` | Port dipakai aplikasi lain. Ubah `APP_PORT` atau `DB_FORWARD_PORT` di `.env` (mis. `5433`), lalu `make down && make up` |
| `Permission denied` di `storage/` atau `vendor/` | Jangan jalankan `make` dengan `sudo`. Image dibangun dengan UID/GID kamu; kalau terlanjur: `sudo chown -R $(id -u):$(id -g) .` |
| Halaman tampil tanpa style / JS | Service `vite` mati. Cek `make ps`, lalu `make logs` |
| `docker compose build` diam lama tanpa output | Coba `docker build --target dev -t sonar-desa-php:dev -f docker/php/Dockerfile .`, lalu `make setup` lagi |
| Error `VillageContextMissing` | Disengaja: query tabel desa tanpa konteks desa. Lihat `docs/ENGINEERING.md` bagian 3 |

## Struktur

```
app/Modules/<Modul>/     Core, Standard, Audit, Project, Report, Review, Demo
resources/js/            pages/, features/<modul>/, components/ui/, layouts/, lib/
docker/                  Dockerfile PHP, Caddyfile, init Postgres
docs/prd/                PRD induk, PRD Fitur Inti, PRD Arsitektur
docs/DECISIONS.md        keputusan dan penyimpangan dari spec
docs/ENGINEERING.md      aturan kode, testing, Git, Definition of Done
tasks/                   task per modul (M0-M7) dan pembagian Dev 1 / Dev 2
```

## Cara kerja tim

Baca `docs/ENGINEERING.md` sebelum commit pertama. Ringkasnya:

- Satu branch per task (`m1-02-price-standard-seeder`), `main` selalu bisa di-deploy.
- Conventional Commits dengan scope modul: `feat(standard): add price standard seeder (M1-02)`.
- `make lint` dan `make test` lulus sebelum push.
- Penyimpangan dari PRD dicatat di `docs/DECISIONS.md`.

## Lisensi

Lihat `LICENSE`.
