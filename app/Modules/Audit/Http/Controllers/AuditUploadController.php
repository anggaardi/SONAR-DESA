<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Controllers;

use App\Modules\Audit\Actions\AiReadBudget;
use App\Modules\Audit\Actions\CreateAuditFromFixture;
use App\Modules\Audit\Enums\AuditStatus;
use App\Modules\Audit\Jobs\ProcessRabDocumentJob;
use App\Modules\Audit\Models\Audit;
use App\Modules\Core\Auth\User;
use App\Modules\Core\Village\CurrentVillage;
use App\Modules\Core\Village\Village;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class AuditUploadController
{
    public function create(AiReadBudget $budget): Response
    {
        $villageId = app(CurrentVillage::class)->id();
        /** @var Village $village */
        $village = Village::findOrFail($villageId);

        $samplePath = base_path('database/seeders/assets/audits/sample-rab-melati.pdf');
        $sampleAvailable = file_exists($samplePath);

        $driver = (string) config('sonar.rab_reader.driver', 'fixture');
        $aiModel = (string) config('sonar.rab_reader.ai.model', 'google/gemini-1.5-flash');
        $aiProvider = (string) config('sonar.rab_reader.ai.provider', 'databyte');
        $hasApiKey = ! blank(config('sonar.rab_reader.ai.api_key'));

        return Inertia::render('audit/Create', [
            'village' => [
                'id' => $village->id,
                'name' => $village->name,
                'slug' => $village->slug,
                'regency' => $village->regency,
            ],
            'sampleAvailable' => $sampleAvailable,
            'sampleFileName' => 'sample-rab-melati.pdf',
            'aiReader' => [
                'driver' => $driver,
                'provider' => $aiProvider,
                'model' => $aiModel,
                'is_ai_active' => $driver === 'ai' && $hasApiKey,
            ],
            'aiBudget' => $budget->statusForVillage($villageId),
        ]);
    }

    public function store(
        Request $request,
        AiReadBudget $budget,
        CreateAuditFromFixture $createFromFixture,
    ): RedirectResponse {
        $villageId = app(CurrentVillage::class)->id();
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $useSample = $request->boolean('use_sample');

        if ($useSample) {
            $sampleSource = base_path('database/seeders/assets/audits/sample-rab-melati.pdf');
            if (! file_exists($sampleSource)) {
                return back()->withErrors(['file' => 'Berkas contoh demo tidak ditemukan di sistem.']);
            }

            $destName = 'audits/demo-sample-'.Str::random(10).'.pdf';
            Storage::disk('local')->put($destName, (string) file_get_contents($sampleSource));

            /** @var Village $village */
            $village = Village::findOrFail($villageId);
            $audit = $createFromFixture->execute($village, $user, $destName, 'demo_sample');

            return redirect()->route('audits.show', $audit)
                ->with('success', 'Contoh RAB demo berhasil dimuat tanpa menggunakan kuota AI.');
        }

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

        $hasBudget = $budget->claimForVillage($villageId);

        $audit = Audit::create([
            'village_id' => $villageId,
            'owner_id' => $user->id,
            'status' => $hasBudget ? AuditStatus::Reading : AuditStatus::ReadFailed,
            'source' => 'ocr_ai',
            'file_path' => $storedPath,
            'written_total_rp' => null,
            'total_ack' => false,
            'error_code' => $hasBudget ? null : 'AI_BUDGET',
        ]);

        if (! $hasBudget) {
            return redirect()->route('audits.show', $audit)
                ->with('error', 'Kuota pembacaan AI harian telah habis. Anda dapat mencoba Contoh RAB atau menambah rincian secara manual.');
        }

        ProcessRabDocumentJob::dispatch($audit->id, $audit->village_id, true);

        return redirect()->route('audits.show', $audit)
            ->with('success', 'Dokumen RAB berhasil diunggah. AI sedang mengekstrak data...');
    }

    public function retry(Request $request, Audit $audit, AiReadBudget $budget): RedirectResponse
    {
        $villageId = app(CurrentVillage::class)->id();
        abort_unless($audit->village_id === $villageId, 403, 'Akses ditolak.');

        if ($audit->status === AuditStatus::Published) {
            return back()->with('error', 'Dokumen audit yang sudah diterbitkan tidak dapat dibaca ulang.');
        }

        if ($audit->source === 'demo_sample') {
            $audit->update([
                'status' => AuditStatus::Reading,
                'error_code' => null,
            ]);

            ProcessRabDocumentJob::dispatch($audit->id, $audit->village_id, true);

            return back()->with('success', 'Pembacaan ulang dokumen contoh demo telah dimulai.');
        }

        if (! $budget->claimForVillage($villageId)) {
            $audit->update([
                'status' => AuditStatus::ReadFailed,
                'error_code' => 'AI_BUDGET',
            ]);

            return back()->with('error', 'Kuota pembacaan AI harian telah habis. Anda dapat mencoba Contoh RAB atau menambah rincian secara manual.');
        }

        $audit->update([
            'status' => AuditStatus::Reading,
            'error_code' => null,
        ]);

        ProcessRabDocumentJob::dispatch($audit->id, $audit->village_id, true);

        return back()->with('success', 'Pembacaan ulang dokumen dengan AI telah dimulai.');
    }

    public function replaceFile(Request $request, Audit $audit, AiReadBudget $budget): RedirectResponse
    {
        $villageId = app(CurrentVillage::class)->id();
        abort_unless($audit->village_id === $villageId, 403, 'Akses ditolak.');

        $audit->loadMissing('project');
        $isPublished = $audit->status === AuditStatus::Published
            || ($audit->project !== null && $audit->project->isPublished());

        if ($isPublished) {
            abort(403, 'Dokumen audit yang sudah diterbitkan tidak dapat diganti berkasnya (D4, D10).');
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], [
            'file.required' => 'Pilih berkas dokumen fisik RAB pengganti terlebih dahulu.',
            'file.mimes' => 'Format berkas harus berupa PDF, JPG, JPEG, atau PNG.',
            'file.max' => 'Ukuran berkas tidak boleh melebihi 10 MB.',
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $newPath = $file->store('audits', 'local');
        $oldPath = $audit->file_path;
        $hasBudget = $budget->claimForVillage($villageId);

        DB::transaction(function () use ($audit, $newPath, $hasBudget): void {
            $audit->items()->delete();
            $audit->update([
                'status' => $hasBudget ? AuditStatus::Reading : AuditStatus::ReadFailed,
                'source' => 'ocr_ai',
                'file_path' => $newPath,
                'written_total_rp' => null,
                'total_ack' => false,
                'read_ms' => null,
                'error_code' => $hasBudget ? null : 'AI_BUDGET',
            ]);
        });

        if ($oldPath && $oldPath !== $newPath && Storage::disk('local')->exists($oldPath)) {
            Storage::disk('local')->delete($oldPath);
        }

        if (! $hasBudget) {
            return redirect()->route('audits.show', $audit)
                ->with('error', 'Kuota pembacaan AI harian telah habis. Anda dapat mencoba Contoh RAB atau menambah rincian secara manual.');
        }

        ProcessRabDocumentJob::dispatch($audit->id, $audit->village_id, true);

        return redirect()->route('audits.show', $audit)
            ->with('success', 'Berkas RAB berhasil diganti. AI sedang mengekstrak ulang dokumen baru...');
    }

    public function destroy(Request $request, Audit $audit): RedirectResponse
    {
        $villageId = app(CurrentVillage::class)->id();
        abort_unless($audit->village_id === $villageId, 403, 'Akses ditolak.');

        $audit->loadMissing('project');
        $isPublished = $audit->status === AuditStatus::Published
            || ($audit->project !== null && $audit->project->isPublished());

        if ($isPublished) {
            abort(403, 'Dokumen audit yang sudah diterbitkan tidak dapat dihapus (D4, D10).');
        }

        $filePath = $audit->file_path;

        DB::transaction(function () use ($audit): void {
            if ($audit->project !== null) {
                $audit->project->measurements()->delete();
                $audit->project->delete();
            }

            $audit->items()->delete();
            $audit->delete();
        });

        if ($filePath && Storage::disk('local')->exists($filePath)) {
            Storage::disk('local')->delete($filePath);
        }

        return redirect()->route('desa.dashboard')
            ->with('success', 'Dokumen RAB berhasil dihapus.');
    }
}
