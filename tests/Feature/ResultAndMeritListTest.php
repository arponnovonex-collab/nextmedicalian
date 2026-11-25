<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamRegistration;
use App\Models\Marks;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\ResultProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResultAndMeritListTest extends TestCase
{
    use RefreshDatabase;

    protected Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exam = Exam::create([
            'title' => 'Admission Test',
            'code' => 'ADM-01',
            'exam_date' => now()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'total_marks' => 100,
            'total_questions' => 100,
            'marks_per_correct' => 1.0,
            'negative_marks_per_incorrect' => 0.25,
            'pass_marks' => 40,
            'status' => 'evaluation_in_progress',
        ]);
    }

    protected function createStudentWithMarks(string $name, string $phone, float $finalMarks, ?int $correct = null, ?int $incorrect = null): ExamRegistration
    {
        $u = User::create([
            'name' => $name,
            'username' => substr($phone, 1),
            'phone' => $phone,
            'password' => Hash::make('secret'),
            'role' => 'student',
            'is_active' => true,
        ]);

        $sp = StudentProfile::create([
            'user_id' => $u->id,
            'registration_number' => substr($phone, 1),
            'canonical_phone' => $phone,
        ]);

        $reg = ExamRegistration::create([
            'exam_id' => $this->exam->id,
            'student_id' => $sp->id,
            'admit_card_token' => Str::random(40),
            'status' => 'registered',
        ]);

        Marks::create([
            'exam_registration_id' => $reg->id,
            'evaluated_by' => $u->id,
            'evaluation_mode' => $correct !== null ? 'auto' : 'direct',
            'correct_answers' => $correct,
            'incorrect_answers' => $incorrect,
            'unanswered_questions' => $correct !== null ? (100 - $correct - $incorrect) : null,
            'final_marks' => $finalMarks,
            'is_locked' => true,
        ]);

        return $reg;
    }

    public function test_merit_list_ranks_highest_marks_first_with_tie_breaker(): void
    {
        // Student 1: 90 marks
        $s1 = $this->createStudentWithMarks('Top Student', '01700000001', 90.0, 92, 8);

        // Student 2 & 3: tied on 75 marks.
        // Student 2 has 80 correct, 20 incorrect.
        // Student 3 has 77 correct, 8 incorrect.
        // Student 2 should win tie-breaker because of higher correct answers (80 vs 77).
        $s2 = $this->createStudentWithMarks('Tied Higher Correct', '01700000002', 75.0, 80, 20);
        $s3 = $this->createStudentWithMarks('Tied Lower Correct', '01700000003', 75.0, 77, 8);

        // Student 4: 30 marks (Failed)
        $s4 = $this->createStudentWithMarks('Failed Student', '01700000004', 30.0, 30, 0);

        $service = app(ResultProcessingService::class);
        $ranks = $service->calculateRanks($this->exam);

        $this->assertCount(4, $ranks);

        // Rank 1: Top Student (90 marks)
        $this->assertEquals('Top Student', $ranks[0]['student_name']);
        $this->assertEquals(1, $ranks[0]['overall_merit']);

        // Rank 2: Tied Higher Correct (80 correct)
        $this->assertEquals('Tied Higher Correct', $ranks[1]['student_name']);
        $this->assertEquals(2, $ranks[1]['overall_merit']);

        // Rank 3: Tied Lower Correct (77 correct)
        $this->assertEquals('Tied Lower Correct', $ranks[2]['student_name']);
        $this->assertEquals(3, $ranks[2]['overall_merit']);

        // Rank 4: Failed Student (30 marks)
        $this->assertEquals(4, $ranks[3]['overall_merit']);
        $this->assertFalse($ranks[3]['is_passed']);
    }

    public function test_unpublished_results_hidden_from_student(): void
    {
        $reg = $this->createStudentWithMarks('Student A', '01711111111', 85.0);
        $studentUser = $reg->student->user;
        $token = $studentUser->createToken('test')->plainTextToken;

        // Result is not yet published
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/student/exams/{$this->exam->id}/result");

        $response->assertStatus(403);
    }
}
