const path = require('path');
const fs = require('fs');
const { Sketch, C } = require('/home/kadekangga/Projects/MEMSKILL/feature-spec/scripts/lib/sketch');

const OUT = path.resolve(__dirname, 'img');
fs.mkdirSync(OUT, { recursive: true });
const MUTED = '#868e96';

// -------------------------------------------------------------
// Diagram 1: Siklus Hidup Alur Kerja Pemdes (4 Fase)
// -------------------------------------------------------------
{
  const s = new Sketch(1400, 720, 301);
  s.text(700, 38, 'Siklus Hidup Alur Kerja Pemerintah Desa (Pemdes): Ingesti hingga Pengawasan', { size: 24, bold: true });

  // Fase 1: Ingesti & Audit RAB
  const f1 = s.rect(40, 100, 300, 170, {
    fill: C.blue,
    label: ['Fase 1: Ingesti & Audit', '• Unggah PDF/Scan RAB', '• AI Vision Ekstraksi Tabel', '• Matching ke SSH Gianyar', '• Pure PHP PriceLabeler'],
    size: 15,
  });

  // Fase 2: Gatekeeper & Wizard
  const f2 = s.rect(380, 100, 300, 170, {
    fill: C.yellow,
    label: ['Fase 2: Gatekeeper & Setup', '• Cek Baris Kuning (NeedsCheck)', '• Verifikasi Selisih Total', '• Set Koordinat GPS (Geofence)', '• Pilih Template Spek Fisik'],
    size: 15,
  });

  // Fase 3: Publikasi & Cetak QR
  const f3 = s.rect(720, 100, 300, 170, {
    fill: C.green,
    label: ['Fase 3: Terbit & Sosialisasi', '• Status: Published', '• Live di /p/{slug} & /d/{village}', '• Cetak Poster QR A4', '• Pasang di Plang Fisik Proyek'],
    size: 15,
  });

  // Fase 4: Operasional Fisik
  const f4 = s.rect(1060, 100, 300, 170, {
    fill: C.orange,
    label: ['Fase 4: Operasional Fisik', '• Update Progres (%) Berkala', '• Unggah Foto Lapangan (WebP)', '• Bersihkan EXIF Kamera (Privasi)', '• Catat Addendum Perubahan (CCO)'],
    size: 15,
  });

  s.arrow([f1.r, f2.l], { label: 'Tabel Terbaca' });
  s.arrow([f2.r, f3.l], { label: '0 Baris Kuning' });
  s.arrow([f3.r, f4.l], { label: 'Proyek Berjalan' });

  // Hubungan ke Warga & BPD (Bagian Bawah)
  s.rect(40, 340, 1320, 340, { dash: true, stroke: MUTED, sw: 1.5, r: 16 });
  s.text(200, 370, 'Ekosistem Pengawasan Publik Terpadu', { size: 16, bold: true, color: MUTED });

  const warga = s.rect(120, 420, 520, 200, {
    fill: C.teal,
    label: [
      'Warga Desa (Auditor Lapangan)',
      '• Scan QR di Papan Proyek tanpa login',
      '• Validasi Jarak Geofence GPS (<= 300 meter)',
      '• Ukur Parameter Fisik (Tebal, Lebar, Panjang)',
      '• Foto Langsung Kamera (Anti-Galeri)',
      '• Kirim Laporan Cek Spek',
    ],
    size: 15,
  });

  const bpd = s.rect(760, 420, 520, 200, {
    fill: C.violet,
    label: [
      'BPD (Pengawas & Penengah Musyawarah)',
      '• Ambang otomatis >= 3 laporan warga selisih valid',
      '• Proyek naik status: "Dalam Tinjauan BPD"',
      '• Sidang Musyawarah Desa: Panggil Pemdes & TPK',
      '• Pemdes beri klarifikasi / janji perbaikan fisik',
      '• BPD tutup siklus: Status "Ditinjau BPD"',
    ],
    size: 15,
  });

  s.arrow([[870, 270], [380, 420]], { label: 'Scan QR di Lokasi', color: '#196bcd' });
  s.arrow([[640, 520], [760, 520]], { label: '>= 3 Laporan Selisih' });
  s.arrow([[1020, 420], [1210, 270]], { label: 'Klarifikasi & Catatan Musdes', dash: true });

  s.save(`${OUT}/d1-pemdes-lifecycle.png`);
}

// -------------------------------------------------------------
// Diagram 2: Pemetaan Data Setup Pemdes vs Render Publik Warga
// -------------------------------------------------------------
{
  const s = new Sketch(1400, 680, 302);
  s.text(700, 38, 'Pemetaan Data: Setup Pemdes -> Database -> Render Halaman Warga', { size: 24, bold: true });

  const pemdes = s.rect(50, 100, 360, 520, {
    fill: C.blue,
    label: [
      'Setup oleh Pemdes (Input)',
      '',
      '1. Unggah Berkas RAB Asli (PDF/Foto)',
      '2. Verifikasi Item & Harga Satuan',
      '3. Metadata: Nama, Tahun, Sumber Dana',
      '4. Titik Geofence (Latitude, Longitude)',
      '5. Template Ukuran Fisik (Cek Spek)',
      '6. Update Progres Fisik (%)',
      '7. Unggah Foto Dokumentasi Lapangan',
      '8. Catat Addendum Perubahan (CCO)',
    ],
    size: 15,
  });

  const db = s.cylinder(520, 120, 360, 480, {
    fill: C.yellow,
    label: [
      'Database PostgreSQL 17',
      '',
      '• audits.file_path',
      '• audit_items (raw_name, vol, price)',
      '• projects (name, year, fund_source)',
      '• projects.lat & projects.lng',
      '• project_measurements (value, unit)',
      '• projects.progress_pct',
      '• progress_photos (path, WebP)',
      '• project_changes (old -> new)',
    ],
    size: 15,
  });

  const warga = s.rect(990, 100, 360, 520, {
    fill: C.green,
    label: [
      'Render Halaman Warga (/p/{slug})',
      '',
      '1. Tombol "Lihat Dokumen Asli"',
      '2. Tabel Deviasi Harga & Filter',
      '3. Header & Hero Ringkasan Pagu',
      '4. Geofence Radius Validasi <= 300m',
      '5. Parameter "Ukuran di RAB" & Form',
      '6. Progress Bar Progres Fisik',
      '7. Linimasa Galeri Foto Progres',
      '8. Tab Transparan Riwayat Perubahan',
    ],
    size: 15,
  });

  s.arrow([pemdes.r, [520, 200]], { label: 'Input Dokumen' });
  s.arrow([pemdes.r, [520, 360]], { label: 'Wizard Setup' });
  s.arrow([pemdes.r, [520, 500]], { label: 'Update Lapangan' });

  s.arrow([[880, 200], warga.l], { label: 'Dokumen & Tabel' });
  s.arrow([[880, 360], warga.l], { label: 'Spek & Geofence' });
  s.arrow([[880, 500], warga.l], { label: 'Linimasa Progres' });

  s.save(`${OUT}/d2-data-mapping.png`);
}

// -------------------------------------------------------------
// Diagram 3: Gatekeeper Baris Kuning (Zero-Warning Rule)
// -------------------------------------------------------------
{
  const s = new Sketch(1400, 600, 303);
  s.text(700, 38, 'Mekanisme Gatekeeper Baris Kuning (Zero-Warning Policy)', { size: 24, bold: true });

  const ai = s.rect(60, 160, 280, 160, {
    fill: C.violet,
    label: ['AI Parser & Engine', 'Ekstraksi JSON dari PDF', 'Hitung deviasi standar SSH', 'Identifikasi baris ragu/satuan'],
    size: 15,
  });

  const check = s.diamond(540, 240, 260, 180, {
    fill: C.yellow,
    label: ['Evaluasi:', 'Ada Baris Kuning?'],
    size: 16,
  });

  const yellow = s.rect(410, 420, 260, 140, {
    fill: C.red,
    label: ['Tombol Terbit Terkunci', 'Status: NeedsReview', 'Pemdes wajib periksa', 'Inline edit / konfirmasi'],
    size: 14,
  });

  const ready = s.rect(800, 170, 280, 140, {
    fill: C.teal,
    label: ['Status: Ready (Siap Terbit)', '0 Baris Kuning Tersisa', 'Tombol Terbitkan Aktif', 'Buka Wizard Publikasi'],
    size: 15,
  });

  const pub = s.rect(1140, 170, 220, 140, {
    fill: C.green,
    label: ['Status: Published', 'Proyek Terbit ke Publik', 'Generate QR Code A4'],
    size: 15,
  });

  s.arrow([ai.r, check.l]);
  s.arrow([check.b, yellow.t], { label: 'Ya (>= 1 Baris)', color: '#d50b0b' });
  s.arrow([yellow.l, [200, 490], [200, 320]], { label: 'Koreksi Selesai', dash: true });
  s.arrow([check.r, ready.l], { label: 'Tidak (0 Baris)', color: '#288034' });
  s.arrow([ready.r, pub.l], { label: 'Konfirmasi Wizard' });

  s.save(`${OUT}/d3-yellow-gatekeeper.png`);
}

console.log('Diagrams generated successfully in img/!');
