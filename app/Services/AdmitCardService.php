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

        if ($registration->exam && ($registration->exam->code === 'NME-26' || $registration->exam->id === 3)) {
            $dateStr = $registration->exam->exam_date instanceof \DateTimeInterface
                ? $registration->exam->exam_date->format('Y-m-d')
                : (string) $registration->exam->exam_date;
            if ($dateStr !== '2026-11-27' || ! str_starts_with((string) $registration->exam->start_time, '11:00')) {
                try {
                    \Illuminate\Support\Facades\DB::table('exams')->where('id', $registration->exam->id)->update([
                        'exam_date' => '2026-11-27',
                        'start_time' => '11:00:00',
                        'end_time' => '12:00:00',
                    ]);
                    $registration->exam->exam_date = \Carbon\Carbon::parse('2026-11-27');
                    $registration->exam->start_time = '11:00:00';
                    $registration->exam->end_time = '12:00:00';
                } catch (\Throwable $e) {}
            }
        }

        if (! $registration->center_id) {
            throw new \Exception('Admit card cannot be generated: Candidate has not been assigned to an examination center.');
        }

        $verificationUrl = url("/verify/admit/{$registration->admit_card_token}");
        $qrDataUri = $this->qrService->toDataUri($verificationUrl, 100);

        // Resolve signature: Exam specific first, fallback to System Setting
        $signatureDataUri = null;
        $candidates = array_filter([
            $registration->exam->signature_path ?? null,
            class_exists(\App\Models\SystemSetting::class) ? \App\Models\SystemSetting::where('key', 'authorized_signature_image')->value('value') : null,
        ]);

        foreach ($candidates as $rawSig) {
            if (empty($rawSig)) continue;
            if (str_starts_with($rawSig, 'data:image')) {
                $signatureDataUri = $rawSig;
                break;
            }
            $cleanPath = ltrim(str_replace('/storage/', '', $rawSig), '/');
            $candidatePaths = [
                storage_path('app/public/' . $cleanPath),
                public_path($rawSig),
                public_path('storage/' . $cleanPath),
                base_path($rawSig),
            ];
            foreach ($candidatePaths as $cp) {
                if (file_exists($cp) && is_file($cp)) {
                    $mime = mime_content_type($cp) ?: 'image/png';
                    $signatureDataUri = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($cp));
                    break 2;
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

        // Instructions Graphic Data URI
        $instructionsDataUri = null;
        $instImagePath = public_path('images/admit_instructions.png');
        if (file_exists($instImagePath)) {
            $instructionsDataUri = 'data:image/png;base64,' . base64_encode(file_get_contents($instImagePath));
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
            'instructionsDataUri' => $instructionsDataUri,
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
