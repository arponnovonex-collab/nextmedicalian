<?php

namespace App\Services;

use App\Models\AdmitCardDownload;
use App\Models\ExamRegistration;
use App\Models\ResultSnapshot;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdmitCardService
{
    public function __construct(
        protected QrCodeService $qrService
    ) {}

    public function generateAdmitCardPdf(ExamRegistration $registration): \Barryvdh\DomPDF\PDF
    {
        $registration->load(['student.user', 'exam', 'center.zone', 'room']);

        if (! $registration->center_id) {
            throw new \Exception('Admit card cannot be generated: Candidate has not been assigned to an examination center.');
        }

        $verificationUrl = url("/verify/admit/{$registration->admit_card_token}");
        $qrDataUri = $this->qrService->toDataUri($verificationUrl, 100);

        return Pdf::loadView('pdf.admit_card', [
            'registration' => $registration,
            'qrDataUri' => $qrDataUri,
            'verificationUrl' => $verificationUrl,
        ])->setPaper('a4', 'portrait');
    }

    public function generateResultPdf(ExamRegistration $registration, ResultSnapshot $result): \Barryvdh\DomPDF\PDF
    {
        $registration->load(['student.user', 'exam', 'center.zone', 'room']);

        $verificationUrl = url("/verify/result/{$registration->admit_card_token}");
        $qrDataUri = $this->qrService->toDataUri($verificationUrl, 100);

        return Pdf::loadView('pdf.result_sheet', [
            'registration' => $registration,
            'result' => $result,
            'qrDataUri' => $qrDataUri,
            'verificationUrl' => $verificationUrl,
        ])->setPaper('a4', 'portrait');
    }

    public function trackDownload(ExamRegistration $registration, Request $request): void
    {
        $registration->increment('admit_card_downloaded_count');

        if (! $registration->first_admit_download_at) {
            $registration->update(['first_admit_download_at' => now()]);
        }

        AdmitCardDownload::create([
            'exam_registration_id' => $registration->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'downloaded_at' => now(),
        ]);
    }
}
