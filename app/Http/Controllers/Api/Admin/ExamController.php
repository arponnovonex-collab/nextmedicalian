<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamCenter;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Exam::query()->withCount(['registrations', 'examCenters']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $exams = $query->orderBy('exam_date', 'desc')->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $exams,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'code' => 'required|string|max:50|unique:exams,code',
            'description' => 'nullable|string',
            'exam_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'duration_minutes' => 'required|integer|min:15|max:300',
            'total_marks' => 'required|numeric|min:1',
            'total_questions' => 'required|integer|min:1',
            'marks_per_correct' => 'required|numeric|min:0.1',
            'negative_marks_per_incorrect' => 'required|numeric|min:0',
            'marks_per_unanswered' => 'nullable|numeric|min:0',
            'pass_marks' => 'required|numeric|min:0',
            'instructions' => 'nullable|string',
            'registration_deadline' => 'nullable|date',
            'admit_card_publish_at' => 'nullable|date',
            'evaluation_deadline' => 'nullable|date',
            'result_publish_at' => 'nullable|date',
            'status' => 'nullable|in:draft,registration_open,registration_closed,admit_card_published,evaluation_in_progress,evaluation_completed,result_published,archived',
            'centers' => 'nullable|array',
            'centers.*.center_id' => 'required_with:centers|exists:centers,id',
            'centers.*.allocated_capacity' => 'nullable|integer|min:0',
        ]);

        $validated['created_by'] = $request->user()->id;
        $validated['status'] = $validated['status'] ?? 'draft';

        $centers = $validated['centers'] ?? [];
        unset($validated['centers']);

        $exam = Exam::create($validated);

        if (! empty($centers)) {
            foreach ($centers as $c) {
                ExamCenter::create([
                    'exam_id' => $exam->id,
                    'center_id' => $c['center_id'],
                    'allocated_capacity' => $c['allocated_capacity'] ?? 0,
                ]);
            }
        }

        $this->auditLogService->log(
            user: $request->user(),
            action: 'exam_created',
            auditableType: Exam::class,
            auditableId: $exam->id,
            examId: $exam->id,
            newValues: $exam->toArray()
        );

        return response()->json([
            'success' => true,
            'message' => 'Examination created successfully',
            'data' => $exam->load('examCenters.center'),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $exam = Exam::with(['examCenters.center.zone', 'teacherAssignments.teacher', 'creator'])
            ->withCount(['registrations'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $exam,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);

        $rules = [
            'title' => 'required|string|max:200',
            'code' => 'required|string|max:50|unique:exams,code,' . $exam->id,
            'description' => 'nullable|string',
            'exam_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'duration_minutes' => 'required|integer|min:15|max:300',
            'instructions' => 'nullable|string',
            'registration_deadline' => 'nullable|date',
            'admit_card_publish_at' => 'nullable|date',
            'evaluation_deadline' => 'nullable|date',
            'result_publish_at' => 'nullable|date',
        ];

        // Restrict marking configuration changes if evaluation has started
        $hasEvaluations = $exam->registrations()->whereHas('marks')->exists();
        if (! $hasEvaluations) {
            $rules['total_marks'] = 'required|numeric|min:1';
            $rules['total_questions'] = 'required|integer|min:1';
            $rules['marks_per_correct'] = 'required|numeric|min:0.1';
            $rules['negative_marks_per_incorrect'] = 'required|numeric|min:0';
            $rules['marks_per_unanswered'] = 'nullable|numeric|min:0';
            $rules['pass_marks'] = 'required|numeric|min:0';
        }

        $validated = $request->validate($rules);

        $oldValues = $exam->toArray();
        $exam->update($validated);

        $this->auditLogService->log(
            user: $request->user(),
            action: 'exam_updated',
            auditableType: Exam::class,
            auditableId: $exam->id,
            examId: $exam->id,
            oldValues: $oldValues,
            newValues: $exam->fresh()->toArray()
        );

        return response()->json([
            'success' => true,
            'message' => 'Examination updated successfully',
            'data' => $exam,
        ]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:draft,registration_open,registration_closed,admit_card_published,evaluation_in_progress,evaluation_completed,result_published,archived',
        ]);

        $exam = Exam::findOrFail($id);
        $oldStatus = $exam->status;
        $newStatus = $request->status;

        $exam->update(['status' => $newStatus]);

        $this->auditLogService->log(
            user: $request->user(),
            action: 'exam_status_changed',
            auditableType: Exam::class,
            auditableId: $exam->id,
            examId: $exam->id,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $newStatus]
        );

        return response()->json([
            'success' => true,
            'message' => "Examination status changed to {$newStatus}",
            'data' => $exam,
        ]);
    }

    public function syncCenters(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'centers' => 'required|array',
            'centers.*.center_id' => 'required|exists:centers,id',
            'centers.*.allocated_capacity' => 'nullable|integer|min:0',
        ]);

        $exam = Exam::findOrFail($id);

        ExamCenter::where('exam_id', $exam->id)->delete();

        foreach ($request->centers as $c) {
            ExamCenter::create([
                'exam_id' => $exam->id,
                'center_id' => $c['center_id'],
                'allocated_capacity' => $c['allocated_capacity'] ?? 0,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Examination centers synced successfully',
            'data' => $exam->load('examCenters.center.zone'),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);

        if ($exam->registrations()->exists()) {
            // Soft archive instead of hard delete to preserve historical integrity
            $exam->update(['status' => 'archived']);
            return response()->json([
                'success' => true,
                'message' => 'Examination contains registrations; safely archived instead of deleted.',
            ]);
        }

        $exam->delete();

        return response()->json([
            'success' => true,
            'message' => 'Examination deleted successfully',
        ]);
    }
}
