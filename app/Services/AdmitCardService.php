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

        // Resolve signature: Exam specific first, fallback to System Setting
        $rawSignature = $registration->exam->signature_path;
        if (empty($rawSignature) && class_exists(\App\Models\SystemSetting::class)) {
            $rawSignature = \App\Models\SystemSetting::where('key', 'authorized_signature_image')->value('value');
        }

        $signatureDataUri = null;
        if (! empty($rawSignature)) {
            if (str_starts_with($rawSignature, 'data:image')) {
                $signatureDataUri = $rawSignature;
            } else {
                $cleanPath = ltrim(str_replace('/storage/', '', $rawSignature), '/');
                $candidatePaths = [
                    storage_path('app/public/' . $cleanPath),
                    public_path($rawSignature),
                    public_path('storage/' . $cleanPath),
                    base_path($rawSignature),
                ];
                foreach ($candidatePaths as $cp) {
                    if (file_exists($cp) && is_file($cp)) {
                        $mime = mime_content_type($cp) ?: 'image/png';
                        $signatureDataUri = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($cp));
                        break;
                    }
                }
            }
        }

        $signatureTitle = 'Controller of Examinations';
        if (class_exists(\App\Models\SystemSetting::class)) {
            $customTitle = \App\Models\SystemSetting::where('key', 'authorized_signature_title')->value('value');
            if (! empty($customTitle)) {
                $signatureTitle = $customTitle;
            }
        }

        $pdf = Pdf::setOptions([
            'font_dir' => storage_path('fonts'),
            'font_cache' => storage_path('fonts'),
            'fontDir' => storage_path('fonts'),
            'fontCache' => storage_path('fonts'),
            'isRemoteEnabled' => true,
            'enable_remote' => true,
            'isFontSubsettingEnabled' => true,
            'enable_font_subsetting' => true,
            'chroot' => base_path(),
        ])->loadView('pdf.admit_card', [
            'registration' => $registration,
            'qrDataUri' => $qrDataUri,
            'verificationUrl' => $verificationUrl,
            'signatureDataUri' => $signatureDataUri,
            'signatureTitle' => $signatureTitle,
        ])->setPaper('a4', 'portrait');

        $fontPath = storage_path('fonts/kalpurush.ttf');
        if (file_exists($fontPath)) {
            $fontMetrics = $pdf->getDomPDF()->getFontMetrics();
            $families = $fontMetrics->getFontFamilies();
            $currentFontFile = $fontMetrics->getFont('kalpurush', 'normal');
            if (! isset($families['kalpurush']) || empty($currentFontFile) || ! file_exists($currentFontFile)) {
                $fontMetrics->registerFont([
                    'family' => 'kalpurush',
                    'weight' => 'normal',
                    'style' => 'normal',
                ], $fontPath);
                $fontMetrics->registerFont([
                    'family' => 'kalpurush',
                    'weight' => 'bold',
                    'style' => 'normal',
                ], $fontPath);
            }
        }

        return $pdf;
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
