<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers;

use App\Modules\Audit\Domain\PriceLabeler;
use App\Modules\Audit\Enums\AuditStatus;
use App\Modules\Audit\Enums\PriceLabel;
use App\Modules\Audit\Models\Audit;
use App\Modules\Audit\Models\AuditItem;
use App\Modules\Core\Village\CurrentVillage;
use App\Modules\Standard\Models\PriceStandard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class AuditItemController
{
    /**
     * Mark a yellow row as verified / "Sudah Benar" (D1 gatekeeper).
     */
    public function confirm(Request $request, Audit $audit, AuditItem $item): RedirectResponse
    {
        $villageId = app(CurrentVillage::class)->id();
        abort_unless($audit->village_id === $villageId, 403, 'Akses ditolak.');
        abort_unless($item->audit_id === $audit->id, 404, 'Baris belanja tidak ditemukan dalam audit ini.');
        $this->ensureEditable($audit);

        DB::transaction(function () use ($audit, $item): void {
            $standard = $item->standard;
            $newLabel = $item->label;
            $overPct = $item->over_pct;
            $diffRp = $item->diff_rp;

            if ($item->label === PriceLabel::NeedsCheck) {
                if ($standard && $standard->price_rp > 0) {
                    if ($item->unit_price_rp > $standard->price_rp) {
                        $diff = $item->unit_price_rp - $standard->price_rp;
                        $pct = (int) ceil(($diff * 100) / $standard->price_rp);
                        $newLabel = PriceLabel::AboveStandard;
                        $overPct = $pct;
                        $diffRp = (int) bcmul((string) (float) $item->volume, (string) $diff, 0);
                    } else {
                        $newLabel = PriceLabel::WithinStandard;
                        $overPct = null;
                        $diffRp = 0;
                    }
                } else {
                    $newLabel = PriceLabel::NoReference;
                    $overPct = null;
                    $diffRp = null;
                }
            }

            $item->update([
                'corrected' => true,
                'label' => $newLabel,
                'over_pct' => $overPct,
                'diff_rp' => $diffRp,
            ]);

            $this->updateAuditStatusIfResolved($audit);
        });

        return back()->with('success', 'Baris rincian belanja telah dikonfirmasi (Sudah Benar).');
    }

    /**
     * Inline update for an audit item (correcting volume, unit, price, or price standard).
     */
    public function update(
        Request $request,
        Audit $audit,
        AuditItem $item,
        PriceLabeler $priceLabeler
    ): RedirectResponse {
        $villageId = app(CurrentVillage::class)->id();
        abort_unless($audit->village_id === $villageId, 403, 'Akses ditolak.');
        abort_unless($item->audit_id === $audit->id, 404, 'Baris belanja tidak ditemukan dalam audit ini.');
        $this->ensureEditable($audit);

        $validated = $request->validate([
            'raw_name' => ['required', 'string', 'max:255'],
            'volume' => ['required', 'numeric', 'min:0.0001'],
            'unit' => ['required', 'string', 'max:32'],
            'unit_price_rp' => ['required', 'integer', 'min:0'],
            'amount_rp' => ['nullable', 'integer', 'min:0'],
            'standard_id' => ['nullable', 'integer', 'exists:price_standards,id'],
        ], [
            'raw_name.required' => 'Nama uraian belanja wajib diisi.',
            'volume.required' => 'Volume belanja wajib diisi.',
            'unit.required' => 'Satuan belanja wajib diisi.',
            'unit_price_rp.required' => 'Harga satuan wajib diisi.',
        ]);

        $standard = ! empty($validated['standard_id'])
            ? PriceStandard::find($validated['standard_id'])
            : null;

        $volumeStr = (string) $validated['volume'];
        $unitPrice = (int) $validated['unit_price_rp'];
        $amountRp = isset($validated['amount_rp'])
            ? (int) $validated['amount_rp']
            : (int) bcmul($volumeStr, (string) $unitPrice, 0);

        $labelResult = $priceLabeler->label(
            rawName: $validated['raw_name'],
            volume: $volumeStr,
            unit: $validated['unit'],
            unitPriceRp: $unitPrice,
            amountRp: $amountRp,
            standardPriceRp: $standard?->price_rp,
            standardUnit: $standard?->unit,
            doubtFields: [],
        );

        DB::transaction(function () use ($audit, $item, $validated, $standard, $volumeStr, $unitPrice, $amountRp, $labelResult): void {
            $item->update([
                'raw_name' => $validated['raw_name'],
                'volume' => $volumeStr,
                'unit' => $validated['unit'],
                'unit_price_rp' => $unitPrice,
                'amount_rp' => $amountRp,
                'standard_id' => $standard?->id,
                'label' => $labelResult->label,
                'over_pct' => $labelResult->overPct,
                'diff_rp' => $labelResult->diffRp,
                'doubt' => $labelResult->reason ? ['reason' => $labelResult->reason] : null,
                'corrected' => $labelResult->label !== PriceLabel::NeedsCheck,
            ]);

            $this->updateAuditStatusIfResolved($audit);
        });

        return back()->with('success', 'Rincian belanja berhasil dikoreksi.');
    }

    /**
     * Delete an audit item (e.g. duplicate or subtotal row mistakenly captured by OCR).
     */
    public function destroy(Request $request, Audit $audit, AuditItem $item): RedirectResponse
    {
        $villageId = app(CurrentVillage::class)->id();
        abort_unless($audit->village_id === $villageId, 403, 'Akses ditolak.');
        abort_unless($item->audit_id === $audit->id, 404, 'Baris belanja tidak ditemukan dalam audit ini.');
        $this->ensureEditable($audit);

        DB::transaction(function () use ($audit, $item): void {
            $item->delete();

            $this->updateAuditStatusIfResolved($audit);
        });

        return back()->with('success', 'Baris rincian belanja berhasil dihapus.');
    }

    private function ensureEditable(Audit $audit): void
    {
        $isPublished = $audit->status === AuditStatus::Published
            || ($audit->project !== null && $audit->project->isPublished());

        if ($isPublished) {
            abort(403, 'Rincian audit yang sudah diterbitkan tidak dapat diubah (D4). Perubahan setelah terbit hanya diperbolehkan melalui CCO.');
        }
    }

    private function updateAuditStatusIfResolved(Audit $audit): void
    {
        $hasUncorrectedYellow = $audit->items()
            ->where('label', PriceLabel::NeedsCheck)
            ->where('corrected', false)
            ->exists();

        if (! $hasUncorrectedYellow && $audit->status === AuditStatus::NeedsReview) {
            $audit->update(['status' => AuditStatus::Ready]);
        }
    }
}
