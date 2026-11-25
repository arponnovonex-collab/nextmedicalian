<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\Exam;
use App\Models\ExamRegistration;
use App\Models\Marks;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function attendanceSheet(Request $request): StreamedResponse|JsonResponse
    {
        $request->validate(['exam_id' => 'required|exists:exams,id']);
        $examId = (int) $request->exam_id;
        $exam = Exam::findOrFail($examId);

        $query = ExamRegistration::where('exam_id', $examId)
            ->with(['student.user', 'center.zone', 'room'])
            ->orderBy('center_id')
            ->orderBy('room_id')
            ->orderBy('seat_number');

        if ($request->filled('center_id')) {
            $query->where('center_id', $request->center_id);
        }

        $registrations = $query->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="attendance_sheet_' . $exam->code . '.csv"',
        ];

        return response()->stream(function () use ($registrations) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'SL',
                'Registration Number',
                'Candidate Name',
                'Mobile Number',
                'Institution',
                'Center Name',
                'Room Number',
                'Seat Number',
                'Candidate Signature',
                'Attendance Status',
            ]);

            foreach ($registrations as $i => $r) {
                fputcsv($handle, [
                    $i + 1,
                    $r->student->registration_number,
                    $r->student->user->name,
                    $r->student->canonical_phone,
                    $r->student->institution ?? '',
                    $r->center?->name ?? 'Unallocated',
                    $r->room?->room_number ?? 'N/A',
                    $r->seat_number ?? 'N/A',
                    '_______________________', // Signature space
                    '[ ] Present  [ ] Absent',
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    public function seatPlan(Request $request): StreamedResponse|JsonResponse
    {
        $request->validate(['exam_id' => 'required|exists:exams,id']);
        $examId = (int) $request->exam_id;
        $exam = Exam::findOrFail($examId);

        $query = ExamRegistration::where('exam_id', $examId)
            ->with(['student.user', 'center.zone', 'room'])
            ->orderBy('center_id')
            ->orderBy('room_id')
            ->orderBy('seat_number');

        if ($request->filled('center_id')) {
            $query->where('center_id', $request->center_id);
        }

        $registrations = $query->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="seat_plan_' . $exam->code . '.csv"',
        ];

        return response()->stream(function () use ($registrations) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Center Code',
                'Center Name',
                'Room Number',
                'Seat Number',
                'Registration Number',
                'Candidate Name',
                'Institution',
                'HSC Batch',
            ]);

            foreach ($registrations as $r) {
                fputcsv($handle, [
                    $r->center?->code ?? 'N/A',
                    $r->center?->name ?? 'Unallocated',
                    $r->room?->room_number ?? 'N/A',
                    $r->seat_number ?? 'N/A',
                    $r->student->registration_number,
                    $r->student->user->name,
                    $r->student->institution ?? '',
                    $r->student->hsc_batch ?? '',
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }

    public function evaluatorsReport(Request $request): StreamedResponse|JsonResponse
    {
        $request->validate(['exam_id' => 'required|exists:exams,id']);
        $examId = (int) $request->exam_id;
        $exam = Exam::findOrFail($examId);

        $evaluations = Marks::whereHas('registration', fn($q) => $q->where('exam_id', $examId))
            ->with(['evaluator', 'registration.student.user'])
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="evaluators_report_' . $exam->code . '.csv"',
        ];

        return response()->stream(function () use ($evaluations) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Teacher Name',
                'Teacher Phone',
                'Registration Number',
                'Candidate Name',
                'Evaluation Mode',
                'Final Marks',
                'Submitted At',
            ]);

            foreach ($evaluations as $e) {
                fputcsv($handle, [
                    $e->evaluator->name,
                    $e->evaluator->phone,
                    $e->registration->student->registration_number,
                    $e->registration->student->user->name,
                    strtoupper($e->evaluation_mode),
                    $e->final_marks,
                    $e->submitted_at->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }
}
