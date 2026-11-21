<?php

declare(strict_types=1);

namespace App\Modules\Audit\Console;

use App\Modules\Audit\Domain\PriceLabeler;
use App\Modules\Audit\Reading\AiRabReader;
use App\Modules\Audit\Reading\FixtureRabReader;
use App\Modules\Audit\Reading\RabReader;
use App\Modules\Audit\Reading\StandardRef;
use App\Modules\Standard\Models\PriceStandard;
use Illuminate\Console\Command;

/**
 * Artisan command for Dev 2 to test document processing (PDF / Image)
 * via OpenAI-compatible AI Vision (DataByte) or local fixtures.
 */
final class ProcessRabDocumentCommand extends Command
{
    protected $signature = 'audit:process-rab
                            {file : Path ke file PDF atau gambar dokumen RAB}
                            {--driver= : Driver pembaca: "ai" (DataByte Gateway) atau "fixture"}
                            {--key= : Override API Key DataByte langsung di terminal}
                            {--base-url= : Override Base URL gateway (default: https://ai.databyte.co.id/gateway/v1)}
                            {--model= : Override nama model (default: google/gemini-1.5-flash)}';

    protected $description = 'Ekstrak tabel RAB dari PDF/gambar berbasis AI Vision (DataByte) dengan deteksi baris buram/ragu';

    public function handle(PriceLabeler $labeler): int
    {
        $fileArg = (string) $this->argument('file');
        $filePath = str_starts_with($fileArg, '/') ? $fileArg : base_path($fileArg);

        if (! file_exists($filePath)) {
            $this->error("File tidak ditemukan: {$filePath}");

            return self::FAILURE;
        }

        $driver = (string) ($this->option('driver') ?: config('sonar.rab_reader.driver', 'fixture'));

        $baseUrl = (string) ($this->option('base-url') ?: config('sonar.rab_reader.ai.base_url', 'https://ai.databyte.co.id/gateway/v1'));
        $model = (string) ($this->option('model') ?: config('sonar.rab_reader.ai.model', 'google/gemini-1.5-flash'));
        $apiKey = (string) ($this->option('key') ?: config('sonar.rab_reader.ai.api_key', env('AI_API_KEY', '')));

        /** @var RabReader $reader */
        $reader = match ($driver) {
            'ai' => new AiRabReader(
                baseUrl: $baseUrl,
                apiKey: $apiKey,
                model: $model,
                timeoutSeconds: (int) config('sonar.rab_reader.timeout_seconds', 60),
            ),
            default => app(FixtureRabReader::class),
        };

        $this->info('===========================================================');
        $this->info('  SONAR DESA: PEMROSESAN DOKUMEN RAB BERBASIS AI VISION');
        $this->info('===========================================================');
        $this->line("File      : <comment>{$filePath}</comment>");
        $this->line("Ukuran    : <comment>".round(filesize($filePath) / 1024, 2).' KB</comment>');
        $this->line("Driver    : <comment>{$driver}</comment>");
        if ($driver === 'ai') {
            $this->line("Gateway   : <comment>{$baseUrl}</comment>");
            $this->line("Model     : <comment>{$model}</comment>");
            $maskedKey = strlen($apiKey) > 8 ? substr($apiKey, 0, 7).'...'.substr($apiKey, -4) : '(kosong)';
            $this->line("API Key   : <comment>{$maskedKey}</comment>");
        }
        $this->newLine();

        $this->comment('Mengambil daftar katalog standar acuan resmi (tanpa harga)...');
        $standards = PriceStandard::query()
            ->select(['code', 'name', 'unit'])
            ->get()
            ->map(static fn (PriceStandard $s): StandardRef => new StandardRef(
                code: $s->code,
                name: $s->name,
                unit: $s->unit,
            ))
            ->all();

        $this->line('Katalog standar aktif: '.count($standards).' item.');
        $this->comment('Memproses dokumen dengan AI Vision...');

        $result = $reader->read($filePath, $standards);

        if (! $result->isSuccess()) {
            $this->error("Gagal memproses dokumen! Error code: {$result->errorCode}");
            if ($result->rawResponse) {
                $this->error("Respons server:\n{$result->rawResponse}");
            }

            return self::FAILURE;
        }

        $this->info("Ekstraksi Berhasil dalam {$result->readMs} ms! Ditemukan ".count($result->rows).' baris item.');
        $this->newLine();

        // Tampilkan tabel hasil ekstraksi
        $tableRows = [];
        $doubtCount = 0;

        foreach ($result->rows as $row) {
            $hasDoubt = ! empty($row->doubtFields);
            if ($hasDoubt) {
                $doubtCount++;
            }

            $doubtInfo = $hasDoubt
                ? '<fg=yellow>⚠️ KABUR: ['.implode(', ', $row->doubtFields).']</>'
                : '<fg=green>Jelas</>';

            $tableRows[] = [
                $row->position,
                $row->rawName,
                ($row->volume ?? '-').' '.($row->unit ?? ''),
                $row->unitPriceRp !== null ? 'Rp'.number_format($row->unitPriceRp, 0, ',', '.') : '<fg=red>NULL</>',
                $row->amountRp !== null ? 'Rp'.number_format($row->amountRp, 0, ',', '.') : '<fg=red>NULL</>',
                $row->standardCode ?? '<fg=gray>Tanpa acuan</>',
                $doubtInfo,
            ];
        }

        $this->table(
            ['No', 'Nama Item Asli', 'Volume', 'Harga Satuan', 'Jumlah', 'Kode Standar', 'Kejelasan Visual'],
            $tableRows
        );

        $this->newLine();
        $this->line('Total Tertulis di Lembar: '.($result->writtenTotalRp !== null ? 'Rp'.number_format($result->writtenTotalRp, 0, ',', '.') : '<fg=yellow>Tidak tertera</>'));
        $this->line('Baris dengan bagian kabur/ragu (Kuning): '.($doubtCount > 0 ? "<fg=yellow>{$doubtCount} baris</>" : '<fg=green>0 baris (Sempurna)</>'));

        return self::SUCCESS;
    }
}
