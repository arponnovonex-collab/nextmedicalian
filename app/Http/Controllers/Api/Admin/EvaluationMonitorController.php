<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Center;
use App\Models\Exam;
use App\Models\ExamRegistration;
use App\Models\Marks;
use App\Models\MarksCorrectionRequest;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EvaluationMonitorController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    public function monitor(Request $request): JsonResponse
    {
        $request->validate(['exam_id' => 'required|exists:exams,id']);
        $examId = (int) $request->exam_id;
        $exam = Exam::findOrFail($examId);

        $totalRegistered = ExamRegistration::where('exam_id', $examId)->count();
        $totalEvaluated = ExamRegistration::where('exam_id', $examId)->whereHas('marks')->count();
        $totalPending = $totalRegistered - $totalEvaluated;
        $completionPct = $totalRegistered > 0 ? round(($totalEvaluated / $totalRegistered) * 100, 1) : 0;

        $absentCount = AttendanceRecord::whereHas('registration', fn($q) => $q->where('exam_id', $examId))
            ->where('is_present', false)
            ->count();

        // Teacher-wise breakdown
        $teacherProgress = DB::table('teacher_exam_assignments')
            ->join('users', 'teacher_exam_assignments.teacher_id', '=', 'users.id')
            ->leftJoin('marks', function ($join) use ($examId) {
                $join->on('marks.evaluated_by', '=', 'users.id')
                     ->whereIn('marks.exam_registration_id', function ($query) use ($examId) {
                         $query->select('id')->from('exam_registrations')->where('exam_id', $examId);
                     });
            })
            ->where('teacher_exam_assignments.exam_id', $examId)
            ->select('users.id', 'users.name', 'users.username', DB::raw('count(marks.id) as evaluated_count'))
            ->groupBy('users.id', 'users.name', 'users.username')
            ->get();

        // Center-wise breakdown
        $centerProgress = DB::table('centers')
            ->join('exam_centers', 'centers.id', '=', 'exam_centers.center_id')
            ->leftJoin('exam_registrations', function ($join) use ($examId) {
                $join->on('exam_registrations.center_id', '=', 'centers.id')
                     ->where('exam_registrations.exam_id', '=', $examId);
            })
            ->leftJoin('marks', 'marks.exam_registration_id', '=', 'exam_registrations.id')
            ->where('exam_centers.exam_id', $examId)
            ->select(
                'centers.id',
                'centers.name',
                'centers.code',
                DB::raw('count(exam_registrations.id) as total_students'),
                DB::raw('count(marks.id) as evaluated_count')
            )
            ->groupBy('centers.id', 'centers.name', 'centers.code')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'exam' => $exam,
                'metrics' => [
                    'total_registered' => $totalRegistered,
                    'total_evaluated' => $totalEvaluated,
                    'pending_evaluations' => $totalPending,
                    'absent_students' => $absentCount,
                    'completion_percentage' => $completionPct,
                ],
                'teacher_progress' => $teacherProgress,
                'center_progress' => $centerProgress,
            ],
        ]);
    }

    public function marks(Request $request): JsonResponse
    {
        $request->validate(['exam_id' => 'required|exists:exams,id']);
        $examId = (int) $request->exam_id;

        $query = Marks::whereHas('registration', fn($q) => $q->where('exam_id', $examId))
            ->with([
                'registration.student.user',
                'registration.center.zone',
                'evaluator',
                'correctionRequests'
            ]);

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->whereHas('registration.student', function ($sq) use ($search) {
                $sq->where('registration_number', 'like', "%{$search}%")
                   ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%{$search}%"));
            });
        }

        $marks = $query->orderBy('submitted_at', 'desc')->paginate($request->input('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $marks,
        ]);
    }

    public function corrections(Request $request): JsonResponse
    {
        $query = MarksCorrectionRequest::with([
            'marks.registration.student.user',
            'marks.registration.exam',
            'requester',
            'reviewer'
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $requests = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    public function reviewCorrection(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'admin_notes' => 'nullable|string',
        ]);

        $correction = MarksCorrectionRequest::with('marks.registration')->findOrFail($id);

        if ($correction->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This correction request has already been reviewed.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $oldMarks = $correction->marks->final_marks;
            $newMarks = $correction->proposed_marks;

            $correction->update([
                'status' => $request->status,
                'reviewed_by' => $request->user()->id,
                'admin_notes' => $request->admin_notes,
                'reviewed_at' => now(),
            ]);

            if ($request->status === 'approved') {
                $correction->marks->update([
                    'final_marks' => $newMarks,
                    'positive_marks' => $newMarks,
                    'negative_marks' => 0.00,
                    'unanswered_marks' => 0.00,
                    'evaluation_mode' => 'direct', // converted to direct after approved correction
                ]);
            }

            $this->auditLogService->log(
                user: $request->user(),
                action: 'marks_correction_' . $request->status,
                auditableType: MarksCorrectionRequest::class,
                auditableId: $correction->id,
                examId: $correction->marks->registration->exam_id,
                oldValues: ['final_marks' => $oldMarks],
                newValues: ['final_marks' => $newMarks, 'status' => $request->status]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Correction request {$request->status} successfully.",
                'data' => $correction->fresh(),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function adminCorrect(Request $request): JsonResponse
    {
        $request->validate([
            'marks_id' => 'required|exists:marks,id',
            'new_marks' => 'required|numeric|min:0',
            'reason' => 'required|string|min:5',
        ]);

        $marks = Marks::with('registration')->findOrFail($request->marks_id);
        $oldMarks = $marks->final_marks;
        $newMarks = round((float) $request->new_marks, 2);

        $marks->update([
            'final_marks' => $newMarks,
            'positive_marks' => $newMarks,
            'negative_marks' => 0.00,
            'unanswered_marks' => 0.00,
            'evaluation_mode' => 'direct',
        ]);

        $this->auditLogService->log(
            user: $request->user(),
            action: 'admin_direct_marks_correction',
            auditableType: Marks::class,
            auditableId: $marks->id,
            examId: $marks->registration->exam_id,
            oldValues: ['final_marks' => $oldMarks],
            newValues: ['final_marks' => $newMarks, 'reason' => $request->reason]
        );

        return response()->json([
            'success' => true,
            'message' => 'Marks updated and audited successfully by administrator.',
            'data' => $marks->fresh(),
        ]);
    }

    public function recordAbsence(Request $request): JsonResponse
    {
        $request->validate([
            'registration_id' => 'required|exists:exam_registrations,id',
            'is_present' => 'required|boolean',
            'remarks' => 'nullable|string',
        ]);

        $record = AttendanceRecord::updateOrCreate(
            ['exam_registration_id' => $request->registration_id],
            [
                'is_present' => $request->is_present,
                'recorded_by' => $request->user()->id,
                'remarks' => $request->remarks,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Attendance status recorded.',
            'data' => $record,
        ]);
    }
}
