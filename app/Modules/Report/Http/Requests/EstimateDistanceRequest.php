<?php

declare(strict_types=1);

namespace App\Modules\Report\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class EstimateDistanceRequest extends FormRequest
{
    /** Public endpoint: the visitor has not signed in yet (F11); the project check is in the controller. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
