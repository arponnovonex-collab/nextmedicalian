<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\Exam;
use App\Models\ExamRegistration;
use App\Models\ExamRoom;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\PhoneNormalizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StudentController extends Controller
{
    public function __construct(
        protected PhoneNormalizationService $phoneService,
        protected AuditLogService $auditLogService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = ExamRegistration::with([
            'student.user',
            'exam',
            'center.zone',
            'room',
            'marks.evaluator',
            'resultSnapshot'
        ]);

        if ($request->filled('exam_id')) {
            $query->where('exam_id', $request->exam_id);
        }

        if ($request->filled('center_id')) {
            $query->where('center_id', $request->center_id);
        }

        if ($request->filled('zone_id')) {
            $query->whereHas('center', fn($q) => $q->where('zone_id', $request->zone_id));
        }

        if ($request->filled('evaluation_status')) {
            if ($request->evaluation_status === 'evaluated') {
                $query->whereHas('marks');
            } elseif ($request->evaluation_status === 'pending') {
                $query->whereDoesntHave('marks');
            }
        }

        if ($request->filled('admit_card_status')) {
            if ($request->admit_card_status === 'downloaded') {
                $query->where('admit_card_downloaded_count', '>', 0);
            } elseif ($request->admit_card_status === 'not_downloaded') {
                $query->where('admit_card_downloaded_count', 0);
            }
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereHas('student', function ($sq) use ($search) {
                    $sq->where('registration_number', 'like', "%{$search}%")
                       ->orWhere('canonical_phone', 'like', "%{$search}%")
                       ->orWhere('institution', 'like', "%{$search}%")
                       ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%{$search}%"));
                })->orWhere('seat_number', 'like', "%{$search}%");
            });
        }

        $registrations = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $registrations,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:150',
            'phone' => 'required|string',
            'email' => 'nullable|email|max:150',
            'exam_id' => 'required|exists:exams,id',
            'center_id' => 'nullable|exists:centers,id',
            'room_id' => 'nullable|exists:exam_rooms,id',
            'seat_number' => 'nullable|string|max:50',
            'institution' => 'nullable|string|max:150',
            'hsc_batch' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female,other',
            'address' => 'nullable|string',
            'guardian_phone' => 'nullable|string',
        ]);

        $parsedPhone = $this->phoneService->parse($request->phone);
        if (! $parsedPhone) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Bangladeshi mobile number. Please supply a valid 11-digit phone number.',
            ], 422);
        }

        $canonicalPhone = $parsedPhone['canonical_phone'];
        $regNo = $parsedPhone['registration_number'];

        DB::beginTransaction();
        try {
            $studentProfile = StudentProfile::where('canonical_phone', $canonicalPhone)
                ->orWhere('registration_number', $regNo)
                ->first();

            $isNew = false;
            $tempPassword = null;

            if (! $studentProfile) {
                $tempPassword = 'Med' . random_int(10000, 99999);
                $user = User::create([
                    'name' => $request->name,
                    'username' => $regNo,
                    'phone' => $canonicalPhone,
                    'email' => $request->email,
                    'password' => Hash::make($tempPassword),
                    'role' => 'student',
                    'is_active' => true,
                    'must_change_password' => true,
                ]);

                $studentProfile = StudentProfile::create([
                    'user_id' => $user->id,
                    'registration_number' => $regNo,
                    'canonical_phone' => $canonicalPhone,
                    'institution' => $request->institution,
                    'hsc_batch' => $request->hsc_batch,
                    'gender' => $request->gender,
                    'address' => $request->address,
                    'guardian_phone' => $request->guardian_phone,
                ]);

                $isNew = true;
            }

            // Check if student already enrolled in this examination
            $existing = ExamRegistration::where('exam_id', $request->exam_id)
                ->where('student_id', $studentProfile->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student is already registered for this examination.',
                ], 422);
            }

            $registration = ExamRegistration::create([
                'exam_id' => $request->exam_id,
                'student_id' => $studentProfile->id,
                'center_id' => $request->center_id,
                'room_id' => $request->room_id,
                'seat_number' => $request->seat_number,
                'admit_card_token' => Str::random(40),
                'status' => 'registered',
            ]);

            $this->auditLogService->log(
                user: $request->user(),
                action: 'student_registered_manually',
                auditableType: ExamRegistration::class,
                auditableId: $registration->id,
                examId: $request->exam_id,
                newValues: ['registration_number' => $regNo, 'is_new_student' => $isNew]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Student registered successfully',
                'data' => [
                    'registration' => $registration->load(['student.user', 'center.zone', 'room']),
                    'temporary_password' => $tempPassword,
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function show(int $id): JsonResponse
    {
        $registration = ExamRegistration::with([
            'student.user',
            'exam',
            'center.zone',
            'room',
            'marks.evaluator',
            'attendance',
            'resultSnapshot',
            'admitCardDownloads'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $registration,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $registration = ExamRegistration::with('student.user')->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:150',
            'institution' => 'nullable|string|max:150',
            'hsc_batch' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female,other',
            'address' => 'nullable|string',
            'guardian_phone' => 'nullable|string',
        ]);

        $registration->student->user->update(['name' => $request->name]);
        $registration->student->update([
            'institution' => $request->institution,
            'hsc_batch' => $request->hsc_batch,
            'gender' => $request->gender,
            'address' => $request->address,
            'guardian_phone' => $request->guardian_phone,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Student profile updated successfully',
            'data' => $registration->fresh(['student.user']),
        ]);
    }

    public function reassignSeat(Request $request, int $id): JsonResponse
    {
        $registration = ExamRegistration::findOrFail($id);

        $request->validate([
            'center_id' => 'required|exists:centers,id',
            'room_id' => 'nullable|exists:exam_rooms,id',
            'seat_number' => 'nullable|string|max:50',
        ]);

        // Check for duplicate seat allocation in same exam & center
        if ($request->filled('seat_number') && $request->filled('room_id')) {
            $duplicate = ExamRegistration::where('exam_id', $registration->exam_id)
                ->where('center_id', $request->center_id)
                ->where('room_id', $request->room_id)
                ->where('seat_number', $request->seat_number)
                ->where('id', '!=', $registration->id)
                ->first();

            if ($duplicate) {
                return response()->json([
                    'success' => false,
                    'message' => "Seat {$request->seat_number} in this room is already occupied by another student.",
                ], 422);
            }
        }

        $old = [
            'center_id' => $registration->center_id,
            'room_id' => $registration->room_id,
            'seat_number' => $registration->seat_number,
        ];

        $registration->update([
            'center_id' => $request->center_id,
            'room_id' => $request->room_id,
            'seat_number' => $request->seat_number,
        ]);

        $this->auditLogService->log(
            user: $request->user(),
            action: 'seat_reassigned',
            auditableType: ExamRegistration::class,
            auditableId: $registration->id,
            examId: $registration->exam_id,
            oldValues: $old,
            newValues: [
                'center_id' => $request->center_id,
                'room_id' => $request->room_id,
                'seat_number' => $request->seat_number,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Center and seat allocation updated successfully',
            'data' => $registration->fresh(['center.zone', 'room']),
        ]);
    }

    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $registration = ExamRegistration::with('student.user')->findOrFail($id);
        $user = $registration->student->user;

        $newPassword = 'Med' . random_int(10000, 99999);
        $user->update([
            'password' => Hash::make($newPassword),
            'must_change_password' => true,
        ]);

        $this->auditLogService->log(
            user: $request->user(),
            action: 'admin_reset_student_password',
            auditableType: User::class,
            auditableId: $user->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully',
            'data' => [
                'registration_number' => $registration->student->registration_number,
                'new_temporary_password' => $newPassword,
            ],
        ]);
    }

    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $registration = ExamRegistration::with('student.user')->findOrFail($id);
        $user = $registration->student->user;

        $user->update(['is_active' => ! $user->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Student account ' . ($user->is_active ? 'activated' : 'deactivated'),
            'data' => ['is_active' => $user->is_active],
        ]);
    }
}
