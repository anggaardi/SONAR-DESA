<?php

declare(strict_types=1);

namespace App\Modules\Audit\Reading;

/**
 * Offline fixture reader used for tests, CI, and instant demo loading (PRD Arsitektur 6.5).
 * Reads pre-cached JSON files from database/data/rab-fixtures/<hash>.json.
 */
final readonly class FixtureRabReader implements RabReader
{
    public function __construct(
        private ?string $fixtureDirectory = null,
    ) {}

    /**
     * Read pre-cached extraction result from JSON fixture.
     *
     * @param  list<StandardRef>  $standards
     */
    public function read(string $filePath, array $standards): RabReadResult
    {
        $dir = $this->fixtureDirectory ?? database_path('data/rab-fixtures');

        // Check fixture by md5 file hash, exact filename, stem filename, or default fallback
        $hash = file_exists($filePath) ? md5_file($filePath) : basename($filePath);
        $fixtureFile = "{$dir}/{$hash}.json";

        // Map known demo sample hashes to fixture files
        if (! file_exists($fixtureFile) && file_exists($filePath)) {
            $knownHashes = [
                'a02d46d4ca558dffe50d4a14db9d86a4' => "{$dir}/sample-rab-melati.json",
                'bf500fadc02d34fc359f29d4a6288775' => "{$dir}/sample-rab-posyandu.json",
                'fac58f402f5c6292a72ecb8e830a6d98' => "{$dir}/sample-rab-subak-uma.json",
            ];
            $md5 = md5_file($filePath);
            if (isset($knownHashes[$md5]) && file_exists($knownHashes[$md5])) {
                $fixtureFile = $knownHashes[$md5];
            }
        }

        if (! file_exists($fixtureFile)) {
            $fileName = strtolower(basename($filePath));
            if (str_starts_with($fileName, 'demo-sample-') || str_contains($fileName, 'melati')) {
                $melatiFixture = "{$dir}/sample-rab-melati.json";
                if (file_exists($melatiFixture)) {
                    $fixtureFile = $melatiFixture;
                }
            } elseif (str_contains($fileName, 'posyandu')) {
                $posyanduFixture = "{$dir}/sample-rab-posyandu.json";
                if (file_exists($posyanduFixture)) {
                    $fixtureFile = $posyanduFixture;
                }
            } elseif (str_contains($fileName, 'subak')) {
                $subakFixture = "{$dir}/sample-rab-subak-uma.json";
                if (file_exists($subakFixture)) {
                    $fixtureFile = $subakFixture;
                }
            }
        }

        if (! file_exists($fixtureFile)) {
            $byStem = "{$dir}/".pathinfo($filePath, PATHINFO_FILENAME).'.json';
            if (file_exists($byStem)) {
                $fixtureFile = $byStem;
            }
        }

        if (! file_exists($fixtureFile)) {
            // Check if there is any json in the fixture directory as fallback
            $fallbacks = glob("{$dir}/*.json");
            $fixtureFile = ! empty($fallbacks) ? $fallbacks[0] : null;
        }

        if ($fixtureFile === null || ! file_exists($fixtureFile)) {
            return new RabReadResult(
                rows: [],
                readMs: 10,
                errorCode: 'FIXTURE_NOT_FOUND',
            );
        }

        $content = file_get_contents($fixtureFile);
        $data = json_decode((string) $content, true);

        if (! is_array($data)) {
            return new RabReadResult(
                rows: [],
                readMs: 10,
                errorCode: 'INVALID_FIXTURE_JSON',
            );
        }

        $writtenTotalRp = isset($data['written_total_rp']) && is_numeric($data['written_total_rp'])
            ? (int) $data['written_total_rp']
            : null;

        $rows = [];
        $validStandardCodes = array_map(static fn (StandardRef $ref): string => $ref->code, $standards);

        foreach ($data['rows'] ?? [] as $index => $row) {
            $rawCode = isset($row['standard_code']) ? (string) $row['standard_code'] : null;
            $standardCode = ($rawCode !== null && in_array($rawCode, $validStandardCodes, true))
                ? $rawCode
                : null;

            $rows[] = new RabReadRow(
                position: (int) ($row['position'] ?? $index + 1),
                rawName: (string) ($row['raw_name'] ?? 'Item'),
                volume: isset($row['volume']) ? (string) $row['volume'] : null,
                unit: isset($row['unit']) ? (string) $row['unit'] : null,
                unitPriceRp: isset($row['unit_price_rp']) ? (int) $row['unit_price_rp'] : null,
                amountRp: isset($row['amount_rp']) ? (int) $row['amount_rp'] : null,
                standardCode: $standardCode,
                doubtFields: is_array($row['doubt_fields'] ?? null) ? $row['doubt_fields'] : [],
            );
        }

        return new RabReadResult(
            rows: $rows,
            writtenTotalRp: $writtenTotalRp,
            readMs: 120, // instant fixture response
            rawResponse: $content,
        );
    }
}
