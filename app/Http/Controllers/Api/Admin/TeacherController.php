<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Marks;
use App\Models\TeacherExamAssignment;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\PhoneNormalizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TeacherController extends Controller
{
    public function __construct(
        protected PhoneNormalizationService $phoneService,
        protected AuditLogService $auditLogService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = User::where('role', 'teacher')->with(['teacherProfile', 'teacherAssignments.exam']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $teachers = $query->orderBy('name')->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $teachers,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'username' => 'required|string|max:50|unique:users,username',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:150|unique:users,email',
            'password' => 'required|string|min:6',
            'designation' => 'nullable|string|max:100',
            'institution' => 'nullable|string|max:150',
            'department' => 'nullable|string|max:100',
            'assigned_exams' => 'nullable|array',
            'assigned_exams.*' => 'exists:exams,id',
        ]);

        DB::beginTransaction();
        try {
            $user = User::create([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'phone' => $validated['phone'],
                'email' => $validated['email'] ?? null,
                'password' => Hash::make($validated['password']),
                'role' => 'teacher',
                'is_active' => true,
                'must_change_password' => false,
            ]);

            TeacherProfile::create([
                'user_id' => $user->id,
                'designation' => $validated['designation'] ?? null,
                'institution' => $validated['institution'] ?? null,
                'department' => $validated['department'] ?? null,
            ]);

            if (! empty($validated['assigned_exams'])) {
                foreach ($validated['assigned_exams'] as $examId) {
                    TeacherExamAssignment::create([
                        'teacher_id' => $user->id,
                        'exam_id' => $examId,
                        'assigned_by' => $request->user()->id,
                    ]);
                }
            }

            $this->auditLogService->log(
                user: $request->user(),
                action: 'teacher_created',
                auditableType: User::class,
                auditableId: $user->id,
                newValues: ['name' => $user->name, 'username' => $user->username]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Teacher account created successfully',
                'data' => $user->load(['teacherProfile', 'teacherAssignments.exam']),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function show(int $id): JsonResponse
    {
        $teacher = User::where('role', 'teacher')
            ->with(['teacherProfile', 'teacherAssignments.exam'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $teacher,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $teacher = User::where('role', 'teacher')->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:150|unique:users,email,' . $teacher->id,
            'designation' => 'nullable|string|max:100',
            'institution' => 'nullable|string|max:150',
            'department' => 'nullable|string|max:100',
        ]);

        $teacher->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
        ]);

        $teacher->teacherProfile()->updateOrCreate(
            ['user_id' => $teacher->id],
            [
                'designation' => $validated['designation'] ?? null,
                'institution' => $validated['institution'] ?? null,
                'department' => $validated['department'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Teacher updated successfully',
            'data' => $teacher->load(['teacherProfile', 'teacherAssignments.exam']),
        ]);
    }

    public function assignExams(Request $request, int $id): JsonResponse
    {
        $teacher = User::where('role', 'teacher')->findOrFail($id);

        $request->validate([
            'exam_ids' => 'required|array',
            'exam_ids.*' => 'exists:exams,id',
        ]);

        TeacherExamAssignment::where('teacher_id', $teacher->id)->delete();

        foreach ($request->exam_ids as $examId) {
            TeacherExamAssignment::create([
                'teacher_id' => $teacher->id,
                'exam_id' => $examId,
                'assigned_by' => $request->user()->id,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Examinations assigned successfully',
            'data' => $teacher->load('teacherAssignments.exam'),
        ]);
    }

    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $teacher = User::where('role', 'teacher')->findOrFail($id);

        $request->validate([
            'password' => 'required|string|min:6',
        ]);

        $teacher->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Teacher password reset successfully',
        ]);
    }

    public function toggleStatus(int $id): JsonResponse
    {
        $teacher = User::where('role', 'teacher')->findOrFail($id);
        $teacher->update(['is_active' => ! $teacher->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Teacher account ' . ($teacher->is_active ? 'activated' : 'deactivated'),
            'data' => ['is_active' => $teacher->is_active],
        ]);
    }

    public function stats(int $id): JsonResponse
    {
        $teacher = User::where('role', 'teacher')->findOrFail($id);

        $totalEvaluated = Marks::where('evaluated_by', $teacher->id)->count();
        $todayEvaluated = Marks::where('evaluated_by', $teacher->id)
            ->whereDate('submitted_at', now()->toDateString())
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_evaluated' => $totalEvaluated,
                'today_evaluated' => $todayEvaluated,
            ],
        ]);
    }
}
