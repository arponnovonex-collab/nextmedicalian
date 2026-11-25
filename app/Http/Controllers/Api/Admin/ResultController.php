<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ResultSnapshot;
use App\Services\ResultProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResultController extends Controller
{
    public function __construct(
        protected ResultProcessingService $resultService
    ) {}

    public function preview(Request $request): JsonResponse
    {
        $request->validate(['exam_id' => 'required|exists:exams,id']);
        $examId = (int) $request->exam_id;

        $previewData = $this->resultService->previewResults($examId);

        return response()->json([
            'success' => true,
            'data' => $previewData,
        ]);
    }

    public function publish(Request $request): JsonResponse
    {
        $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'confirm_incomplete' => 'nullable|boolean',
        ]);

        $examId = (int) $request->exam_id;
        $exam = Exam::findOrFail($examId);

        $preview = $this->resultService->previewResults($examId);
        if (! empty($preview['warnings']) && ! $request->boolean('confirm_incomplete')) {
            return response()->json([
                'success' => false,
                'message' => 'There are unresolved evaluation warnings. Please confirm to proceed with publishing incomplete results.',
                'warnings' => $preview['warnings'],
                'requires_confirmation' => true,
            ], 422);
        }

        try {
            $published = $this->resultService->publishResults($examId, $request->user());
            return response()->json([
                'success' => true,
                'message' => "Results published successfully (Version {$published['version']}). {$published['published_count']} student results are now live.",
                'data' => $published,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Result publication failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function exportMeritList(Request $request): StreamedResponse|JsonResponse
    {
        $request->validate(['exam_id' => 'required|exists:exams,id']);
        $examId = (int) $request->exam_id;
        $exam = Exam::findOrFail($examId);

        // Get latest version snapshots or live ranks
        $latestVersion = ResultSnapshot::where('exam_id', $exam->id)->max('version');
        $snapshots = ResultSnapshot::where('exam_id', $exam->id)
            ->where('version', $latestVersion)
            ->with(['student.user', 'registration.center.zone', 'registration.room'])
            ->orderBy('overall_merit')
            ->get();

        if ($snapshots->isEmpty()) {
            // If not published yet, generate from calculateRanks
            $ranks = $this->resultService->calculateRanks($exam);
        } else {
            $ranks = $snapshots->map(function ($s) {
                return [
                    'overall_merit' => $s->overall_merit,
                    'zone_merit' => $s->zone_merit,
                    'center_merit' => $s->center_merit,
                    'registration_number' => $s->student->registration_number,
                    'student_name' => $s->student->user->name,
                    'institution' => $s->student->institution,
                    'zone_name' => $s->registration->center?->zone?->name ?? 'N/A',
                    'center_name' => $s->registration->center?->name ?? 'N/A',
                    'obtained_marks' => $s->obtained_marks,
                    'percentage' => $s->percentage,
                    'is_passed' => $s->is_passed,
                    'correct_count' => $s->correct_count ?? 'N/A',
                    'incorrect_count' => $s->incorrect_count ?? 'N/A',
                ];
            });
        }

        $filename = 'merit_list_' . strtolower(str_replace(' ', '_', $exam->code)) . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($ranks) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Overall Rank',
                'Zone Rank',
                'Center Rank',
                'Registration Number',
                'Student Name',
                'Institution',
                'Zone',
                'Examination Center',
                'Obtained Marks',
                'Percentage (%)',
                'Result Status',
                'Correct Answers',
                'Incorrect Answers',
            ]);

            foreach ($ranks as $r) {
                fputcsv($handle, [
                    $r['overall_merit'],
                    $r['zone_merit'],
                    $r['center_merit'],
                    $r['registration_number'],
                    $r['student_name'],
                    $r['institution'] ?? '',
                    $r['zone_name'],
                    $r['center_name'],
                    $r['obtained_marks'],
                    $r['percentage'] . '%',
                    $r['is_passed'] ? 'PASSED' : 'FAILED',
                    $r['correct_count'] ?? '',
                    $r['incorrect_count'] ?? '',
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }
}
