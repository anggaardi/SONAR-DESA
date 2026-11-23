<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers;

use App\Modules\Audit\Enums\AuditStatus;
use App\Modules\Audit\Jobs\ProcessRabDocumentJob;
use App\Modules\Audit\Models\Audit;
use App\Modules\Core\Village\CurrentVillage;
use App\Modules\Core\Village\Village;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class AuditUploadController
{
    public function create(): Response
    {
        $villageId = app(CurrentVillage::class)->id();
        /** @var Village $village */
        $village = Village::findOrFail($villageId);

        $samplePath = base_path('database/seeders/assets/audits/sample-rab-melati.pdf');
        $sampleAvailable = file_exists($samplePath);

        return Inertia::render('audit/Create', [
            'village' => [
                'id' => $village->id,
                'name' => $village->name,
                'slug' => $village->slug,
                'regency' => $village->regency,
            ],
            'sampleAvailable' => $sampleAvailable,
            'sampleFileName' => 'sample-rab-melati.pdf',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $villageId = app(CurrentVillage::class)->id();
        $user = $request->user();

        $useSample = $request->boolean('use_sample');

        if ($useSample) {
            $sampleSource = base_path('database/seeders/assets/audits/sample-rab-melati.pdf');
            if (! file_exists($sampleSource)) {
                return back()->withErrors(['file' => 'Berkas contoh demo tidak ditemukan di sistem.']);
            }

            $destName = 'audits/demo-sample-'.Str::random(10).'.pdf';
            Storage::disk('local')->put($destName, (string) file_get_contents($sampleSource));
            $storedPath = $destName;
        } else {
            $request->validate([
                'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            ], [
                'file.required' => 'Pilih berkas dokumen fisik RAB terlebih dahulu.',
                'file.mimes' => 'Format berkas harus berupa PDF, JPG, JPEG, atau PNG.',
                'file.max' => 'Ukuran berkas tidak boleh melebihi 10 MB.',
            ]);

            /** @var UploadedFile $file */
            $file = $request->file('file');
            $storedPath = $file->store('audits', 'local');
        }

        $audit = Audit::create([
            'village_id' => $villageId,
            'owner_id' => $user !== null ? $user->id : 0,
            'status' => AuditStatus::Reading,
            'source' => $useSample ? 'demo_sample' : 'ocr_ai',
            'file_path' => $storedPath,
            'written_total_rp' => null,
            'total_ack' => false,
        ]);

        ProcessRabDocumentJob::dispatch($audit->id, $audit->village_id);

        return redirect()->route('audits.show', $audit)
            ->with('success', 'Dokumen RAB berhasil diunggah. AI sedang mengekstrak data...');
    }

    public function retry(Request $request, Audit $audit): RedirectResponse
    {
        $villageId = app(CurrentVillage::class)->id();
        abort_unless($audit->village_id === $villageId, 403, 'Akses ditolak.');

        if ($audit->status === AuditStatus::Published) {
            return back()->with('error', 'Dokumen audit yang sudah diterbitkan tidak dapat dibaca ulang.');
        }

        $audit->update([
            'status' => AuditStatus::Reading,
            'error_code' => null,
        ]);

        ProcessRabDocumentJob::dispatch($audit->id, $audit->village_id);

        return back()->with('success', 'Pembacaan ulang dokumen dengan AI telah dimulai.');
    }
}
