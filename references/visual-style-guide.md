# Visual Style Guide

Dua lapisan visual: **dokumen** (docx bersih, profesional) dan **diagram** (hand-drawn ala Excalidraw — terasa ringan dan "sketsa diskusi", bukan diagram korporat kaku). Kontrasnya disengaja: teks rapi, gambar hangat.

---

## 1. Dokumen (.docx)

Semua token ada di `scripts/lib/docx-kit.js` (`THEME`). Ubah di satu tempat itu kalau brand berubah.

| Token | Nilai | Dipakai untuk |
|---|---|---|
| Font teks | Segoe UI | Semua teks (Word Windows). Di Mac/LibreOffice akan disubstitusi otomatis |
| Font kode | Consolas | Inline code & code block |
| Ink | `#1E1E2E` | Teks utama |
| Muted | `#6C757D` | Caption, meta, footer |
| Accent | `#5B3FD1` (violet) | Nomor heading, garis H1, bullet, header tabel, checkbox |
| Accent light | `#EFEBFF` | Header tabel, callout note |
| Inline code | teks `#A61E4D` di atas `#F8F0FC` | Nama file, field, endpoint |

**Halaman:** A4, margin 0.75" (konten 9746 DXA ≈ 650 px). Header kanan: `Feature Spec · <Judul> · v<versi>`. Footer tengah: `Hal. X / Y`.

**Hierarki:**
- H1: angka violet + judul 17 pt bold + garis bawah violet tebal.
- H2: `n.m` violet + judul 12.5 pt bold.
- H3: 11 pt bold (judul kecil di dalam bagian, mis. "Tasks", "Acceptance Criteria").
- Body 10 pt, tabel 9 pt, caption 8.5 pt italic.

**Komponen & kapan dipakai:**

| Komponen | Tone | Kapan |
|---|---|---|
| `Callout('note')` | violet | TL;DR, pesan pembuka instruksi dev |
| `Callout('info')` | biru | Catatan implementasi, persiapan QA |
| `Callout('warn')` | oranye | Jaga scope, peringatan proses |
| `Callout('danger')` | merah | Insight kritis yang bisa membuat fitur gagal diam-diam |
| `Callout('gray')` | abu | Info administratif (dokumen yang digantikan) |
| `storyHeader` | biru/hijau/oranye per story | Kartu pembuka setiap story (garis atas tebal + estimasi + dependensi) |
| `Tbl` | header violet muda, zebra | Semua data terstruktur |
| `Checks` | ☐ violet | DoR, DoD, checklist operasional |
| `Code` | abu muda, Consolas 7.5 pt | Pseudo-code, struktur data, contoh JSON |

**Aturan layout:**
- Tabel selalu lebar penuh, `columnWidths` proporsional, baris `cantSplit`.
- Story ke-2 dst dan bagian QA mulai di halaman baru.
- Line spacing selalu `lineRule: auto` (tanpa ini LibreOffice/Word bisa memperlakukannya *exact* → gambar & judul terpotong). `docx-kit` sudah menangani.
- Markup inline: `**bold**`, `` `code` ``, dan `` **`bold code`** `` didukung. Hindari `*` di dalam backtick (gunakan kata, mis. "di bawah `/extranet`").

## 2. Diagram (Excalidraw style)

Engine: `scripts/lib/sketch.js` — roughjs (garis goyang + arsiran hachure) + font **Virgil** (font asli Excalidraw) → PNG 2× via resvg.

**Palet (fill pastel Excalidraw):**

| Nama | Hex | Makna konsisten |
|---|---|---|
| `blue` | `#a5d8ff` | Data/penyimpanan, story 1, hal "milik tenant" |
| `green` | `#b2f2bb` | Logika inti/resolver, status sukses, story 2 |
| `yellow` | `#ffec99` | Default/fallback, keputusan (diamond), catatan |
| `red` | `#ffc9c9` | Masalah, gagal, out of scope, cache |
| `violet` | `#d0bfff` | UI/admin, API, epic |
| `orange` | `#ffd8a8` | Perubahan penting, registry, story 3 |
| `gray` | `#e9ecef` | Existing/netral, langkah biasa |

**Diagram mana untuk apa:**

| Diagram | Bagian | Wajib? | Inti yang harus terlihat |
|---|---|---|---|
| As-Is vs To-Be | 1 | Ya (M/L) | Masalah sekarang (kotak merah) vs target + fallback |
| Arsitektur komponen | 6.1 | Ya | Komponen baru, penyimpanan, cache, fallback, *semua* pemakai runtime |
| Flowchart pengguna | 6.2 | Ya jika ada UI | Happy path + cabang validasi gagal + aksi lain (nonaktif, audit) |
| Wireframe | 6.3 | Ya jika ada UI baru | Layout list + detail, badge status, field rahasia ter-mask, tombol aksi |
| ERD | 6.4 | Ya jika ada tabel baru | Tabel existing (abu) vs BARU (biru), PK/FK, unique, catatan |
| Sequence per story | 6.7 | Ya untuk alur runtime yang berubah | Langkah bernomor; **highlight kuning** = berubah, **oranye** = perubahan urutan penting; note out-of-scope merah |
| Epic + timeline | 9 | Ya | Epic → story (estimasi), dependensi putus-putus, gantt per hari + slot QA |

**Aturan menggambar:**
- Gambar **mekanisme nyata** (nama kelas/endpoint/tabel sesungguhnya), bukan kotak generik.
- Teks hanya ASCII (font Virgil tidak punya glyph panah/✓) — tulis panah sebagai `->`.
- Label panah diberi latar putih (otomatis) dan diletakkan di segmen yang lowong (`seg`, `dx`, `dy`).
- Ukuran kanvas lebar 1200–1440 px; dirender 2×; di dokumen lebar 600–648 px.
- Satu diagram = satu pesan. Kalau perlu >15 kotak, pecah.
- Setelah render, **buka PNG-nya** dan periksa: label bertumpuk, teks keluar kotak, kotak terpotong tepi kanvas, catatan sequence terpotong di bawah.
