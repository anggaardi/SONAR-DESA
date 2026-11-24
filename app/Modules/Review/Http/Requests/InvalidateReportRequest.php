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

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:255'],
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
