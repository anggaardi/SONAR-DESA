# Fase 3 — Document Blueprint

Kerangka baku Feature Spec. Urutan bagian tetap — pembaca terbiasa mencari di tempat yang sama setiap dokumen. Kolom **Komponen** merujuk ke helper di `scripts/lib/docx-kit.js`.

Bahasa: penjelasan **Bahasa Indonesia**, istilah teknis tetap **English** (endpoint, cache, fallback, payload). Kalimat pendek. Tidak ada basa-basi.

---

## Halaman cover (1 halaman, wajib muat)

| Elemen | Isi | Komponen |
|---|---|---|
| Eyebrow + judul + subjudul | `FEATURE SPEC` / nama fitur / 1 kalimat apa & di mana | `cover()` |
| Tabel meta | Status, Versi, Owner, Tanggal, Pelaksana, Estimasi, Repo, Epic | `cover()` |
| Callout "menggantikan" | Jika ada dokumen lama yang diganti/dihapus + alasannya | `Callout('gray', …)` |
| Cara membaca | Tabel Peran → wajib baca → opsional (Lead, Developer, QA) | `Tbl` |
| Daftar isi | 2 kolom, nomor bagian | `toc()` |

## Bagian 1–13

| # | Bagian | Pertanyaan yang dijawab | Isi wajib | Komponen |
|---|---|---|---|---|
| 1 | **TL;DR** | "Ini apa, singkatnya?" | 3 paragraf: masalah sekarang → solusi → kenapa scalable. Diikuti diagram As-Is vs To-Be | `Callout('note')`, `Figure` |
| 2 | **Latar belakang, tujuan & metrik** | "Kenapa? Kapan berhasil?" | Masalah (bullet), Tujuan `G1..Gn`, Metrik dengan target terukur | `Bullets`, `Tbl` |
| 3 | **Scope** | "Apa yang dikerjakan & tidak?" | Tabel 3 kolom IN / OUT (tiket terpisah) / LATER + callout "Jaga scope" | `scopeTable`, `Callout('warn')` |
| 4 | **Keputusan yang disepakati** | "Kenapa desainnya begini?" | `D1..Dn`: keputusan + alasan. Gabungan jawaban user + keputusan desain AI | `Tbl` |
| 5 | **Kondisi sekarang vs target** | "Apa yang ada & di mana harus diubah?" | Tabel Area / Kondisi / Titik kode (`path` + fungsi + ~baris) + callout **Penting** untuk insight non-obvious | `Tbl`, `Callout('danger')` |
| 6 | **Desain solusi** | "Bagaimana membangunnya?" | 6.1 Arsitektur (diagram + tabel komponen baru & lokasi) · 6.2 Alur pengguna (flowchart) · 6.3 Wireframe + aturan UI · 6.4 Model data (ERD + contoh struktur) · 6.5 Kontrak (interface/schema + aturan field + cara test) · 6.6 Logika inti (pseudo-code singkat + aturan) · 6.7 Alur runtime per story (sequence diagram + catatan implementasi) · 6.8 Kontrak API (tabel endpoint + contoh respons) · 6.9 Aturan keamanan | `Figure`, `Tbl`, `Code`, `Callout('info')` |
| 7 | **Edge cases & aturan** | "Kalau X terjadi, bagaimana?" | Tabel Kondisi → Perilaku sistem (10–15 baris), termasuk kasus "perilaku benar yang terlihat seperti bug" | `Tbl` |
| 8 | **Instruksi untuk Developer** | "Apa yang harus saya cek sebelum mulai?" | Callout pembuka · 8.1 Definition of Ready (checklist) · 8.2 Lakukan / Hindari (tabel) · 8.3 Definition of Done (checklist) | `Callout('note')`, `Checks`, `Tbl` |
| 9 | **Epic & Story breakdown** | "Urutan kerjanya apa?" | Diagram epic + timeline · ringkasan epic · per story: kartu header (estimasi, dependensi) · user story · tabel Tasks (BE/FE/Ops) · Acceptance Criteria Given/When/Then | `Figure`, `storyHeader`, `Tbl`, `AC` |
| 10 | **QA test cases per story** | "Apa yang harus diuji?" | Callout persiapan QA (lingkungan, device, akses, definisi prioritas) · tabel per story: ID, Skenario, Langkah, Expected, Prio · regresi di akhir setiap story | `Callout('info')`, `TC` |
| 11 | **Rollout, rollback & monitoring** | "Bagaimana rilis dengan aman?" | Langkah rollout bernomor · rollback cepat & penuh · apa yang dimonitor | `Numbered`, `Bullets` |
| 12 | **Risiko & open questions** | "Apa yang bisa salah? Apa yang belum diputuskan?" | Tabel Risiko/Dampak/Mitigasi · Open questions (non-blocking) dengan Owner & Batas | `Tbl` |
| 13 | **Lampiran** | "Langkah operasional yang berulang?" | Checklist operasional (mis. onboarding tenant/credential) | `Checks` |

Story kedua dan seterusnya dimulai di **halaman baru** (`pageBreak()`), begitu juga bagian QA — supaya kartu story & callout tidak terbelah.

## Aturan menulis per komponen

**Story & epic**
- Jumlah story mengikuti kebutuhan atau permintaan user. Jika user menetapkan jumlah story (mis. 3), fondasi bersama masuk ke story pertama dan ditandai `(+ fondasi)`.
- Story > 3 hari → pertimbangkan pecah. Estimasi dalam hari untuk 1 dev; total di cover & ringkasan epic.
- User story: `**Sebagai** <role>, **saya ingin** <aksi>, **agar** <manfaat>.`
- Tasks dikelompokkan per area: `BE - Fondasi`, `BE - Provider`, `BE - API`, `BE - Runtime`, `FE`, `Ops`. Sebut file/kelas nyata dari Fact Sheet.

**Acceptance Criteria**
- 5–8 per story, Given/When/Then, terukur (kode status, waktu, nama event audit).
- Selalu ada AC "default/fallback tetap berjalan" dan AC "isolasi antar tenant/pengguna" jika relevan.

**Test case QA**
- ID `S<story>-<nn>`, langkah yang bisa diikuti orang non-dev, expected spesifik.
- Campuran: happy path, validasi, negatif (otorisasi/audience salah), isolasi, perubahan tanpa restart, kebersihan log, **regresi**.
- Prioritas: P1 = blocker rilis, P2 = wajib sebelum rollout berikutnya, P3 = nice to have.

**Edge cases** — sumber utamanya insight non-obvious dari Fase 1 + perilaku fallback + konkurensi + kegagalan eksternal (timeout, cache down, key hilang).

**Instruksi dev**
- DoR: baca bagian mana, branch naming, environment lokal, credential/test data dari Lead (bukan production), perintah setup (mis. generate key), file kunci, eskalasi ambiguitas.
- Lakukan/Hindari: turunan langsung dari insight non-obvious dan pola codebase.
- DoD: AC + test case lulus di staging, automated test, migration reversible, env example, changelog, cek log, demo.

**Kode di dokumen** — hanya pseudo-code/kontrak yang memperjelas keputusan. Baris ≤ ~90 karakter (dipecah manual). Tidak ada nilai rahasia; gunakan contoh dummy (`1234-abc.apps…`).

## Quality bar (cek sebelum render)

- [ ] Setiap klaim teknis di bagian 5–6 punya path file nyata.
- [ ] Setiap jawaban user ada di Decision log.
- [ ] Setiap insight non-obvious muncul minimal di satu tempat (callout/edge case/Lakukan-Hindari).
- [ ] Setiap story punya estimasi, dependensi, tasks, AC; setiap AC punya ≥1 test case.
- [ ] Kolom OUT memuat semua temuan di luar scope.
- [ ] Referensi silang benar (mis. "Bagian 6.8" memang kontrak API).
- [ ] Tidak ada secret, token, password, atau ID internal sensitif.
- [ ] Panjang wajar: fitur M ±10–15 halaman, L ±18–25 halaman (diagram makan ruang).

## Mode PRD/TDD terpisah (hanya jika user eksplisit minta)

| Dokumen | Ambil bagian | Catatan |
|---|---|---|
| PRD (untuk bisnis/stakeholder) | 1, 2, 3, 4 (hanya keputusan produk), 6.2, 6.3, 9 (user story & AC saja), 12 | Tanpa nama tabel/endpoint/file/kode |
| TDD (untuk engineer) | 4 (keputusan teknis), 5, 6, 7, 8, 9 (tasks), 10, 11, 12 | Merujuk PRD di cover |

Gunakan `docx-kit` yang sama, dua script/dua file output.
