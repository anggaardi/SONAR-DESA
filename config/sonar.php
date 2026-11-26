<?php

declare(strict_types=1);

// Product rules and limits. Read through config('sonar.*'), never env() in code,
// so values can be calibrated (PRD Fitur QF2-QF4) without touching logic.
return [

    'display_timezone' => env('DISPLAY_TIMEZONE', 'Asia/Makassar'),

    // Short git SHA baked in at deploy (F0-4, shown in the footer).
    'commit' => env('GIT_COMMIT', 'dev'),

    'rab_reader' => [
        // "ai" in production, "fixture" in tests, CI, and when AI must be switched off.
        'driver' => env('RAB_READER', 'fixture'),
        'timeout_seconds' => (int) env('RAB_READ_TIMEOUT_SECONDS', 60),
        'ai' => [
            'provider' => env('AI_PROVIDER', 'databyte'),
            'base_url' => env('AI_BASE_URL', 'https://ai.databyte.co.id/gateway/v1'),
            'api_key' => env('AI_API_KEY'),
            'model' => env('AI_MODEL', 'google/gemini-1.5-flash'),
        ],
    ],

    // Global daily AI budget (C5). 0 disables AI reads and falls back to "Isi manual".
    'ai_budget' => [
        'daily_reads' => (int) env('AI_DAILY_READ_LIMIT', 150),
        'demo_daily_reads' => (int) env('AI_DEMO_DAILY_READ_LIMIT', 60),
    ],

    'price' => [
        // PRD Fitur 3.4: volume x unit price may differ from amount by at most this many rupiah.
        'row_tolerance_rp' => (int) env('PRICE_ROW_TOLERANCE_RP', 1000),
    ],

    'report' => [
        'radius_m' => (int) env('REPORT_RADIUS_M', 300),
        'max_accuracy_m' => (int) env('REPORT_MAX_ACCURACY_M', 100),
        // PRD Fitur Inti 5.1 steps 6-7: 1-3 camera photos, note up to 280 characters.
        'max_photos' => (int) env('REPORT_MAX_PHOTOS', 3),
        'max_note_length' => (int) env('REPORT_MAX_NOTE_LENGTH', 280),
        // C10: sends per account per project per day (display timezone); the next one is refused.
        'max_per_day' => (int) env('REPORT_MAX_PER_DAY', 3),

        // Public distance hint (D-008): coarse on purpose so repeated probes cannot pin down the project point.
        'distance_hint_round_m' => (int) env('REPORT_DISTANCE_HINT_ROUND_M', 10),
        'distance_hint_max_m' => (int) env('REPORT_DISTANCE_HINT_MAX_M', 1000),
        'distance_hints_per_minute' => (int) env('REPORT_DISTANCE_HINTS_PER_MINUTE', 30),
        // D-010: IP is only a rate limit, never stored. Loose on purpose: with CGNAT many warga share one IP.
        'submits_per_minute' => (int) env('REPORT_SUBMITS_PER_MINUTE', 20),
    ],

    'review' => [
        'threshold' => (int) env('REVIEW_THRESHOLD', 3),
    ],

    // Measurement templates by project type for Cek Spek (PRD Fitur Inti 3.5 & F19).
    'measurement_templates' => [
        'rabat_beton' => [
            ['name' => 'Ketebalan Beton Cor', 'unit' => 'cm', 'default_value' => '12.0', 'how_to' => 'Ukur ketebalan cor di batas bekisting tepi jalan pada minimal 3 titik berbeda.'],
            ['name' => 'Lebar Badan Jalan', 'unit' => 'meter', 'default_value' => '2.50', 'how_to' => 'Rentangkan meteran tegak lurus dari tepi kiri ke tepi kanan jalan rabat beton.'],
            ['name' => 'Panjang Total Pekerjaan', 'unit' => 'meter', 'default_value' => '450.0', 'how_to' => 'Ukur bentang jalan dari titik nol prasasti proyek sampai titik akhir pengecoran.'],
        ],
        'saluran_irigasi' => [
            ['name' => 'Panjang Saluran', 'unit' => 'meter', 'default_value' => '300.0', 'how_to' => 'Ukur panjang bentang saluran drainase/irigasi dari hulu ke hilir.'],
            ['name' => 'Lebar Dalam Saluran', 'unit' => 'cm', 'default_value' => '60.0', 'how_to' => 'Ukur bukaan lebar bersih penampang dalam saluran air.'],
            ['name' => 'Tinggi Dinding Saluran', 'unit' => 'cm', 'default_value' => '80.0', 'how_to' => 'Ukur kedalaman dari bibir atas pasangan batu hingga dasar saluran.'],
        ],
        'gedung' => [
            ['name' => 'Panjang Bangunan', 'unit' => 'meter', 'default_value' => '15.0', 'how_to' => 'Ukur sisi terpanjang dinding luar bangunan.'],
            ['name' => 'Lebar Bangunan', 'unit' => 'meter', 'default_value' => '10.0', 'how_to' => 'Ukur sisi pendek dinding luar bangunan.'],
            ['name' => 'Tinggi Dinding Bangunan', 'unit' => 'meter', 'default_value' => '3.80', 'how_to' => 'Ukur tinggi vertikal dari lantai finish hingga batas ring balok plafon.'],
        ],
        'lampu_jalan' => [
            ['name' => 'Jumlah Titik Lampu', 'unit' => 'unit', 'default_value' => '20', 'how_to' => 'Hitung fisik tiang lampu PJU yang terpasang di sepanjang ruas jalan.'],
            ['name' => 'Tinggi Tiang Lampu', 'unit' => 'meter', 'default_value' => '6.0', 'how_to' => 'Ukur tinggi vertikal tiang dari base plate fondasi hingga armatur lampu.'],
        ],
        'lainnya' => [
            ['name' => 'Volume Utama Pekerjaan', 'unit' => 'unit', 'default_value' => '1.0', 'how_to' => 'Ukur atau hitung kesesuaian fisik pekerjaan utama di lokasi.'],
        ],
    ],

    'demo' => [
        // The Desa Contoh village that every demo session clones (PRD Arsitektur 6.7).
        'template_slug' => env('DEMO_TEMPLATE_SLUG', 'desa-contoh-template'),
        'upload_limit' => (int) env('DEMO_UPLOAD_LIMIT', 3),
        'ttl_hours' => (int) env('DEMO_TTL_HOURS', 24),
        // M6-05: "Pakai foto & lokasi contoh" puts the visitor this far from the project, well inside the radius.
        'sample_distance_m' => (int) env('DEMO_SAMPLE_DISTANCE_M', 20),
        'sample_accuracy_m' => (int) env('DEMO_SAMPLE_ACCURACY_M', 10),
    ],

];
