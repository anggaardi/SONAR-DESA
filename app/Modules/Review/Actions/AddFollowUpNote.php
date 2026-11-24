<?php

declare(strict_types=1);

namespace App\Modules\Review\Actions;

use App\Modules\Review\Models\ReviewCycle;
use App\Modules\Review\Models\ReviewNote;
use DomainException;

/**
 * Appends a dated public follow-up note to an already-closed review cycle (B9, F15).
 * Closed outcomes are immutable; corrections happen only via follow-up notes.
 */
final readonly class AddFollowUpNote
{
    public function execute(ReviewCycle $cycle, string $note): ReviewNote
    {
        if ($cycle->closed_at === null) {
            throw new DomainException('Catatan susulan hanya bisa ditambahkan pada hasil tinjauan yang sudah tersimpan.');
        }

        $trimmed = trim($note);
        if ($trimmed === '') {
            throw new DomainException('Catatan susulan tidak boleh kosong.');
        }

        return ReviewNote::create([
            'cycle_id' => $cycle->id,
            'note' => $trimmed,
        ]);
    }
}
