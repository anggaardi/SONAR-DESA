<?php

declare(strict_types=1);

namespace App\Modules\Review\Http\Requests;

use App\Modules\Core\Auth\Role;
use App\Modules\Core\Auth\User;
use Illuminate\Foundation\Http\FormRequest;

final class InvalidateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->hasRole(Role::Bpd);
    }

    protected function prepareForValidation(): void
    {
        $rawNote = $this->input('note');
        if (! is_string($rawNote) || trim($rawNote) === '') {
            $this->merge(['note' => null]);
        } else {
            $this->merge(['note' => trim($rawNote)]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'note' => ['nullable', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan laporan tidak valid wajib diisi.',
        ];
    }
}
