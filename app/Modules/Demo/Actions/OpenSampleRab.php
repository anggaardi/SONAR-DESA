<?php

declare(strict_types=1);

namespace App\Modules\Demo\Actions;

use App\Modules\Audit\Actions\CreateAuditFromFixture;
use App\Modules\Audit\Models\Audit;
use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\CurrentVillage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Opens one of the 3 pre-cached sample RABs inside the visitor's demo village as Pemerintah Desa
 * without calling any external AI service (PRD Fitur Inti 7.1, 7.3, AC M1, M3, S2-AC1).
 */
final class OpenSampleRab
{
    public function __construct(
        private readonly DemoSession $demo,
        private readonly CreateAuditFromFixture $createFromFixture,
    ) {}

    public function execute(Request $request, string $sample): Audit
    {
        $storedPath = match ($sample) {
            '1' => 'audits/sample-rab-melati.pdf',
            '2' => 'audits/sample-rab-subak-uma.pdf',
            '3' => 'audits/sample-rab-posyandu.pdf',
            default => null,
        };
        abort_if($storedPath === null, 404);

        $village = $this->demo->start($request, Role::Desa);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $disk = Storage::disk('local');
        if (! $disk->exists($storedPath)) {
            $assetPath = database_path("seeders/assets/{$storedPath}");
            abort_unless(file_exists($assetPath), 404);
            $disk->put($storedPath, (string) file_get_contents($assetPath));
        }

        $currentVillage = app(CurrentVillage::class);
        $currentVillage->set($village->id);

        try {
            return $this->createFromFixture->execute(
                village: $village,
                owner: $user,
                storedFilePath: $storedPath,
                source: 'demo_sample',
            );
        } finally {
            $currentVillage->clear();
        }
    }
}
