<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamRegistration;
use App\Models\ResultSnapshot;
use App\Services\AdmitCardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StudentPortalController extends Controller
{
    public function __construct(
        protected AdmitCardService $admitCardService
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $student = $user->studentProfile;

        if (! $student) {
            return response()->json([
                'success' => false,
                'message' => 'No student profile associated with this account.',
            ], 404);
        }

        $registrations = ExamRegistration::where('student_id', $student->id)
            ->with(['exam', 'center.zone', 'room', 'resultSnapshot'])
            ->orderBy('created_at', 'desc')
            ->get();

        $enrolledExams = $registrations->map(function ($reg) {
            $exam = $reg->exam;
            $center = $reg->center;

            $examDateTime = \Carbon\Carbon::parse($exam->exam_date->format('Y-m-d') . ' ' . $exam->start_time);
            $now = now();
            $isUpcoming = $examDateTime->isFuture();

            return [
                'registration_id' => $reg->id,
                'exam_id' => $exam->id,
                'exam_title' => $exam->title,
                'exam_code' => $exam->code,
                'exam_description' => $exam->description,
                'exam_date' => $exam->exam_date->format('Y-m-d'),
                'exam_date_formatted' => $exam->exam_date->format('d F, Y (l)'),
                'start_time' => date('h:i A', strtotime($exam->start_time)),
                'end_time' => date('h:i A', strtotime($exam->end_time)),
                'duration_minutes' => $exam->duration_minutes,
                'total_marks' => $exam->total_marks,
                'total_questions' => $exam->total_questions,
                'marks_per_correct' => $exam->marks_per_correct,
                'negative_marks_per_incorrect' => $exam->negative_marks_per_incorrect,
                'pass_marks' => $exam->pass_marks,
                'instructions' => $exam->instructions,
                'status' => $exam->status,
                'is_upcoming' => $isUpcoming,
                'countdown_seconds' => $isUpcoming ? max(0, $now->diffInSeconds($examDateTime, false)) : 0,
                'is_admit_available' => $exam->isAdmitCardDownloadable() && (bool) $center,
                'admit_publish_date' => $exam->admit_card_publish_at?->format('d M, Y h:i A'),
                'is_result_published' => $exam->isResultPublished(),
                'result_publish_date' => $exam->result_publish_at?->format('d M, Y h:i A'),
                'admit_downloads_count' => $reg->admit_card_downloaded_count,
                'center' => $center ? [
                    'name' => $center->name,
                    'address' => $center->address,
                    'zone' => $center->zone->name,
                    'google_maps_url' => $center->google_maps_url,
                    'google_maps_embed_url' => $center->google_maps_embed_url,
                    'contact_person' => $center->contact_person,
                    'contact_phone' => $center->contact_phone,
                    'special_instructions' => $center->special_instructions,
                ] : null,
                'room_number' => $reg->room?->room_number ?? 'To be notified',
                'seat_number' => $reg->seat_number ?? 'To be notified',
                'has_result' => (bool) $reg->resultSnapshot,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'student' => [
                    'name' => $user->name,
                    'registration_number' => $student->registration_number,
                    'phone' => $student->canonical_phone,
                    'institution' => $student->institution,
                    'hsc_batch' => $student->hsc_batch,
                    'gender' => $student->gender,
                    'address' => $student->address,
                ],
                'enrolled_exams' => $enrolledExams,
            ],
        ]);
    }

    public function downloadAdmitCard(Request $request, int $examId): Response|JsonResponse
    {
        $user = $request->user();
        $student = $user->studentProfile;

        $registration = ExamRegistration::where('student_id', $student->id)
            ->where('exam_id', $examId)
            ->with(['exam', 'center.zone', 'room'])
            ->firstOrFail();

        if (! $registration->center_id) {
            return response()->json([
                'success' => false,
                'message' => 'Your examination center has not yet been assigned. Please contact administration.',
            ], 422);
        }

        if (! $registration->exam->isAdmitCardDownloadable()) {
            return response()->json([
                'success' => false,
                'message' => 'Admit Card is not yet released for this examination.',
            ], 403);
        }

        $this->admitCardService->trackDownload($registration, $request);
        $pdf = $this->admitCardService->generateAdmitCardPdf($registration);

        $filename = 'admit_card_' . $student->registration_number . '_' . $registration->exam->code . '.pdf';

        return $pdf->stream($filename);
    }

    public function viewResult(Request $request, int $examId): JsonResponse
    {
        $user = $request->user();
        $student = $user->studentProfile;

        $registration = ExamRegistration::where('student_id', $student->id)
            ->where('exam_id', $examId)
            ->with('exam')
            ->firstOrFail();

        if (! $registration->exam->isResultPublished()) {
            return response()->json([
                'success' => false,
                'message' => 'Result Not Published Yet. Please check back later.',
            ], 403);
        }

        $latestVersion = ResultSnapshot::where('exam_id', $examId)->max('version');
        $result = ResultSnapshot::where('exam_registration_id', $registration->id)
            ->where('version', $latestVersion)
            ->first();

        if (! $result) {
            return response()->json([
                'success' => false,
                'message' => 'No published result found for this examination registration.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'student_name' => $user->name,
                'registration_number' => $student->registration_number,
                'exam_title' => $registration->exam->title,
                'exam_code' => $registration->exam->code,
                'total_marks' => $registration->exam->total_marks,
                'obtained_marks' => $result->obtained_marks,
                'percentage' => $result->percentage,
                'overall_merit' => $result->overall_merit,
                'zone_merit' => $result->zone_merit,
                'center_merit' => $result->center_merit,
                'is_passed' => $result->is_passed,
                'correct_count' => $result->correct_count,
                'incorrect_count' => $result->incorrect_count,
                'unanswered_count' => $result->unanswered_count,
                'published_at' => $result->published_at->format('d F, Y h:i A'),
            ],
        ]);
    }

    public function downloadResultPdf(Request $request, int $examId): Response|JsonResponse
    {
        $user = $request->user();
        $student = $user->studentProfile;

        $registration = ExamRegistration::where('student_id', $student->id)
            ->where('exam_id', $examId)
            ->with(['exam', 'center.zone', 'room'])
            ->firstOrFail();

        if (! $registration->exam->isResultPublished()) {
            return response()->json([
                'success' => false,
                'message' => 'Result Not Published Yet.',
            ], 403);
        }

        $latestVersion = ResultSnapshot::where('exam_id', $examId)->max('version');
        $result = ResultSnapshot::where('exam_registration_id', $registration->id)
            ->where('version', $latestVersion)
            ->firstOrFail();

        $pdf = $this->admitCardService->generateResultPdf($registration, $result);
        $filename = 'result_sheet_' . $student->registration_number . '_' . $registration->exam->code . '.pdf';

        return $pdf->stream($filename);
    }
}
