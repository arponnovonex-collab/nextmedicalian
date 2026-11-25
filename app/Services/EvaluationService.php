<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamRegistration;
use App\Models\Marks;
use App\Models\MarksCorrectionRequest;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EvaluationService
{
    public function __construct(
        protected PhoneNormalizationService $phoneService,
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Search student by registration number or phone for evaluation by teacher.
     * Enforces teacher assignment and checks duplicate marks status.
     */
    public function searchStudentForTeacher(User $teacher, int $examId, string $regNoInput): array
    {
        // 1. Verify teacher is assigned to this exam
        $isAssigned = $teacher->teacherAssignments()->where('exam_id', $examId)->exists();
        if (! $isAssigned && ! $teacher->isAdmin()) {
            throw ValidationException::withMessages([
                'exam_id' => ['You are not assigned to evaluate this examination.'],
            ]);
        }

        $exam = Exam::findOrFail($examId);

        // 2. Resolve student registration number
        $cleanInput = trim($regNoInput);
        $studentProfile = StudentProfile::where('registration_number', $cleanInput)
            ->orWhere('canonical_phone', $cleanInput)
            ->first();

        if (! $studentProfile) {
            $parsed = $this->phoneService->parse($cleanInput);
            if ($parsed) {
                $studentProfile = StudentProfile::where('registration_number', $parsed['registration_number'])
                    ->orWhere('canonical_phone', $parsed['canonical_phone'])
                    ->first();
            }
        }

        if (! $studentProfile) {
            throw ValidationException::withMessages([
                'registration_number' => ["No student found matching '{$cleanInput}'."],
            ]);
        }

        // 3. Find registration for this exam
        $registration = ExamRegistration::where('exam_id', $exam->id)
            ->where('student_id', $studentProfile->id)
            ->with(['student.user', 'center.zone', 'room'])
            ->first();

        if (! $registration) {
            throw ValidationException::withMessages([
                'registration_number' => ["Student is not registered for examination: {$exam->title}."],
            ]);
        }

        // 4. Check if marks already exist (STRICT PRIVACY-PRESERVING DUPLICATE CHECK)
        $existingMarks = Marks::where('exam_registration_id', $registration->id)->first();

        $marksAlreadyAssigned = false;
        $isPersonallyEvaluated = false;
        $personalMarks = null;

        if ($existingMarks) {
            $marksAlreadyAssigned = true;
            if ($existingMarks->evaluated_by === $teacher->id) {
                $isPersonallyEvaluated = true;
                $personalMarks = [
                    'marks_id' => $existingMarks->id,
                    'evaluation_mode' => $existingMarks->evaluation_mode,
                    'final_marks' => $existingMarks->final_marks,
                    'correct_answers' => $existingMarks->correct_answers,
                    'incorrect_answers' => $existingMarks->incorrect_answers,
                    'unanswered_questions' => $existingMarks->unanswered_questions,
                    'submitted_at' => $existingMarks->submitted_at->toIso8601String(),
                ];
            }
        }

        return [
            'registration_id' => $registration->id,
            'student_name' => $registration->student->user->name,
            'registration_number' => $registration->student->registration_number,
            'institution' => $registration->student->institution,
            'hsc_batch' => $registration->student->hsc_batch,
            'exam_id' => $exam->id,
            'exam_title' => $exam->title,
            'exam_code' => $exam->code,
            'total_marks' => $exam->total_marks,
            'total_questions' => $exam->total_questions,
            'marks_per_correct' => $exam->marks_per_correct,
            'negative_marks_per_incorrect' => $exam->negative_marks_per_incorrect,
            'marks_per_unanswered' => $exam->marks_per_unanswered,
            'center_name' => $registration->center?->name ?? 'Unallocated',
            'room_number' => $registration->room?->room_number ?? 'N/A',
            'seat_number' => $registration->seat_number ?? 'N/A',
            'marks_already_assigned' => $marksAlreadyAssigned,
            'is_personally_evaluated' => $isPersonallyEvaluated,
            'personal_marks' => $personalMarks,
        ];
    }

    /**
     * Submit marks atomically with concurrency locking and duplicate prevention.
     */
    public function submitMarks(User $teacher, array $data): Marks
    {
        $regId = (int) $data['registration_id'];
        $mode = $data['evaluation_mode'] ?? 'auto';

        return DB::transaction(function () use ($teacher, $regId, $mode, $data) {
            // Lock registration row with pessimistic lock
            $registration = ExamRegistration::lockForUpdate()->findOrFail($regId);
            $exam = Exam::findOrFail($registration->exam_id);

            // 1. Verify teacher is assigned
            $isAssigned = $teacher->teacherAssignments()->where('exam_id', $exam->id)->exists();
            if (! $isAssigned && ! $teacher->isAdmin()) {
                throw ValidationException::withMessages([
                    'authorization' => ['You are not authorized to evaluate this examination.'],
                ]);
            }

            // 2. Strict Duplicate Prevention: check if marks already exist
            $existing = Marks::where('exam_registration_id', $registration->id)->first();
            if ($existing) {
                throw ValidationException::withMessages([
                    'marks' => ['Marks Already Assigned. This student has already been evaluated for this examination.'],
                ]);
            }

            // 3. Compute marks based on mode
            if ($mode === 'direct') {
                $finalMarks = round((float) $data['final_marks'], 2);

                if ($finalMarks > (float) $exam->total_marks) {
                    throw ValidationException::withMessages([
                        'final_marks' => ["Obtained marks ({$finalMarks}) cannot exceed maximum exam marks ({$exam->total_marks})."],
                    ]);
                }

                $marks = Marks::create([
                    'exam_registration_id' => $registration->id,
                    'evaluated_by' => $teacher->id,
                    'evaluation_mode' => 'direct',
                    'positive_marks' => $finalMarks,
                    'negative_marks' => 0.00,
                    'unanswered_marks' => 0.00,
                    'final_marks' => $finalMarks,
                    'is_locked' => true,
                    'submitted_at' => now(),
                ]);
            } else {
                // Auto calculation mode
                $correct = (int) ($data['correct_answers'] ?? 0);
                $incorrect = (int) ($data['incorrect_answers'] ?? 0);
                $unanswered = (int) ($data['unanswered_questions'] ?? 0);

                $totalSubmittedQuestions = $correct + $incorrect + $unanswered;
                if ($totalSubmittedQuestions !== (int) $exam->total_questions) {
                    throw ValidationException::withMessages([
                        'questions_count' => ["Total questions sum ({$correct} correct + {$incorrect} incorrect + {$unanswered} unanswered = {$totalSubmittedQuestions}) must equal total exam questions ({$exam->total_questions})."],
                    ]);
                }

                $positiveMarks = round($correct * (float) $exam->marks_per_correct, 2);
                $negativeMarks = round($incorrect * (float) $exam->negative_marks_per_incorrect, 2);
                $unansweredMarks = round($unanswered * (float) $exam->marks_per_unanswered, 2);

                $calculatedFinal = round($positiveMarks - $negativeMarks + $unansweredMarks, 2);

                $marks = Marks::create([
                    'exam_registration_id' => $registration->id,
                    'evaluated_by' => $teacher->id,
                    'evaluation_mode' => 'auto',
                    'correct_answers' => $correct,
                    'incorrect_answers' => $incorrect,
                    'unanswered_questions' => $unanswered,
                    'positive_marks' => $positiveMarks,
                    'negative_marks' => $negativeMarks,
                    'unanswered_marks' => $unansweredMarks,
                    'final_marks' => $calculatedFinal,
                    'is_locked' => true,
                    'submitted_at' => now(),
                ]);
            }

            $this->auditLogService->log(
                user: $teacher,
                action: 'marks_submitted',
                auditableType: Marks::class,
                auditableId: $marks->id,
                examId: $exam->id,
                newValues: [
                    'registration_id' => $registration->id,
                    'final_marks' => $marks->final_marks,
                    'mode' => $marks->evaluation_mode,
                ]
            );

            return $marks;
        });
    }

    /**
     * Submit a correction request by teacher for their own submitted marks.
     */
    public function requestCorrection(User $teacher, array $data): MarksCorrectionRequest
    {
        $marksId = (int) $data['marks_id'];
        $marks = Marks::where('id', $marksId)->where('evaluated_by', $teacher->id)->firstOrFail();

        $proposedMarks = round((float) $data['proposed_marks'], 2);
        $reason = trim($data['reason']);

        // Check if there is already a pending correction request
        $existingPending = MarksCorrectionRequest::where('marks_id', $marks->id)
            ->where('status', 'pending')
            ->first();

        if ($existingPending) {
            throw ValidationException::withMessages([
                'correction' => ['A pending correction request is already awaiting admin review for this student.'],
            ]);
        }

        $request = MarksCorrectionRequest::create([
            'marks_id' => $marks->id,
            'requested_by' => $teacher->id,
            'old_marks' => $marks->final_marks,
            'proposed_marks' => $proposedMarks,
            'reason' => $reason,
            'status' => 'pending',
        ]);

        $this->auditLogService->log(
            user: $teacher,
            action: 'marks_correction_requested',
            auditableType: MarksCorrectionRequest::class,
            auditableId: $request->id,
            examId: $marks->registration->exam_id,
            oldValues: ['old_marks' => $marks->final_marks],
            newValues: ['proposed_marks' => $proposedMarks, 'reason' => $reason]
        );

        return $request;
    }
}
