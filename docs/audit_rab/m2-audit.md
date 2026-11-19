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
- Jika `writtenTotalRp` kosong / null: `isMatch = true` (tidak ada pembanding tertulis).
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
2. Total tertulis null $\rightarrow$ `isMatch = true`.
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
