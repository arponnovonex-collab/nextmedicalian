<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Marks;
use App\Services\EvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherEvaluationController extends Controller
{
    public function __construct(
        protected EvaluationService $evaluationService
    ) {}

    public function assignedExams(Request $request): JsonResponse
    {
        $teacher = $request->user();

        $exams = Exam::whereHas('teacherAssignments', fn($q) => $q->where('teacher_id', $teacher->id))
            ->orderBy('exam_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $exams,
        ]);
    }

    public function dashboardStats(Request $request): JsonResponse
    {
        $teacher = $request->user();
        $examId = $request->input('exam_id');

        $marksQuery = Marks::where('evaluated_by', $teacher->id);
        if ($examId) {
            $marksQuery->whereHas('registration', fn($q) => $q->where('exam_id', $examId));
        }

        $totalEvaluated = (clone $marksQuery)->count();
        $todayEvaluated = (clone $marksQuery)->whereDate('submitted_at', now()->toDateString())->count();

        // Recent 10 marks submissions
        $recentSubmissions = (clone $marksQuery)
            ->with(['registration.student.user', 'registration.exam'])
            ->orderBy('submitted_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_evaluated' => $totalEvaluated,
                'today_evaluated' => $todayEvaluated,
                'recent_submissions' => $recentSubmissions,
            ],
        ]);
    }

    public function searchStudent(Request $request): JsonResponse
    {
        $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'reg_no' => 'required|string',
        ]);

        $examId = (int) $request->exam_id;
        $regNo = $request->reg_no;

        $studentData = $this->evaluationService->searchStudentForTeacher(
            $request->user(),
            $examId,
            $regNo
        );

        return response()->json([
            'success' => true,
            'data' => $studentData,
        ]);
    }

    public function submitMarks(Request $request): JsonResponse
    {
        $request->validate([
            'registration_id' => 'required|exists:exam_registrations,id',
            'evaluation_mode' => 'required|in:direct,auto',
            'final_marks' => 'required_if:evaluation_mode,direct|nullable|numeric|min:0',
            'correct_answers' => 'required_if:evaluation_mode,auto|nullable|integer|min:0',
            'incorrect_answers' => 'required_if:evaluation_mode,auto|nullable|integer|min:0',
            'unanswered_questions' => 'required_if:evaluation_mode,auto|nullable|integer|min:0',
        ]);

        $marks = $this->evaluationService->submitMarks($request->user(), $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Marks submitted and locked successfully.',
            'data' => $marks,
        ]);
    }

    public function evaluationHistory(Request $request): JsonResponse
    {
        $teacher = $request->user();
        $examId = $request->input('exam_id');

        $query = Marks::where('evaluated_by', $teacher->id)
            ->with([
                'registration.student.user',
                'registration.exam',
                'registration.center',
                'correctionRequests'
            ]);

        if ($examId) {
            $query->whereHas('registration', fn($q) => $q->where('exam_id', $examId));
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->whereHas('registration.student', function ($sq) use ($search) {
                $sq->where('registration_number', 'like', "%{$search}%")
                   ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%{$search}%"));
            });
        }

        $history = $query->orderBy('submitted_at', 'desc')->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }

    public function requestCorrection(Request $request): JsonResponse
    {
        $request->validate([
            'marks_id' => 'required|exists:marks,id',
            'proposed_marks' => 'required|numeric|min:0',
            'reason' => 'required|string|min:10',
        ]);

        $correction = $this->evaluationService->requestCorrection($request->user(), $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Correction request submitted for administrative review.',
            'data' => $correction,
        ]);
    }
}
