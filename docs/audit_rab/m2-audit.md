# Spesifikasi & Desain Logika Audit RAB (M2)

> **Modul:** M2 - Audit RAB (Fitur Andalan)  
> **Owner:** Dev 2  
> **Status:** Siap Implementasi  
> **Acuan Regulasi & Dokumen:**  
> - PRD Arsitektur v1.2: Bagian 6.4, 6.5, 6.6, 9.2, 9.3, Task M2-02  
> - PRD Fitur Inti v1.2: Bagian 3.3, 3.4, Bagian 9 (Edge Cases), AC F2-1  
> - Invarian: *"Numbers by code, words by LLM"* (Prinsip D-002 / A11)

---

## 1. Ringkasan & Invarian Arsitektur

Logika audit anggaran pada Modul M2 bertanggung jawab membaca, memverifikasi, dan memberi label harga satuan serta menguji integritas total RAB secara **deterministik 100% menggunakan PHP murni**.

### Invarian Wajib:
1. **AI Tidak Menghitung Angka:** AI (`AiRabReader`) hanya bertugas membaca teks/tabel dan memetakan nama item ke kode acuan standar.
2. **PHP Domain Murni:** Seluruh label (`PriceLabel`), persentase deviasi (`over_pct`), selisih rupiah (`diff_rp`), dan cek total (`TotalChecker`) dihitung di lapisan `Domain/` tanpa memanggil Eloquent, Facade, DB, atau I/O.
3. **Presisi Finansial (Zero Float):** Rupiah disimpan sebagai `int` (bigint di database), volume disimpan sebagai string `numeric(14,3)`, dan semua operasi matematis dihitung menggunakan `BcMath\Number` (PHP 8.4) atau fungsi ekstensi `bcmath`.
4. **Bahasa Netral di Kode & UI:** Tidak menggunakan istilah penuduh ("korupsi", "mark-up", "curang"). Gunakan padanan resmi: `AboveStandard` (*"Di atas standar"*), `NeedsCheck` (*"Perlu dicek"*), dan `NoReference` (*"Tanpa acuan"*).

---

## 2. Enum: Status & Label

### 2.1 Enum `AuditStatus`
Lokasi file: `app/Modules/Audit/Enums/AuditStatus.php`

Mencatat tahapan siklus hidup dokumen audit dari pengunggahan hingga dipublikasikan ke publik.

```php
namespace App\Modules\Audit\Enums;

enum AuditStatus: string
{
    case Draft = 'draft';               // Draf baru dibuat / isi manual
    case Reading = 'reading';           // AI sedang memproses dokumen di queue (ReadRabJob)
    case NeedsReview = 'needs_review';  // Selesai baca, ada baris kuning (Perlu dicek)
    case Ready = 'ready';               // Bebas baris kuning, siap diterbitkan
    case Published = 'published';       // Sudah terbit menjadi proyek publik ber-QR
    case ReadFailed = 'read_failed';    // Gagal baca / timeout > 60 detik (bisa coba lagi / manual)
}
```

#### Diagram Transisi Status:
```
[Unggah PDF] ──▶ Reading ──┬──▶ NeedsReview (ada baris kuning) ──┐
                           │                                      │
                           ├──▶ Ready (0 baris kuning) ◀──────────┘ (setelah verifikasi)
                           │       │
                           │       └──▶ [Terbitkan] ──▶ Published
                           │
                           └──▶ ReadFailed (timeout/error) ──▶ [Isi Manual / Coba Lagi]
```

### 2.2 Enum `PriceLabel`
Lokasi file: `app/Modules/Audit/Enums/PriceLabel.php`

Label komparasi harga satuan item RAB terhadap standar harga resmi (Kabupaten Gianyar).

```php
namespace App\Modules\Audit\Enums;

enum PriceLabel: string
{
    case WithinStandard = 'within_standard'; // Harga satuan <= standar (Hijau)
    case AboveStandard  = 'above_standard';  // Harga satuan > standar (Merah: +X%)
    case NoReference    = 'no_reference';    // Item tidak ada di daftar standar (Abu-abu)
    case NeedsCheck     = 'needs_check';     // Anomali: satuan beda / hitungan salah / AI ragu (Kuning)
}
```

---

## 3. Class: `PriceLabeler` (Logika Penentuan Label)

Lokasi file: `app/Modules/Audit/Domain/PriceLabeler.php`  
DTO Output: `app/Modules/Audit/Domain/LabelResult.php`

### 3.1 Kontrak DTO `LabelResult`
```php
namespace App\Modules\Audit\Domain;

use App\Modules\Audit\Enums\PriceLabel;

final readonly class LabelResult
{
    /**
     * @param list<string> $doubtFields
     */
    public function __construct(
        public PriceLabel $label,
        public ?int $overPct = null,       // Persen di atas standar (dibulatkan ke atas, min 1)
        public ?int $diffRp = null,        // (Harga RAB - Standar) * Volume (hanya jika AboveStandard)
        public array $doubtFields = [],    // Field yang diragukan
        public ?string $reason = null,     // Keterangan khusus (mis. 'satuan_berbeda', 'deviasi_perkalian')
    ) {}
}
```

### 3.2 Aturan Evaluasi (Rule Precedence)
Urutan evaluasi deterministik wajib mengikuti hierarki berikut:

1. **Kelengkapan Nilai Dasar:**
   - Jika `unitPriceRp === null` atau `volume === null` atau volume $\le 0$:  
     $\rightarrow$ `PriceLabel::NeedsCheck`, `reason = 'nilai_tidak_lengkap'`.
2. **AI Doubt Fields:**
   - Jika array `doubtFields` tidak kosong (AI menandai teks/angka buram atau ragu):  
     $\rightarrow$ `PriceLabel::NeedsCheck`, `reason = 'ai_ragu'`.
3. **Inkonsistensi Satuan:**
   - Jika item memiliki acuan standar, tetapi satuan RAB tidak sama dengan satuan standar (misal: *sak* vs *kg*):  
     $\rightarrow$ `PriceLabel::NeedsCheck`, `reason = 'satuan_berbeda'`. *(Tidak ada konversi otomatis di MVP)*.
4. **Validasi Perhitungan Baris (Volume $\times$ Harga Satuan vs Jumlah):**
   - Toleransi diambil dari konfigurasi `config('sonar.price.row_tolerance_rp', 1000)`.
   - Hitung deviasi: $\Delta = |(\text{volume} \times \text{unitPriceRp}) - \text{amountRp}|$.
   - Jika $\Delta > \text{rowToleranceRp}$:  
     $\rightarrow$ `PriceLabel::NeedsCheck`, `reason = 'deviasi_perkalian'`.
5. **Ketiadaan Acuan Standar:**
   - Jika item tidak memiliki acuan standar (`$standard === null`):  
     $\rightarrow$ `PriceLabel::NoReference` (*"Tanpa acuan"* - tidak pernah hijau, tidak dihitung selisihnya).
6. **Harga Sesuai Standar ($\le$ Standar):**
   - Jika `unitPriceRp <= standardPriceRp`:  
     $\rightarrow$ `PriceLabel::WithinStandard`, `overPct = null`, `diffRp = null`.
7. **Harga di Atas Standar ($>$ Standar):**
   - Jika `unitPriceRp > standardPriceRp`:  
     $\rightarrow$ `PriceLabel::AboveStandard`.
     - **Rumus Persen:** $\text{overPct} = \lceil \frac{\text{unitPriceRp} - \text{standardPriceRp}}{\text{standardPriceRp}} \times 100 \rceil$.  
       *(Wajib pembulatan ke atas / ceil; selisih +0.3% dibulatkan menjadi +1%, dilarang menghasilkan +0%)*.
     - **Rumus Selisih Rupiah:** $\text{diffRp} = (\text{unitPriceRp} - \text{standardPriceRp}) \times \text{volume}$.  
       *(Dihitung dengan BcMath integer rupiah)*.

---

## 4. Class: `TotalChecker` (Cek Konsistensi Total)

Lokasi file: `app/Modules/Audit/Domain/TotalChecker.php`  
DTO Output: `app/Modules/Audit/Domain/TotalCheckResult.php`

### 4.1 Kontrak DTO `TotalCheckResult`
```php
namespace App\Modules\Audit\Domain;

final readonly class TotalCheckResult
{
    public function __construct(
        public int $calculatedTotalRp,     // Jumlah seluruh amount_rp baris item
        public ?int $writtenTotalRp,       // Total yang tertulis di lembar RAB
        public bool $isMatch,              // Apakah cocok (selisih <= toleransi)
        public int $diffRp,                // Selisih mutlak
    ) {}
}
```

### 4.2 Aturan Bisnis TotalChecker
- Menjumlahkan seluruh `amount_rp` dari baris-baris `AuditItem`.
- Membandingkan dengan `written_total_rp` yang diekstrak AI dari dokumen asli.
- Jika `writtenTotalRp` kosong / null: `isMatch = true` (tidak ada pembanding tertulis). **⚠ Celah: lihat usulan 7.1.**
- Jika `calculatedTotalRp !== writtenTotalRp`:
  - `isMatch = false`.
  - Di UI audit muncul pesan peringatan: *"Ada baris yang mungkin terlewat atau terbaca dua kali"*.
  - Status tidak langsung gagal, namun tombol **Terbitkan** mewajibkan konfirmasi centang manual (`total_ack = true`).

---

## 5. Rencana Pengujian: Pest Unit Test

Lokasi:
- `tests/Unit/Audit/PriceLabelerTest.php`
- `tests/Unit/Audit/TotalCheckerTest.php`

### 5.1 Matriks Skenario Test `PriceLabelerTest` (AC F2-1)
1. **Sesuai (WithinStandard):**
   - Harga RAB lebih murah dari standar (misal: Rp60.000 vs Rp65.000).
   - Harga RAB sama persis dengan standar (misal: Rp65.000 vs Rp65.000).
2. **Di Atas Standar (AboveStandard):**
   - Harga RAB di atas standar (misal: Rp85.000 vs Rp65.000 $\rightarrow$ +31%, selisih $\text{Rp20.000} \times \text{vol}$).
   - **Edge Case Pembulatan Ceil:** Deviasi tipis Rp85.255 vs Rp85.000 (+0.3%) wajib menghasilkan `over_pct = 1` (bukan 0%).
3. **Tanpa Acuan (NoReference):**
   - Item tanpa referensi standar (`standard: null`) $\rightarrow$ label `no_reference`, selisih & over_pct `null`.
4. **Perlu Dicek (NeedsCheck):**
   - Satuan tidak cocok (RAB: `sak`, Standar: `kg`).
   - Perkalian baris tidak akurat: `volume = 10`, `unit_price = 10.000`, `amount = 150.000` (selisih > toleransi Rp1.000).
   - Perkalian baris masih dalam batas toleransi (selisih Rp500 $\le$ Rp1.000) $\rightarrow$ tetap lolos ke komparasi harga.
   - AI menyertakan `doubt_fields: ['volume']`.
   - Data angka tidak lengkap / null.

### 5.2 Matriks Skenario Test `TotalCheckerTest`
1. Cocok persis: total kalkulasi sama dengan total tertulis $\rightarrow$ `isMatch = true`.
2. Total tertulis null $\rightarrow$ `isMatch = true`. *(Berubah bila usulan 7.1 disetujui.)*
3. Selisih total: total kalkulasi Rp105.000.000 vs tertulis Rp100.000.000 $\rightarrow$ `isMatch = false`, `diffRp = 5.000.000`.

---

## 6. Verifikasi & Definition of Done

Jalankan perintah berikut melalui Docker dev container:
```bash
# 1. Jalankan unit test Domain Audit
docker compose exec app php artisan test --filter=PriceLabelerTest
docker compose exec app php artisan test --filter=TotalCheckerTest

# 2. Pastikan linting dan analisis statis bersih (Larastan level 6)
make lint
```

---

## 7. Celah Deteksi Baris Terlewat & Usulan (menunggu rapat 22 Nov)

> **Status:** usulan, belum disetujui. Aturan di bagian 3-5 tetap berlaku sampai ada keputusan.
> Ketiga usulan menambah field pada `RabReadRow` / `RabReadResult` (kontrak PRD Arsitektur 6.5), jadi bila
> disetujui wajib dicatat sebagai entri `D-xxx` di `docs/DECISIONS.md`, prompt naik ke `RAB_PROMPT_V2`, dan
> fixture `database/data/rab-fixtures/*.json` ikut diperbarui.

### Latar belakang
Satu-satunya penjaga "baris terlewat atau terbaca dua kali" saat ini adalah `TotalChecker`: jumlah semua baris
dibandingkan dengan total akhir yang tertulis di RAB (F7, A8). Cek per baris (volume × harga = jumlah) menangkap
**salah baca angka**, tetapi tidak bisa menangkap **baris yang tidak terbaca sama sekali**, karena baris itu tidak
pernah ada di tabel. Penanda ragu dari AI (`doubtFields`) juga tidak bisa diandalkan untuk ini: AI tidak tahu
baris mana yang ia lewatkan.

### 7.1 Total tertulis tidak terbaca dianggap cocok (prioritas tinggi)

**Celah.** Aturan 4.2: `writtenTotalRp === null` → `isMatch = true`. Bila AI gagal membaca baris "Jumlah Total"
(terpotong di tepi halaman, foto miring, total ada di halaman terpisah), cek baris terlewat **mati tanpa tanda**.
Desa melihat tidak ada peringatan dan menganggap tabel lengkap.

**Contoh.** RAB 15 baris, AI membaca 14 baris dan tidak menemukan total. Hasil: tidak ada baris kuning, tidak ada
peringatan total, proyek bisa terbit dengan satu baris anggaran hilang dari halaman publik.

**Usulan.**
- `TotalCheckResult::$isMatch` (bool) diganti enum `TotalCheckStatus`:
  - `Match`: selisih ≤ toleransi.
  - `Mismatch`: selisih > toleransi → peringatan "Ada baris yang mungkin terlewat atau terbaca dua kali" (sama
    seperti sekarang).
  - `Missing`: total tertulis tidak terbaca → peringatan baru *"Total di RAB tidak terbaca. Ketik total dari
    dokumen asli."*
- Desa mengetik total dari dokumen asli di layar audit (`written_total_rp` bisa diisi/diubah manual, tercatat
  sebagai koreksi). Setelah diisi, cek dijalankan ulang dan statusnya menjadi `Match` atau `Mismatch`.
- Syarat terbit: status `Missing` **mengunci Terbitkan** (seperti baris kuning). `Mismatch` tetap boleh terbit
  dengan centang `total_ack` (aturan A8 tidak berubah).

**Pilihan untuk rapat.** Bila ada RAB desa yang memang tidak mencantumkan total, tambahkan centang kedua
"RAB ini tidak mencantumkan total" sebagai pengganti mengetik total. Rekomendasi: **jangan**, karena RAB resmi
selalu mencantumkan jumlah total, dan centang ini membuka jalan pintas yang sama dengan celah aslinya.

**Catatan terkait.** Teks 4.2 menulis `calculatedTotalRp !== writtenTotalRp` (persis), sedangkan DTO 4.1 menulis
"selisih ≤ toleransi". Perlu diputuskan sekaligus: memakai `config('sonar.price.row_tolerance_rp')` atau toleransi
total tersendiri (`sonar.price.total_tolerance_rp`), karena pembulatan per baris bisa menumpuk di total.

**Test tambahan (`TotalCheckerTest`).**
- Total tertulis null → `Missing` (menggantikan skenario 5.2 nomor 2).
- Total diketik manual cocok → `Match`; tidak cocok → `Mismatch`.
- Selisih tepat di batas toleransi → `Match`; lewat 1 rupiah → `Mismatch`.

### 7.2 Hanya total akhir yang dicek

**Celah.** RAB desa umumnya dikelompokkan (contoh di seed: Persiapan & Tanah, Bahan, Upah & Alat) dan setiap
kelompok punya subtotal. Bila hanya total akhir yang dibandingkan:
- Peringatan `Mismatch` tidak menunjuk letak masalah; desa harus mencocokkan semua baris satu per satu.
- Dua kesalahan di kelompok berbeda bisa saling menutupi (satu baris terlewat di Bahan, satu baris terbaca dua
  kali di Upah dengan nilai mirip).

**Usulan.**
- `RabReadRow` mendapat `?string $group` (nama kelompok seperti tertulis), dan `RabReadResult` mendapat
  `list<WrittenSubtotal> $writtenSubtotals` (`group`, `amountRp`).
- `TotalChecker` juga membandingkan jumlah baris per kelompok dengan subtotal tertulisnya, dan mengembalikan
  daftar kelompok yang tidak cocok.
- UI audit: *"Kelompok Bahan: jumlah baris Rp12.450.000, tertulis Rp13.200.000"*, dengan tautan lompat ke
  kelompok itu dan dokumen asli di sampingnya.
- Kelompok tanpa subtotal tertulis dilewati (tidak ada peringatan palsu). Syarat terbit tidak bertambah:
  ketidakcocokan subtotal hanya memperjelas letak `Mismatch` pada total akhir.

**Manfaat sampingan.** Edge case PRD Fitur Inti 9 ("baris Sub total terbaca sebagai item") lebih mudah
ditangkap: baris yang jumlahnya sama dengan jumlah baris-baris sebelumnya di kelompok yang sama bisa diberi
`NeedsCheck` dengan `reason = 'kemungkinan_subtotal'`. Ini opsional dan bisa dipisah dari usulan 7.2.

**Test tambahan.**
- Subtotal cocok semua → tidak ada kelompok bermasalah.
- Satu baris hilang di satu kelompok → hanya kelompok itu yang dilaporkan.
- Kelompok tanpa subtotal tertulis → dilewati.
- Baris tanpa `group` (RAB tidak berkelompok) → hanya total akhir yang dicek (perilaku sekarang).

### 7.3 Nomor urut baris tidak dicek

**Celah.** Kolom "No." di RAB tidak dibaca, padahal itu penanda paling langsung untuk baris yang hilang. Total
dan subtotal hanya bisa bilang "ada yang kurang Rp750.000"; nomor urut bisa bilang "baris nomor 3 tidak ada".

**Usulan.**
- `RabReadRow` mendapat `?string $itemNumber`: teks asli kolom nomor ("1", "2", "A.1"), tidak dinormalisasi AI.
- Fungsi murni `Domain/NumberingChecker` (tanpa Eloquent/Facade), per kelompok:
  - Nomor bilangan bulat berurutan dengan lompatan → peringatan *"Nomor 3 tidak ada di tabel. Cek dokumen asli."*
  - Nomor yang muncul dua kali → kedua baris `NeedsCheck` dengan `reason = 'nomor_ganda'` (kemungkinan terbaca
    dua kali).
  - Penomoran campuran atau tidak berupa bilangan ("A", "A.1", "-", kosong) → kelompok itu dilewati. Lebih baik
    tidak memberi peringatan daripada peringatan palsu.
- Lompatan nomor diperlakukan seperti `Mismatch` total: peringatan + centang konfirmasi sebelum terbit, bukan
  kunci. Alasannya, RAB asli kadang memang melompati nomor (baris dihapus saat revisi musdes).

**Test tambahan (`NumberingCheckerTest`).**
- 1, 2, 3, 4 → tidak ada temuan.
- 1, 2, 4 → nomor 3 hilang.
- 1, 2, 2, 3 → nomor 2 ganda, dua baris ditandai.
- Penomoran per kelompok mulai lagi dari 1 → tidak dianggap ganda.
- "A.1", "A.2", "-" → dilewati tanpa temuan.

### Ringkasan keputusan yang diminta

| Usulan | Prioritas | Mengubah kontrak | Mengubah syarat terbit | Perkiraan |
| --- | --- | --- | --- | --- |
| 7.1 Total tidak terbaca = `Missing` | Tinggi (menutup celah diam-diam) | `TotalCheckResult` | Ya: `Missing` mengunci Terbitkan | S |
| 7.2 Cek subtotal per kelompok | Sedang | `RabReadRow.group`, `RabReadResult.writtenSubtotals` | Tidak | M |
| 7.3 Cek nomor urut | Sedang | `RabReadRow.itemNumber` | Ya: centang bila ada lompatan | M |

Rekomendasi: 7.1 masuk M2-02 sekarang. 7.2 dan 7.3 masuk bila waktu cukup setelah spike; field `group` dan
`itemNumber` sebaiknya tetap diminta di `RAB_PROMPT_V2` sejak awal, supaya hasil spike bisa menilai seberapa
akurat AI membacanya tanpa mengubah prompt lagi.
