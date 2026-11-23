<?php

declare(strict_types=1);

namespace App\Modules\Project\Http\Requests;

use App\Modules\Project\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreProgressPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project && $this->user()?->can('manage', $project);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Project|null $project */
        $project = $this->route('project');

        return [
            'progress_pct' => ['required', 'integer', 'between:0,100'],
            'photos' => ['required', 'array', 'min:1', 'max:5'],
            'photos.*' => ['required', 'file', 'mimes:jpeg,png,webp', 'max:10240'],
            'decrease_reason' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => $project instanceof Project && (int) $this->input('progress_pct') < $project->progress_pct),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decrease_reason.required' => 'Penurunan persentase progres fisik wajib menyertakan alasan keterangan (D13).',
            'photos.required' => 'Unggah minimal 1 foto dokumentasi lapangan.',
            'photos.max' => 'Maksimal 5 foto per unggahan.',
        ];
    }
}
