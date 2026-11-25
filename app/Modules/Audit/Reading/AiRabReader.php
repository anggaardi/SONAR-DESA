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
Anda adalah sistem AI Vision khusus untuk mengekstrak SELURUH produk dan item rincian anggaran dari dokumen Rencana Anggaran Biaya (RAB) desa di Indonesia (baik RAB 1 halaman maupun RAB Siskeudes/APBDes multi-halaman).

ATURAN EKSTRAKSI WAJIB:
1. BACA SELURUH HALAMAN DARI AWAL SAMPAI AKHIR:
   Periksa setiap halaman dokumen tanpa terkecuali. Jangan berhenti di satu halaman atau hanya mencatat halaman rekapitulasi.
2. EKSTRAK SEMUA PRODUK & ITEM YANG MEMILIKI ANGGARAN (LEAF ITEMS):
   Ekstrak semua baris produk/item rincian terkecil (baris bernomor 01., 02., 03., 1., 2., dst.) yang memiliki nilai VOLUME, HARGA SATUAN, dan JUMLAH BIAYA:
   - Material konstruksi (semen, pasir, batu kali, bata merah, besi beton, koral, kayu, rangka baja ringan, atap, kalsiboard, keramik, cat, paku, beton readymix, dll.)
   - Peralatan, elektronik, mebelair, dan sarana fisik (CCTV, kamera IPCam, monitor, kabel fiber/LAN, laptop, komputer, AC, kursi, terop, sound system, alat kesehatan, timbangan, tensi, senter, dll.)
   - Barang perlengkapan, ATK (kertas HVS, tinta printer, map, bolpoin, buku, materai, lakban, gunting, dll.), cetakan/fotocopy, banner/baliho, alat kebersihan (sapu, ember, tong sampah, dll.), seragam, BBM, pelumas/oli, suku cadang, bibit tanaman, susu/PMT/obat, konsumsi
   - Upah pekerja/tukang, sewa alat/molen, honorarium, jasa servis/pemeliharaan, dan rincian beranggaran lainnya.
3. JANGAN MENCATAT BARIS KATEGORI / KELOMPOK REKENING:
   Abaikan baris judul bidang, sub-bidang, kegiatan, atau kode rekening induk yang kolom Volume dan Harga Satuannya kosong (seperti "5. BELANJA", "5.2.1. Belanja Barang Perlengkapan", "5.2.1.01. Belanja Alat Tulis Kantor", "5.3.4.03. Belanja Modal ... - Bahan Baku/Material", "Sub Total", "JUMLAH (Rp)").
4. FORMAT BARIS RINGKAS (COMPACT TUPLE):
   Agar cepat dan tidak terpotong pada PDF tebal, setiap item di dalam "rows" WAJIB ditulis sebagai array 5 elemen:
   ["raw_name", "volume", "unit", unit_price_rp, amount_rp]
   - Hanya tambahkan elemen ke-6 ("standard_code") jika cocok dengan katalog standar resmi, dan elemen ke-7 (array "doubt_fields") jika teks pada dokumen kabur/sulit dibaca.
   - Pisahkan angka volume dan satuan bila tertulis menyatu (misal "40 Rim" -> volume "40", unit "Rim"; "21,89 M3" -> volume "21.89", unit "M3"; "5.320 Bh" -> volume "5320", unit "Bh").
   - Bulatkan unit_price_rp dan amount_rp ke integer rupiah terdekat (abaikan ",00").

Contoh keluaran JSON rapat (minified):
{"written_total_rp":42940000,"rows":[["Semen Portland 50 kg","380","sak",85000,32300000,"MAT-001"],["Pasir Pasang","38","m3",280000,10640000]]}
PROMPT;

    public function __construct(
        private ?string $baseUrl = null,
        private ?string $apiKey = null,
        private ?string $model = null,
        private ?int $timeoutSeconds = null,
    ) {}

    /**
     * Read and extract structured rows from an RAB document.
     * Automatically tries Method 1 (Jalur A: fast digital PDF stream extraction) first for PDFs,
     * and falls back to Method 2 (Jalur B: AI Vision OCR) for scanned PDFs and photos.
     *
     * @param  list<StandardRef>  $standards
     */
    public function read(string $filePath, array $standards): RabReadResult
    {
        if (! file_exists($filePath)) {
            return new RabReadResult(
                rows: [],
                readMs: 0,
                errorCode: 'FILE_NOT_FOUND',
            );
        }

        // Method 1 (Jalur A): Fast digital PDF coordinate table extraction (~50ms for 59 pages)
        if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'pdf') {
            $digitalResult = $this->readViaDigitalPdf($filePath, $standards);
            if ($digitalResult !== null && $digitalResult->isSuccess()) {
                return $digitalResult;
            }
        }

        // Method 2 (Jalur B): AI Vision extraction for scanned PDFs, photos (JPG/PNG), or custom layouts
        return $this->readViaAiVision($filePath, $standards);
    }

    /**
     * Method 1 (Jalur A): Fast deterministic extraction from digital PDF text streams (e.g. Siskeudes multi-page PDF).
     * Extracts all budgeted product rows across all pages in ~50ms without burning AI Vision tokens.
     * Returns null if the PDF is a scanned image without digital table text streams.
     *
     * @param  list<StandardRef>  $standards
     */
    public function readViaDigitalPdf(string $filePath, array $standards): ?RabReadResult
    {
        $startTime = microtime(true);

        if (! file_exists($filePath)) {
            return null;
        }

        $pdfBytes = file_get_contents($filePath);
        if ($pdfBytes === false || ! str_contains($pdfBytes, 'stream')) {
            return null;
        }

        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdfBytes, $matches);
        if (empty($matches[1])) {
            return null;
        }

        $pagesCells = [];
        $currentCells = [];

        foreach ($matches[1] as $rawStream) {
            $decompressed = @gzuncompress($rawStream);
            if ($decompressed === false || ! str_contains($decompressed, 'BT') || str_contains($decompressed, 'GSUB')) {
                continue;
            }

            if (! preg_match_all('/([0-9.]+)\s+([0-9.]+)\s+Tm\s*\[(.*?)\]\s*TJ/s', $decompressed, $tmMatches, PREG_SET_ORDER)) {
                continue;
            }

            $streamCells = [];
            foreach ($tmMatches as $tm) {
                $x = (float) $tm[1];
                $y = (float) $tm[2];
                preg_match_all('/<([0-9A-Fa-f]{4})>(-?[0-9.]+)?/', $tm[3], $hexMatches, PREG_SET_ORDER);

                $text = '';
                foreach ($hexMatches as $hex) {
                    $ascii = hexdec($hex[1]) + 29;
                    if ($ascii >= 32 && $ascii <= 126) {
                        $text .= chr($ascii);
                    }
                    if (isset($hex[2]) && (float) $hex[2] < -100) {
                        $text .= ' ';
                    }
                }

                $trimmed = trim($text);
                if ($trimmed !== '') {
                    $streamCells[] = ['x' => $x, 'y' => $y, 'text' => $trimmed];
                }
            }

            if ($streamCells === []) {
                continue;
            }

            $joinedStreamText = implode(' ', array_column($streamCells, 'text'));
            if (str_contains($joinedStreamText, 'RENCANA ANGGARAN BIAYA')) {
                if ($currentCells !== []) {
                    $pagesCells[] = $currentCells;
                }
                $currentCells = $streamCells;
            } else {
                $currentCells = array_merge($currentCells, $streamCells);
            }
        }

        if ($currentCells !== []) {
            $pagesCells[] = $currentCells;
        }

        if ($pagesCells === []) {
            return null;
        }

        $rawRows = [];
        $position = 1;
        $singlePageTotalRp = null;

        foreach ($pagesCells as $cells) {
            usort($cells, static fn (array $a, array $b): int => $a['y'] <=> $b['y']);

            /** @var list<array{y: float, cells: list<array{x: float, y: float, text: string}>}> $clusteredRows */
            $clusteredRows = [];
            foreach ($cells as $cell) {
                $lastIdx = count($clusteredRows) - 1;
                if ($lastIdx < 0 || abs($cell['y'] - $clusteredRows[$lastIdx]['y']) > 5.0) {
                    $clusteredRows[] = ['y' => $cell['y'], 'cells' => [$cell]];
                } else {
                    $clusteredRows[$lastIdx]['cells'][] = $cell;
                }
            }

            $kegiatanLabel = null;
            foreach ($clusteredRows as $rowGroup) {
                $rowCells = $rowGroup['cells'];
                usort($rowCells, static fn (array $a, array $b): int => $a['x'] <=> $b['x']);
                $texts = array_column($rowCells, 'text');
                $line = implode(' | ', $texts);

                if (str_starts_with($line, 'Output/Keluaran')) {
                    $lastCol = trim((string) end($texts));
                    if ($lastCol !== '' && $lastCol !== ':') {
                        $kegiatanLabel = $lastCol;
                    }
                } elseif (str_starts_with($line, 'JUMLAH (Rp)') && count($pagesCells) === 1) {
                    $singlePageTotalRp = $this->parseRupiahInt(end($texts));
                }

                if (preg_match('/^0?\d+\.\s*\|\s*(.+?)\s*\|\s*(?:ADD|DDS|PBH|PAD|PBK|DLL|BKK|BHPR)?\s*\|?\s*([0-9.,]+\s+\S+)\s*\|\s*([0-9.,]+)\s*\|\s*([0-9.,]+)$/i', $line, $rm)) {
                    $itemName = trim($rm[1]);
                    if (count($pagesCells) > 1 && $kegiatanLabel !== null && ! str_contains(strtolower($itemName), strtolower($kegiatanLabel))) {
                        $itemName .= " ({$kegiatanLabel})";
                    }

                    $unitPriceRp = $this->parseRupiahInt($rm[3]);
                    $amountRp = $this->parseRupiahInt($rm[4]);
                    [$volume, $unit] = $this->extractVolumeAndUnit($rm[2], null, $unitPriceRp, $amountRp);

                    $matchedCode = $this->matchStandardCode($rm[1], $unit, $standards);

                    $rawRows[] = [
                        'position' => $position++,
                        'raw_name' => $itemName,
                        'volume' => $volume,
                        'unit' => $unit,
                        'unit_price_rp' => $unitPriceRp,
                        'amount_rp' => $amountRp,
                        'standard_code' => $matchedCode,
                        'doubt_fields' => [],
                    ];
                }
            }
        }

        if ($rawRows === []) {
            return null;
        }

        $validStandardCodes = array_map(static fn (StandardRef $ref): string => $ref->code, $standards);
        $rows = $this->sanitizeRows($rawRows, $validStandardCodes);

        $calculatedTotalRp = 0;
        foreach ($rows as $r) {
            $calculatedTotalRp += $r->amountRp ?? 0;
        }

        $elapsedMs = max(1, (int) round((microtime(true) - $startTime) * 1000));

        Log::info('rab.read.digital_pdf', [
            'pages' => count($pagesCells),
            'items' => count($rows),
            'ms' => $elapsedMs,
        ]);

        return new RabReadResult(
            rows: $rows,
            writtenTotalRp: $singlePageTotalRp ?? $calculatedTotalRp,
            readMs: $elapsedMs,
        );
    }

    /**
     * Method 2 (Jalur B): AI Vision extraction via OpenAI-compatible gateway for scanned PDFs and photos.
     *
     * @param  list<StandardRef>  $standards
     */
    public function readViaAiVision(string $filePath, array $standards): RabReadResult
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
                    'reasoning_effort' => 'low',
                    'max_tokens' => 16384,
                    'temperature' => 0.0,
                ]);

            $elapsedMs = (int) round((microtime(true) - $startTime) * 1000);

            if ($response->failed()) {
                Log::warning('rab.read.ai_failed', [
                    'status' => $response->status(),
                    'ms' => $elapsedMs,
                    'error' => $response->body(),
                ]);

                $errorCode = $response->status() === 402 ? 'AI_QUOTA_EXCEEDED' : 'AI_REQUEST_FAILED';

                return new RabReadResult(
                    rows: [],
                    readMs: $elapsedMs,
                    errorCode: $errorCode,
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

            // 4. Parse & Validate Structured Output (strip optional markdown fences & salvage if truncated)
            $cleanedJson = trim((string) $rawContent);
            if (str_starts_with($cleanedJson, '```')) {
                $cleanedJson = (string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $cleanedJson);
            }

            $parsed = $this->decodeJsonOrSalvage($cleanedJson);
            if (! is_array($parsed)) {
                return new RabReadResult(
                    rows: [],
                    readMs: $elapsedMs,
                    errorCode: 'INVALID_JSON_RESPONSE',
                    rawResponse: $rawContent,
                );
            }

            $writtenTotalRp = $this->parseRupiahInt($parsed['written_total_rp'] ?? null);

            $rows = $this->sanitizeRows($parsed['rows'] ?? [], $validStandardCodes);

            Log::info('rab.read', [
                'model' => $model,
                'items' => count($rows),
                'ms' => $elapsedMs,
            ]);

            if ($rows === []) {
                return new RabReadResult(
                    rows: [],
                    writtenTotalRp: $writtenTotalRp,
                    readMs: $elapsedMs,
                    errorCode: 'NO_RAB_ITEMS_FOUND',
                    rawResponse: $rawContent,
                );
            }

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

        $lines = ['Daftar Katalog Standar Acuan Resmi (Kode, Nama, Satuan - Tanpa Harga):'];
        foreach ($standards as $standard) {
            $lines[] = "- Kode: {$standard->code} | Nama: {$standard->name} | Satuan: {$standard->unit}";
        }
        $lines[] = "\nSilakan baca dokumen RAB terlampir dan cocokkan item ke kode standar di atas.";

        return implode("\n", $lines);
    }

    /**
     * Match an item name and unit against the standard price catalog deterministically.
     *
     * @param  list<StandardRef>  $standards
     */
    private function matchStandardCode(string $rawName, ?string $unit, array $standards): ?string
    {
        if ($standards === []) {
            return null;
        }

        $normalizedName = strtolower(trim($rawName));
        $normalizedUnit = $unit !== null ? strtolower(trim($unit)) : '';

        foreach ($standards as $standard) {
            $stdName = strtolower(trim($standard->name));
            $stdUnit = strtolower(trim($standard->unit));

            if ($normalizedUnit !== '' && $stdUnit !== '' && $normalizedUnit !== $stdUnit) {
                continue;
            }

            if ($normalizedName === $stdName || str_contains($normalizedName, $stdName) || str_contains($stdName, $normalizedName)) {
                return $standard->code;
            }
        }

        return null;
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
     * Decode JSON response or salvage completed rows if response hit token length limit on very large PDFs.
     *
     * @return array<string, mixed>|null
     */
    private function decodeJsonOrSalvage(string $json): ?array
    {
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Attempt to salvage truncated JSON array of rows (e.g. cut off at max_tokens)
        $rowsPos = strpos($json, '"rows"');
        if ($rowsPos === false) {
            return null;
        }

        $lastTupleEnd = strrpos($json, '],');
        $lastObjectEnd = strrpos($json, '},');
        $cutPos = max($lastTupleEnd !== false ? $lastTupleEnd + 1 : 0, $lastObjectEnd !== false ? $lastObjectEnd + 1 : 0);

        if ($cutPos > $rowsPos) {
            $salvaged = substr($json, 0, $cutPos).']}';
            $decodedSalvaged = json_decode($salvaged, true);
            if (is_array($decodedSalvaged)) {
                return $decodedSalvaged;
            }
        }

        return null;
    }

    /**
     * Validate, normalize numbers, strip invalid standards, and collect doubt fields.
     * Supports both associative object rows and compact tuple rows.
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

            $normalizedRow = $this->normalizeRowShape($row);

            $rawName = trim((string) ($normalizedRow['raw_name'] ?? ''));
            $rawName = (string) preg_replace('/^(?:0?\d+[\.\)]\s*)/', '', $rawName);
            if ($rawName === '') {
                continue;
            }

            $doubtFields = [];
            if (isset($normalizedRow['doubt_fields']) && is_array($normalizedRow['doubt_fields'])) {
                foreach ($normalizedRow['doubt_fields'] as $field) {
                    if (is_string($field) && trim($field) !== '') {
                        $doubtFields[] = trim($field);
                    }
                }
            }

            // Unit Price Rp & Amount Rp (uang integer murni)
            $rawUnitPrice = $normalizedRow['unit_price_rp'] ?? null;
            $unitPriceRp = $this->parseRupiahInt($rawUnitPrice);
            if ($rawUnitPrice !== null && $rawUnitPrice !== '' && ($unitPriceRp === null || $unitPriceRp < 0)) {
                $unitPriceRp = null;
                $doubtFields[] = 'unit_price_rp';
            }

            $rawAmount = $normalizedRow['amount_rp'] ?? null;
            $amountRp = $this->parseRupiahInt($rawAmount);
            if ($rawAmount !== null && $rawAmount !== '' && ($amountRp === null || $amountRp < 0)) {
                $amountRp = null;
                $doubtFields[] = 'amount_rp';
            }

            // Extract & normalize volume and unit (handles Siskeudes combined "40 Rim" and thousands dots "5.320")
            $rawVolume = isset($normalizedRow['volume']) ? trim((string) $normalizedRow['volume']) : null;
            $rawUnit = isset($normalizedRow['unit']) ? trim((string) $normalizedRow['unit']) : null;

            [$volume, $unit] = $this->extractVolumeAndUnit($rawVolume, $rawUnit, $unitPriceRp, $amountRp);

            if ($rawVolume !== null && $rawVolume !== '' && $volume === null) {
                $doubtFields[] = 'volume';
            }

            // Skip accidental category/header rows that have no volume and no unit price
            if ($volume === null && $unitPriceRp === null && $doubtFields === []) {
                continue;
            }

            // If amount_rp was omitted or 0 while volume and unit_price_rp are positive, calculate amount_rp
            if (($amountRp === null || $amountRp === 0) && $volume !== null && is_numeric($volume) && $unitPriceRp !== null && $unitPriceRp > 0) {
                $amountRp = (int) round((float) $volume * $unitPriceRp);
            }

            // standardCode validation (PRD Arsitektur 6.5: standardCode yang tidak ada di daftar dibuang jadi null)
            $rawStandardCode = isset($normalizedRow['standard_code']) ? trim((string) $normalizedRow['standard_code']) : null;
            $standardCode = null;
            if (! empty($rawStandardCode) && in_array($rawStandardCode, $validStandardCodes, true)) {
                $standardCode = $rawStandardCode;
            }

            $doubtFields = array_values(array_unique($doubtFields));

            $position = isset($normalizedRow['position']) && is_numeric($normalizedRow['position']) && (int) $normalizedRow['position'] > 0
                ? (int) $normalizedRow['position']
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
     * Normalize either an associative row or an indexed tuple row into a standard key map.
     *
     * @param  array<mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRowShape(array $row): array
    {
        if (array_key_exists('raw_name', $row)) {
            return $row;
        }

        // Indexed tuple: [position, raw_name, volume, unit, unit_price_rp, amount_rp, standard_code?, doubt_fields?]
        if (isset($row[0], $row[1]) && is_numeric($row[0]) && is_string($row[1])) {
            return [
                'position' => (int) $row[0],
                'raw_name' => $row[1],
                'volume' => $row[2] ?? null,
                'unit' => $row[3] ?? null,
                'unit_price_rp' => $row[4] ?? null,
                'amount_rp' => $row[5] ?? null,
                'standard_code' => $row[6] ?? null,
                'doubt_fields' => $row[7] ?? [],
            ];
        }

        // Indexed tuple without position: [raw_name, volume, unit, unit_price_rp, amount_rp, standard_code?, doubt_fields?]
        if (isset($row[0]) && is_string($row[0])) {
            return [
                'raw_name' => $row[0],
                'volume' => $row[1] ?? null,
                'unit' => $row[2] ?? null,
                'unit_price_rp' => $row[3] ?? null,
                'amount_rp' => $row[4] ?? null,
                'standard_code' => $row[5] ?? null,
                'doubt_fields' => $row[6] ?? [],
            ];
        }

        return [];
    }

    /**
     * Parse rupiah integer from int, float, or Indonesian formatted currency string (e.g. "5.297.380,00").
     */
    private function parseRupiahInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) round($value);
        }

        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        if (! is_string($value)) {
            return null;
        }

        $cleaned = trim((string) preg_replace('/[^\d.,-]/', '', $value));
        $normalized = $this->normalizeNumberString($cleaned);

        return $normalized !== null ? (int) round((float) $normalized) : null;
    }

    /**
     * Extract volume and unit, handling combined strings ("40 Rim") and disambiguating thousands dots ("5.320").
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function extractVolumeAndUnit(?string $rawVolume, ?string $rawUnit, ?int $unitPriceRp, ?int $amountRp): array
    {
        $unit = $rawUnit !== null && $rawUnit !== '' ? $rawUnit : null;
        $volCandidate = $rawVolume;

        if ($volCandidate !== null && preg_match('/^([0-9.,]+)\s+([a-zA-Z0-9\'"]+)$/u', $volCandidate, $matches)) {
            $volCandidate = $matches[1];
            if ($unit === null) {
                $unit = $matches[2];
            }
        }

        $volume = $this->normalizeNumberString($volCandidate, $unitPriceRp, $amountRp);

        return [$volume, $unit];
    }

    /**
     * Normalize decimal representation (e.g. "1.250,5" or "1,250.5" -> "1250.5"),
     * and disambiguate Indonesian thousands separator "5.320" vs decimal "5.320" using price & amount.
     */
    private function normalizeNumberString(?string $value, ?int $unitPriceRp = null, ?int $amountRp = null): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $cleaned = trim($value);

        // Pattern Indonesia: 1.250,50 (ribuan titik, desimal koma)
        if (preg_match('/^\d{1,3}(\.\d{3})*,\d+$/', $cleaned)) {
            $cleaned = str_replace('.', '', $cleaned);
            $cleaned = str_replace(',', '.', $cleaned);
            $cleaned = rtrim(rtrim($cleaned, '0'), '.');
        } elseif (preg_match('/^\d+,\d+$/', $cleaned)) {
            // e.g. "12,5" or "242000,00"
            $cleaned = str_replace(',', '.', $cleaned);
            $cleaned = rtrim(rtrim($cleaned, '0'), '.');
        } elseif (preg_match('/^\d{1,3}(,\d{3})+\.\d+$/', $cleaned)) {
            // Pattern US: 1,250.50
            $cleaned = str_replace(',', '', $cleaned);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $cleaned)) {
            // Ambiguous pattern like "5.320" or "36.540" or "242.000"
            $withoutDots = str_replace('.', '', $cleaned);
            if ($unitPriceRp !== null && $unitPriceRp > 0 && $amountRp !== null && $amountRp > 0) {
                $asDecimalDiff = abs(((float) $cleaned * $unitPriceRp) - $amountRp);
                $asThousandDiff = abs(((float) $withoutDots * $unitPriceRp) - $amountRp);
                $cleaned = ($asThousandDiff <= $asDecimalDiff) ? $withoutDots : $cleaned;
            } elseif (substr_count($cleaned, '.') > 1) {
                // Multiple dots like "1.250.000" is definitely thousands separator
                $cleaned = $withoutDots;
            }
        }

        return is_numeric($cleaned) ? $cleaned : null;
    }
}
