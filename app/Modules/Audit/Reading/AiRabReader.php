<?php

declare(strict_types=1);

namespace App\Modules\Audit\Reading;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * AI Vision Document Reader connecting to an OpenAI-compatible API Gateway (such as DataByte).
 *
 * Implements structured extraction of RAB documents (PDF / image) and detects
 * blurry, ambiguous, or damaged fields (PRD Fitur Inti 3.4 & PRD Arsitektur 6.5).
 */
final readonly class AiRabReader implements RabReader
{
    public const string RAB_PROMPT_V1 = <<<'PROMPT'
Anda adalah sistem AI Vision khusus untuk mengekstrak data dari dokumen Rencana Anggaran Biaya (RAB) proyek fisik desa di Indonesia.

Tugas Anda:
1. Analisis gambar / lembar dokumen RAB yang dilampirkan secara visual.
2. Ekstrak setiap baris rincian item bahan material, upah tukang/pekerja, dan sewa alat ke dalam daftar array "rows".
3. Abaikan baris judul bab, header kolom tabel, baris subtotal, dan baris total di dalam array "rows".
4. Jika di lembar dokumen tertera nilai total keseluruhan tertulis (misal "Total Biaya", "Jumlah Total Realisasi", atau grand total di footer dokumen), masukkan angka bulat tersebut ke field "written_total_rp". Jika tidak ditemukan, isi null.
5. DETEKSI BAGIAN SULIT DIBACA / NGEBLUR / RUSAK (doubt_fields):
   Untuk setiap baris yang Anda baca, periksa kejelasan visualnya:
   - Jika ada teks atau angka yang KABUR (ngeblur), TINTA PUDAR/HILANG, CACAT CETAK/LIPATAN KERTAS, STAMPEL MENIMPA ANGKA, atau SULIT DIBACA DENGAN YAKIN:
     WAJIB masukkan nama field tersebut ke dalam array "doubt_fields" (pilihan nama field: "raw_name", "volume", "unit", "unit_price_rp", "amount_rp").
     Contoh: jika angka volume tidak jelas terbaca -> "doubt_fields": ["volume"].
     Contoh: jika harga satuan tertutup stempel -> "doubt_fields": ["unit_price_rp"].
   - Jika seluruh teks dan angka pada baris tersebut terbaca sangat tajam, jelas, dan pasti: kosongkan array -> "doubt_fields": [].
6. PENCOCOKAN KODE STANDAR (standard_code):
   Cocokkan "raw_name" dengan daftar acuan standar yang diberikan pengguna HANYA jika jenis barang dan satuannya bersesuaian.
   - Jika cocok: isi "standard_code" dengan kode item standar tersebut.
   - Jika TIDAK cocok atau tidak terdaftar: WAJIB isi "standard_code": null.
7. Format Nilai Angka:
   - "position": integer urutan baris (1, 2, 3, ...)
   - "raw_name": teks asli item di dokumen
   - "volume": string angka desimal/bulat (misal "380", "1.5", "12.25"). Jangan sertakan nama satuan di volume.
   - "unit": satuan item (misal "sak", "m3", "kg", "btg", "OH", "ls")
   - "unit_price_rp": integer rupiah harga satuan tanpa simbol Rp atau pemisah ribuan (misal 85000)
   - "amount_rp": integer rupiah jumlah total baris tanpa Rp atau pemisah ribuan (misal 32300000)

Format keluaran WAJIB berupa objek JSON valid dengan struktur:
{
  "written_total_rp": 105000000,
  "rows": [
    {
      "position": 1,
      "raw_name": "Semen Portland 50 kg",
      "volume": "380",
      "unit": "sak",
      "unit_price_rp": 85000,
      "amount_rp": 32300000,
      "standard_code": "MAT-001",
      "doubt_fields": []
    }
  ]
}
PROMPT;

    public function __construct(
        private ?string $baseUrl = null,
        private ?string $apiKey = null,
        private ?string $model = null,
        private ?int $timeoutSeconds = null,
    ) {}

    /**
     * Read and extract structured rows from an RAB document using AI Vision.
     *
     * @param  string  $filePath
     * @param  list<StandardRef>  $standards
     */
    public function read(string $filePath, array $standards): RabReadResult
    {
        $startTime = microtime(true);

        if (! file_exists($filePath)) {
            return new RabReadResult(
                rows: [],
                readMs: 0,
                errorCode: 'FILE_NOT_FOUND',
            );
        }

        $baseUrl = $this->baseUrl ?? (string) config('sonar.rab_reader.ai.base_url', 'https://ai.databyte.co.id/v1');
        $apiKey = $this->apiKey ?? (string) config('sonar.rab_reader.ai.api_key', '');
        $model = $this->model ?? (string) config('sonar.rab_reader.ai.model', 'google/gemini-1.5-flash');
        $timeout = $this->timeoutSeconds ?? (int) config('sonar.rab_reader.timeout_seconds', 60);

        if (blank($apiKey)) {
            return new RabReadResult(
                rows: [],
                readMs: 0,
                errorCode: 'AI_KEY_MISSING',
            );
        }

        // 1. Prepare Base64 Data URL for Vision
        $fileBytes = file_get_contents($filePath);
        if ($fileBytes === false) {
            return new RabReadResult(
                rows: [],
                readMs: 0,
                errorCode: 'FILE_READ_ERROR',
            );
        }

        $mimeType = $this->detectMimeType($filePath);
        $dataUrl = 'data:'.$mimeType.';base64,'.base64_encode($fileBytes);

        // 2. Format standard catalog text (names and units only, no prices: PRD Arsitektur 6.5)
        $catalogPrompt = $this->buildCatalogPrompt($standards);
        $validStandardCodes = array_map(static fn (StandardRef $ref): string => $ref->code, $standards);

        // 3. Request OpenAI-compatible chat/completions endpoint
        $endpoint = rtrim($baseUrl, '/').'/chat/completions';

        try {
            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->post($endpoint, [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => self::RAB_PROMPT_V1,
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => $catalogPrompt,
                                ],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => $dataUrl,
                                        'detail' => 'high',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.1,
                ]);

            $elapsedMs = (int) round((microtime(true) - $startTime) * 1000);

            if ($response->failed()) {
                Log::warning('rab.read.ai_failed', [
                    'status' => $response->status(),
                    'ms' => $elapsedMs,
                    'error' => $response->body(),
                ]);

                return new RabReadResult(
                    rows: [],
                    readMs: $elapsedMs,
                    errorCode: 'AI_REQUEST_FAILED',
                    rawResponse: $response->body(),
                );
            }

            $responseData = $response->json();
            $rawContent = $responseData['choices'][0]['message']['content'] ?? '';

            if (empty($rawContent)) {
                return new RabReadResult(
                    rows: [],
                    readMs: $elapsedMs,
                    errorCode: 'EMPTY_AI_RESPONSE',
                );
            }

            // 4. Parse & Validate Structured Output
            $parsed = json_decode($rawContent, true);
            if (! is_array($parsed)) {
                return new RabReadResult(
                    rows: [],
                    readMs: $elapsedMs,
                    errorCode: 'INVALID_JSON_RESPONSE',
                    rawResponse: $rawContent,
                );
            }

            $writtenTotalRp = isset($parsed['written_total_rp']) && is_numeric($parsed['written_total_rp'])
                ? (int) $parsed['written_total_rp']
                : null;

            $rows = $this->sanitizeRows($parsed['rows'] ?? [], $validStandardCodes);

            Log::info('rab.read', [
                'model' => $model,
                'items' => count($rows),
                'ms' => $elapsedMs,
            ]);

            return new RabReadResult(
                rows: $rows,
                writtenTotalRp: $writtenTotalRp,
                readMs: $elapsedMs,
                rawResponse: $rawContent,
            );
        } catch (Throwable $e) {
            $elapsedMs = (int) round((microtime(true) - $startTime) * 1000);

            Log::error('rab.read.exception', [
                'ms' => $elapsedMs,
                'message' => $e->getMessage(),
            ]);

            return new RabReadResult(
                rows: [],
                readMs: $elapsedMs,
                errorCode: 'AI_TIMEOUT',
                rawResponse: $e->getMessage(),
            );
        }
    }

    /**
     * @param  list<StandardRef>  $standards
     */
    private function buildCatalogPrompt(array $standards): string
    {
        if (empty($standards)) {
            return "Silakan baca dokumen RAB terlampir dan ekstrak datanya sesuai instruksi JSON.\nTidak ada daftar kode standar acuan yang aktif.";
        }

        $lines = ["Daftar Katalog Standar Acuan Resmi (Kode, Nama, Satuan - Tanpa Harga):"];
        foreach ($standards as $standard) {
            $lines[] = "- Kode: {$standard->code} | Nama: {$standard->name} | Satuan: {$standard->unit}";
        }
        $lines[] = "\nSilakan baca dokumen RAB terlampir dan cocokkan item ke kode standar di atas.";

        return implode("\n", $lines);
    }

    /**
     * Determine MIME type from file extension or content.
     */
    private function detectMimeType(string $filePath): string
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    /**
     * Validate, normalize numbers, strip invalid standards, and collect doubt fields.
     *
     * @param  array<mixed>  $rawRows
     * @param  list<string>  $validStandardCodes
     * @return list<RabReadRow>
     */
    private function sanitizeRows(array $rawRows, array $validStandardCodes): array
    {
        $sanitized = [];
        $positionCounter = 1;

        foreach ($rawRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rawName = trim((string) ($row['raw_name'] ?? ''));
            if ($rawName === '') {
                continue;
            }

            $doubtFields = [];
            if (isset($row['doubt_fields']) && is_array($row['doubt_fields'])) {
                foreach ($row['doubt_fields'] as $field) {
                    if (is_string($field)) {
                        $doubtFields[] = trim($field);
                    }
                }
            }

            // Normalisasi volume (format angka lokal Indonesia: 1.250,5 -> 1250.5)
            $rawVolume = isset($row['volume']) ? trim((string) $row['volume']) : null;
            $volume = $this->normalizeNumberString($rawVolume);
            if ($rawVolume !== null && $volume === null) {
                $doubtFields[] = 'volume';
            }

            $unit = isset($row['unit']) ? trim((string) $row['unit']) : null;

            // Unit Price Rp (uang integer murni)
            $unitPriceRp = null;
            if (isset($row['unit_price_rp']) && is_numeric($row['unit_price_rp'])) {
                $unitPriceInt = (int) $row['unit_price_rp'];
                if ($unitPriceInt >= 0) {
                    $unitPriceRp = $unitPriceInt;
                } else {
                    $doubtFields[] = 'unit_price_rp';
                }
            }

            // Amount Rp (uang integer murni)
            $amountRp = null;
            if (isset($row['amount_rp']) && is_numeric($row['amount_rp'])) {
                $amountInt = (int) $row['amount_rp'];
                if ($amountInt >= 0) {
                    $amountRp = $amountInt;
                } else {
                    $doubtFields[] = 'amount_rp';
                }
            }

            // standardCode validation (PRD Arsitektur 6.5: standardCode yang tidak ada di daftar dibuang jadi null)
            $rawStandardCode = isset($row['standard_code']) ? trim((string) $row['standard_code']) : null;
            $standardCode = null;
            if (! empty($rawStandardCode) && in_array($rawStandardCode, $validStandardCodes, true)) {
                $standardCode = $rawStandardCode;
            }

            $doubtFields = array_values(array_unique($doubtFields));

            $position = isset($row['position']) && is_int($row['position']) && $row['position'] > 0
                ? $row['position']
                : $positionCounter;

            $sanitized[] = new RabReadRow(
                position: $position,
                rawName: $rawName,
                volume: $volume,
                unit: $unit,
                unitPriceRp: $unitPriceRp,
                amountRp: $amountRp,
                standardCode: $standardCode,
                doubtFields: $doubtFields,
            );

            $positionCounter++;
        }

        return $sanitized;
    }

    /**
     * Normalize decimal representation (e.g. "1.250,5" or "1,250.5" -> "1250.5").
     */
    private function normalizeNumberString(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $cleaned = trim($value);

        // Pattern Indonesia: 1.250,50 (ribuan titik, desimal koma)
        if (preg_match('/^\d{1,3}(\.\d{3})*,\d+$/', $cleaned)) {
            $cleaned = str_replace('.', '', $cleaned);
            $cleaned = str_replace(',', '.', $cleaned);
        } elseif (preg_match('/^\d+,\d+$/', $cleaned)) {
            // e.g. "12,5"
            $cleaned = str_replace(',', '.', $cleaned);
        } elseif (preg_match('/^\d{1,3}(,\d{3})*\.\d+$/', $cleaned)) {
            // Pattern US: 1,250.50
            $cleaned = str_replace(',', '', $cleaned);
        }

        return is_numeric($cleaned) ? $cleaned : null;
    }
}
