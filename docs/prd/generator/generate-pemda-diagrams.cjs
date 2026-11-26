// Diagram untuk Feature Spec Peran Pemda (Pemerintah Kabupaten) Sonar Desa.
// Menggunakan sketch.js dari MEMSKILL (lean-feature-spec), sama seperti generate-pemdes-diagrams.cjs.
const path = require('path');
const fs = require('fs');
const { Sketch, C, INK, sequence } = require('/home/kadekangga/Projects/MEMSKILL/feature-spec/scripts/lib/sketch');

const OUT = path.resolve(__dirname, 'img');
fs.mkdirSync(OUT, { recursive: true });
const MUTED = '#868e96';

// -------------------------------------------------------------
// PM1: As-Is vs To-Be - siapa pemilik acuan harga
// -------------------------------------------------------------
{
  const s = new Sketch(1400, 640, 401);
  s.rect(25, 75, 650, 545, { dash: true, sw: 1.3, stroke: MUTED, r: 20 });
  s.rect(725, 75, 650, 545, { dash: true, sw: 1.3, stroke: MUTED, r: 20 });
  s.text(350, 42, 'SEKARANG', { size: 28, bold: true, color: '#c92a2a' });
  s.text(1050, 42, 'TARGET', { size: 28, bold: true, color: '#2b8a3e' });

  const seed = s.rect(60, 110, 270, 100, { fill: C.gray, label: ['ProjectSeeder', '8 harga ditulis tim dev', 'tahun 2026'], size: 16 });
  const db = s.cylinder(390, 105, 250, 115, { fill: C.blue, label: ['price_standards', 'regency + year'], size: 16 });
  s.arrow([seed.r, [db.x, seed.cy]]);
  const audit = s.rect(200, 290, 300, 90, { fill: C.gray, label: ['Audit RAB Pemdes', 'label Hijau / Merah / Abu'], size: 16 });
  s.arrow([[db.cx, db.y + 115], [db.cx, 250], [audit.cx + 60, 250], [audit.cx + 60, audit.y]]);
  s.rect(55, 420, 590, 170, {
    fill: C.red, title: false, size: 16, label: [
      'Masalah:',
      '- acuan diisi tim dev, bukan pihak berwenang',
      '- juri: Perbup SKPD 2022 tidak cocok & usang',
      '- item "Tanpa acuan" berhenti di situ, tidak ada yang menindak',
      '- ubah harga = halaman warga yang sudah terbit ikut bergeser',
    ],
  });

  const pemda = s.rect(755, 110, 250, 100, { fill: C.violet, label: ['Pemda Kab. Gianyar', 'role pemda'], size: 17 });
  const kat = s.cylinder(1080, 105, 260, 115, { fill: C.blue, label: ['Katalog acuan', 'per tahun, ada pemilik'], size: 16 });
  s.arrow([pemda.r, [kat.x, pemda.cy]], { label: 'kelola' });
  const aud2 = s.rect(1080, 280, 260, 95, { fill: C.green, label: ['Audit RAB Pemdes', 'harga acuan disalin', '(snapshot)'], size: 16 });
  s.arrow([[kat.cx, kat.y + 115], aud2.t]);
  const q = s.rect(755, 280, 250, 95, { fill: C.yellow, label: ['Antrean', '"Tanpa Acuan"'], size: 17 });
  s.arrow([aud2.l, q.r], { label: 'item tanpa acuan' });
  s.arrow([q.t, pemda.b], { label: 'jadikan acuan' });
  const rk = s.rect(755, 420, 585, 75, { fill: C.orange, label: ['Rekap kabupaten', 'per kecamatan & desa: prioritas pendampingan'], size: 16 });
  s.arrow([[aud2.cx, aud2.b], [aud2.cx, rk.y]], { dash: true });
  s.rect(755, 520, 585, 70, { fill: C.green, title: false, size: 16, label: ['Acuan punya pemilik resmi dan makin lengkap seiring pemakaian.', 'Halaman yang sudah terbit tidak berubah diam-diam.'] });
  s.save(`${OUT}/pm1-asis-tobe.png`);
}

// -------------------------------------------------------------
// PM2: Siklus tata kelola 4 peran
// -------------------------------------------------------------
{
  const s = new Sketch(1400, 600, 402);
  s.text(700, 40, 'Satu siklus: Pemda -> Pemdes -> Warga -> BPD -> kembali ke Pemda', { size: 24, bold: true });
  const pemda = s.rect(80, 110, 330, 170, { fill: C.violet, label: ['1. Pemda (BARU)', 'menetapkan katalog acuan', 'per tahun anggaran', 'menutup item tanpa acuan'], size: 16 });
  const desa = s.rect(990, 110, 330, 170, { fill: C.blue, label: ['2. Pemdes', 'unggah RAB -> AI baca', 'kode melabel vs acuan', 'terbitkan ke /p/{slug}'], size: 16 });
  const warga = s.rect(990, 380, 330, 170, { fill: C.teal, label: ['3. Warga', 'scan QR di lokasi', 'Cek Spek: ukur & foto', 'laporan sesuai / selisih'], size: 16 });
  const bpd = s.rect(80, 380, 330, 170, { fill: C.green, label: ['4. BPD', '>= 3 pelapor selisih valid', 'siklus tinjauan dibuka', 'catatan musyawarah'], size: 16 });
  s.arrow([pemda.r, desa.l], { label: 'katalog acuan (snapshot ke audit)' });
  s.arrow([desa.b, warga.t], { label: 'proyek terbit' });
  s.arrow([warga.l, bpd.r], { label: 'laporan selisih' });
  s.arrow([bpd.t, pemda.b], { label: 'rekap kabupaten', dash: true });
  const mid = s.rect(520, 250, 360, 130, { fill: C.yellow, label: ['Umpan balik ke Pemda', '- item tanpa acuan dari semua desa', '- jumlah item di atas acuan', '- proyek dalam tinjauan BPD'], size: 15 });
  s.arrow([[desa.l[0], 230], [mid.r[0], 290]], { dash: true });
  s.arrow([[mid.l[0], 315], [pemda.r[0], 250]], { dash: true });
  s.save(`${OUT}/pm2-siklus.png`);
}

// -------------------------------------------------------------
// PM3: Arsitektur komponen
// -------------------------------------------------------------
{
  const s = new Sketch(1400, 760, 403);
  s.text(700, 36, 'Komponen baru (oranye) dan yang disentuh (kuning)', { size: 22, bold: true });
  const user = s.person(90, 150, 'Pemda');
  const mw = s.rect(190, 100, 300, 110, { fill: C.orange, label: ['routes /pemda', "auth + role:pemda", 'pemda.write (tolak demo)'], size: 15 });
  s.arrow([[125, 150], mw.l]);
  const c1 = s.rect(560, 80, 300, 70, { fill: C.orange, label: ['PemdaRecapController', 'GET /pemda'], size: 15 });
  const c3 = s.rect(560, 170, 300, 70, { fill: C.orange, label: ['PemdaQueueController', 'GET /pemda/tanpa-acuan'], size: 15 });
  const c2 = s.rect(560, 260, 300, 70, { fill: C.orange, label: ['PemdaStandardController', '/pemda/acuan (CRUD + salin)'], size: 15 });
  [c1, c2, c3].forEach((c) => s.arrow([mw.r, c.l]));
  const rv = s.rect(960, 150, 380, 110, { fill: C.orange, label: ['RegencyVillages (Action)', 'villages.regency = user.regency', 'non-demo + desa demo milik sesi', 'satu-satunya pelepas scope village'], size: 15 });
  s.arrow([c1.r, [rv.x, 180]]);
  s.arrow([c3.r, [rv.x, 225]]);
  const ps = s.cylinder(560, 400, 300, 110, { fill: C.blue, label: ['price_standards', '(existing, tanpa kolom baru)'], size: 15 });
  s.arrow([[c2.cx, c2.b[1]], [c2.cx, 400]], { label: 'tulis' });
  const tbl = s.cylinder(960, 400, 380, 120, { fill: C.blue, label: ['villages (+district)', 'audits, audit_items (+snapshot)', 'projects, reports, review_cycles'], size: 15 });
  s.arrow([[rv.cx, rv.b[1]], [rv.cx, 400]], { label: 'baca saja' });
  const job = s.rect(190, 400, 300, 110, { fill: C.yellow, label: ['Pemdes (existing)', 'ProcessRabDocumentJob', 'AuditItemController, ApplyProjectCco'], size: 15 });
  s.arrow([[job.r[0], 455], [ps.x, 455]], { label: 'baca acuan', dash: true });
  const rd = s.rect(190, 590, 650, 120, { fill: C.yellow, label: ['Titik baca harga acuan -> pakai audit_items.standard_price_rp', 'ProjectResource (halaman warga), AuditReviewController,', 'DesaProjectManageController'], size: 15 });
  s.arrow([[job.cx, job.b[1]], [job.cx, rd.y]], { label: 'simpan snapshot' });
  const demo = s.rect(960, 590, 380, 120, { fill: C.yellow, label: ['Demo (existing)', 'DemoSession: akun pemda per klon', 'pintu "Pemda" di /demo, hanya baca'], size: 15 });
  s.save(`${OUT}/pm3-arsitektur.png`);
}

// -------------------------------------------------------------
// PM4: Wireframe Rekap & Antrean
// -------------------------------------------------------------
{
  const s = new Sketch(1400, 820, 404);
  const panel = (x, title) => {
    s.rect(x, 30, 660, 770, { fill: '#ffffff', fillStyle: 'solid', r: 14 });
    s.rect(x, 30, 660, 54, { fill: C.violet, r: 14, label: title, size: 18 });
  };
  // Rekap
  panel(25, '/pemda  -  Rekap Kabupaten Gianyar  [Tahun 2026 v]');
  const kpi = [['18', 'desa'], ['11', 'desa sudah terbit'], ['37', 'item di atas acuan'], ['2', 'proyek ditinjau BPD']];
  kpi.forEach(([n, t], i) => {
    s.rect(45 + i * 158, 105, 145, 85, { fill: i === 3 ? C.yellow : C.gray, r: 8, label: [n, t], size: 15 });
  });
  s.text(45, 225, 'Per kecamatan', { size: 17, anchor: 'start', bold: true });
  const kh = ['Kecamatan', 'Desa', 'Terbit', 'Di atas', 'Ditinjau'];
  const kx = [55, 270, 360, 460, 570];
  kh.forEach((h, i) => s.text(kx[i], 255, h, { size: 14, anchor: 'start', color: MUTED }));
  [['Ubud', '3', '3', '9', '1'], ['Sukawati', '4', '2', '14', '1'], ['Payangan', '2', '0', '-', '-']].forEach((r, ri) => {
    r.forEach((v, i) => s.text(kx[i], 290 + ri * 32, v, { size: 15, anchor: 'start' }));
  });
  s.text(45, 410, 'Desa (urut: prioritas pendampingan)', { size: 17, anchor: 'start', bold: true });
  const dh = ['Desa', 'Kecamatan', 'Di atas', 'Tanpa acuan', 'Status'];
  const dx = [55, 230, 380, 470, 580];
  dh.forEach((h, i) => s.text(dx[i], 440, h, { size: 14, anchor: 'start', color: MUTED }));
  const badgeFill = { Ditinjau: C.yellow, Terbit: C.green, 'Belum unggah': C.gray };
  [['Contoh Sukawati', 'Sukawati', '8', '5', 'Ditinjau'], ['Desa Contoh', 'Gianyar', '3', '2', 'Terbit'], ['Contoh Payangan', 'Payangan', '-', '-', 'Belum unggah']].forEach((r, ri) => {
    r.slice(0, 4).forEach((v, i) => s.text(dx[i], 475 + ri * 38, v, { size: 15, anchor: 'start' }));
    s.rect(560, 458 + ri * 38, 115, 28, { fill: badgeFill[r[4]], fillStyle: 'solid', r: 12, label: r[4], size: 13 });
  });
  s.rect(45, 600, 620, 90, { fill: C.blue, title: false, size: 14, label: ['Klik baris desa -> buka /d/{slug} (halaman publik desa).', 'Bahasa netral: tidak ada skor, ranking, atau kata "korupsi".', 'Angka hanya dari proyek terbit di tahun yang dipilih.'] });
  s.rect(45, 710, 420, 50, { fill: C.gray, r: 8, label: 'Menu: Rekap | Acuan | Tanpa Acuan', size: 15 });

  // Antrean
  panel(715, '/pemda/tanpa-acuan  -  Item tanpa acuan');
  s.rect(735, 100, 200, 34, { fill: C.violet, fillStyle: 'solid', r: 16, label: 'Belum ada acuan (6)', size: 14 });
  s.rect(945, 100, 200, 34, { fill: C.gray, fillStyle: 'solid', r: 16, label: 'Sudah ada acuan (2)', size: 14 });
  const qh = ['Nama item (contoh)', 'Desa', 'Baris', 'Median antar-desa'];
  const qx = [745, 960, 1025, 1085];
  qh.forEach((h, i) => s.text(qx[i], 170, h, { size: 14, anchor: 'start', color: MUTED }));
  [['Paving block 8 cm', '4', '6', 'Rp 118.000 / m2'], ['Pipa PVC 3 inch', '3', '3', 'Rp 96.500 / btg'], ['Cat tembok 5 kg', '2', '2', '- (< 3 desa)']].forEach((r, ri) => {
    const y = 210 + ri * 62;
    r.forEach((v, i) => s.text(qx[i], y, v, { size: 15, anchor: 'start' }));
    s.rect(1235, y - 22, 120, 32, { fill: C.green, fillStyle: 'solid', r: 8, label: 'Jadikan acuan', size: 13 });
  });
  s.rect(735, 420, 620, 250, { fill: '#ffffff', fillStyle: 'solid', r: 12, dash: true });
  s.text(755, 450, 'Modal "Jadikan acuan" (tahun 2026)', { size: 16, anchor: 'start', bold: true });
  [['Kode', 'MAT-PVB-08'], ['Nama', 'Paving block 8 cm'], ['Satuan', 'm2'], ['Harga (Rp)', '_______   pembanding: Rp 118.000'], ['Sumber', 'Perbup Gianyar No. .../2026']].forEach(([l, v], i) => {
    s.text(755, 490 + i * 34, l, { size: 14, anchor: 'start', color: MUTED });
    s.text(880, 490 + i * 34, v, { size: 14, anchor: 'start' });
  });
  s.rect(1215, 620, 120, 34, { fill: C.blue, fillStyle: 'solid', r: 8, label: 'Simpan', size: 14 });
  s.rect(735, 690, 620, 90, { fill: C.yellow, title: false, size: 14, label: ['Median hanya bila >= 3 desa dengan satuan sama.', 'Label: "Pembanding antar-desa, bukan standar resmi".', 'Mode demo: tombol nonaktif + keterangan "Mode demo".'] });
  s.save(`${OUT}/pm4-wireframe.png`);
}

// -------------------------------------------------------------
// PM5: ERD perubahan skema
// -------------------------------------------------------------
{
  const s = new Sketch(1320, 600, 405);
  const ent = (x, y, w, title, rows, fill, tag) => {
    const rh = 27;
    const h = 50 + rows.length * rh + 10;
    s.rect(x, y, w, h, { fill: '#ffffff', fillStyle: 'solid', r: 10 });
    s.rect(x, y, w, 46, { fill, fillStyle: 'hachure', r: 10, label: title, size: 19, bold: true });
    if (tag) {
      s.text(x + w - 8, y - 10, tag, { size: 14, anchor: 'end', color: tag.startsWith('+') ? '#2b8a3e' : MUTED, bold: tag.startsWith('+') });
    }
    rows.forEach((r, i) => {
      const [k, t, c] = r;
      s.text(x + 14, y + 72 + i * rh, k, { size: 15, anchor: 'start', color: c || INK });
      s.text(x + w - 14, y + 72 + i * rh, t, { size: 13, anchor: 'end', color: MUTED });
    });
    return { x, y, w, h, rowY: (i) => y + 66 + i * rh };
  };
  const NEW = '#2b8a3e';
  const users = ent(40, 50, 300, 'users', [['PK id', 'bigint', '#c92a2a'], ['role', '+ pemda'], ['FK village_id', 'nullable', '#1971c2'], ['regency', 'nullable, BARU', NEW]], C.gray, '+1 kolom');
  const villages = ent(40, 290, 300, 'villages', [['PK id', 'bigint', '#c92a2a'], ['regency', 'string'], ['district', 'nullable, BARU', NEW], ['is_demo', 'bool']], C.gray, '+1 kolom');
  const ps = ent(500, 50, 330, 'price_standards', [['PK id', 'bigint', '#c92a2a'], ['regency', 'string'], ['code', 'string'], ['name, unit', 'string'], ['price_rp', 'bigint'], ['year', 'int'], ['source_ref', 'string']], C.gray, 'tanpa perubahan');
  s.text(665, 315, 'UNIQUE (regency, code, year)', { size: 14, color: '#1971c2' });
  const ai = ent(960, 50, 330, 'audit_items', [['PK id', 'bigint', '#c92a2a'], ['FK audit_id', 'bigint', '#1971c2'], ['FK standard_id', 'nullOnDelete', '#1971c2'], ['raw_name, unit', 'string'], ['unit_price_rp', 'bigint'], ['label, over_pct, diff_rp', ''], ['standard_price_rp', 'nullable, BARU', NEW]], C.gray, '+1 kolom');
  s.arrow([[190, users.y + users.h], [190, villages.y]], { head: false });
  s.text(205, users.y + users.h + 18, 'N', { size: 17 });
  s.text(205, villages.y - 8, '1', { size: 17 });
  s.arrow([[830, ps.rowY(0)], [900, ps.rowY(0)], [900, ai.rowY(2)], [960, ai.rowY(2)]], { head: false });
  s.text(845, ps.rowY(0) - 12, '1', { size: 17 });
  s.text(945, ai.rowY(2) - 12, 'N', { size: 17 });
  s.rect(400, 360, 430, 200, { fill: C.yellow, title: false, size: 15, label: ['Catatan:', '- users.regency diisi hanya untuk role pemda', '- nilainya HARUS sama persis dengan', '  villages.regency ("Gianyar")', '- villages.district = nama kecamatan', '- tidak ada tabel baru'] });
  s.rect(900, 360, 390, 200, { fill: C.green, title: false, size: 15, label: ['standard_price_rp:', '- diisi setiap kali PriceLabeler jalan', '- backfill dari price_standards', '  saat migrate', '- semua tampilan harga acuan', '  membaca kolom ini'] });
  s.save(`${OUT}/pm5-erd.png`);
}

// -------------------------------------------------------------
// PM6: Sequence - item tanpa acuan sampai audit berikutnya
// -------------------------------------------------------------
{
  const s = new Sketch(1400, 1000, 406);
  const P = [
    { key: 'd', label: 'Pemdes', x: 110, color: C.blue },
    { key: 'job', label: 'ProcessRabDocumentJob', x: 420, color: C.gray },
    { key: 'db', label: 'audit_items /\nprice_standards', x: 760, color: C.blue },
    { key: 'pq', label: 'Pemda (/pemda)', x: 1110, color: C.violet },
  ];
  const end = sequence(s, P, [
    { from: 'd', to: 'job', text: 'unggah RAB (audit A)', n: 1 },
    { from: 'job', to: 'db', text: 'catalogForRegencyAndYear', n: 2 },
    { from: 'job', to: 'db', text: 'simpan item: label no_reference,\nstandard_price_rp = null', n: 3, hl: '#fff3bf' },
    { from: 'pq', to: 'db', text: 'GET /pemda/tanpa-acuan\n(group nama ternormalisasi)', n: 4, hl: '#fff3bf' },
    { from: 'db', to: 'pq', text: 'grup + jumlah desa + median', ret: true, n: 5 },
    { from: 'pq', to: 'db', text: 'POST /pemda/acuan\n(kode, nama, satuan, harga, tahun)', n: 6, hl: '#fff3bf' },
    { note: 'Audit A yang sudah terbit TIDAK diubah. Audit A yang masih draf: acuan baru muncul di pilihan acuan layar review.', over: ['job', 'pq'], pad: 110, color: C.yellow },
    { from: 'd', to: 'job', text: 'unggah RAB (audit B)', n: 7 },
    { from: 'job', to: 'db', text: 'katalog kini berisi acuan baru', n: 8 },
    { from: 'job', to: 'db', text: 'label hijau/merah +\nstandard_price_rp = harga saat itu', n: 9, hl: '#ffd8a8' },
    { note: 'Kuning = berubah. Oranye = snapshot: kalau Pemda ubah harga lagi, audit B tidak bergeser.', over: ['d', 'pq'], pad: 90, color: C.gray },
  ], { top: 20, bw: 220 });
  s.h = end + 10;
  s.save(`${OUT}/pm6-seq-antrean.png`);
}

// -------------------------------------------------------------
// PM7: Epic & timeline per dev
// -------------------------------------------------------------
{
  const s = new Sketch(1400, 860, 407);
  const epic = s.rect(450, 20, 500, 90, { fill: C.violet, label: ['EPIC: Peran Pemda', 'katalog acuan, antrean, rekap kabupaten'], size: 19 });
  const st = [
    [20, 'S1 Fondasi role', ['role, kolom, route', 'seed desa contoh'], 'Dev 2 - 2 hr', C.blue],
    [250, 'S2 Snapshot acuan', ['audit_items', '.standard_price_rp'], 'Dev 1 - 2 hr', C.green],
    [480, 'S3 Katalog acuan', ['CRUD + salin tahun'], 'BE 2 + FE 2.5 hr', C.orange],
    [710, 'S4 Antrean', ['tanpa acuan', '+ median antar-desa'], 'BE 2 + FE 2 hr', C.yellow],
    [940, 'S5 Rekap', ['per kecamatan', '& per desa'], 'BE 2 + FE 3 hr', C.teal],
    [1170, 'S6 Demo Pemda', ['pintu demo,', 'hanya baca'], 'BE 1 + FE 1 hr', C.pink],
  ];
  st.forEach(([x, t, l, e, f]) => {
    const b = s.rect(x, 170, 210, 150, { fill: f, label: [t, ...l, e], size: 15 });
    s.arrow([epic.b, b.t]);
  });
  s.text(700, 350, 'S3-S6 butuh S1. Tampilan harga di S3-S5 mengandalkan S2.', { size: 15, color: MUTED });

  const days = ['27', '28', '29', '30', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];
  const x0 = 130;
  const dw = 78;
  const y0 = 430;
  s.text(40, 395, 'Rencana harian (27 Nov - 12 Des 2026)', { size: 18, anchor: 'start', bold: true });
  days.forEach((d, i) => {
    s.line([[x0 + i * dw, y0], [x0 + i * dw, y0 + 300]], { sw: 0.7, stroke: '#ced4da', roughness: 0.3 });
    s.text(x0 + i * dw + dw / 2, y0 - 12, d, { size: 14, color: MUTED });
  });
  s.line([[x0 + 16 * dw, y0], [x0 + 16 * dw, y0 + 300]], { sw: 0.7, stroke: '#ced4da', roughness: 0.3 });
  const bar = (row, d1, d2, t, f) => s.rect(x0 + d1 * dw + 3, y0 + 14 + row * 70, (d2 - d1) * dw - 6, 50, { fill: f, label: t, size: 14, r: 8 });
  s.text(70, y0 + 45, 'Dev 2', { size: 16, bold: true });
  s.text(70, y0 + 115, 'Dev 1', { size: 16, bold: true });
  s.text(70, y0 + 185, 'QA', { size: 16, bold: true });
  s.text(70, y0 + 255, 'Rilis', { size: 16, bold: true });
  bar(0, 0, 2, 'S1 fondasi', C.blue);
  bar(0, 2, 4, 'S3 BE', C.orange);
  bar(0, 4, 6, 'S4 BE', C.yellow);
  bar(0, 6, 8, 'S5 BE', C.teal);
  bar(0, 8, 9, 'S6 BE', C.pink);
  bar(0, 9, 14, 'perbaiki temuan QA', C.gray);
  bar(1, 0, 2, 'S2 snapshot', C.green);
  bar(1, 2, 4.5, 'S3 FE', C.orange);
  bar(1, 4.5, 6.5, 'S4 FE', C.yellow);
  bar(1, 6.5, 9.5, 'S5 FE', C.teal);
  bar(1, 9.5, 10.5, 'S6 FE', C.pink);
  bar(2, 10.5, 13.5, 'QA end-to-end (Dev 1)', C.red);
  bar(3, 14, 16, 'deploy', C.violet);
  s.rect(40, 760, 1320, 70, { fill: C.yellow, title: false, size: 15, label: ['Kontrak dulu: di hari pertama tiap story, Dev 2 push route + controller dengan props lengkap (boleh data kosong),', 'supaya Dev 1 bisa mulai halaman Vue di hari yang sama. 13-18 Des: video demo & cadangan.'] });
  s.save(`${OUT}/pm7-epic-timeline.png`);
}

console.log('Pemda diagrams generated in img/');
