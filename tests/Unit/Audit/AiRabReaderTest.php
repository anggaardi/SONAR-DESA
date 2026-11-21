<?php

declare(strict_types=1);

use App\Modules\Audit\Reading\AiRabReader;
use App\Modules\Audit\Reading\StandardRef;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/sonar_test_'.uniqid();
    File::ensureDirectoryExists($this->tempDir);
    $this->sampleFile = $this->tempDir.'/sample.pdf';
    file_put_contents($this->sampleFile, '%PDF-1.4 mock content');

    $this->standards = [
        new StandardRef(code: 'MAT-001', name: 'Semen Portland 50 kg', unit: 'sak'),
        new StandardRef(code: 'MAT-002', name: 'Pasir Beton', unit: 'm3'),
    ];

    $this->reader = new AiRabReader(
        baseUrl: 'https://ai.databyte.co.id/v1',
        apiKey: 'sk-mock-key-for-testing',
        model: 'google/gemini-1.5-flash',
        timeoutSeconds: 30,
    );
});

afterEach(function () {
    File::deleteDirectory($this->tempDir);
});

describe('AiRabReader', function () {
    it('returns error when file does not exist', function () {
        $result = $this->reader->read('/invalid/path/nonexistent.pdf', $this->standards);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->errorCode)->toBe('FILE_NOT_FOUND');
    });

    it('extracts structured rows and written total from OpenAI-compatible gateway response', function () {
        $mockAiPayload = [
            'written_total_rp' => 105_000_000,
            'rows' => [
                [
                    'position' => 1,
                    'raw_name' => 'Semen Portland 50 kg',
                    'volume' => '380',
                    'unit' => 'sak',
                    'unit_price_rp' => 85_000,
                    'amount_rp' => 32_300_000,
                    'standard_code' => 'MAT-001',
                    'doubt_fields' => [],
                ],
                [
                    'position' => 2,
                    'raw_name' => 'Pasir Beton',
                    'volume' => '40',
                    'unit' => 'm3',
                    'unit_price_rp' => 250_000,
                    'amount_rp' => 10_000_000,
                    'standard_code' => 'MAT-002',
                    'doubt_fields' => [],
                ],
            ],
        ];

        Http::fake([
            'https://ai.databyte.co.id/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode($mockAiPayload, JSON_THROW_ON_ERROR),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->reader->read($this->sampleFile, $this->standards);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->writtenTotalRp)->toBe(105_000_000)
            ->and(count($result->rows))->toBe(2)
            ->and($result->rows[0]->rawName)->toBe('Semen Portland 50 kg')
            ->and($result->rows[0]->unitPriceRp)->toBe(85_000)
            ->and($result->rows[0]->standardCode)->toBe('MAT-001')
            ->and($result->rows[0]->doubtFields)->toBeEmpty();
    });

    it('detects and records doubt_fields for blurry or illegible columns', function () {
        $mockAiPayload = [
            'written_total_rp' => 50_000_000,
            'rows' => [
                [
                    'position' => 1,
                    'raw_name' => 'Kayu Bekisting 2x3',
                    'volume' => '1.5',
                    'unit' => 'm3',
                    'unit_price_rp' => 2_400_000,
                    'amount_rp' => 3_600_000,
                    'standard_code' => null,
                    'doubt_fields' => ['volume', 'unit_price_rp'],
                ],
            ],
        ];

        Http::fake([
            'https://ai.databyte.co.id/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode($mockAiPayload, JSON_THROW_ON_ERROR),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->reader->read($this->sampleFile, $this->standards);

        expect($result->isSuccess())->toBeTrue()
            ->and(count($result->rows))->toBe(1)
            ->and($result->rows[0]->doubtFields)->toBe(['volume', 'unit_price_rp']);
    });

    it('strips hallucinated standard_code if not present in the provided standard list (PRD Arsitektur 6.5)', function () {
        $mockAiPayload = [
            'rows' => [
                [
                    'position' => 1,
                    'raw_name' => 'Baris Dengan Kode Khayalan',
                    'volume' => '10',
                    'unit' => 'm3',
                    'unit_price_rp' => 100_000,
                    'amount_rp' => 1_000_000,
                    'standard_code' => 'KODE-KHAYALAN-999',
                    'doubt_fields' => [],
                ],
            ],
        ];

        Http::fake([
            'https://ai.databyte.co.id/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode($mockAiPayload, JSON_THROW_ON_ERROR),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->reader->read($this->sampleFile, $this->standards);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->rows[0]->standardCode)->toBeNull();
    });

    it('normalizes Indonesian number formatting in volume', function () {
        $mockAiPayload = [
            'rows' => [
                [
                    'position' => 1,
                    'raw_name' => 'Urugan Pasir',
                    'volume' => '1.250,5', // ribuan titik, desimal koma
                    'unit' => 'm3',
                    'unit_price_rp' => 120_000,
                    'amount_rp' => 150_060_000,
                    'standard_code' => null,
                    'doubt_fields' => [],
                ],
            ],
        ];

        Http::fake([
            'https://ai.databyte.co.id/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode($mockAiPayload, JSON_THROW_ON_ERROR),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->reader->read($this->sampleFile, $this->standards);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->rows[0]->volume)->toBe('1250.5');
    });

    it('handles AI request failure and returns appropriate error code', function () {
        Http::fake([
            'https://ai.databyte.co.id/v1/chat/completions' => Http::response('Server Error', 500),
        ]);

        $result = $this->reader->read($this->sampleFile, $this->standards);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->errorCode)->toBe('AI_REQUEST_FAILED');
    });
});
