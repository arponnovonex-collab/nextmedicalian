<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamRegistration;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentRegistrationAndReuseTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Exam $exam1;
    protected Exam $exam2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'username' => 'admin',
            'phone' => '01700000000',
            'password' => Hash::make('admin123456'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->exam1 = Exam::create([
            'title' => 'Exam 1',
            'code' => 'EXAM-01',
            'exam_date' => now()->addDays(2)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'total_marks' => 100,
            'total_questions' => 100,
            'marks_per_correct' => 1.0,
            'negative_marks_per_incorrect' => 0.25,
            'pass_marks' => 40,
        ]);

        $this->exam2 = Exam::create([
            'title' => 'Exam 2',
            'code' => 'EXAM-02',
            'exam_date' => now()->addDays(5)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'total_marks' => 100,
            'total_questions' => 100,
            'marks_per_correct' => 1.0,
            'negative_marks_per_incorrect' => 0.25,
            'pass_marks' => 40,
        ]);
    }

    public function test_creates_new_student_and_generates_registration_number(): void
    {
        $token = $this->admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/students', [
                'name' => 'Fahim Ahmed',
                'phone' => '+8801712345678',
                'exam_id' => $this->exam1->id,
                'institution' => 'Notre Dame College',
                'hsc_batch' => 'HSC-2026',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('student_profiles', [
            'canonical_phone' => '01712345678',
            'registration_number' => '1712345678',
        ]);

        $this->assertDatabaseHas('users', [
            'username' => '1712345678',
            'role' => 'student',
            'must_change_password' => true,
        ]);

        $this->assertDatabaseHas('exam_registrations', [
            'exam_id' => $this->exam1->id,
        ]);
    }

    public function test_reuses_existing_student_for_second_exam_without_overwriting_password(): void
    {
        $token = $this->admin->createToken('test')->plainTextToken;

        // Register for exam 1
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/students', [
                'name' => 'Fahim Ahmed',
                'phone' => '01712345678',
                'exam_id' => $this->exam1->id,
            ]);

        $student = StudentProfile::where('canonical_phone', '01712345678')->first();
        $originalPasswordHash = $student->user->password;

        // Register for exam 2
        $res2 = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/students', [
                'name' => 'Fahim Ahmed',
                'phone' => '01712345678',
                'exam_id' => $this->exam2->id,
            ]);

        $res2->assertStatus(201);

        // Verify total user profiles is still 1
        $this->assertEquals(1, StudentProfile::where('canonical_phone', '01712345678')->count());

        // Verify password was NOT overwritten
        $student->refresh();
        $this->assertEquals($originalPasswordHash, $student->user->password);

        // Verify student has 2 registrations
        $this->assertEquals(2, ExamRegistration::where('student_id', $student->id)->count());
    }

    public function test_rejects_duplicate_registration_in_same_exam(): void
    {
        $token = $this->admin->createToken('test')->plainTextToken;

        $payload = [
            'name' => 'Fahim Ahmed',
            'phone' => '01712345678',
            'exam_id' => $this->exam1->id,
        ];

        $res1 = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/students', $payload);
        $res1->assertStatus(201);

        // Try registering again in same exam
        $res2 = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/admin/students', $payload);
        $res2->assertStatus(422);
    }

    public function test_can_download_csv_template(): void
    {
        $response = $this->get('/api/public/template/student-import.csv');
        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
    }
}
