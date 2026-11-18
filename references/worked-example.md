# Worked Example — Tenant-Isolated Credentials

Studi kasus nyata yang menjadi dasar skill ini. Gunakan sebagai patokan *tingkat detail* dan *nada* di setiap fase. Output finalnya bisa direproduksi dengan `scripts/examples/`.

---

## Fase 0 — Intake (pesan user, diringkas)

> Goals: pemisahan credential production per tenant untuk layanan cloud. Scope: Google OAuth Website, Google OAuth Android, Firebase Cloud Messaging, dan harus scalable untuk credential berikutnya. Diatur di extranet (`extranet.localhost`). Konsep: (1) user tidak perlu pilih tenant, menu di atas; (2) menu Credentials baru berisi list tenant; (3) CRUD, default sediakan credential goals kita. Pertanyaan: apakah store di DB central? Diskusi dulu sebelum breakdown.

Penjelasan user sudah cukup → AI **tidak bertanya ulang**, langsung discovery.

## Fase 1 — Discovery

Dua sub-agent paralel:
- **API agent** (Laravel): versi & package; arsitektur tenancy; extranet (route, auth, menu, setting per tenant); semua titik Google OAuth (web, native, OAuth v2); FCM (package, credential, singleton, job, device token); enkripsi/cache/queue; nama env key saja; dokumen terkait.
- **Frontend agent** (Vue + Capacitor): stack; penentuan tenant web vs native; build mobile per tenant; extranet (layout, menu, tenant selector, pola CRUD); Google login web & Android; FCM plugin & registrasi token; nama env key.

AI lalu memverifikasi sendiri: config Google, `redirectToGoogle` / `handleGoogleCallback`, validasi audience native, inisialisasi Messaging.

**Insight non-obvious yang ditemukan** (inilah nilai utama fase ini):
1. Callback OAuth menukar code dengan credential global **sebelum** tenant diketahui → urutan harus dibalik.
2. Messaging FCM disimpan di **static singleton** → worker queue terkunci pada project pertama.
3. Model tenant memakai VirtualColumn; kolom baru yang tidak terdaftar **terbuang diam-diam** → jangan tambah kolom di tabel tenants.
4. App mobile mem-*bake* client ID & `google-services.json` saat build → pengaturan di extranet hanya mengubah backend; harus konsisten dengan build.
5. Route extranet terdaftar di **dua file** route.
6. Cache default ter-tag oleh bootstrapper tenancy, sedangkan staging memakai driver file → gunakan store redis eksplisit.

**Di luar scope** (dicatat, lalu disepakati jadi tiket terpisah): verifikasi signature token native, state OAuth tidak ditandatangani, secret ter-commit di repo & dokumen lama, login extranet menerima password plaintext, bug fallback tenant di job push.

## Fase 2 — Diskusi

Struktur jawaban AI:
1. Kondisi sekarang — tabel Area / Kondisi / Dampak + insight + temuan security (lokasi saja).
2. Rekomendasi: **"Ya, DB central — tapi tabel generik"**, tabel opsi (`.env` per tenant ❌, kolom di tenants ❌, tabel di DB tenant ❌, tabel generik central + enkripsi ✅, secrets manager → later).
3. Sketsa: tabel `tenant_credentials` + audit log, enkripsi key khusus, secret write-only, provider registry (`key/schema/validate/test`), resolver + cache + fallback, titik integrasi runtime, UX extranet (matriks tenant × provider, kartu per provider, Google Web+Android = 1 provider).
4. Delapan pertanyaan, masing-masing dengan rekomendasi.

Jawaban user → Decision log:

| # | Pertanyaan | Jawaban |
|---|---|---|
| 1 | Fallback saat kosong | Fallback `.env` (tidak boleh down) |
| 2 | Siapa membuat project GCP/Firebase | Tim kita (assist), nanti bisa properti sendiri |
| 3 | Redirect URI | Per domain tenant |
| 4 | iOS | Field opsional, tidak diuji fase ini |
| 5 | Penyimpanan | DB central cukup |
| 6 | Siapa boleh edit | Semua root |
| 7 | Temuan security | Tiket terpisah (jaga scope) |
| 8 | Dokumen lama | Ganti & hapus |

Ditambah keputusan desain AI: 1 provider Google, key enkripsi khusus, provider didefinisikan di kode, jangan tambah kolom tenants.

## Fase 3 — Produksi

Permintaan output user: docx rapi, diagram hand-drawn Excalidraw, instruksi dev (cek & checklist sebelum mengerjakan), epic dipecah tepat 3 story (Web, Android, FCM), test case QA per story.

Keputusan struktur AI: fondasi (tabel, enkripsi, resolver, menu) digabung ke Story 1 karena jumlah story ditetapkan 3 → S2 & S3 bergantung pada S1.

Hasil: 22 halaman, 13 bagian, 9 diagram, 16+8+11 test case. Estimasi: S1 4–5 hari, S2 1.5–2 hari, S3 2–3 hari (~9–10 hari termasuk QA & rollout).

## Fase 4 — Review

Masalah yang ditemukan AI sendiri saat render (dan sudah ditangani oleh `docx-kit`):
- Gambar terpotong menjadi irisan tipis & judul cover bertumpuk → line spacing tanpa `lineRule` dibaca *exact*.
- Backtick literal muncul di dalam teks bold → parser nested bold+code.
- Baris kode membungkus → pecah ≤ 90 karakter.
- Daftar isi meluber → TOC 2 kolom.
- Kartu Story 3 terbelah halaman → page break sebelum story & QA.
- Diagram: label bertumpuk, catatan sequence terpotong, lebar catatan tidak mengikuti teks → diperbaiki di `sketch.js`.

Handover menyebut: lokasi file, isi per bagian, keputusan struktur yang perlu dicek, kasus "terlihat bug tapi benar" (menonaktifkan credential Google membuat APK yang dibuild dengan client tenant itu gagal login — 401), open questions, catatan font/ukuran, dan pengingat rotasi secret di tiket terpisah.
