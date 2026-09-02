<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\UploadMedicalRecordFileAction;
use App\Enums\AuditAction;
use App\Enums\MedicalRecordFileCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreMedicalRecordFileRequest;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordFile;
use App\Models\User;
use App\Support\Auditing\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Arquivos clínicos anexados a um prontuário (Seção 11 do documento de
 * visão). Visualização e download são sempre auditados aqui (RN-008) —
 * nunca no model/Action de upload, que só registra a criação.
 */
class MedicalRecordFileController extends Controller
{
    public function store(StoreMedicalRecordFileRequest $request, MedicalRecord $medicalRecord, UploadMedicalRecordFileAction $action): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $action->handle(
            $medicalRecord,
            $request->file('file'),
            MedicalRecordFileCategory::from($request->validated('category')),
            $user,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Arquivo enviado.']);

        return back();
    }

    public function show(MedicalRecord $medicalRecord, MedicalRecordFile $file, AuditLogger $auditLogger): StreamedResponse
    {
        $this->authorizeFileAccess($medicalRecord, $file);

        $auditLogger->log(
            AuditAction::Viewed,
            auditable: $file,
            organization: $medicalRecord->organization,
            unit: $medicalRecord->unit,
        );

        return Storage::disk($file->disk)->response($file->path, $file->original_filename);
    }

    public function download(MedicalRecord $medicalRecord, MedicalRecordFile $file, AuditLogger $auditLogger): StreamedResponse
    {
        $this->authorizeFileAccess($medicalRecord, $file);

        $auditLogger->log(
            AuditAction::Downloaded,
            auditable: $file,
            organization: $medicalRecord->organization,
            unit: $medicalRecord->unit,
        );

        return Storage::disk($file->disk)->download($file->path, $file->original_filename);
    }

    private function authorizeFileAccess(MedicalRecord $medicalRecord, MedicalRecordFile $file): void
    {
        abort_unless($file->medical_record_id === $medicalRecord->id, HttpResponse::HTTP_NOT_FOUND);
        $this->authorize('manageFiles', $medicalRecord);
    }
}
