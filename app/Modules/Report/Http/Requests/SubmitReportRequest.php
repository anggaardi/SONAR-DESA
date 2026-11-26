<?php

declare(strict_types=1);

namespace App\Modules\Report\Http\Requests;

use App\Modules\Core\Auth\User;
use App\Modules\Project\Actions\FindPublicProject;
use App\Modules\Project\Models\Project;
use App\Modules\Report\Domain\ReportSubmission;
use App\Modules\Report\Domain\SubmittedAnswer;
use App\Modules\Report\Enums\MeasurementAnswer;
use App\Modules\Report\Http\Middleware\EnsureDeviceCookie;
use App\Modules\Report\Models\Report;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Server-side checks for POST /p/{slug}/reports (PRD Fitur Inti 5.1 steps 5-8, C5, C6). The screen checks the same
 * things (checkSpecRules.ts, useCamera.ts) only to help; this is the rule. The day limit (C10) needs a lock, so
 * SubmitReport checks it inside its transaction.
 */
final class SubmitReportRequest extends FormRequest
{
    /** Up to 9,999,999.999: what a field measurement of a village project can reasonably be. */
    private const MEASURED_VALUE_PATTERN = '/^\d{1,7}(\.\d{1,3})?$/';

    private ?Project $project = null;

    /** Unknown or unpublished project is a 404 before the role check, like the public page (P10). */
    public function authorize(): bool
    {
        $this->project();

        return $this->user()?->can('create', Report::class) ?? false;
    }

    /**
     * The published project behind {slug}. A project without a location point is a 404 too: no distance can be
     * computed for it (same rule as POST /p/{slug}/cek/jarak, D-008).
     */
    public function project(): Project
    {
        if ($this->project !== null) {
            return $this->project;
        }

        $project = app(FindPublicProject::class)->handle($this, (string) $this->route('slug'));

        if ($project === null || $project->lat === null || $project->lng === null) {
            abort(404);
        }

        $project->loadMissing('measurements');

        return $this->project = $project;
    }

    /** The route has the auth middleware and authorize() passed, so this is always the signed-in warga. */
    public function citizen(): User
    {
        $user = $this->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /** "8,5" is accepted like on the screen (checkSpecRules.ts parseMeasuredValue). Empty strings become null. */
    protected function prepareForValidation(): void
    {
        $answers = $this->input('answers');

        if (! is_array($answers)) {
            return;
        }

        $this->merge([
            'answers' => array_map(static function (mixed $answer): mixed {
                if (is_array($answer) && is_string($answer['measured_value'] ?? null)) {
                    $trimmed = trim($answer['measured_value']);
                    $answer['measured_value'] = $trimmed === '' ? null : str_replace(',', '.', $trimmed);
                }

                return $answer;
            }, $answers),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $measurementIds = $this->project()->measurements->pluck('id')->all();

        return [
            // C4: every measurement of this project is accounted for once, and only those.
            'answers' => ['required', 'array', 'size:'.count($measurementIds)],
            'answers.*' => ['required', 'array:measurement_id,answer,measured_value'],
            'answers.*.measurement_id' => ['required', 'integer', 'distinct', Rule::in($measurementIds)],
            'answers.*.answer' => ['required', Rule::enum(MeasurementAnswer::class)],
            // Measured value is optional on mismatch; for other answers it is dropped.
            'answers.*.measured_value' => [
                'exclude_unless:answers.*.answer,'.MeasurementAnswer::Mismatch->value,
                'nullable',
                'string',
                'regex:'.self::MEASURED_VALUE_PATTERN,
                'numeric',
                'gt:0',
            ],

            // C6: 1-3 photos. The camera sends JPEG; the file is re-encoded on save so EXIF/GPS is dropped.
            'photos' => ['required', 'array', 'min:1', 'max:'.(int) config('sonar.report.max_photos')],
            'photos.*' => ['required', 'file', 'mimes:jpeg,png,webp', 'max:10240'],

            'note' => ['nullable', 'string', 'max:'.(int) config('sonar.report.max_note_length')],

            // Read again at send time (M4-05 readFresh). A coarse fix cannot prove the warga is at the project,
            // so it is refused like out of radius (D-009, C7). The distance itself is checked in SubmitReport.
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['required', 'numeric', 'min:0', 'max:'.(int) config('sonar.report.max_accuracy_m')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'answers.required' => 'Jawab semua ukuran sebelum mengirim.',
            'answers.size' => 'Jawab semua ukuran sebelum mengirim.',
            'answers.*.measurement_id.in' => 'Ada ukuran yang bukan milik proyek ini. Muat ulang halaman, lalu coba lagi.',
            'answers.*.measurement_id.distinct' => 'Satu ukuran terjawab dua kali. Muat ulang halaman, lalu coba lagi.',
            'answers.*.answer.required' => 'Pilih Sesuai, Tidak, atau Tidak bisa ukur.',
            'answers.*.answer.enum' => 'Pilih Sesuai, Tidak, atau Tidak bisa ukur.',
            'answers.*.measured_value.required' => 'Isi hasil ukurmu untuk jawaban "Tidak".',
            'answers.*.measured_value.regex' => 'Hasil ukur harus berupa angka, contoh: 8 atau 8,5.',
            'answers.*.measured_value.numeric' => 'Hasil ukur harus berupa angka, contoh: 8 atau 8,5.',
            'answers.*.measured_value.gt' => 'Hasil ukur harus lebih dari 0.',
            'photos.required' => 'Ambil minimal 1 foto dari kamera.',
            'photos.min' => 'Ambil minimal 1 foto dari kamera.',
            'photos.max' => 'Foto paling banyak :max.',
            'photos.*.mimes' => 'Foto harus diambil dari kamera.',
            'photos.*.max' => 'Ukuran foto terlalu besar. Ambil ulang fotonya.',
            'note.max' => 'Catatan paling banyak :max huruf.',
            'lat.*' => 'Lokasi belum terbaca. Izinkan lokasi, lalu kirim lagi.',
            'lng.*' => 'Lokasi belum terbaca. Izinkan lokasi, lalu kirim lagi.',
            'accuracy_m.required' => 'Lokasi belum terbaca. Izinkan lokasi, lalu kirim lagi.',
            'accuracy_m.numeric' => 'Lokasi belum terbaca. Izinkan lokasi, lalu kirim lagi.',
            'accuracy_m.min' => 'Lokasi belum terbaca. Izinkan lokasi, lalu kirim lagi.',
            'accuracy_m.max' => 'Sinyal lokasi lemah (ketelitian lebih dari :max m). Tunggu sebentar di tempat terbuka, lalu kirim lagi.',
        ];
    }

    public function submission(): ReportSubmission
    {
        /** @var list<array{measurement_id: int|string, answer: string, measured_value?: string}> $answers */
        $answers = $this->validated('answers');

        /** @var list<UploadedFile> $photos */
        $photos = array_values($this->file('photos', []));

        $note = $this->validated('note');

        return new ReportSubmission(
            answers: array_map(static fn (array $answer): SubmittedAnswer => new SubmittedAnswer(
                measurementId: (int) $answer['measurement_id'],
                answer: MeasurementAnswer::from($answer['answer']),
                measuredValue: $answer['measured_value'] ?? null,
            ), $answers),
            photos: $photos,
            note: is_string($note) && trim($note) !== '' ? trim($note) : null,
            lat: (float) $this->validated('lat'),
            lng: (float) $this->validated('lng'),
            accuracyMeters: (float) $this->validated('accuracy_m'),
            deviceHash: $this->deviceHash(),
        );
    }

    /**
     * D-010: the device is the cookie set by EnsureDeviceCookie (route middleware), stored only as an HMAC keyed
     * with APP_KEY, so the database alone cannot be used to recognise a browser.
     */
    private function deviceHash(): string
    {
        return hash_hmac('sha256', (string) $this->cookie(EnsureDeviceCookie::COOKIE), (string) config('app.key'));
    }
}
