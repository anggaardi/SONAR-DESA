// Generator diagram arsitektur Sonar Desa menggunakan roughjs + Virgil font (Excalidraw style)
const path = require('path');
const fs = require('fs');
const { Sketch, C } = require('/home/kadekangga/Projects/MEMSKILL/feature-spec/scripts/lib/sketch');

const OUT = path.resolve(__dirname, 'img');
fs.mkdirSync(OUT, { recursive: true });
const MUTED = '#868e96';

// -------------------------------------------------------------
// Diagram 1: Topologi Arsitektur Docker (Zero-Issue Environment)
// -------------------------------------------------------------
{
  const s = new Sketch(1400, 780, 201);
  s.text(700, 38, 'Topologi Docker Compose: Sonar Desa (Local & VPS)', { size: 26, bold: true });

  // Host / Client
  const client = s.rect(450, 75, 500, 80, { fill: C.blue, label: ['Host Browser / HP Warga / Laptop Juri', 'Akses via http://localhost:8000 atau Domain Publik'], size: 16 });

  // Web Nginx
  const nginx = s.rect(480, 205, 440, 80, { fill: C.teal, label: ['web: Nginx (Alpine)', 'Port 8000:80 · Reverse Proxy + Static Vite Assets'], size: 16 });

  s.arrow([client.b, nginx.t], { label: 'HTTP / HTTPS' });

  // App & Worker container
  s.rect(40, 340, 1320, 210, { dash: true, stroke: MUTED, sw: 1.5, r: 16 });
  s.text(200, 365, 'PHP 8.3 FPM & CLI Ecosystem', { size: 16, bold: true, color: MUTED });

  const app = s.rect(80, 400, 560, 120, { fill: C.green, label: ['app: PHP 8.3 FPM (Laravel 11 + Inertia.js)', 'Pure Domain Services · Controllers · Google Socialite', 'Time locked to WITA (Asia/Makassar)'], size: 15 });
  const worker = s.rect(760, 400, 560, 120, { fill: C.yellow, label: ['worker: PHP 8.3 CLI (Queue Listener)', 'php artisan queue:listen · Async AI Document Extraction', 'Poster QR Generator (A4 Format)'], size: 15 });

  s.arrow([[640, 285], [360, 400]], { label: 'FastCGI :9000' });
  s.arrow([[760, 285], [1040, 400]], { label: 'Background Jobs', dash: true });

  // Backing Services
  const pg = s.cylinder(80, 600, 260, 140, { fill: C.blue, label: ['postgres: Postgres 16', 'Relational DB · JSONB', 'Audits & Items & Reports'], size: 14 });
  const redis = s.cylinder(400, 600, 260, 140, { fill: C.red, label: ['redis: Redis 7', 'Session Demo Isolation', 'Queue & App Cache'], size: 14 });
  const minio = s.cylinder(720, 600, 260, 140, { fill: C.orange, label: ['minio: S3 Storage', 'Dokumen Asli RAB (PDF)', 'Foto Kamera Cek Spek'], size: 14 });
  const mail = s.rect(1040, 620, 280, 100, { fill: C.gray, label: ['mailpit: Local SMTP', 'Simulasi Notifikasi Email'], size: 14 });

  s.arrow([[360, 520], [210, 600]], { label: 'SQL' });
  s.arrow([[360, 520], [530, 600]], { label: 'Session / Cache' });
  s.arrow([[1040, 520], [530, 600]], { label: 'Pop Jobs' });
  s.arrow([[1040, 520], [850, 600]], { label: 'S3 API' });

  s.save(`${OUT}/d1-arsitektur-docker.png`);
}

// -------------------------------------------------------------
// Diagram 2: Peta 5 Modul Inti & Alur Kerja
// -------------------------------------------------------------
{
  const s = new Sketch(1400, 720, 202);
  s.text(700, 38, 'Peta 5 Modul Fungsional Sonar Desa & Alur Data', { size: 26, bold: true });

  const m1 = s.rect(60, 100, 360, 150, { fill: C.green, label: ['Modul 1: Audit RAB', 'Pemdes / BPD · Unggah PDF', 'AI OCR Parsing & Fuzzy Match', 'Pure Math Check · Review Baris Kuning'], size: 15 });
  const m2 = s.rect(520, 100, 360, 150, { fill: C.blue, label: ['Modul 2: Halaman Proyek & QR', 'Warga (Tanpa Login) · Load <= 3s', 'Rincian Label · Ukuran di RAB', 'Viewer Dokumen Asli Berttd · Cetak A4'], size: 15 });
  const m3 = s.rect(980, 100, 360, 150, { fill: C.orange, label: ['Modul 3: Cek Spek & Laporan', 'Warga (Google Login) · Radius <= 300m', 'Kamera HP Langsung (Anti-Galeri)', 'Checklist Spek Fisik Lapangan'], size: 15 });

  const m0 = s.cylinder(520, 340, 360, 160, { fill: C.yellow, label: ['Modul 0: Core Platform & DB', 'PostgreSQL 16 · Katalog Perbup Gianyar', 'Auth Guards · WITA Time Helpers', 'Single Source of Truth'], size: 15 });

  const m4 = s.rect(980, 350, 360, 140, { fill: C.violet, label: ['Modul 4: Dashboard BPD', 'BPD · Pantau Proyek Prioritas', 'Review Laporan Anonim (Pelapor A/B/C)', 'Ambang 3 Laporan -> Status Publik'], size: 15 });

  s.arrow([m1.r, m2.l], { label: 'Terbitkan Proyek' });
  s.arrow([m2.r, m3.l], { label: 'Klik Cek Spek' });
  s.arrow([[1160, 250], [1160, 350]], { label: 'Kirim Laporan Valid' });
  s.arrow([m4.l, [880, 420]], { label: 'Hasil Tinjauan BPD' });
  s.arrow([[700, 340], [700, 250]], { label: 'Data Publik' });

  // Modul 5 Banner Demo
  s.rect(60, 560, 1280, 120, { fill: C.yellow, label: [
    'Modul 5: Mode Demo & Evaluasi Juri (Techcomfest 2027)',
    '• 4 Pintu Masuk 1-Klik (Warga, BPD, Pemdes, Contoh RAB) tanpa login akun pribadi & tanpa ke Gianyar',
    '• Sandbox terisolasi per sesi juri di Redis · Mock Tombol "Pakai Foto & Lokasi Contoh"',
  ], size: 16 });

  s.save(`${OUT}/d2-peta-modul.png`);
}

// -------------------------------------------------------------
// Diagram 3: Pipeline AI "Numbers by Code, Words by AI"
// -------------------------------------------------------------
{
  const s = new Sketch(1400, 620, 203);
  s.text(700, 38, 'Pipeline AI Deterministik: Numbers by Code, Words by AI', { size: 26, bold: true });

  const upload = s.rect(40, 120, 250, 130, { fill: C.blue, label: ['1. Upload Dokumen', 'PDF / Scan RAB (<= 10MB)', 'Simpan di MinIO S3'], size: 15 });
  const queue = s.rect(340, 120, 260, 130, { fill: C.yellow, label: ['2. Async Queue Job', 'ProcessRabDocumentJob', 'Status: Membaca (10-20s)'], size: 15 });
  const llm = s.rect(650, 120, 340, 130, { fill: C.violet, label: ['3. Gemini 1.5 Flash (Vision)', 'Hanya Ekstraksi Teks Mentah', 'Keluarkan Structured JSON', 'Item Name, Vol, Satuan, Harga'], size: 15 });
  const pure = s.rect(1040, 120, 320, 130, { fill: C.green, label: ['4. Pure PHP Domain Engine', 'RabCalculationEngine.php', '• Fuzzy Match ke Perbup Gianyar', '• Vol x Harga = Total? (Toleransi 1rb)'], size: 15 });

  s.arrow([upload.r, queue.l]);
  s.arrow([queue.r, llm.l]);
  s.arrow([llm.r, pure.l]);

  const yellow = s.rect(200, 350, 440, 140, { fill: C.yellow, label: ['Ada Baris Kuning (Perlu Dicek)?', '• Beda satuan (sak vs kg) atau hitungan selisih', '• Status: Perlu Diperiksa · Tombol Terbit Terkunci', '• Pemdes Wajib Koreksi / Konfirmasi'], size: 14 });
  const ready = s.rect(760, 350, 440, 140, { fill: C.teal, label: ['Semua Bersih / Terkonfirmasi', '• Status: Siap Terbit', '• Input 1-5 Ukuran Wajib Template Proyek', '• Terbitkan -> Halaman Publik & QR A4 Siap'], size: 14 });

  s.arrow([[1200, 250], [1200, 310], [420, 310], [420, 350]], { label: 'Ada Anomali' });
  s.arrow([[1200, 250], [1200, 310], [980, 310], [980, 350]], { label: 'Valid / Bersih' });
  s.arrow([yellow.r, ready.l], { label: 'Koreksi Manusia Selesai' });

  s.rect(40, 520, 1320, 70, { fill: C.gray, label: ['Prinsip Mutlak: Angka & kalkulasi selalu dihitung kode. AI tidak pernah memutuskan vonis hukum atau selisih rupiah.'], size: 15 });

  s.save(`${OUT}/d3-ai-pipeline.png`);
}

// -------------------------------------------------------------
// Diagram 4: Siklus Status Laporan Warga & BPD
// -------------------------------------------------------------
{
  const s = new Sketch(1400, 600, 204);
  s.text(700, 38, 'Logika Siklus Review BPD & Eskalasi Status Publik Proyek', { size: 26, bold: true });

  const s1 = s.rect(60, 130, 320, 140, { fill: C.gray, label: ['Status: Tanpa Penanda', '< 3 Laporan Selisih Valid', 'Warga Cek Spek di Lokasi', 'Netral, tidak pernah lencana hijau'], size: 15 });
  const s2 = s.rect(540, 130, 340, 140, { fill: C.yellow, label: ['Status: "Dalam Tinjauan BPD"', 'Eskalasi Otomatis (>= 3 Laporan Valid)', 'Muncul Lencana Publik Kuning', 'BPD Meninjau Foto & Hasil Ukur'], size: 15 });
  const s3 = s.rect(1020, 130, 320, 140, { fill: C.blue, label: ['Status: "Ditinjau BPD"', 'BPD Simpan Hasil + Catatan >=30 char', 'Pilihan: Ditindaklanjuti / Sesuai', 'Lencana Biru Publik Tampil'], size: 15 });

  s.arrow([s1.r, s2.l], { label: 'Laporan ke-3 Masuk' });
  s.arrow([s2.r, s3.l], { label: 'BPD Simpan Keputusan' });

  // Siklus Baru
  s.arrow([[1180, 270], [1180, 360], [220, 360], [220, 270]], { label: 'Siklus Baru Dimulai: Hitungan laporan reset ke 0 · Laporan lama diarsipkan', seg: 1 });

  s.rect(60, 420, 1280, 140, { fill: C.teal, label: [
    'Integritas Jejak Audit (*Tamper-Proof*):',
    '• Keputusan BPD yang sudah disimpan TIDAK BISA diedit atau dihapus.',
    '• Koreksi atau perubahan fakta lapangan hanya bisa ditambahkan via "Catatan Susulan" bertanggal (F15).',
    '• Identitas pelapor warga dilindungi: BPD hanya melihat "Pelapor A/B/C", publik hanya melihat jumlah orang.',
  ], size: 15 });

  s.save(`${OUT}/d4-siklus-bpd.png`);
}

// -------------------------------------------------------------
// Diagram 5: Timeline Pengerjaan 32 Hari (Dev 1 & Dev 2)
// -------------------------------------------------------------
{
  const s = new Sketch(1400, 680, 205);
  s.text(700, 38, 'Rencana Kalender Eksekusi 32 Hari (16 Nov - 17 Des 2026)', { size: 26, bold: true });

  // Phase bars
  s.rect(40, 100, 1320, 70, { fill: C.violet, label: ['Fase 0 & 1: Persiapan, Docker Setup, Spike AI Gemini & Perbup Gianyar (16 - 22 Nov)'], size: 16 });

  // Dev 1
  s.text(120, 210, 'Dev 1 (Ketua / Public & Citizen)', { size: 16, bold: true });
  s.rect(260, 230, 280, 90, { fill: C.blue, label: ['Fitur 2: Halaman Publik', 'Vite 360px · Dokumen Asli · QR', '23 - 26 Nov'], size: 14 });
  s.rect(580, 230, 380, 90, { fill: C.orange, label: ['Fitur 3: Cek Spek & Laporan', 'GPS Lock <=300m · Direct Camera · Google Auth', '27 Nov - 3 Des'], size: 14 });
  s.rect(1000, 230, 360, 90, { fill: C.yellow, label: ['Fitur 5: Mode Demo Juri', 'Data Desa Contoh · Skenario Demo', '4 - 5 Des'], size: 14 });

  // Dev 2
  s.text(120, 360, 'Dev 2 (Core Engine & Oversight)', { size: 16, bold: true });
  s.rect(260, 380, 480, 90, { fill: C.green, label: ['Fitur 1: Audit RAB Engine', 'AI Parser Job · Perbup Matcher · Layar Koreksi · Terbit', '23 Nov - 1 Des'], size: 14 });
  s.rect(780, 380, 580, 90, { fill: C.violet, label: ['Fitur 4: Dashboard BPD & Siklus Status', 'Daftar Proyek · Review Anonim · Ambang Eskalasi · Note Publik', '2 - 5 Des'], size: 14 });

  // Milestone: 26 Nov Kerangka Jalan
  s.diamond(540, 200, 200, 70, { fill: C.red, label: ['26 Nov: Kerangka Jalan!', 'Coba RAB -> Terbit -> QR'], size: 13 });

  // Buffer & Submit
  s.rect(40, 500, 680, 80, { fill: C.teal, label: ['Integrasi, Polishing & Stress Test Docker (6 - 11 Des)'], size: 16 });
  const freeze = s.rect(760, 500, 600, 80, { fill: C.red, label: ['12 Des: FEATURE FREEZE', 'Uji 2 HP Android · Rekam Video · Submit 17 Des (H-1)'], size: 15 });

  s.text(700, 630, 'Target: Submit H-1 (17 Des 2026 23:59 WIB) · Karya Stabil & Teruji', { size: 17, bold: true, color: '#c92a2a' });

  s.save(`${OUT}/d5-roadmap-sprint.png`);
}

console.log('✅ All 5 architectural diagrams generated successfully into SONAR-DESA/docs/img/');
