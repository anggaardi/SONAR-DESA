# Engineering Principles — dasar setiap rekomendasi

Prinsip ini dipakai saat Fase 2 (memutuskan desain) dan Fase 3 (menulis aturan dev, edge case, rollout). Setiap prinsip punya *cara menerapkan* yang konkret. Untuk standar koding harian di codebase membership, lihat juga `../../SKILL.md` (membership-fullstack-standards).

---

## Lean engineering (tim kecil)

| Prinsip | Cara menerapkan |
|---|---|
| **Satu dokumen, satu sumber kebenaran** | Feature Spec menggantikan PRD+TDD+BRD. Perubahan keputusan → update dokumen + baris decision log, bukan dokumen baru |
| **Setiap bagian harus menjawab pertanyaan nyata** | Sebelum menulis bagian, sebutkan pertanyaan & penanyanya. Tidak ada → hapus |
| **Ukuran sesuai fitur** | S (<2 hari): TL;DR, Scope, AC, Open questions. M (2–7 hari): + flow, desain lite, edge case. L (>1 minggu): semua bagian; pertimbangkan pecah epic |
| **Definition of Ready sebelum coding** | Dev tidak mulai sebelum scope, AC, dan pertanyaan blocking beres |
| **Pakai pola yang sudah ada** | Tiru pola existing (tabel setting, audit log, halaman CRUD) sebelum membuat pola baru |
| **No over-engineering** | Abstraksi baru (registry/interface) hanya jika ada kebutuhan berikutnya yang disebut user |

## Desain & arsitektur

| Prinsip | Cara menerapkan |
|---|---|
| **Fallback aman / zero downtime** | Fitur baru default ke perilaku lama (mis. `.env`) bila data baru kosong/nonaktif |
| **Migration additive** | Tabel/kolom baru tanpa mengubah yang lama → rollback = revert deploy, data dibiarkan |
| **Rollback cepat tanpa deploy** | Sediakan saklar (nonaktifkan / feature flag) yang efeknya detik |
| **Simpan data di lapisan yang membutuhkannya paling awal** | Mis. data yang dibutuhkan sebelum konteks tenant aktif → simpan di central |
| **Generik di data, spesifik di kode** | Tabel generik (`provider` + `config` JSON + `secrets` terenkripsi) + class per provider (schema/validate/test) |
| **Cache dengan versi** | Key cache/instance memuat `version`; setiap write → `version++` + invalidasi |
| **Long-running process** | Jangan simpan state per-tenant di static/singleton tanpa key per tenant+versi |
| **Kontrak eksplisit** | Interface kecil (`key/schema/validate/test/fromEnv`), kontrak API berupa tabel endpoint + contoh respons |
| **Konsistensi build-time vs runtime** | Jika nilai juga di-bake saat build (mobile), dokumen wajib menyebut aturan konsistensinya + checklist onboarding |

## Keamanan (minimum dalam scope)

| Prinsip | Cara menerapkan |
|---|---|
| **Secret write-only** | API/UI tidak pernah mengembalikan nilai; tampil `••••last4`; kosong saat edit = tidak berubah |
| **Kunci enkripsi terpisah** | Key khusus fitur, bukan app key umum; simpan di password manager tim |
| **Audit tanpa nilai** | Audit log menyimpan *nama field*, aktor, IP, waktu — tidak pernah nilai |
| **Log bersih** | Larang `log($credential)`/`dd()`; QA meng-grep log |
| **Least privilege** | Gunakan middleware/guard yang sudah ada; tulis siapa yang boleh |
| **Temuan security di luar scope** | Catat lokasi (tanpa nilai), sarankan tiket terpisah + rotasi bila secret ter-commit |

## Kualitas & pengiriman

| Prinsip | Cara menerapkan |
|---|---|
| **AC terukur (Given/When/Then)** | Setiap AC bisa diuji tanpa interpretasi; sertakan angka (≤ 5 detik, 401, 422) |
| **Test case per story** | ID unik (`S1-01`), langkah konkret, expected spesifik, prioritas P1/P2/P3, regresi di akhir |
| **1 PR per story** | Dengan screenshot UI + hasil test case |
| **Rollout bertahap** | Deploy dengan semua tenant default → pilot 1 tenant → monitor → lanjut |
| **Observability** | Log terstruktur sumber keputusan runtime (mis. `source=tenant|env`, `version`) tanpa nilai rahasia |
| **Scope guard** | Hal baru di tengah jalan → Open questions/tiket baru, bukan ditambahkan diam-diam |
