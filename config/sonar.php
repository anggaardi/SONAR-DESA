<?php

declare(strict_types=1);

// Product rules and limits. Read through config('sonar.*'), never env() in code,
// so values can be calibrated (PRD Fitur QF2-QF4) without touching logic.
return [

    'display_timezone' => env('DISPLAY_TIMEZONE', 'Asia/Makassar'),

    'rab_reader' => [
        // "ai" in production, "fixture" in tests, CI, and when AI must be switched off.
        'driver' => env('RAB_READER', 'fixture'),
        'timeout_seconds' => (int) env('RAB_READ_TIMEOUT_SECONDS', 60),
        'ai' => [
            'provider' => env('AI_PROVIDER'),
            'model' => env('AI_MODEL'),
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
    ],

    'review' => [
        'threshold' => (int) env('REVIEW_THRESHOLD', 3),
    ],

    'demo' => [
        'upload_limit' => (int) env('DEMO_UPLOAD_LIMIT', 3),
        'ttl_hours' => (int) env('DEMO_TTL_HOURS', 24),
    ],

];
