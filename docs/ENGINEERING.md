# ENGINEERING

Aturan engineering Sonar Desa, disalin dari PRD Arsitektur v1.2 Bagian 9. Dipakai sampai submit dan menjadi
acuan review. Aturan yang ingin diubah dicatat dulu di `docs/DECISIONS.md`. AI agent (Kiro, Claude, Copilot)
yang membantu coding juga mengikuti file ini.

## 1. Sumber kebenaran

| Prioritas | Sumber | Menentukan |
| --- | --- | --- |
| 1 | `docs/DECISIONS.md` | Keputusan dan penyimpangan terbaru (entri terbaru menang) |
| 2 | PRD Fitur Inti v1.2 | Alur, aturan produk, teks UI, AC fitur |
| 3 | PRD Arsitektur v1.2 | Stack, struktur, modul, aturan kode |
| 4 | PRD Sonar Desa v1.0 | Scope, tujuan, jadwal |

Perilaku yang tidak tertulis di spec tidak dibuat. Kalau benar-benar kurang, pilih opsi paling sederhana yang
konsisten, kerjakan, lalu catat di DECISIONS.md.

## 2. Invarian produk (tidak boleh dilanggar)

- **Angka oleh kode, bacaan oleh AI.** Label, persen, selisih, cek total, jarak, jenis laporan, ambang, dan status
  publik hanya dihitung di kelas Domain. Vue dan AI tidak pernah menjadi sumber angka.
- **Kode fitur tidak tahu sedang demo.** Tidak ada `if ($village->is_demo)` di M1-M5.
- **Setiap panggilan luar punya batas waktu dan jalur gagal.** AI: 60 dtk -> Gagal baca + Isi manual.
  Google: gagal -> pesan + coba lagi, isian tidak hilang.
- **Bahasa netral.** Teks UI hanya dari `resources/js/lib/labels.ts`; kata "korupsi", "mark-up", "curang",
  "penyelewengan" tidak ada di kode UI (dicek grep di CI).
- **Privasi pelapor.** Identitas, email, foto laporan, dan alasan tidak valid tidak pernah masuk props halaman publik.
- **Waktu UTC di data, WITA di layar.** Hanya `lib/format.ts` yang memformat tanggal.
- **Tanpa secret di kode.** Nama variabel di `.env.example`, nilai tidak pernah di repo, chat, atau dokumen.

## 3. Backend (Laravel)

| Lakukan | Hindari |
| --- | --- |
| Satu Action per kasus pakai, dipanggil dari controller | Logika bisnis di controller, model observer, atau Blade/Vue |
| FormRequest untuk validasi + `authorize()` lewat Policy | Validasi manual di controller; cek role dengan `if` tersebar |
| Eloquent Resource untuk props Inertia | `Inertia::render(..., ['audit' => $audit])` dengan model utuh |
| Transaksi untuk tulis multi-tabel; `lockForUpdate` untuk hitungan ambang | Menghitung ulang status di luar transaksi |
| Enum PHP backed untuk status, label, jenis laporan, role | String bebas atau konstanta tersebar |
| Rupiah `int`, volume `numeric` + `BcMath\Number` | `float` untuk uang atau volume |
| Angka aturan dari `config/sonar.php` | Magic number (300, 3, 60) di kode |
| `declare(strict_types=1)`, tipe di semua parameter dan return | Array asosiatif bebas antar lapisan (pakai DTO readonly) |
| Migration baru untuk setiap perubahan | Mengedit migration yang sudah di-merge |
| Job menyimpan id, bukan model; `WithVillage`; `failed()` | State statis yang tidak di-reset antar job |
| Log terstruktur: `Log::info('rab.read', ['audit_id' => ..., 'ms' => ...])` | Log isi file, email warga, koordinat, atau API key |

### Batas modul (PRD Arsitektur 6.1)

- Modul lain hanya boleh memakai Model dan Action publik sebuah modul. `Domain/`, `Jobs/`, dan `Http/` milik modul itu sendiri.
- Arah ketergantungan tidak boleh berbalik. M4 tidak memanggil M5: M4 mengirim event `ReportSubmitted`,
  listener di M5 menghitung ulang ambang (listener sync, transaksi yang sama).
- M0 (Core) tidak boleh memakai modul fitur. M6 (Demo) boleh membaca semua tabel karena tugasnya mengkloning.

### Village scope

- Tabel (V) dengan `village_id` memakai trait `BelongsToVillage`. Query tanpa `CurrentVillage` melempar
  `VillageContextMissing`, tidak pernah mengembalikan semua desa.
- Tabel anak (tanpa `village_id`) mengimplementasikan `OwnedThroughParent` dan dicek `VillageOwnership`
  (atau Policy + `scopeBindings()`). Desa lain -> 404, bukan 403 (C1).
- `users` tidak memakai global scope (C3). Akses desa/BPD dicek lewat Policy.
- Tabel baru yang terhubung ke `villages` wajib didaftarkan di `VillageCloner` (M6).

## 4. Frontend (Vue + Inertia)

| Lakukan | Hindari |
| --- | --- |
| `<script setup lang="ts">`, `defineProps<T>()` dengan tipe dari `types/` | Options API, `any`, props tanpa tipe |
| `pages/` hanya menyusun; logika di `features/<modul>/` (komponen + composable) | Satu file page > 300 baris |
| `useForm` Inertia untuk semua form; error dari server | Validasi aturan produk hanya di client |
| Komponen dasar di `components/ui`, tanpa kata domain | `components/ui` yang memanggil route atau tahu "audit" |
| `PriceLabel` selalu teks + warna (aksesibel) | Label warna saja |
| Mobile first: uji 360 px untuk layar warga | Scroll horizontal di HP |
| Elemen semantik (button, table, dialog), fokus terlihat | `div` yang bisa diklik |
| Import antar fitur lewat `components/` atau `lib/` | `features/report` meng-import isi `features/audit` |
| Tidak ada `console.log` di commit (ESLint error) | Kode yang di-comment |

## 5. Kode yang bersih

- Nama memakai istilah PRD Fitur Inti 1.1 dalam bahasa Inggris yang konsisten (daftar padanan di bawah).
- Fungsi melakukan satu hal, umumnya < 40 baris, <= 3 parameter (lebih dari itu pakai DTO). Return lebih awal;
  nested `if` maksimal 2 tingkat.
- Interface hanya bila ada dua implementasi nyata sekarang (RabReader). Tanpa repository pattern, base class
  "untuk nanti", atau package modul.
- Helper diekstrak pada pemakaian nyata ketiga, bukan pertama.
- Komentar (bahasa Inggris) menjelaskan kenapa, batasan, satuan, dan rujukan spec:
  `// PRD Fitur Inti 3.4: round up so "+0%" never appears.` TODO wajib menyebut ID task: `// TODO(M4-05): ...`.
- Dependency baru butuh satu kalimat alasan di deskripsi PR / pesan commit.

### Padanan istilah

| PRD (Indonesia) | Kode (Inggris) |
| --- | --- |
| Desa | `Village` |
| Pemerintah Desa / BPD / Warga | `Role::Desa` / `Role::Bpd` / `Role::Warga` |
| Standar harga | `PriceStandard` |
| Template ukuran | `MeasurementTemplate` |
| Audit / item RAB | `Audit` / `AuditItem` |
| Label harga: Sesuai, Di atas standar, Tanpa acuan, Perlu dicek | `PriceLabel::WithinStandard`, `AboveStandard`, `NoReference`, `NeedsCheck` |
| Status audit: Draf, Membaca, Perlu diperiksa, Siap terbit, Terbit, Gagal baca | `AuditStatus::Draft`, `Reading`, `NeedsReview`, `Ready`, `Published`, `ReadFailed` |
| Proyek / ukuran Cek Spek | `Project` / `ProjectMeasurement` |
| Riwayat perubahan | `ProjectChange` |
| Foto progres | `ProgressPhoto` |
| Laporan; jenis Selisih, Sesuai, Tidak bisa diukur | `Report`; `ReportKind::Mismatch`, `Match`, `Unmeasurable` |
| Siklus tinjauan / catatan susulan | `ReviewCycle` / `ReviewNote` |
| Status publik: tanpa penanda, Dalam tinjauan, Ditinjau | `PublicStatus::None`, `InReview`, `Reviewed` |
| Hasil BPD: Ditindaklanjuti, Tidak ada selisih | `ReviewOutcome::FollowedUp`, `NoMismatch` |

Nilai enum backend sama dengan kunci di `resources/js/lib/labels.ts` (snake_case), sehingga Resource mengirim
nilai dan Vue memilih teksnya.

## 6. Testing

| Apa | Alat | Wajib |
| --- | --- | --- |
| Kelas Domain (PriceLabeler, TotalChecker, DistanceCalculator, ReportClassifier, ReviewThreshold) | Pest unit, dataset per baris aturan | 100% cabang aturan; batas di kedua sisi (300 m / 300,1 m; harga sama / +1 rupiah) |
| Action + Policy + route | Pest feature (Postgres asli) | Satu happy path + gagal utama: validasi 422, belum login, role salah, village lain 404 |
| Pembacaan RAB | Pest + FixtureRabReader | Tidak ada request jaringan keluar (`Http::preventStrayRequests()`, aktif global di `tests/Pest.php`) |
| Isolasi village + clone demo | Pest feature | F0-5, F0-6, F6-2 |
| `lib/format.ts` | Vitest | Rupiah (Rp85.000, Rp4,2 juta), WITA, persen |
| Alur juri end-to-end | Checklist manual di staging | Setiap deploy setelah 26 Nov (M7-01) |

Test harus deterministik: waktu dibekukan (`$this->travelTo()`), tanpa jaringan, tanpa sleep. Setiap bug yang
ditemukan mendapat test di level terendah yang bisa mereproduksinya.

## 7. Git dan alur kerja

| Hal | Aturan |
| --- | --- |
| Branch | `main` selalu bisa di-deploy. Satu branch per task: `m2-03-fixture-reader`. Setelah 12 Des: `fix/`, `perf/`, `style/`, `docs/` saja |
| Commit | Conventional Commits, scope = modul: `feat(audit): add price labeler (M2-02)`. Subjek imperatif <= 72 karakter |
| PR | Kecil (idealnya < 400 baris berubah), checklist DoD di template PR, screenshot untuk UI |
| Review | Wajib review silang untuk migration, kelas Domain, Policy, file compose, dan Dockerfile. Lainnya boleh self-merge bila CI hijau |
| Kontrak bersama | Perubahan tabel atau Resource milik modul lain dibicarakan dulu, dicatat di DECISIONS.md |
| Merge | Squash merge. Branch dihapus setelah merge |

Semua perintah dijalankan lewat Docker (`make ...`), bukan PHP/Node di host.

### Alur Kolaborasi & Standar CI (Dev 1 & Dev 2 / AI)

Untuk menjaga `main` dan PR selalu hijau tanpa kegagalan di GitHub Actions, alur kerja commit–push–PR mengikuti protokol berikut:

1. **Pre-Commit Gate Lokal (Dev 2 / AI):**
   - Sebelum membuat commit Git, Dev 2 **wajib** menjalankan seluruh rangkaian pengujian CI lokal yang identik dengan GitHub Actions:
     - **PHP:** `docker exec -t sonar-desa-dev-app-1 vendor/bin/pint --test && docker exec -t sonar-desa-dev-app-1 vendor/bin/phpstan analyse --memory-limit=1G --no-progress && docker exec -t sonar-desa-dev-app-1 php artisan test --compact`
     - **JS:** `sh scripts/check-neutral-language.sh && npm run lint && npm run typecheck && npm run test && npm run build`
   - **Pantangan keras:** Dilarang membuat commit jika ada salah satu cek yang gagal/merah.
   - **Filter berkas:** Berkas `.md` (dokumentasi/skills) tidak di-stage/di-push bersamaan dengan kode fitur kecuali diminta eksplisit.

2. **Commit Lokal & Serah Terima (Dev 2):**
   - Dev 2 membuat commit bersih dengan format Conventional Commits di branch kerja lokal.
   - Dev 2 mengabari Dev 1 bahwa seluruh cek lokal lulus dan kode siap di-push.

3. **Push Remote (Dev 1 / User):**
   - Dev 1 menjalankan `git push origin <branch>` dari terminal atau IDE menggunakan kredensial akun pribadinya.
   - Dev 1 mengonfirmasi ke Dev 2 bahwa push telah selesai.

4. **Pembuatan PR & Pemantauan CI Otomatis (Dev 2):**
   - Dev 2 langsung menyediakan tautan 1-klik untuk membuka Pull Request di GitHub (judul dan deskripsi terisi otomatis).
   - Dev 2 otomatis memeriksa status API GitHub Actions hingga seluruh job CI (PHP, JS, Secrets) selesai dan terverifikasi berstatus hijau (`success`).

## 8. Definition of Done (per task)

- [ ] AC fondasi task dan AC fitur yang dirujuk sudah dicek sendiri, bukan diasumsikan.
- [ ] `make lint` dan `make test` lulus di container; CI hijau.
- [ ] Kelas Domain baru punya unit test; route baru punya feature test (happy + gagal utama). Route yang menerima
      ID tabel anak punya test "village lain -> 404".
- [ ] Route baru tercantum di PRD Arsitektur 6.8 (atau 14.3 setelah dikonfirmasi); tabel baru yang terhubung ke
      `villages` terdaftar di `VillageCloner`.
- [ ] Tidak ada secret, `dd()`, `dump()`, `console.log`, kode di-comment, atau TODO tanpa ID task.
- [ ] Teks UI baru ada di `lib/labels.ts` dan sesuai PRD Fitur Inti 8.2.
- [ ] Env baru ada di `.env.example` dan `config/sonar.php`.
- [ ] Layar warga dicek di 360 px; layar dengan kamera/lokasi dicek di HTTPS (tunnel/staging).
- [ ] Penyimpangan dari spec dicatat di DECISIONS.md. Status task di `tasks/` diperbarui.

## 9. Definition of Ready (sebelum mulai modul)

- [ ] Bagian spec yang dirujuk modul sudah dibaca, termasuk edge case PRD Fitur Inti Bagian 9.
- [ ] Dependensi di kartu modul sudah selesai, atau ada factory/fixture pengganti.
- [ ] Bentuk tabel dan Resource yang dipakai modul lain sudah disepakati (kontrak bersama 22 Nov).
- [ ] Pertanyaan blocking sudah dijawab; yang tidak blocking masuk Open questions.
