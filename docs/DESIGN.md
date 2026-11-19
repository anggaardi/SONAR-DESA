# DESIGN

Design system Sonar Desa. Dasar: INA Digital Design System (IDDS), mode terang, tema `inagov`.
Pola alur dipinjam dari GOV.UK, MYDS (Malaysia), dan SGDS (Singapura). Keputusan: D-006.

Urutan sumber kebenaran untuk tampilan:

| # | Sumber | Isi |
| :-: | :--- | :--- |
| 1 | `docs/DECISIONS.md` | Keputusan terbaru menang |
| 2 | `resources/css/app.css` (`@theme`) | Token yang benar-benar berlaku di kode |
| 3 | `resources/js/components/ui/*` | Cara token dipakai (varian resmi) |
| 4 | File ini | Aturan dan alasan |
| 5 | PRD Sonar Desa 6.1, PRD Fitur Inti 4.3 / 7.2 | Aturan UI produk |

Kalau ada konflik antara file ini dan kode, ikuti kode, lalu perbaiki salah satunya dan catat di DECISIONS.md.

## 1. Tema dan suasana

"Papan informasi publik yang humanis dan tepercaya": tenang, terang, ramah warga (*human-friendly*), dan berwibawa. Warga desa harus merasa sedang membaca dokumen publik yang transparan dan mengayomi; desa dan BPD merasa memakai alat kerja modern yang efisien.

- Kanvas putih/subtle bersih, hampir semua elemen netral. Warna hanya muncul untuk aksi utama dan status semantik.
- Satu warna brand (biru IDDS `#196bcd`) untuk aksi utama dan navigasi.
- **Spot Ilustrasi Humanis (IDDS)**: Mengadopsi prinsip pustaka ilustrasi resmi INA Digital Design System — gaya *flat line-art* membulat (*rounded corners*) dengan bentuk geometris ramah Indonesia dan warna semantik token (`primary-subtle`, `stroke-strong`, `surface-subtle`). Ilustrasi hadir pada titik sentuh bermakna (*purposeful*): Empty State, panduan Cek Spek, dan konfirmasi keamanan data warga.
- **Bukan hiasan acak**: Tetap dilarang menggunakan ilustrasi 3D generic, gradien neon/glow mengambang, glassmorphism, atau ornamen tanpa tujuan (prinsip antislop IDDS).

## 2. Warna

Semua kelas di bawah tersedia sebagai `bg-*`, `text-*`, `border-*`. Nilai dari IDDS, dicek kontrasnya
(WCAG 2.1 AA: teks 4,5:1, elemen non-teks 3:1).

### Netral

| Token | Hex | IDDS | Peran | Kontras |
| :--- | :--- | :--- | :--- | :--- |
| `surface` | `#ffffff` | background-primary | Kanvas halaman, kartu, input | - |
| `surface-subtle` | `#f8f8f7` | background-secondary | Latar blok sekunder, header tabel | - |
| `surface-muted` | `#f2f2f2` | background-tertiary | Hover baris, latar nonaktif | - |
| `content` | `#1f1f1f` | content-primary | Teks utama, ikon | 16,5:1 |
| `content-secondary` | `#525252` | content-secondary | Teks pendukung, label kolom | 7,8:1 |
| `content-tertiary` | `#737373` | neutral-500 | Metadata, helper text | 4,7:1 |
| `content-inverse` | `#ffffff` | - | Teks di atas `primary` / solid | - |
| `stroke` | `#e5e5e5` | stroke-primary | Divider, border kartu, garis tabel | - |
| `stroke-strong` | `#737373` | neutral-500 | Border input, checkbox | 4,7:1 |

`content-tertiary` sengaja tidak memakai `#a3a3a3` (IDDS content-tertiary) karena hanya 2,5:1.

### Brand

| Token | Hex | Peran | Kontras |
| :--- | :--- | :--- | :--- |
| `primary` | `#196bcd` | Tombol utama, tautan, outline fokus, navigasi aktif | 5,2:1 di putih |
| `primary-hover` | `#1557a8` | Hover/pressed tombol utama | 7,1:1 |
| `primary-subtle` | `#ecf3fe` | Latar item navigasi aktif, baris terpilih | - |

### Status

Pola tetap: **teks gelap di atas latar lembut + border**, ditambah ikon. Varian `*-solid` hanya untuk
titik indikator kecil, garis progres, atau ikon; tidak pernah untuk latar teks.

| Arti | Teks | Latar | Border | Solid | Kontras teks |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `positive` | `#1b561a` | `#edfded` | `#a6f0a5` | `#288034` | 8,3:1 |
| `negative` | `#a40f0a` | `#fdeded` | `#ffa0a0` | `#d50b0b` | 7,0:1 |
| `warning` | `#553b06` | `#fff9eb` | `#ffe58a` | `#ffcc14` | 9,9:1 |
| `info` | `#002a69` | `#ecf3fe` | `#84b4fb` | `#0049b8` | 12,3:1 |
| `demo` | `#3b1fc6` | `#e2ddfd` | - | - | 7,3:1 |

### Pemetaan ke istilah PRD

Satu arti = satu warna di seluruh aplikasi. Kunci kiri sama dengan `lib/labels.ts`.

| Kondisi | Warna | Sumber |
| :--- | :--- | :--- |
| `priceLabel.within_standard` "Sesuai" | positive | PRD Fitur 3.4 |
| `priceLabel.above_standard` "Di atas standar +X%" | negative | PRD Fitur 3.4 |
| `priceLabel.needs_check` "Perlu dicek" (baris kuning) | warning | PRD Fitur 3.4 |
| `priceLabel.no_reference` "Tanpa acuan" | netral (`surface-muted` + `content-secondary`) | PRD D8: tidak pernah hijau |
| `publicStatus.none` | tanpa lencana | PRD Fitur 4.3 |
| `publicStatus.in_review` "Dalam tinjauan BPD sejak ..." | warning | PRD Fitur 4.3 |
| `publicStatus.reviewed` "Ditinjau BPD: ..." | info | PRD Fitur 4.3 |
| Diterbitkan oleh BPD (jalur Musdes, F23) | info | Adendum 6.2 |
| `auditStatus.draft` / `reading` | netral | - |
| `auditStatus.needs_review` | warning | - |
| `auditStatus.ready` / `published` | positive | - |
| `auditStatus.read_failed` | negative | - |
| Jarak Cek Spek <= 300 m / > 300 m | positive / warning | PRD Fitur 5.3 |
| Banner "Data demo - desa & angka fiktif" | demo | PRD Fitur 7.2 |

Merah `negative` berarti "perlu perhatian", bukan tuduhan. Teksnya tetap netral (D9).

## 3. Tipografi

- Font: **Inter** (sama dengan IDDS, MYDS, SGDS), self-hosted lewat `@fontsource-variable/inter`
  (`'Inter Variable'`). Fallback `system-ui`.
- Weight: 400 body, 500 label dan tombol, 600 heading, 700 hanya angka besar ringkasan (total RAB).
- Angka uang, volume, persen: `tabular-nums`, rata kanan di tabel.
- Skala memakai kelas default Tailwind, yang sama dengan skala IDDS (`--ina-font-*`):

| Kelas | Ukuran / line-height | Pakai |
| :--- | :--- | :--- |
| `text-xs` | 12 / 16 | Hanya metadata minor (build, waktu relatif). Bukan untuk info penting |
| `text-sm` | 14 / 20 | Label form, helper, sel tabel desktop, lencana |
| `text-base` | 16 / 24 | Body default, input, tombol |
| `text-lg` | 18 / 28 | Body layar warga yang penting (ringkasan proyek) |
| `text-xl` | 20 / 28 | Judul kartu / section kecil |
| `text-2xl` | 24 / 32 | H1 layar warga (HP), H2 desktop |
| `text-3xl` | 30 / 36 | H1 desktop |
| `text-4xl` | 36 / 40 | Angka ringkasan (total anggaran) |

- Satu H1 per halaman, menjelaskan isi halaman.
- Kapitalisasi: kalimat biasa untuk semua teks, termasuk tombol ("Cek Spek di lokasi"). Tanpa huruf kapital
  semua untuk label panjang.
- Placeholder tidak pernah menggantikan label.

## 4. Spacing, layout, bentuk

- Spacing kelipatan 4 px, memakai skala Tailwind yang sama dengan IDDS `--ina-spacing-*`.
  Pilih dari: 1, 2, 3, 4, 6, 8, 12, 16 (4-64 px). Hindari nilai arbitrer (`p-[13px]`).
- Jarak antar elemen dalam grup `gap-2`-`gap-3`; antar grup `gap-4`-`gap-6`; antar section `gap-8`-`gap-12`.
- Breakpoint default Tailwind = IDDS (`sm` 640, `md` 768, `lg` 1024, `xl` 1280).
- Layar warga (`PublicLayout`): satu kolom, `max-w-md` (448 px), `px-4`. Dirancang dari 360 px.
- Layar desa/BPD (`AppLayout`): sidebar 256 px + konten `max-w-7xl`, `px-6`. Tetap bisa dibuka di HP.
- Radius (IDDS `--ina-radius-*`):

| Kelas | px | Pakai |
| :--- | :--- | :--- |
| `rounded-sm` | 4 | Checkbox, tag kecil |
| `rounded-md` | 6 | Tombol, input, select |
| `rounded-lg` | 8 | Kartu, dialog, alert, tabel |
| `rounded-full` | - | Lencana status, avatar, titik indikator |

  Tanpa `rounded-xl` ke atas.

## 5. Kedalaman

- Lapisan dibedakan oleh latar (`surface` -> `surface-subtle`) dan border `stroke`, bukan bayangan.
- `shadow-sm` hanya untuk elemen melayang: dropdown, toast, dialog. Kartu dan tabel tanpa shadow.
- Overlay dialog: `bg-content/50`.

## 6. Komponen

Dibuat sendiri di `components/ui` (A13). Daftar dan aturan pakai:

| Komponen | Aturan |
| :--- | :--- |
| `Button` | Varian `primary` (`bg-primary text-content-inverse`, hover `primary-hover`), `secondary` (`border-stroke-strong bg-surface text-content`), `ghost` (teks `primary`), `danger` (hanya aksi hapus). Tinggi 44 px (`h-11`) di layar warga, 40 px (`h-10`) di desktop. `rounded-md`, `font-medium`. **Satu `primary` per layar** (PRD 6.1). Status loading: label tetap + spinner, tombol `disabled`. |
| `Badge` | `rounded-full border px-2.5 py-0.5 text-sm font-medium`, warna dari tabel status. Selalu berisi teks; ikon boleh di depan. |
| `PriceLabel` | `Badge` khusus label harga. Selalu teks + warna + ikon (centang / panah atas / tanda tanya / garis). |
| `Alert` | Kotak `rounded-lg border` dengan latar `*-subtle`, ikon, judul `font-semibold`, isi `text-sm`. Untuk pesan status, kegagalan, dan info. Pesan selalu menyebut apa yang terjadi dan apa yang bisa dilakukan. |
| `Card` | `rounded-lg border border-stroke bg-surface p-4` (warga) / `p-6` (desktop). Tanpa shadow. Tanpa kartu di dalam kartu; pakai divider. |
| `SummaryList` | Pola GOV.UK summary list: daftar `dt`/`dd` dengan divider `stroke`. Untuk rincian proyek publik dan layar cek isian sebelum kirim. |
| `Table` | Header `bg-surface-subtle text-sm font-medium text-content-secondary`, baris dipisah `border-stroke`, hover `surface-muted`. Angka rata kanan `tabular-nums`. Baris "Perlu dicek" diberi `bg-warning-subtle` + `PriceLabel`. Di HP tabel berubah jadi daftar kartu, tanpa scroll horizontal halaman. |
| `Input` / `Select` / `Textarea` | Label `text-sm font-medium` di atas, `h-11 rounded-md border-stroke-strong px-3`, fokus outline `primary`. Error: border `negative-solid` + pesan `text-sm text-negative` di bawah, terhubung lewat `aria-describedby`. |
| `Radio` / `Checkbox` | Target sentuh minimal 44 px; seluruh baris pilihan bisa diklik. Dipakai untuk Sesuai / Tidak / Tidak bisa ukur. |
| `Dialog` | `<dialog>` native, `rounded-lg shadow-sm`, judul + aksi di bawah, fokus terkunci. |
| `Stepper` | Untuk alur Cek Spek: "Langkah 2 dari 5" + judul langkah. Satu hal per layar (GOV.UK). |
| `EmptyState` | Komponen human-friendly dengan spot ilustrasi IDDS (line-art SVG: plang desa, peta arah arsip, pencarian kosong, segel sukses), judul bersahabat, deskripsi membimbing, dan tombol aksi. |
| `DemoBanner` | Bar penuh `bg-demo-subtle text-demo` di atas semua layar demo, dengan "Ganti peran" dan "Mulai ulang demo". |

Ikon: SVG garis 1,5-2 px, `currentColor`, ukuran 16/20/24. Ikon selalu punya teks di sebelahnya atau
`aria-label`. Ikon tidak boleh hanya hiasan.

## 7. Bahasa dan teks

- Semua teks UI dari `lib/labels.ts`, sama persis dengan PRD Fitur Inti 8.2.
- Netral (D9). Kata yang dilarang ada di ENGINEERING.md 2.
- Kalimat pendek, kata kerja aktif, sapaan "kamu" untuk warga sesuai teks PRD ("Laporanmu masuk ke BPD").
- Pesan error menyebut penyebab dan langkah berikutnya: "Sinyal lokasi lemah, tunggu sebentar lalu coba lagi",
  bukan "Terjadi kesalahan".
- Rupiah, tanggal, persen hanya lewat `lib/format.ts` (Rp85.000, Rp4,2 juta, WITA).

## 8. Responsif dan aksesibilitas

- Mobile first. Layar warga dicek di 360 px dan 1440 px; tanpa scroll horizontal.
- Target sentuh minimal 44 x 44 px.
- Fokus terlihat: global `:focus-visible` outline 2 px `primary`. Jangan dimatikan.
- Status tidak pernah hanya warna: selalu teks, sebaiknya juga ikon (PRD 6.1).
- Elemen semantik dulu (`button`, `a`, `table`, `dialog`, `nav`, `main`), ARIA hanya untuk mengisi celah.
- Animasi hanya transisi 150-300 ms (`transition-colors`), hormati `prefers-reduced-motion`.

## 9. Lakukan dan jangan

Lakukan:
- Pakai token semantik (`bg-surface`, `text-content-secondary`, `border-stroke`). Palet default Tailwind
  (`bg-slate-50`, `text-indigo-600`) sudah di-reset dan tidak menghasilkan CSS. Yang tersisa hanya
  `black`/`white` (lightbox foto) dan `transparent`/`current`.
- Satu tombol utama per layar; aksi lain `secondary` atau `ghost`.
- Sebut sumber angka di dekat angka ("Standar harga Kab. Gianyar 2026", "Dibaca otomatis, diperiksa Pemerintah Desa").

Jangan:
- Gradien, glassmorphism, `shadow-md` ke atas, `rounded-xl` ke atas.
- Hex mentah di class (`bg-[#...]`).
- Warna status untuk hiasan, atau hijau untuk proyek tanpa laporan.
- Lebih dari 4 warna status dalam satu layar.
- Teks penting di `text-xs` atau warna `content-tertiary`.
- Informasi penting hanya di tooltip.
- Emoji di UI.

## 10. Panduan prompt untuk AI agent

Referensi cepat: kanvas `surface` #ffffff, teks `content` #1f1f1f, sekunder `content-secondary` #525252,
border `stroke` #e5e5e5, aksi `primary` #196bcd, status positive/negative/warning/info (teks gelap di atas latar
lembut), font Inter, radius 6 px tombol/input dan 8 px kartu, tanpa shadow.

Contoh prompt:

```txt
Buat halaman proyek publik warga (PublicLayout, 360 px) sesuai docs/DESIGN.md.
Kartu ringkasan: nama proyek text-2xl font-semibold, total anggaran text-4xl font-bold tabular-nums,
SummaryList untuk lokasi, jenis, dan ukuran di RAB. Daftar item sebagai kartu dengan PriceLabel.
Satu tombol primary "Cek Spek di lokasi" di bawah. Semua teks dari lib/labels.ts.
```

## 11. Checklist sebelum selesai

- [ ] `grep -rnE "(bg|text|border)-(slate|gray|indigo|blue|rose|red|amber|green|emerald)-[0-9]" <file baru>` kosong
- [ ] `grep -rnE "bg-gradient|shadow-(md|lg|xl|2xl)|rounded-(xl|2xl|3xl)|(bg|text|border)-\[#" <file baru>` kosong
- [ ] Hanya satu tombol `primary` per layar
- [ ] Setiap status punya teks; warna sesuai tabel pemetaan di bagian 2
- [ ] Rapi di 360 px dan 1440 px, tanpa scroll horizontal
- [ ] Fokus keyboard terlihat di semua elemen interaktif
- [ ] Teks dari `lib/labels.ts`, format dari `lib/format.ts`
- [ ] `make lint` lulus

## 12. Referensi

- INA Digital Design System: https://design.inadigital.go.id (token diambil dari CSS situs, 19 Nov 2026)
- IDDS skill pack untuk AI agent: https://github.com/halimdirgantara/ina-digital-design-system-skills
- MYDS (Malaysia): https://design.digital.gov.my
- SGDS (Singapura), struktur token semantik: https://www.designsystem.tech.gov.sg
- GOV.UK Design System, pola Check answers / Summary list / Tag: https://design-system.service.gov.uk
- USWDS (Amerika Serikat), komponen Tag: https://designsystem.digital.gov
- NL Design System (Belanda): https://nldesignsystem.nl
- Screenshot referensi lokal (tidak di-commit): `storage/app/design-refs/`
