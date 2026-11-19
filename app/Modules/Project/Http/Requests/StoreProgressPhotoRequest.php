<?php

declare(strict_types=1);

namespace App\Modules\Project\Http\Requests;

use App\Modules\Project\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'progress_pct' => ['required', 'integer', 'between:0,100'],
            'photos' => ['required', 'array', 'min:1', 'max:5'],
            'photos.*' => ['required', 'file', 'mimes:jpeg,png,webp', 'max:10240'],
        ];
    }
}
