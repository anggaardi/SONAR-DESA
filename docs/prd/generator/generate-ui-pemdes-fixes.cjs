// Generator daftar kerja perbaikan UI halaman Pemdes (untuk Dev 1).
// Menggunakan docx-kit dari MEMSKILL (lean-feature-spec), sama seperti generate-pemdes-spec.cjs.
const path = require('path');
const kit = require('/home/kadekangga/Projects/MEMSKILL/feature-spec/scripts/lib/docx-kit');

const OUT = path.resolve(__dirname, '..', 'Perbaikan-UI-Pemdes.docx');
const IMG = path.resolve(__dirname, 'img');

const k = kit.create({
  title: 'Sonar Desa - Perbaikan UI Halaman Pemdes',
  version: '1.0',
  imgDir: IMG,
});

const { add, P, H1, H2, H3, Bullets, Checks, Tbl, Callout, Code, cover, toc, pageBreak } = k;

const textTbl = (rows) => Tbl(['Baris', 'Teks lama', 'Teks baru'], rows, [8, 46, 46], { size: 16 });
const classTbl = (rows) => Tbl(['Baris', 'Masalah', 'Perubahan'], rows, [8, 46, 46], { size: 16 });

// ==========================================
// COVER
// ==========================================
add(cover({
  eyebrow: 'DAFTAR KERJA UI',
  heading: 'Perbaikan UI Halaman Pemdes: Daftar Perubahan per File',
  subtitle: 'Mengembalikan halaman Pemerintah Desa ke design system IDDS: teks untuk perangkat desa, bukan untuk pembaca PRD, dan kelas yang sesuai docs/DESIGN.md',
  meta: [
    ['Untuk', 'Dev 1 (Frontend & QA)'],
    ['Dari', 'Dev 2'],
    ['Tanggal', '26 November 2026'],
    ['Estimasi', '1,5–2 hari kerja'],
    ['Acuan', '`docs/DESIGN.md`, PRD Pemdes v1.2 (Story S6)'],
    ['Basis kode', '`main` per 26 Nov 2026'],
  ],
}));

add(Callout('note', 'Kenapa halaman Pemdes terasa "generated"', [
  '- **Teks ditulis untuk pembaca PRD.** Kode keputusan (D1, D9, F3), istilah teknis ("Gatekeeper", "Ekstraksi", "Ingesti", "deterministik", "PublishProject") dan nama model AI tampil ke perangkat desa.',
  '- **Kelas melanggar `docs/DESIGN.md` kita sendiri.** Sudut `rounded-xl`/`rounded-2xl`, bayangan di kartu, gradien, teks 10–11 px, dan angka `font-mono`.',
  '- **Semua diberi penekanan.** 72 ikon SVG inline, 43 tombol `<button>` buatan sendiri, dan blok berwarna yang bersaing di satu layar.',
]));

add(H3('Cara memakai dokumen ini'));
add(Bullets([
  'Kerjakan **Bagian 2 (Aturan umum)** dulu, karena sebagian besar bisa lewat cari-ganti lintas file.',
  'Lalu kerjakan tabel per file di Bagian 3–7 dari atas ke bawah. Nomor baris mengacu ke `main` per 26 Nov 2026 dan bisa bergeser setelah perubahan pertama.',
  'Tidak ada logika atau route yang berubah. Kalau sebuah teks dipakai di test, sesuaikan test-nya.',
  '`desa/ProjectCreate.vue` tidak dirapikan karena dipensiunkan (D17, Story S6).',
]));

add(toc([
  'Ringkasan temuan',
  'Aturan umum lintas file',
  'audit/Review.vue',
  'audit/Create.vue',
  'features/project/PublishWizardModal.vue',
  'desa/Dashboard.vue',
  'desa/ProjectManage.vue',
  'Checklist selesai',
]));
add(pageBreak());

// ==========================================
// 1. Ringkasan temuan
// ==========================================
add(H1('Ringkasan temuan'));
add(P('Jumlah pelanggaran per file. `rounded-xl+`, `shadow`, dan `gradien` dilarang di kartu oleh `docs/DESIGN.md` bagian 4, 5, dan 9.'));
add(Tbl(
  ['File', 'Baris', 'Sudut xl+', 'Shadow', 'Gradien', 'Teks 10–11', 'Mono', 'Button', 'Jargon'],
  [
    ['`audit/Review.vue`', '1.737', '13', '33', '0', '15', '8', '26', '14'],
    ['`audit/Create.vue`', '520', '14', '3', '1', '2', '1', '0', '6'],
    ['`project/PublishWizardModal.vue`', '1.044', '2', '17', '0', '1', '3', '9', '4'],
    ['`desa/Dashboard.vue`', '957', '5', '2', '1', '4', '2', '3', '6'],
    ['`desa/ProjectManage.vue`', '1.007', '5', '3', '0', '2', '3', '5', '7'],
  ],
  [24, 8, 9, 10, 10, 10, 8, 10, 11],
  { size: 15 },
));
add(P('Dampak terbesar dengan usaha terkecil: **teks** (Bagian 2.2). Perangkat desa tidak tahu arti "D9", dan kode semacam ini paling cepat membuat halaman terbaca sebagai hasil AI.'));

// ==========================================
// 2. Aturan umum
// ==========================================
add(H1('Aturan umum lintas file'));

add(H2('Kelas: cari dan ganti'));
add(P('Jalankan dulu perintah dari `docs/DESIGN.md` bagian 11. Targetnya hasil kosong:'));
add(Code([
  'grep -rnE "bg-gradient|shadow-(md|lg|xl|2xl)|rounded-(xl|2xl|3xl)|(bg|text|border)-\\[#|text-\\[1[01]px\\]|font-mono|(text|bg|border)-(stone|gray)-|bg-white" \\',
  '  resources/js/pages/audit resources/js/pages/desa resources/js/features/project/PublishWizardModal.vue',
]));
add(Tbl(['Cari', 'Ganti dengan', 'Catatan'], [
  ['`rounded-xl`, `rounded-2xl`', '`rounded-lg` (kartu, alert, dialog) atau `rounded-md` (tombol, input)', 'DESIGN.md 4: tanpa `rounded-xl` ke atas'],
  ['`shadow-…` pada kartu, panel, tombol', 'Hapus. Batas cukup `border border-stroke`', '`shadow-sm` hanya untuk dialog, dropdown, toast, dan bar tombol yang menempel di bawah'],
  ['`shadow-2xl` pada modal wizard', '`shadow-sm`', 'DESIGN.md 5'],
  ['`bg-gradient-to-…`', '`bg-surface` atau `bg-surface-subtle`', 'DESIGN.md 9: dilarang'],
  ['`text-[10px]`, `text-[11px]`', '`text-xs` (desktop) atau `text-sm` (layar warga)', 'Skala Tailwind sama dengan IDDS. Teks 10–11 px sulit dibaca di HP'],
  ['`font-mono` pada angka/rupiah', '`tabular-nums` (tetap Inter)', 'DESIGN.md 3: satu font, angka rata dengan `tabular-nums`'],
  ['`bg-white`, `text-stone-…`, `border-stone-…`', '`bg-surface`, `text-content`, `border-stroke`', 'Hanya token dari `app.css`'],
  ['`<button class="... bg-primary ...">`', '`<Button variant="primary">` (atau `secondary`, `ghost`, `danger`)', 'Satu varian `primary` per layar (PRD 6.1)'],
  ['`<div class="rounded-… border ...">` sebagai kartu', '`<Card>`', 'Tanpa kartu di dalam kartu; pakai divider'],
  ['Panel status buatan sendiri (hijau/kuning/merah)', '`<Alert>`', 'Pola: latar lembut + border + ikon + judul + isi'],
  ['Lingkaran nomor langkah (1, 2, 3)', 'Teks "Langkah 2 dari 3" di atas judul', 'Mengurangi ornamen'],
  ['Ikon SVG inline dekoratif', 'Hapus, atau satu ikon yang membawa arti', 'Ikon hanya untuk status dan aksi'],
], [28, 36, 36], { size: 16 }));

add(H2('Teks: aturan dan kamus istilah'));
add(Bullets([
  '**Hapus semua kode spesifikasi** dari teks UI: `(D1)`, `(D9)`, `(F3)`, `(D8 & D10)`, dan seterusnya. Kode cukup ada di komentar kode dan nama test.',
  '**Jangan tampilkan nama komponen teknis:** `PublishProject`, "Background Job", nama model AI, "Reader: Fixture Offline". Informasi seperti itu cukup di log.',
  '**Subjudul menyebut apa yang harus dilakukan pengguna**, bukan cara kerja sistem. Maksimal satu kalimat.',
  '**Jangan mengklaim hal yang tidak dilakukan sistem.** Contoh: sistem tidak memverifikasi stempel basah, jadi label "Terverifikasi" tidak boleh ada.',
]));
add(Tbl(['Istilah sekarang', 'Pakai', 'Contoh'], [
  ['Ekstraksi / mengekstrak', 'Membaca / dibaca', '"Dokumen sedang dibaca"'],
  ['Ingesti', 'Unggah', '"Unggah dokumen RAB"'],
  ['Gatekeeper', '(hapus) atau "pemeriksaan sebelum terbit"', '"Semua baris sudah diperiksa"'],
  ['Deterministik', 'Otomatis', '"Label harga dihitung ulang otomatis"'],
  ['Geofence', 'Radius lokasi', '"Warga hanya bisa mengirim Cek Spek dalam radius 300 m"'],
  ['Wizard Publikasi', 'Terbitkan proyek', 'Judul modal: "Terbitkan proyek"'],
  ['CCO / Audit Trail / append-only', 'Perubahan (addendum) / Riwayat perubahan', '"Riwayat perubahan"'],
  ['Invarian sistem', '(hapus)', 'Ganti dengan alasan dalam bahasa biasa'],
  ['Audit & Ingesti RAB (menu)', 'Dokumen RAB', 'Menu samping di 4 halaman'],
], [28, 32, 40], { size: 16 }));

add(H2('Struktur: menu samping dan file besar'));
add(Bullets([
  '**Menu samping ditulis ulang di setiap halaman** (`Review.vue` 455–470, juga di `Create.vue`, `Dashboard.vue`, `ProjectManage.vue`). Pindahkan ke satu layout Pemdes, seperti `AppLayout.vue` yang sudah memakai `DesktopSidebar`. Label menu cukup diubah sekali.',
  '**Pecah file di atas ±400 baris** menjadi komponen di `resources/js/features/`. Detail per file ada di bagian masing-masing.',
]));

// ==========================================
// 3. Review.vue
// ==========================================
add(H1('audit/Review.vue'));
add(P('Layar terpenting: tempat Pemdes mencocokkan tabel dengan dokumen asli. Saat ini ada lima blok yang bersaing (panel hijau, bar total, chip filter, tabel, bar tombol). Targetnya: satu pesan status, tabel, dan satu tombol primary.'));
add(H3('Teks'));
add(textTbl([
  ['463', 'Audit & Ingesti RAB', 'Dokumen RAB (lewat layout bersama)'],
  ['519', 'Verifikasi Hasil Ekstraksi RAB & Gatekeeper', 'Periksa rincian RAB'],
  ['521–523', 'Cocokkan data tabel digital terhadap berkas fisik berstempel. Seluruh baris kuning wajib diselesaikan sebelum terbit (D1).', 'Cocokkan tabel dengan dokumen asli. Periksa semua baris kuning sebelum menerbitkan.'],
  ['795', 'Dokumen Berstempel Basah Terverifikasi (D9)', 'Hapus. Sistem tidak memverifikasi stempel, jadi klaim ini tidak benar.'],
  ['849', 'Ekstraksi Otomatis AI Mengalami Kendala', 'Dokumen belum bisa dibaca otomatis'],
  ['866–869', '... tetap dapat diekstrak cepat lewat Jalur A, atau isi rincian belanja secara manual.', 'Hapus "Jalur A". Cukup: "Coba baca ulang, atau isi rincian secara manual."'],
  ['872–873', 'Berkas asli Anda tetap tersimpan aman. Anda dapat mencoba ekstraksi ulang atau menginput rincian belanja secara manual.', 'Berkas tetap tersimpan. Coba baca ulang, atau isi rincian secara manual.'],
  ['897', 'Coba Ekstraksi Ulang', 'Coba baca ulang'],
  ['960', 'Publikasi Terkunci: {n} Baris Perlu Diperiksa (D1)', '{n} baris perlu diperiksa sebelum terbit'],
  ['989–993', 'Gatekeeper D1 Lolos: 0 Baris Kuning. Semua rincian belanja telah terverifikasi. Anda dapat melanjutkan ke wizard penerbitan ...', 'Hapus panel hijau besar. Cukup teks kecil di dekat tombol: "Semua baris sudah diperiksa."'],
  ['1028–1040', 'Konsistensi Total (F3): Total Tertulis Rp... vs Jumlah Baris Rp... (Selisih Rp...)', 'Total di dokumen Rp115.000.000, jumlah baris Rp114.980.000 (beda Rp20.000).'],
  ['1423', 'Terkunci: selesaikan {n} baris kuning (D1)', 'Periksa {n} baris kuning untuk menerbitkan'],
  ['1426', 'Terkunci: konfirmasi ketidakcocokan total tertulis (F3)', 'Centang konfirmasi selisih total untuk menerbitkan'],
  ['1432', 'AI sedang mengekstrak data...', 'Dokumen sedang dibaca…'],
  ['1435', 'Ekstraksi AI gagal: silakan coba lagi atau isi manual', 'Dokumen belum terbaca. Coba lagi atau isi manual.'],
]));
add(H3('Kelas & struktur'));
add(classTbl([
  ['708–783', '**Simulasi kertas RAB palsu** (`bg-white`, `text-stone-…`, `shadow-md`) tampil saat pratinjau gagal. Ini layar bukti, jadi dokumen tiruan bisa disangka asli.', 'Hapus seluruh simulasi. Ganti `<Alert>`: "Pratinjau tidak tersedia" + tombol "Buka berkas asli".'],
  ['805, 844', 'Kartu status `rounded-2xl ... shadow-xs`', '`<Alert color="info">` (sedang dibaca) dan `<Alert color="negative">` (gagal dibaca)'],
  ['808', 'Kotak ikon `h-16 w-16 rounded-2xl`', 'Hapus kotaknya. Cukup indikator putar kecil di samping teks.'],
  ['1358', 'Bar tombol menempel `rounded-xl` + bayangan', '`rounded-lg border-t border-stroke shadow-sm` (satu-satunya shadow di layar ini)'],
  ['1380, 1403', 'Tombol `<button>` dengan `bg-primary px-5 py-3`', '`<Button variant="primary">`'],
  ['Semua', '26 `<button>` mentah, 15 teks 10–11 px, 8 `font-mono`, 33 `shadow`', 'Sesuai tabel Bagian 2.1'],
  ['Semua', '1.737 baris dalam satu file', 'Pecah ke `features/audit/`: `DocumentViewer.vue`, `ReadingStatus.vue`, `TotalCheck.vue`, `AuditItemsTable.vue`, `PublishBar.vue`'],
]));

// ==========================================
// 4. Create.vue
// ==========================================
add(H1('audit/Create.vue'));
add(P('Layar unggah cukup berisi satu hal: memilih berkas. Penjelasan "fase", "AI Vision", dan nama model dihapus.'));
add(H3('Teks'));
add(textTbl([
  ['147', 'Audit & Ingesti RAB', 'Dokumen RAB (lewat layout bersama)'],
  ['208', 'Fase 1: Ingesti Dokumen & Audit Anggaran', 'Hapus label ini. Judul halaman cukup "Unggah dokumen RAB".'],
  ['211–215', 'AI Vision: {model} / Reader: Fixture Offline (`font-mono`)', 'Hapus dari UI. Informasi teknis cukup di log.'],
  ['245–270', 'Kartu langkah: "Ekstraksi AI · Background Job", "Gatekeeper Review · Cek Baris Kuning"', 'Satu kalimat: "Setelah diunggah, sistem membaca tabel RAB. Anda lalu memeriksa hasilnya sebelum terbit."'],
  ['434', 'Mulai Ekstraksi AI', 'Unggah dan baca dokumen'],
  ['512–515', 'Ketentuan Hukum & Invarian Sistem (D9): Berkas dokumen asli berstempel wajib diunggah agar ...', 'Kenapa harus dokumen asli? Warga bisa mencocokkan tabel di Sonar Desa dengan dokumen berstempel.'],
]));
add(H3('Kelas & struktur'));
add(classTbl([
  ['288', 'Area unggah `rounded-2xl border-2`', '`rounded-lg border-2 border-dashed border-stroke-strong`'],
  ['310, 351', 'Kotak ikon `h-16 w-16 rounded-2xl`', 'Ikon 24 px tanpa kotak, atau hapus'],
  ['443', '`bg-gradient-to-r from-primary-subtle/40 via-surface ...`', '`bg-surface-subtle border border-stroke`'],
  ['245–270', 'Tiga kartu langkah dengan lingkaran nomor', 'Diganti satu kalimat (lihat tabel teks)'],
]));

// ==========================================
// 5. PublishWizardModal.vue
// ==========================================
add(H1('features/project/PublishWizardModal.vue'));
add(P('Langkah terakhir sebelum data dibuka ke warga. Teksnya harus menegaskan konsekuensi nyata: setelah terbit, proyek tidak bisa ditarik.'));
add(H3('Teks'));
add(textTbl([
  ['328', 'Wizard Publikasi Proyek Fisik', 'Terbitkan proyek'],
  ['331', 'Satu pintu penerbitan resmi ke hadapan warga desa (PublishProject · D8)', 'Isi 3 langkah. Setelah terbit, halaman proyek bisa dibuka warga.'],
  ['613', 'Langkah 2: Titik Koordinat GPS & Geofence (D3)', 'Langkah 2 dari 3: Lokasi proyek'],
  ['615–616', 'Titik GPS ini mengaktifkan radius geofence (≤ 300m) agar warga hanya dapat ...', 'Warga hanya bisa mengirim Cek Spek dalam radius 300 m dari titik ini.'],
  ['764–766', '... Parameter kunci template baku tidak dapat dihapus demi akuntabilitas (D2).', 'Ukuran dari template wajib diisi dan tidak bisa dihapus.'],
  ['941–946', 'Integritas Publik Terjamin (D8 & D10): Setelah tombol ... ditekan, proyek fisik akan langsung berstatus publik ...', 'Setelah terbit, proyek tidak bisa ditarik kembali. Perubahan berikutnya tercatat dan terlihat warga.'],
]));
add(H3('Kelas & struktur'));
add(classTbl([
  ['306', 'Modal `rounded-2xl shadow-2xl`', '`rounded-lg shadow-sm` (sesuai `Dialog`)'],
  ['1021', 'Tombol terbit `<button>` dengan `bg-primary`', '`<Button variant="primary">Terbitkan proyek</Button>`'],
  ['Semua', '9 `<button>` mentah, 17 `shadow`, 3 `font-mono`', 'Sesuai tabel Bagian 2.1'],
  ['134, 196', 'Bug `origin: custom` (bukan tampilan)', 'Sudah tercatat di PRD v1.2 Story S6 (P1). Kerjakan bersamaan.'],
]));

// ==========================================
// 6. Dashboard.vue
// ==========================================
add(H1('desa/Dashboard.vue'));
add(P('Dashboard adalah daftar tugas: dokumen apa yang perlu dibaca, diperiksa, atau sudah terbit. Banner promosi "AI Vision" diganti satu ajakan unggah yang sederhana.'));
add(H3('Teks'));
add(textTbl([
  ['290', 'Audit & Ingesti RAB', 'Dokumen RAB (lewat layout bersama)'],
  ['402', 'Badge nama model AI ({model} ({provider}))', 'Hapus'],
  ['406', 'Ingesti & Audit Otomatis Dokumen RAB dengan AI Vision', 'Unggah dokumen RAB'],
  ['408–411', 'Unggah berkas PDF atau foto ... Model AI Vision mengekstrak rincian tabel ... secara deterministik.', 'Unggah PDF atau foto RAB berstempel. Sistem membaca tabelnya dan membandingkan harga dengan standar harga Kabupaten {kabupaten}.'],
  ['493–494', 'Berkas RAB fisik yang sedang diekstrak AI atau menunggu verifikasi Gatekeeper sebelum diterbitkan.', 'Dokumen yang sedang dibaca atau perlu Anda periksa sebelum terbit.'],
  ['503', 'Memantau Ekstraksi AI...', 'Sedang dibaca…'],
  ['541', 'Gagal Ekstraksi', 'Belum terbaca'],
]));
add(H3('Kelas & struktur'));
add(classTbl([
  ['390', 'Banner `bg-gradient-to-r from-primary-subtle/20 ...`', '`<Card>` biasa dengan satu tombol primary "Unggah dokumen RAB"'],
  ['513', 'Kartu dokumen `rounded-xl ... shadow`', '`<Card>` atau baris tabel dengan `<Badge>` status'],
  ['455', 'Tombol "Isi Manual" ke `/desa/projects/create`', 'Sudah tercatat di PRD v1.2 Story S6 (P1): arahkan ke `/audits/create`'],
]));

// ==========================================
// 7. ProjectManage.vue
// ==========================================
add(H1('desa/ProjectManage.vue'));
add(P('Perangkat desa memakai halaman ini setelah proyek berjalan. Istilah "CCO" boleh dipertahankan di dalam kurung karena dikenal di pekerjaan konstruksi, tetapi judul memakai bahasa biasa.'));
add(H3('Teks'));
add(textTbl([
  ['264', 'Audit & Ingesti RAB', 'Dokumen RAB (lewat layout bersama)'],
  ['578', 'CCO Tambah Kurang Volume Belanja RAB', 'Ubah volume atau harga (addendum/CCO)'],
  ['579–581', 'Sesuai aturan D13, setiap perubahan ... dihitung ulang secara deterministik oleh sistem (D14).', 'Setiap perubahan wajib diberi alasan dan langsung tampil di riwayat yang bisa dibaca warga. Label harga dihitung ulang otomatis.'],
  ['667', 'CCO Parameter Ukuran Fisik (Cek Spek Warga)', 'Ubah target ukuran Cek Spek'],
  ['669–672', '... dapat disesuaikan melalui CCO (D13). Parameter tidak dapat dihapus ... (D11).', 'Ukuran yang diukur warga di lapangan. Nilai target bisa diubah dengan alasan; ukuran tidak bisa dihapus setelah terbit.'],
  ['805', 'Linimasa Riwayat Perubahan (Audit Trail CCO)', 'Riwayat perubahan'],
  ['807–809', 'Catatan append-only perubahan teknis proyek ... (D10, D13).', 'Semua perubahan setelah terbit. Riwayat ini juga tampil di halaman warga dan tidak bisa dihapus.'],
]));
add(H3('Kelas & struktur'));
add(classTbl([
  ['Semua', '5 `rounded-xl+`, 3 `shadow`, 3 `font-mono`, 5 `<button>` mentah, 20 kotak ber-border manual', 'Sesuai tabel Bagian 2.1. Kotak ber-border menjadi `<Card>` + divider'],
  ['Semua', '1.007 baris', 'Pecah ke `features/project/`: `ProgressPanel.vue`, `ItemChangeForm.vue`, `MeasurementChangeForm.vue`, `ChangeHistory.vue`'],
]));

// ==========================================
// 8. Checklist
// ==========================================
add(H1('Checklist selesai'));
add(Checks([
  'Perintah grep di Bagian 2.1 tidak menghasilkan apa pun untuk kelima file.',
  '`grep -rnE "\\((D|F)[0-9]+" resources/js/pages resources/js/features` hanya menemukan komentar, bukan teks tampil.',
  'Tidak ada kata "Ekstraksi", "Ingesti", "Gatekeeper", "deterministik", "PublishProject", atau nama model AI di teks tampil.',
  'Setiap layar punya tepat satu `<Button variant="primary">`.',
  'Menu samping Pemdes berasal dari satu layout, bukan disalin di tiap halaman.',
  'Simulasi kertas palsu di `Review.vue` sudah dihapus.',
  '`npm run lint` dan `vue-tsc` lulus. Test yang memeriksa teks lama sudah disesuaikan.',
  'Dicek di HP (360 px) dan desktop: unggah → review → terbit → kelola proyek.',
]));

async function main() {
  console.log('Writing document to:', OUT);
  await k.save(OUT);
  console.log('Docx created successfully!');
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
