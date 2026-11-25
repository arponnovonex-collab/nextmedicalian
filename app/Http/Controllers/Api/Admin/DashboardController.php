<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\Exam;
use App\Models\ExamRegistration;
use App\Models\Marks;
use App\Models\StudentProfile;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $examId = $request->input('exam_id');

        $activeExam = null;
        if ($examId) {
            $activeExam = Exam::find($examId);
        }

        // Global base counts
        $totalExams = Exam::count();
        $upcomingExams = Exam::where('exam_date', '>=', now()->toDateString())->count();
        $completedExams = Exam::where('status', 'result_published')->orWhere('status', 'archived')->count();
        $totalTeachers = User::where('role', 'teacher')->count();
        $totalZones = Zone::count();
        $totalCenters = Center::count();

        // Registration & Evaluation query
        $regQuery = ExamRegistration::query();
        if ($examId) {
            $regQuery->where('exam_id', $examId);
        }

        $totalRegisteredStudents = $examId
            ? $regQuery->count()
            : StudentProfile::count();

        $admitCardDownloads = (clone $regQuery)->where('admit_card_downloaded_count', '>', 0)->count();
        
        $totalEvaluated = (clone $regQuery)->whereHas('marks')->count();
        $totalPending = $totalRegisteredStudents - $totalEvaluated;

        // Zone-wise student distribution for chart
        $zoneDistribution = DB::table('exam_registrations')
            ->join('centers', 'exam_registrations.center_id', '=', 'centers.id')
            ->join('zones', 'centers.zone_id', '=', 'zones.id')
            ->when($examId, fn($q) => $q->where('exam_registrations.exam_id', $examId))
            ->select('zones.name as zone', DB::raw('count(exam_registrations.id) as students'))
            ->groupBy('zones.name')
            ->orderByDesc('students')
            ->get();

        // Exam-wise registration statistics for chart
        $examRegistrations = Exam::select('id', 'title', 'code')
            ->withCount(['registrations', 'resultSnapshots'])
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get()
            ->map(function ($e) {
                return [
                    'exam' => $e->code,
                    'title' => $e->title,
                    'registrations' => $e->registrations_count,
                    'evaluated' => Marks::whereHas('registration', fn($q) => $q->where('exam_id', $e->id))->count(),
                ];
            });

        // Teacher-wise evaluation count for chart
        $teacherEvaluationCounts = DB::table('marks')
            ->join('users', 'marks.evaluated_by', '=', 'users.id')
            ->join('exam_registrations', 'marks.exam_registration_id', '=', 'exam_registrations.id')
            ->when($examId, fn($q) => $q->where('exam_registrations.exam_id', $examId))
            ->select('users.name as teacher', DB::raw('count(marks.id) as evaluated_count'))
            ->groupBy('users.name')
            ->orderByDesc('evaluated_count')
            ->limit(10)
            ->get();

        // Result distribution (Marks brackets: 0-39, 40-59, 60-79, 80-100)
        $marksQuery = Marks::query();
        if ($examId) {
            $marksQuery->whereHas('registration', fn($q) => $q->where('exam_id', $examId));
        }

        $marksBrackets = [
            ['bracket' => '< 40 (Fail)', 'count' => (clone $marksQuery)->where('final_marks', '<', 40)->count()],
            ['bracket' => '40 - 59', 'count' => (clone $marksQuery)->whereBetween('final_marks', [40, 59.99])->count()],
            ['bracket' => '60 - 79', 'count' => (clone $marksQuery)->whereBetween('final_marks', [60, 79.99])->count()],
            ['bracket' => '80 - 100', 'count' => (clone $marksQuery)->where('final_marks', '>=', 80)->count()],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'active_exam' => $activeExam,
                'summary' => [
                    'total_registered_students' => $totalRegisteredStudents,
                    'total_exams' => $totalExams,
                    'upcoming_exams' => $upcomingExams,
                    'completed_exams' => $completedExams,
                    'total_teachers' => $totalTeachers,
                    'total_zones' => $totalZones,
                    'total_centers' => $totalCenters,
                    'total_evaluated' => $totalEvaluated,
                    'pending_evaluations' => max(0, $totalPending),
                    'admit_card_downloads' => $admitCardDownloads,
                    'result_status' => $activeExam ? $activeExam->status : 'global',
                ],
                'charts' => [
                    'zone_distribution' => $zoneDistribution,
                    'exam_registrations' => $examRegistrations,
                    'teacher_evaluations' => $teacherEvaluationCounts,
                    'marks_distribution' => $marksBrackets,
                ],
            ],
        ]);
    }
}
