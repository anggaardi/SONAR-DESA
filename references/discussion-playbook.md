# Fase 2 — Discussion Playbook

Tujuan fase ini: mengubah Fact Sheet menjadi **keputusan yang disepakati user**. Dokumen final tidak boleh ditulis sebelum user menjawab.

---

## 1. Struktur pesan (urutan tetap)

```markdown
<1 kalimat: analisis selesai, ada temuan yang memengaruhi desain>

## 1. Kondisi sekarang
<arsitektur relevan 1 paragraf>
| Area | Kondisi sekarang | Dampak untuk fitur |
**Insight penting:** <hal non-obvious yang mengubah desain>
**Temuan di luar scope:** <bernomor, singkat>

## 2. Rekomendasi teknis
### <Keputusan utama — jawab pertanyaan user secara langsung, mis. "Ya, simpan di DB central, tapi …">
| Opsi | Penilaian |   ← ✅ rekomendasi / ❌ ditolak, alasan 1 baris
### <Sketsa desain>  ← data model (code block), komponen/kontrak, alur runtime
### <UX>             ← sesuai konsep user, + saran perbaikan kecil

## 3. Pertanyaan sebelum breakdown
1. **<Topik>:** <pertanyaan>
   → *Rekomendasi: <default + alasan singkat>*
…
<penutup: setelah dijawab, saya susun Feature Spec lengkap berisi …>
```

## 2. Aturan rekomendasi

- **Jawab pertanyaan user lebih dulu.** Kalau user bertanya "apakah kita simpan di DB central?", kalimat pertama bagian rekomendasi harus menjawab itu.
- **Satu rekomendasi, tegas.** Opsi alternatif ditampilkan dalam tabel dengan alasan 1 baris — bukan esai.
- **Alasan konkret dari kode**, bukan "best practice" abstrak. Contoh baik: *"karena credential dibutuhkan di callback OAuth sebelum DB tenant aktif"*.
- **Sesuaikan skala tim.** Untuk 1 developer: pilih solusi yang bisa dikerjakan dan dirawat satu orang. Solusi enterprise (Vault, secrets manager, microservice) cukup disebut sebagai "Later" dengan jalur migrasi (mis. lewat interface).
- **Desain untuk perubahan berikutnya** tanpa over-engineering: registry/strategy pattern hanya kalau user memang menyebut "nanti ada credential/provider lain".
- Prinsip yang dipakai: `engineering-principles.md`.

## 3. Aturan pertanyaan

- **Maksimal ~8 pertanyaan**, urutkan dari yang paling memengaruhi arsitektur.
- **Setiap pertanyaan punya rekomendasi default** → user bisa jawab "default semua" atau hanya mengoreksi beberapa nomor.
- **Jangan tanya yang bisa dijawab kode.**
- Kategori pertanyaan yang hampir selalu perlu:
  | Kategori | Contoh |
  |---|---|
  | Perilaku saat kosong/gagal | Fallback ke default atau tolak? |
  | Kepemilikan & operasional | Siapa yang membuat/memegang akses (tim internal vs klien)? |
  | Variasi per tenant/domain | Per domain atau satu URL central? |
  | Batas scope platform | iOS ikut sekarang atau field opsional saja? |
  | Infrastruktur | Cukup DB terenkripsi atau sudah ada secrets manager? |
  | Otorisasi | Siapa yang boleh mengubah? Perlu approval? |
  | Temuan di luar scope | Masuk epic ini atau tiket terpisah? |
  | Artefak lama | Dokumen lama diganti/dihapus? |
- Bentuk pertanyaan bisa berupa daftar bernomor di chat. Gunakan tool pilihan-ganda hanya jika ≤4 pertanyaan dan opsinya benar-benar diskrit.

## 4. Setelah user menjawab

1. Petakan setiap jawaban menjadi baris **Decision log** (`D1…Dn`: keputusan + alasan).
2. Tambahkan keputusan desain milik AI yang sudah dijelaskan di rekomendasi (mis. "Web + Android = 1 provider", "jangan tambah kolom di tabel X") sebagai `D` berikutnya — supaya developer tahu itu keputusan, bukan kebetulan.
3. Jawaban "tiket terpisah" → masuk kolom **OUT** + callout "Jaga scope".
4. Jika jawaban user membuka ambiguitas baru yang **memblokir** desain → tanyakan singkat sekali lagi. Jika tidak memblokir → masukkan ke **Open questions** dengan owner & batas waktu, lalu lanjut ke Fase 3.
5. Jika user juga meminta format dokumen/struktur tambahan (instruksi dev, pembagian story, test case), catat sebagai kebutuhan output Fase 3.
