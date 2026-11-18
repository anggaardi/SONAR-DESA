# Dokumen Strategis & Spesifikasi PRD v1.2: Sonar Desa
**Adendum Respon Intake Juri, Reposisi Pitch, Mitigasi Risiko, & Kepatuhan Regulasi**

---

| Metadata Dokumen | Detail |
|---|---|
| **Proyek** | Sonar Desa (Techcomfest 2027) |
| **Versi Dokumen** | 1.2 (Adendum Strategis & Spesifikasi Fitur) |
| **Status** | Disetujui (Baseline Implementasi & Pitch Deck) |
| **Tanggal Terbit** | 18 November 2026 |
| **Owner** | Tim Pengembang Sonar Desa (Dev 1 & Dev 2) |
| **Dokumen Acuan** | PRD v1.0, PRD Fitur v1.1, Feature Spec v1.0 |

---

## 1. Executive Summary & Reposisi Nilai Produk (Pitch Pivot)

### 1.1 Reposisi Nilai: Berhenti Menyebut "Pendeteksi Mark-up"
Berdasarkan data penindakan korupsi nasional oleh Indonesia Corruption Watch (ICW) tahun 2024 (total 364 kasus):
* **Penyalahgunaan Anggaran:** 187 kasus (51,4%) — *Modus Terbesar*
* **Proyek Fiktif:** 42 kasus (11,5%) — *Modus Kedua Terbesar*
* **Mark-up Harga:** 33 kasus (9,1%) — *Modus Ketiga*

Data ini membuktikan bahwa *mark-up* harga satuan bukanlah modus tunggal atau dominan. Menjual Sonar Desa semata-mata sebagai "AI pendeteksi mark-up" membuat produk rentan diserang juri dari sudut pandang validitas modus dan kelengkapan SPJ/kuitansi realisasi.

```
SEBELUMNYA (Pitch v1.0):
"Sonar Desa adalah platform AI pendeteksi mark-up anggaran RAB proyek desa."

SEKARANG (Pitch v1.2 - Defensible Positioning):
"Sonar Desa membuka janji tertulis (RAB) ke hadapan warga, dan memberi warga instrumen terukur untuk membuktikan janji fisiknya langsung di lokasi proyek."
```

### 1.2 Batasan Lingkup Realisasi/SPJ (Disclosed Openly)
Sonar Desa secara eksplisit membatasi lingkup pada tahap **RAB (Perencanaan/Janji Anggaran) dan Kondisi Fisik Lapangan**. Tahap kuitansi/SPJ (Surat Pertanggungjawaban) tidak dicakup di MVP karena dokumen SPJ bersifat tertutup, muncul pasca-proyek selesai, dan rawan manipulasi administratif internal. Batasan ini dicantumkan secara jujur dan terbuka di PRD Fitur 1.2.

---

## 2. Fondasi Hukum Keterbukaan & Mekanisme Adopsi Desa

### 2.1 Dasar Hukum Kewajiban Keterbukaan Informasi Anggaran Desa
Pemerintah Desa tidak dapat mengelak dari kewajiban transparansi anggaran berdasarkan instrumen hukum positif:
1. **UU No. 6 Tahun 2014 tentang Desa:**
   * **Pasal 68 ayat (1) huruf a:** Masyarakat desa berhak meminta dan mendapatkan informasi dari Pemerintah Desa serta mengawasi kegiatan penyelenggaraan Pemerintahan Desa, pelaksanaan Pembangunan Desa, pembinaan kemasyarakatan Desa, dan pemberdayaan masyarakat Desa.
   * **Pasal 82 ayat (1) & (2):** Masyarakat Desa berhak mendapatkan informasi mengenai rencana dan pelaksanaan Pembangunan Desa. Pemerintah Desa wajib menginformasikan perencanaan dan pelaksanaan RPJMDes, RKPDes, dan APBDes kepada masyarakat Desa melalui layanan informasi kepada umum dan melaporkannya dalam Musyawarah Desa.
2. **UU No. 14 Tahun 2008 tentang Keterbukaan Informasi Publik (KIP):**
   * Pemerintah Desa berkategori sebagai **Badan Publik**, sehingga dokumen anggaran publik (termasuk rincian APBDes dan RAB pembangunan fisik) berstatus terbuka untuk publik.
3. **Indikator Desa Antikorupsi KPK RI:**
   * KPK menetapkan pemenuhan media informasi APBDes yang transparan dan dapat diakses publik secara berkala sebagai salah satu indikator inti penilaian Desa Antikorupsi.

### 2.2 Jalur Adopsi MVP vs Roadmap Jangka Panjang
* **Kenyataan di Lapangan:** Sistem perangkat lunak tidak memiliki kekuatan eksekutif untuk memaksa kepala desa mengunggah RAB secara sukarela.
* **Jalur Penetrasi BPD (Badan Permusyawaratan Desa):** BPD memegang salinan dokumen hasil Musrenbangdes/Musdes. Melalui fitur Audit Mandiri BPD (D12), BPD dapat mengaudit dokumen secara privat.
* **Fitur Baru v1.2 (BPD Publisher Rights):** Untuk mengatasi bias seleksi (desa bermasalah menolak unggah), BPD diberikan wewenang menerbitkan data proyek dari dokumen musdes dengan label disclaimer publik:
  > **"Diterbitkan oleh BPD berdasarkan Dokumen Musyawarah Desa (Bukan Diterbitkan oleh Pemerintah Desa)"**
* **Jawaban Jujur untuk Dewan Juri:**
  Pada fase MVP, adopsi bergantung pada inisiatif pengawasan BPD dan kemauan desa percontohan. Mandat kewajiban regulasi tingkat atas (instruksi Bupati/Inspektorat Daerah/Kejaksaan) ditempatkan pada fase **LATER**.

---

## 3. Matriks Komparasi: Sonar Desa vs Jaga Desa (Kejaksaan RI)

### 3.1 Profil Jaga Desa
* **Dasar Hukum & Inisiator:** Program nasional Kejaksaan Republik Indonesia berdasarkan **Instruksi Jaksa Agung (INSJA) No. 5 Tahun 2023** tentang Optimalisasi Peran Kejaksaan RI dalam Membangun Kesadaran Hukum Masyarakat Desa Melalui Program Jaga Desa.
* **Implementasi Lokal:** Diluncurkan oleh Kejari Gianyar pada 5 Februari 2025; disosialisasikan di berbagai daerah (mis. Kejari Bondowoso).
* **Karakteristik Akses:** Berfungsi sebagai sistem asistensi hukum dan pengawalan tertutup antara Kejaksaan dan Kepala Desa (bahkan antar-kades pun tidak saling melihat data). **Warga desa tidak memiliki akses untuk melihat rincian RAB per item apalagi melakukan verifikasi fisik.**

### 3.2 Tabel Perbandingan Fitur

| Dimensi | Jaga Desa (Kejaksaan RI) | Sonar Desa |
|---|---|---|
| **Fokus Utama** | Pengawalan hukum & mitigasi pelanggaran administratif | Partisipasi publik & audit forensik fisik lapangan |
| **Aksesibilitas** | Privat (Aparat Penegak Hukum & Kepala Desa) | Publik (Terbuka untuk seluruh warga desa lewat QR) |
| **Granularitas Data** | Pelaporan umum / agregat proyek | Rincian per item material & batas ukuran fisik RAB |
| **Keterlibatan Warga** | Pasif / Tertutup | Aktif (Cek Spek: GPS $\le$ 300 m, Kamera Langsung) |
| **Output Pengawasan**| Pendampingan yuridis internal kejaksaan | Dashboard audit BPD dengan ambang eskalasi otomatis |

### 3.3 Kalimat Kunci Pitch (Elevator Pitch)
> *"Jaga Desa adalah instrumen pendampingan dan asistensi hukum Kejaksaan untuk aparatur desa; sedangkan Sonar Desa menaruh rincian RAB dan janji spesifikasi fisik tepat di hadapan warga di titik proyek, serta memberi warga alat ukur berbasis bukti untuk mengawasi realisasinya."*

### 3.4 Kebijakan Integrasi
Saat ini Jaga Desa tidak menyediakan API publik. Upaya integrasi resmi masuk dalam kategori **LATER**. Pada fase MVP, hasil audit dan rangkuman laporan warga dari Sonar Desa dapat diekspor oleh BPD sebagai berkas bukti resmi saat berkonsultasi ke Kejari/Jaga Desa.

---

## 4. Evaluasi Standar Acuan Harga & Penyesuaian Analisis

### 4.1 Catatan Kritis Terhadap Acuan Harga
Temuan juri terkait **Perbup Gianyar No. 15 Tahun 2022** (Standar Harga Satuan Pekerjaan untuk SKPD TA 2022) mengidentifikasi dua celah validitas:
1. Perbup tersebut ditujukan untuk SKPD (Dinas teknis), bukan standar harga khusus operasional desa.
2. Isinya merupakan Harga Satuan Pekerjaan (HSP, mis. Rp/m³ beton, Rp/m² rabat), bukan harga bahan material eceran (semen per sak, pasir per m³).
3. Acuan tahun 2022 telah usang untuk tahun anggaran 2026/2027.

### 4.2 Analisis Opsi Kebijakan Harga

| Opsi | Pendekatan | Kelebihan | Risiko & Konsekuensi |
|---|---|---|---|
| **Opsi A** | Bandingkan per Harga Satuan Pekerjaan (HSP: Rp/m³ rabat, Rp/m² jalan) | Struktur data lebih realistis terhadap kontrak fisik; item perbandingan lebih ringkas | Acuan 2022 berisiko basi (inflasi 4 tahun); deviasi persentase bisa distorsi jika acuan tidak diperbarui |
| **Opsi B** | Acuan Khusus Standar Satuan Harga (SSH) Desa Gianyar | Dasar hukum paling sahih dan relevan untuk entitas pemerintahan desa | Wajib diverifikasi ketersediaannya di JDIH Kabupaten Gianyar |
| **Opsi C** | Kabupaten Bali lain dengan Perbup mutakhir (mis. Tabanan No. 19/2025) | Data angka sangat mutakhir dan akurat | Mengharuskan perpindahan lokus demo desa (D3) keluar dari Gianyar |

### 4.3 Ketetapan Strategis
1. **Prioritas Acuan:** Menggunakan **Opsi B** bila dokumen SSH Desa Gianyar ditemukan di JDIH Gianyar. Jika tidak tersedia, gunakan **Opsi A** dengan penyesuaian indeks inflasi atau menggunakan versi pembaruan resmi.
2. **Koreksi Contoh Pitch:** Hapus narasi "harga semen wajar Rp65.000 vs Rp85.000". Ubah contoh menjadi perbandingan pekerjaan fisik:
   * **Contoh Lama:** *Semen Gresik 40kg: RAB Rp85.000 vs Acuan Rp65.000*
   * **Contoh Baru:** *Pekerjaan Rabat Beton Mutu K-125: RAB Rp820.000/m³ vs Standar Analisis Rp695.000/m³ (+18% di atas acuan)*.

---

## 5. Arsitektur Pencegahan Positif Palsu & Rekayasa Laporan

### 5.1 Integritas Ekstraksi AI & Review Manusia
* **AI Tidak Pernah Menerbitkan Dokumen:** Gemini 1.5 Flash hanya bertindak sebagai *data ingestion parser*. Seluruh draf audit berstatus `need_review` sampai diverifikasi manusia.
* **Gatekeeper Baris Kuning:** Publikasi diblokir selama masih ada baris berstatus kuning (*Perlu dicek*). Dokumen baru terbit setelah dikonfirmasi resmi oleh operator desa/BPD.
* **Tampilan Berdampingan (*Dual Viewer*):** File PDF/scan asli berstempel selalu ditampilkan di samping tabel ekstraksi untuk verifikasi instan oleh publik.
* **Rumus Deterministik:** Label *"Di atas acuan"* dihitung secara murni oleh kode matematika PHP, bukan asumsi bahasa model AI. Teks UI berlabel netral: *"Belum tentu salah (perlu klarifikasi volume/spesifikasi)"*.

### 5.2 Pertahanan Anti-Sybil & Pencegahan Rekayasa Warga
Untuk mencegah serangan rekayasa laporan (mis. 1 orang dengan 3 akun Google, atau orkestrasi politik lokal jelang Pilkades):
1. **Device Fingerprinting (1 Perangkat = 1 Pelapor):** Sistem men-generate hash identitas perangkat via browser client-side entropy (disimpan di backend sebagai `device_fingerprint_hash`). Satu perangkat fisik hanya dihitung sebagai 1 entitas pelapor dalam siklus aktif, meskipun menggunakan akun Google berbeda.
2. **Cluster Detection (Peringatan Laporan Berdekatan):** Sistem menyematkan flag penanda khusus di Dashboard BPD jika terdeteksi $\ge 2$ laporan yang dikirim dalam rentang waktu $\le 15$ menit dan radius $\le 20$ meter. BPD diberi visibilitas untuk memeriksa indikasi mobilisasi kelompok.
3. **Ambang Eskalasi Berlapis:** Status proyek berubah menjadi *"Dalam tinjauan BPD"* hanya bila memenuhi:
   * $\ge 3$ laporan dengan selisih ukuran fisik nyata.
   * Dikirim dari $\ge 3$ akun Google terverifikasi.
   * Berasal dari $\ge 3$ ID perangkat berbeda.
   * Koordinat GPS terverifikasi $\le 300$ meter dari titik proyek.
4. **Pernyataan Terbuka Risiko:** Sisa risiko persengkokolan offline (3 warga fisik berbeda dari kubu lawan bersekongkol) diakui secara terbuka sebagai batas teknologi, yang penyelesaian akhirnya berada pada wewenang musyawarah fisik BPD.

### 5.3 Kepatuhan UU No. 27 Tahun 2022 tentang Perlindungan Data Pribadi (UU PDP)
1. **Consent Screen Pra-Pelaporan:** Sebelum kamera dan GPS diaktifkan, layar menampilkan persetujuan eksplisit pemrosesan data verifikasi lokasi.
2. **Pembersihan EXIF Metadata:** Seluruh foto hasil jepretan kamera langsung dibersihkan metadata EXIF-nya (lokasi GPS perangkat, tipe ponsel, waktu pengambilan kamera) menggunakan PHP GD/Exif sanitization sebelum disimpan ke MinIO/S3.
3. **Kebijakan Retensi Koordinat Geospasial:**
   * Koordinat GPS mentah (*latitude, longitude*) pelapor hanya disimpan sementara selama siklus audit aktif.
   * Setelah BPD menutup siklus keputusan, koordinat mentah pelapor **dihapus permanen (PURGED)**; sistem hanya menyimpan data jarak agregat (`distance_meters: 42.5 m`) untuk keperluan audit kepatuhan.

### 5.4 Metodologi Pengukuran Fisik Lapangan
* **Waktu Pengukuran:** Papan proyek dan poster QR dipasang sejak pekerjaan dimulai hingga selesai. Warga dapat mengukur fisik pada tahap tertentu (mis. ketebalan rabat beton dapat diukur di batas bekisting tepi jalan setelah pengecoran).
* **Batasan Mutu Material:** Mutu beton (mis. K-175 / K-250) tidak dapat diuji kasat mata oleh warga dan berada di luar lingkup sistem (tertulis jelas di batasan produk).

---

## 6. Spesifikasi Fitur Baru (Delta PRD v1.2)

```
[PRD Fitur v1.1] + [Adendum v1.2] ---> [PRD Fitur v1.2 Final]
  ├── + F22: Opsi "Proyek Tidak Ditemukan di Lokasi" (Modus Fiktif)
  ├── + F23: Penerbitan Mandiri oleh BPD (Label Khusus Dokumen Musdes)
  ├── + F24: Anti-Sybil Device Fingerprinting (1 Perangkat 1 Pelapor)
  ├── + F25: Anomaly Cluster Detection (Alert 15 Menit / 20 Meter)
  ├── + F26: Privacy Engine & Coordinate Retention Policy (UU PDP)
  └── + F27: Mock Cross-Device & Final Mode (Simulasi Semarang)
```

### 6.1 F22: Deteksi Proyek Fiktif ("Proyek Tidak Ditemukan di Lokasi")
* **Latar Belakang:** Proyek fiktif adalah modus korupsi terbesar kedua di Indonesia (42 kasus per 2024). Warga yang berada di titik koordinat resmi proyek memiliki kapabilitas langsung membuktikan apakah ada pekerjaan fisik atau nihil.
* **Perilaku Sistem:** Pada checklist Cek Spek, ditambahkan opsi radio button:
  * `[ ] Ukuran fisik tidak sesuai spesifikasi`
  * `[ ] Proyek tidak ditemukan di lokasi / Nihil` *(Fitur Baru)*
* **Bukti Wajib:** Jika opsi proyek fiktif dipilih, warga wajib mengambil 2 foto panorama lokasi dari titik GPS valid dan memberikan keterangan singkat kondisi lapangan.

### 6.2 F23: BPD Direct Publisher Mode
* **Perilaku Sistem:** BPD dapat menekan tombol *"Terbitkan ke Publik (Jalur Musdes)"*.
* **Visual Label:** Proyek yang diterbitkan melalui jalur ini diberi badge khusus berwarna netral-biru di bagian header halaman publik:
  > *"Data proyek ini diterbitkan oleh BPD berdasarkan Berita Acara Musyawarah Desa TA 2026."*

### 6.3 F24 & F25: Anti-Sybil & Cluster Detection
* **Database Update:** Tabel `citizen_reports` ditambahkan kolom:
  * `device_hash` (string 64 char, SHA-256)
  * `is_clustered` (boolean, default false)
  * `cluster_group_id` (uuid, nullable)
* **Aturan Bisnis:** Laporan dengan `device_hash` yang sama dalam 1 siklus hanya memperbarui data sebelumnya (*replace*), tidak menambah hitungan laporan valid menuju ambang 3.

### 6.4 F26: UU PDP Data Retention Worker
* **Scheduler Task:** `php artisan privacy:purge-coordinates` dijalankan setiap malam. Memeriksa tabel `audits` dengan status `reviewed` / `archived` > 7 hari, lalu mengatur nilai `submit_lat` dan `submit_lng` pada `citizen_reports` menjadi `NULL`.

### 6.5 F27: Modus Demo Final Semarang (Offline Resilience)
* **Kebutuhan Evaluasi:** Menjamin presentasi babak final di Semarang tidak terkendala jaringan buruk:
  * Dataset Desa Contoh Gianyar pre-seeded.
  * Dokumen contoh RAB 1, 2, 3 pre-cached (ekstraksi instan 0 detik).
  * Opsi QR Cross-Device (juri scan QR dari layar laptop menggunakan HP masing-masing dan langsung masuk ke sandbox sesi demo juri).
  * Video demonstrasi cadangan (offline MP4) siap putar.

---

## 7. Sanitasi Klaim Data & Fact-Checking (Koreksi Narasi Pitch)

Demi menjaga integritas akademik dan reputasi tim di hadapan dewan juri, seluruh klaim data yang tidak memiliki rujukan data primer resmi **wajib disanitasi** dari dokumen dan bahan presentasi:

| Klaim Lama (Dilarang Digunakan) | Status | Koreksi Narasi Resmi (Gunakan Ini) |
|---|---|---|
| *"108 tersangka korupsi desa di 2024"* | ❌ Hapus (Belum ada rincian primer per sektor) | *"ICW mencatat 888 total tersangka korupsi nasional di 2024, di mana penyelewengan anggaran dan proyek fiktif mendominasi sektor pengadaan fisik."* |
| *"Korupsi desa konsisten tertinggi sejak 2015"* | ❌ Hapus (Klaim historis tanpa data runtun) | *"Sektor anggaran desa konsisten menjadi salah satu titik paling rentan penyelewengan anggaran dalam laporan pemantauan berkala ICW."* |
| *"Kerugian negara rata-rata Rp19,9 juta per desa"* | ❌ Hapus (Angka tidak terverifikasi) | Gunakan data agregat resmi ICW atau sebut rentang kasus nyata. |
| *"Penyimpangan semen Rp2 juta menjadi Rp4 juta"* | ⚠️ Ubah Atribusi | Sebutkan secara eksplisit sebagai: *"Contoh simulasi kasus penggelembungan anggaran dari materi edukasi KPK RI."* |

---

## 8. Evaluasi Integritas Tim & Kesiapan Rulebook

> [!CAUTION]
> **BLOCKER KRITIS PENDAFTARAN (Tenggat: 18 November 2026)**
> * Rulebook Techcomfest 2027 halaman 4 menyebut frasa: **"Ketiga anggota tim..."**.
> * Sementara dokumen internal Sonar Desa PRD v1.0 dan Feature Spec v1.0 menetapkan alokasi sumber daya **2 Developer (Dev 1 & Dev 2)**.
> * **Tindakan Wajib Hari Ini:**
>   1. Verifikasi aturan kepesertaan: Apakah jumlah anggota tim **wajib 3 orang** atau diperbolehkan $\le 3$ orang (maksimal 3)?
>   2. Jika rulebook mewajibkan persis 3 anggota, tim wajib mendaftarkan 1 anggota tambahan (mis. UI/UX Designer / QA) hari ini agar tidak gugur di tahap administrasi!

---

## 9. Matriks Indikator Keberhasilan & Target SDG

### 9.1 Indikator Evaluasi Lomba (Techcomfest 2027)
1. **Efisiensi Waktu Audit:** Waktu audit dokumen 40 baris manual (stopwatch: 45–60 menit) vs Sonar Desa ($\le 20$ detik background processing).
2. **Indeks Paham Spek Warga:** Persentase warga desa yang mengetahui harga acuan dan ukuran teknis meningkat dari <10% (papan proyek biasa) menjadi >80% setelah melihat halaman proyek publik Sonar Desa.
3. **Akurasi AI Parsing:** Akurasi ekstraksi Gemini 1.5 Flash mencapai $\ge 90\%$ baris terstruktur pada 3 sampel berkas uji.
4. **Verifikasi Pihak Ketiga:** 100% alur (Warga, BPD, Pemdes) berhasil diuji oleh minimal 2 penguji independen di luar tim tanpa bantuan teknis.

### 9.2 Keselarasan Target SDG (Sustainable Development Goals)
* **SDG 16.5:** Secara substansial mengurangi korupsi dan penyuapan dalam segala bentuknya di tingkat pemerintahan paling mendasar (Desa).
* **SDG 16.6:** Mengembangkan institusi yang efektif, akuntabel, dan transparan di semua tingkat (Pemerintah Desa dan BPD).
* **SDG 16.10:** Menjamin akses publik terhadap informasi dan melindungi kebebasan mendasar sesuai dengan peraturan perundang-undangan nasional.

---

## 10. Matriks Rencana Aksi (Action Items) & Deadlines

```
18 Nov (Hari Ini)          20 Nov                 22 Nov               Segera
     │                       │                      │                    │
     ▼                       ▼                      ▼                    ▼
[Rulebook & Gelombang I]  [Pilih Opsi Acuan]   [Rapat Evaluasi]    [Sanitasi Pitch]
- Cek syarat 3 orang     - Opsi A vs B vs C    - Survei 15 warga   - Hapus klaim 108
- Submit Gelombang I     - Unduh JDIH Gianyar  - Data PDF ICW      - Revisi contoh KPK
                                               - Hasil cek Jaga D.
```

| Tenggat Waktu | Rincian Tindakan | Penanggung Jawab | Status |
|---|---|---|---|
| **Hari Ini (18 Nov)** | Verifikasi syarat anggota tim di Rulebook (2 vs 3 orang) & submit pendaftaran Gelombang I | Dev 1 (Ketua Tim) | 🔴 Urgent Blocker |
| **20 Nov 2026** | Keputusan final standar acuan harga (Opsi A / B / C) berdasarkan penelusuran JDIH Gianyar | Dev 2 | 🟡 Scheduled |
| **22 Nov 2026** | Rapat pleno tim: Uji spike AI, data primer PDF ICW, komparasi fisik Jaga Desa Gianyar, hasil survei warga | Seluruh Tim | 🟡 Scheduled |
| **Segera** | Pembersihan klaim data usang pada slide pitch deck dan PRD; implementasi terminologi "Contoh KPK" | Dev 1 | 🟢 In Progress |

---

## 11. Daftar Referensi & Rujukan Hukum Primer

1. **Undang-Undang Republik Indonesia:**
   * [UU No. 6 Tahun 2014 tentang Desa (Pasal 68 & 82)](https://peraturan.bpk.go.id/Details/38580/uu-no-6-tahun-2014)
   * [UU No. 14 Tahun 2008 tentang Keterbukaan Informasi Publik (KIP)](https://peraturan.bpk.go.id/Details/39080/uu-no-14-tahun-2008)
   * [UU No. 27 Tahun 2022 tentang Perlindungan Data Pribadi (UU PDP)](https://peraturan.bpk.go.id/Details/229798/uu-no-27-tahun-2022)
2. **Regulasi Pengelolaan Keuangan Desa:**
   * [Permendagri No. 20 Tahun 2018 tentang Pengelolaan Keuangan Desa](https://peraturan.bpk.go.id/Download/131060/Permendagri%20Nomor%2020%20Tahun%202018.pdf)
3. **Kejaksaan Republik Indonesia & Jaga Desa:**
   * [Instruksi Jaksa Agung RI No. 5 Tahun 2023 tentang Program Jaga Desa](https://kejaksaan.go.id)
   * [Antara Bali: Kejari Gianyar Luncurkan Aplikasi Jaga Desa Kawal Dana Desa](https://bali.antaranews.com/berita/369373/kejari-gianyar-luncurkan-aplikasi-jaga-desa-kawal-dana-desa)
   * [Kejari Bondowoso: Sosialisasi Aplikasi Jaga Desa](https://kejari-bondowoso.kejaksaan.go.id/berita/detail/kejaksaan-negeri-bondowoso-gelar-sosialisasi-aplikasi-jaga-desa-2c5be)
4. **Data Statistik Korupsi & ICW:**
   * [Indonesia Corruption Watch (ICW): Laporan Tren Penindakan Korupsi 2024](https://antikorupsi.id/id/penindakan-korupsi-2024-merosot-tajam-rekor-terburuk-dalam-5-tahun-terakhir)
   * [GoodStats: Ragam Modus Korupsi di Indonesia 2024](https://data.goodstats.id/statistic/simak-ragam-modus-korupsi-di-indonesia-2024-eqesT)
   * [Liputan6: ICW Sebut Proyek Fiktif Mendominasi Modus Korupsi](https://www.liputan6.com/news/read/5599878/icw-sebut-proyek-fiktif-mendominasi-modus-korupsi-di-indonesia)
   * [Kompas: ICW 888 Orang Jadi Tersangka Korupsi 2024](https://nasional.kompas.com/read/2025/09/30/18430721/icw-888-orang-jadi-tersangka-kasus-korupsi-pada-2024)
5. **Indikator Desa Antikorupsi KPK:**
   * [Komisi Pemberantasan Korupsi (KPK): Buku Panduan Desa Antikorupsi](https://aclc.kpk.go.id)
