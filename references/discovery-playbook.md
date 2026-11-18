# Fase 1 — Discovery Playbook

Tujuan fase ini: menghasilkan **Fact Sheet** — peta kondisi nyata codebase yang relevan dengan fitur, lengkap dengan `path:line`, risiko tersembunyi, dan temuan di luar scope. Semua bagian dokumen yang bersifat teknis berasal dari sini.

---

## 1. Strategi umum

1. **Tentukan pertanyaan dulu, baru baca kode.** Dari penjelasan user, turunkan 5–8 pertanyaan yang harus terjawab. Contoh untuk fitur "credential per tenant":
   - Bagaimana tenant diidentifikasi (host, header, param)?
   - DB central vs DB tenant — di mana data sejenis disimpan sekarang?
   - Di mana saja credential dipakai saat runtime (semua titik konsumsi)?
   - Bagaimana admin panel (extranet) dirouting, diautentikasi, dan pola CRUD-nya?
   - Apa yang dikonfigurasi saat build (mobile) vs saat runtime?
   - Ada pola existing yang bisa ditiru (mis. tabel setting per tenant di central)?
2. **Pecah per repo/lapisan dan jalankan paralel** jika ada sub-agent (mis. satu untuk backend, satu untuk frontend/mobile). Setiap agent mendapat daftar pertanyaan bernomor dan format output yang sama.
3. **Verifikasi silang.** Setelah agent selesai, baca sendiri 2–5 titik kode yang paling menentukan keputusan (fungsi yang akan diubah urutannya, config yang akan diganti, middleware yang akan dilewati). Ringkasan agent bisa salah atau terlalu umum.
4. **Catat insight non-obvious** — ini nilai terbesar fase ini (lihat bagian 4).

## 2. Template prompt sub-agent

Gunakan pola ini (sesuaikan isi bernomor). Prompt harus *self-contained* karena sub-agent tidak melihat percakapan.

```text
Very thorough analysis of the <framework> project at <absolute path>.
Do NOT print secret values from .env or config files (key NAMES only).
I need a factual report with file paths and line numbers on:

1. Versions and key packages relevant to <topic> (from composer.json / package.json).
2. <Architecture question 1, e.g. how a tenant is identified; central vs tenant DB; middleware>.
3. <Admin/UI question: routing, auth guard, menu location, an existing CRUD pattern to copy>.
4. <Every runtime consumer of the thing we are changing — list all call sites>.
5. <Build-time vs runtime configuration, if mobile/frontend is involved>.
6. <Existing similar pattern we can reuse (settings table, feature flags, audit log)>.
7. <Caching, queues/workers, encryption — anything that could hold stale state>.
8. Relevant docs in /docs.

Return a structured, concise but complete report with path:line references.
Flag anything that would silently break if we change <topic>.
```

Tips:
- Minta *"every call site"* untuk hal yang akan diganti — titik konsumsi yang terlewat adalah sumber bug utama.
- Minta *"flag anything that would silently break"* — ini memancing insight non-obvious.
- Selalu larang menyalin nilai secret.

## 3. Checklist area yang hampir selalu relevan

| Area | Yang dicari |
|---|---|
| Stack & versi | Framework, library kunci (versi menentukan API yang tersedia) |
| Identitas tenant / konteks | Header/host/param, middleware, route yang mem-bypass |
| Data | Migration & model terkait, central vs tenant, kolom yang dibatasi library |
| Titik konsumsi | Semua controller/domain/job yang memakai hal yang diubah |
| Admin UI | Layout, menu, router, guard, contoh halaman list + form + modal |
| Auth & permission | Middleware/guard, level akses |
| State tersembunyi | Static singleton, cache (driver & tag), worker long-running, config di-cache |
| Build-time config | Env frontend, file konfigurasi mobile, script build, CI per tenant |
| Pola yang bisa ditiru | Tabel setting serupa, audit log, feature flag |
| Dokumen lama | PRD/TDD/draft terkait — apa yang masih valid, apa yang sudah tidak cocok dengan kode |
| Kesehatan | Bug & isu security yang terlihat sambil lalu (dicatat sebagai OUT) |

## 4. Insight non-obvious — pola yang harus dicari

Pertanyaan pemandu: **"Kalau kita ubah ini dengan cara paling naif, apa yang akan diam-diam salah?"**

Pola yang sering muncul:
- **Urutan eksekusi salah**: data yang dibutuhkan (mis. tenant) baru diketahui *setelah* langkah yang membutuhkannya.
- **State yang menempel di proses**: static property, singleton, driver yang di-cache library, config cache — aman di request web, salah di queue worker.
- **Library membuang data diam-diam**: atribut/kolom yang tidak didaftarkan dianggap virtual dan hilang saat save.
- **Konfigurasi build-time vs runtime**: pengaturan di admin hanya mengubah backend, sementara app mobile membawa nilai dari build → harus konsisten.
- **Route terdaftar ganda**: endpoint yang sama didaftarkan di dua file route; menambah satu saja = bug di salah satu host.
- **Cache dengan tag / driver berbeda per environment**: berjalan di production (redis) tapi gagal di staging (file).
- **Dokumen lama yang salah**: draft lama memakai kolom/fitur yang tidak ada di kode.

Setiap insight seperti ini nantinya muncul di dokumen sebagai: callout **Penting**, baris di tabel **Edge cases**, aturan di **Lakukan/Hindari**, atau keputusan di **Decision log**.

## 5. Fact Sheet (catatan kerja internal)

Isi sebelum masuk Fase 2. Tidak perlu ditampilkan utuh ke user, tapi semua isinya dipakai.

```markdown
## Fact Sheet — <fitur>

### Stack
- Backend: <framework/versi>, packages: <...>
- Frontend: <framework/versi>, mobile: <...>

### Kondisi sekarang (per area)
| Area | Kondisi | path:line | Dampak ke fitur |
|---|---|---|---|

### Titik perubahan (semua call site)
- <file:line> — <apa yang harus berubah>

### Pola yang bisa ditiru
- <tabel/kelas/halaman> — <kenapa cocok>

### Insight non-obvious
1. <insight> — bukti: <path:line> — konsekuensi: <...>

### Di luar scope (catat, jangan kerjakan)
- <temuan> — <lokasi> — <usulan: tiket terpisah>

### Masih ambigu (kandidat pertanyaan Fase 2)
- <hal> — kenapa tidak bisa dijawab dari kode
```

## 6. Output ke user di akhir Fase 1

Bisa digabung dengan Fase 2 dalam satu pesan. Bagian "kondisi sekarang" berisi:
- 1 paragraf arsitektur relevan (singkat).
- Tabel: Area | Kondisi sekarang | Dampak untuk fitur.
- **Insight penting** (1–3 poin, bold).
- **Temuan di luar scope** (bernomor, singkat, dengan saran: tiket terpisah).

Jangan tampilkan seluruh laporan agent mentah — ringkas jadi keputusan-relevan.
