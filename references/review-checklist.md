# Fase 4 — Review Checklist & Handover

AI wajib menemukan masalah sendiri sebelum user melihat dokumen.

---

## 1. Loop verifikasi

```
build docx ─▶ validate ─▶ render PDF ─▶ contact sheet ─▶ LIHAT setiap halaman ─▶ perbaiki ─┐
     ▲                                                                                       │
     └───────────────────────────────────────────────────────────────────────────────────────┘
```

Perintah (detail di `scripts/README.md`):
```bash
node my-spec.js out.docx img/
./render_check.sh out.docx render/      # menghasilkan render/sheet1.png … (4 halaman per sheet)
```
Jika tidak ada renderer (LibreOffice), minimal: validasi struktur + cek setiap PNG diagram, dan beri tahu user bahwa render dokumen belum diverifikasi.

## 2. Checklist visual (per halaman)

| Cek | Gejala | Perbaikan umum |
|---|---|---|
| Gambar utuh | Hanya irisan tipis gambar terlihat | Paragraf gambar perlu `lineRule: auto` (sudah di `docx-kit`) |
| Cover 1 halaman | Daftar isi meluber ke hal. 2 | TOC 2 kolom, kurangi spasi atas |
| Judul tidak tumpang tindih | Eyebrow menimpa judul | Line spacing auto |
| Markup bersih | `**` atau backtick terlihat | Hindari `*` di dalam backtick; nested bold+code didukung |
| Kartu story utuh | Header story di dasar halaman, isinya di halaman berikut | `pageBreak()` sebelum story ke-2 dst |
| Callout utuh | Callout terbelah halaman | `pageBreak()` sebelum bagian, atau pindah urutan |
| Code block | Baris membungkus berantakan | Pecah baris ≤ ~90 karakter |
| Tabel | Kolom terlalu sempit untuk path | Atur proporsi `widths`; ukuran font 17 untuk tabel padat |
| Ruang kosong besar | Setengah halaman kosong sebelum diagram besar | Wajar karena `keepNext`; kalau mengganggu, kecilkan lebar figure |
| Diagram terbaca | Teks diagram terlalu kecil | Kurangi isi diagram atau tambah lebar figure (maks 648) |

## 3. Checklist isi

- [ ] Quality bar di `document-blueprint.md` terpenuhi.
- [ ] Angka konsisten: estimasi di cover = ringkasan epic = kartu story = timeline.
- [ ] Nomor bagian di "Cara membaca", "Instruksi dev", dan referensi silang cocok.
- [ ] Tidak ada nilai rahasia; contoh memakai dummy.
- [ ] Semua jawaban user tercermin; tidak ada keputusan baru yang belum dibahas (kalau ada → tandai di Open questions).

## 4. Format handover ke user

```markdown
Ditulis untuk: <audiens, mis. tim dev & QA yang mengerjakan epic ini, dengan Anda sebagai reviewer>.

Feature Spec sudah jadi: [docs/FeatureSpec-<Nama>.docx](docs/FeatureSpec-<Nama>.docx) — <n> halaman, sudah saya render dan cek per halaman.
<Tindakan file lain yang dilakukan, mis. dokumen lama dihapus.>

**Isi dokumen**
- <bagian → ringkasan 1 baris, kelompokkan>

<n> diagram gaya Excalidraw: <daftar singkat>.

**Yang perlu Anda cek saat review**
- <keputusan struktur yang AI ambil sendiri, mis. fondasi digabung ke Story 1>
- <kasus yang terlihat seperti bug tapi memang perilaku benar>
- <open questions>
- <catatan teknis file: font, ukuran>

<Pengingat penting di luar dokumen, mis. rotasi secret di tiket terpisah.>
```

## 5. Adjustment loop

- Terapkan revisi user langsung di script konten, render ulang, cek ulang halaman yang berubah, serahkan lagi dengan ringkasan perubahan (bukan seluruh handover).
- Revisi yang mengubah keputusan → update Decision log + bagian terdampak (edge case, story, test case).
- Simpan script konten & diagram di folder kerja agar revisi berikutnya cepat; jangan commit file sementara ke repo kecuali diminta.
