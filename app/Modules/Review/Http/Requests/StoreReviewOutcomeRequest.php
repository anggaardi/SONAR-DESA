<?php

declare(strict_types=1);

namespace App\Modules\Review\Http\Requests;

use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use App\Modules\Review\Actions\SubmitReviewOutcome;
use App\Modules\Review\Enums\ReviewOutcome;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreReviewOutcomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasRole(Role::Bpd);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'result' => ['required', Rule::enum(ReviewOutcome::class)],
            'note' => ['required', 'string', 'min:'.SubmitReviewOutcome::MIN_NOTE_LENGTH, 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'result.required' => 'Pilih hasil tinjauan BPD.',
            'note.required' => 'Catatan hasil tinjauan wajib diisi.',
            'note.min' => 'Catatan hasil tinjauan wajib minimal 30 huruf.',
        ];
    }
}
