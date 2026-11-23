<?php

declare(strict_types=1);

namespace App\Modules\Desa\Http\Requests;

use App\Modules\Core\Auth\Role;
use App\Modules\Project\Enums\MeasurementOrigin;
use App\Modules\Project\Enums\ProjectType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === Role::Desa;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $isPublished = filter_var($this->input('is_published'), FILTER_VALIDATE_BOOLEAN);

        return [
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ProjectType::class)],
            'fund_source' => ['required', 'string', 'max:100'],
            'year' => ['required', 'integer', 'min:2020', 'max:2035'],
            'amount_rp' => ['nullable', 'integer', 'min:0'],
            'lat' => [
                Rule::requiredIf($isPublished),
                'nullable',
                'numeric',
                'between:-90,90',
            ],
            'lng' => [
                Rule::requiredIf($isPublished),
                'nullable',
                'numeric',
                'between:-180,180',
            ],
            'is_published' => ['boolean'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.raw_name' => ['required', 'string', 'max:255'],
            'items.*.volume' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit' => ['required', 'string', 'max:32'],
            'items.*.unit_price_rp' => ['required', 'integer', 'min:0'],
            'items.*.standard_id' => ['nullable', 'integer', 'exists:price_standards,id'],
            'measurements' => ['required', 'array', 'min:1', 'max:10'],
            'measurements.*.name' => ['required', 'string', 'max:255'],
            'measurements.*.value' => ['required', 'numeric', 'min:0.001'],
            'measurements.*.unit' => ['required', 'string', 'max:32'],
            'measurements.*.how_to' => ['nullable', 'string', 'max:500'],
            'measurements.*.origin' => ['nullable', Rule::enum(MeasurementOrigin::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Berkas asli dokumen RAB (PDF/JPG/PNG) wajib diunggah (D9).',
            'file.mimes' => 'Format berkas RAB harus berupa PDF, JPG, JPEG, atau PNG.',
            'file.max' => 'Ukuran berkas RAB maksimal 10 MB.',
            'name.required' => 'Nama kegiatan proyek fisik wajib diisi.',
            'type.required' => 'Pilih jenis pekerjaan proyek fisik.',
            'fund_source.required' => 'Pilih atau isi sumber dana proyek.',
            'year.required' => 'Tahun anggaran wajib diisi.',
            'lat.required' => 'Titik koordinat GPS (Latitude) wajib diisi untuk menerbitkan proyek (D3).',
            'lng.required' => 'Titik koordinat GPS (Longitude) wajib diisi untuk menerbitkan proyek (D3).',
            'items.required' => 'Minimal harus ada 1 baris item anggaran RAB (D8, D9).',
            'items.min' => 'Minimal harus ada 1 baris item anggaran RAB (D8, D9).',
            'items.*.raw_name.required' => 'Nama uraian pekerjaan/bahan wajib diisi.',
            'items.*.volume.required' => 'Volume item pekerjaan wajib diisi.',
            'items.*.volume.min' => 'Volume item harus lebih besar dari 0.',
            'items.*.unit.required' => 'Satuan item wajib diisi.',
            'items.*.unit_price_rp.required' => 'Harga satuan (Rp) wajib diisi.',
            'measurements.required' => 'Minimal harus ada 1 parameter ukuran fisik Cek Spek.',
            'measurements.min' => 'Minimal harus ada 1 parameter ukuran fisik Cek Spek.',
            'measurements.*.name.required' => 'Nama ukuran fisik wajib diisi.',
            'measurements.*.value.required' => 'Nilai target ukuran fisik wajib diisi.',
            'measurements.*.value.min' => 'Nilai ukuran fisik harus lebih besar dari 0.',
            'measurements.*.unit.required' => 'Satuan ukuran fisik wajib diisi.',
        ];
    }
}
