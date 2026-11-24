<?php

declare(strict_types=1);

namespace App\Modules\Review\Http\Requests;

use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use Illuminate\Foundation\Http\FormRequest;

final class StoreFollowUpNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasRole(Role::Bpd);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required' => 'Catatan susulan wajib diisi.',
        ];
    }
}
