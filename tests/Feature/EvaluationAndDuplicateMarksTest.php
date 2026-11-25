<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamRegistration;
use App\Models\Marks;
use App\Models\StudentProfile;
use App\Models\TeacherExamAssignment;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EvaluationAndDuplicateMarksTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacherA;
    protected User $teacherB;
    protected Exam $exam;
    protected ExamRegistration $registration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacherA = User::create([
            'name' => 'Teacher A',
            'username' => 'teacherA',
            'phone' => '01711111111',
            'password' => Hash::make('password123'),
            'role' => 'teacher',
            'is_active' => true,
        ]);
        TeacherProfile::create(['user_id' => $this->teacherA->id]);

        $this->teacherB = User::create([
            'name' => 'Teacher B',
            'username' => 'teacherB',
            'phone' => '01722222222',
            'password' => Hash::make('password123'),
            'role' => 'teacher',
            'is_active' => true,
        ]);
        TeacherProfile::create(['user_id' => $this->teacherB->id]);

        $this->exam = Exam::create([
            'title' => 'Biology Central Test',
            'code' => 'BIO-101',
            'exam_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'total_marks' => 100,
            'total_questions' => 100,
            'marks_per_correct' => 1.0,
            'negative_marks_per_incorrect' => 0.25,
            'marks_per_unanswered' => 0.0,
            'pass_marks' => 40,
            'status' => 'evaluation_in_progress',
        ]);

        // Assign both teachers to exam
        TeacherExamAssignment::create(['teacher_id' => $this->teacherA->id, 'exam_id' => $this->exam->id]);
        TeacherExamAssignment::create(['teacher_id' => $this->teacherB->id, 'exam_id' => $this->exam->id]);

        // Create student and registration
        $studentUser = User::create([
            'name' => 'Arif Hossain',
            'username' => '1799999999',
            'phone' => '01799999999',
            'password' => Hash::make('secret'),
            'role' => 'student',
            'is_active' => true,
        ]);

        $studentProfile = StudentProfile::create([
            'user_id' => $studentUser->id,
            'registration_number' => '1799999999',
            'canonical_phone' => '01799999999',
        ]);

        $this->registration = ExamRegistration::create([
            'exam_id' => $this->exam->id,
            'student_id' => $studentProfile->id,
            'admit_card_token' => Str::random(40),
            'status' => 'registered',
        ]);
    }

    public function test_teacher_can_submit_automatic_negative_marks(): void
    {
        Sanctum::actingAs($this->teacherA);

        // Correct 70, Incorrect 20, Unanswered 10 -> 70 - (20 * 0.25) = 65.00
        $response = $this->postJson('/api/teacher/submit-marks', [
            'registration_id' => $this->registration->id,
            'evaluation_mode' => 'auto',
            'correct_answers' => 70,
            'incorrect_answers' => 20,
            'unanswered_questions' => 10,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('marks', [
            'exam_registration_id' => $this->registration->id,
            'evaluated_by' => $this->teacherA->id,
            'final_marks' => 65.00,
            'is_locked' => true,
        ]);
    }

    public function test_strict_duplicate_marks_prevention_between_teachers(): void
    {
        Sanctum::actingAs($this->teacherA);

        // Teacher A submits marks successfully
        $resA = $this->postJson('/api/teacher/submit-marks', [
            'registration_id' => $this->registration->id,
            'evaluation_mode' => 'direct',
            'final_marks' => 80.00,
        ]);
        $resA->assertStatus(200);

        // Teacher B searches student -> sees marks_already_assigned = true, no leak of score or teacher
        Sanctum::actingAs($this->teacherB);
        $searchB = $this->getJson("/api/teacher/search-student?exam_id={$this->exam->id}&reg_no=1799999999");

        $searchB->assertStatus(200)
            ->assertJsonPath('data.marks_already_assigned', true)
            ->assertJsonPath('data.is_personally_evaluated', false);

        $this->assertNull($searchB->json('data.personal_marks'));

        // Teacher B attempts to submit marks -> strictly rejected!
        $resB = $this->postJson('/api/teacher/submit-marks', [
            'registration_id' => $this->registration->id,
            'evaluation_mode' => 'direct',
            'final_marks' => 75.00,
        ]);

        $resB->assertStatus(422);

        // Verify only ONE mark record exists in database
        $this->assertEquals(1, Marks::where('exam_registration_id', $this->registration->id)->count());
        $this->assertEquals(80.00, Marks::where('exam_registration_id', $this->registration->id)->first()->final_marks);
    }

    public function test_teacher_evaluation_history_is_isolated(): void
    {
        Sanctum::actingAs($this->teacherA);

        // Teacher A submits
        $this->postJson('/api/teacher/submit-marks', [
            'registration_id' => $this->registration->id,
            'evaluation_mode' => 'direct',
            'final_marks' => 88.00,
        ]);

        // Teacher A sees 1 record
        $histA = $this->getJson('/api/teacher/evaluation-history');
        $this->assertEquals(1, count($histA->json('data.data')));

        // Teacher B sees 0 records
        Sanctum::actingAs($this->teacherB);
        $histB = $this->getJson('/api/teacher/evaluation-history');
        $this->assertEquals(0, count($histB->json('data.data')));
    }
}
