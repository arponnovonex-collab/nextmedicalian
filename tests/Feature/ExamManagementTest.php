<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Exam;
use App\Models\ExamRegistration;
use App\Models\StudentProfile;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExamManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'System Admin',
            'username' => 'admin_exam_tester',
            'phone' => '01711112222',
            'email' => 'admin_exam@test.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($this->admin);
    }

    public function test_can_delete_exam_without_registrations(): void
    {
        $exam = Exam::create([
            'title' => 'Sample Mock Exam 2026',
            'code' => 'SME-2026',
            'exam_date' => now()->addDays(10)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 60,
            'total_marks' => 100,
            'pass_marks' => 40,
            'status' => 'draft',
        ]);

        $response = $this->deleteJson("/api/admin/exams/{$exam->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('exams', ['id' => $exam->id]);
    }

    public function test_cannot_delete_exam_with_registrations_without_force(): void
    {
        $zone = Zone::create(['name' => 'Dhaka North', 'code' => 'DHK-N']);
        $center = Center::create([
            'zone_id' => $zone->id,
            'name' => 'North Center',
            'code' => 'NC-01',
            'address' => 'Uttara',
            'capacity' => 100,
        ]);

        $exam = Exam::create([
            'title' => 'Pre-Medical 2026',
            'code' => 'PM-2026',
            'exam_date' => now()->addDays(5)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 60,
            'total_marks' => 100,
            'pass_marks' => 40,
            'status' => 'registration_open',
        ]);

        $studentUser = User::create([
            'name' => 'Exam Test Student',
            'username' => '1711122233',
            'phone' => '01711122233',
            'password' => Hash::make('password123'),
            'role' => 'student',
            'is_active' => true,
        ]);

        $studentProfile = StudentProfile::create([
            'user_id' => $studentUser->id,
            'registration_number' => '1711122233',
            'canonical_phone' => '01711122233',
            'gender' => 'male',
        ]);

        ExamRegistration::create([
            'exam_id' => $exam->id,
            'student_id' => $studentProfile->id,
            'center_id' => $center->id,
            'admit_card_token' => 'TEST_ADMIT_EXAM_TOKEN_1',
            'status' => 'registered',
        ]);

        $response = $this->deleteJson("/api/admin/exams/{$exam->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'has_registrations' => true,
            ]);

        $this->assertDatabaseHas('exams', ['id' => $exam->id]);
    }

    public function test_can_force_delete_exam_with_registrations(): void
    {
        $zone = Zone::create(['name' => 'Chittagong South', 'code' => 'CTG-S']);
        $center = Center::create([
            'zone_id' => $zone->id,
            'name' => 'South Center',
            'code' => 'SC-01',
            'address' => 'Agrabad',
            'capacity' => 100,
        ]);

        $exam = Exam::create([
            'title' => 'Medical Entrance Practice',
            'code' => 'MEP-2026',
            'exam_date' => now()->addDays(7)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 60,
            'total_marks' => 100,
            'pass_marks' => 40,
            'status' => 'registration_open',
        ]);

        $studentUser = User::create([
            'name' => 'Candidate Force',
            'username' => '1733344455',
            'phone' => '01733344455',
            'password' => Hash::make('password123'),
            'role' => 'student',
            'is_active' => true,
        ]);

        $studentProfile = StudentProfile::create([
            'user_id' => $studentUser->id,
            'registration_number' => '1733344455',
            'canonical_phone' => '01733344455',
            'gender' => 'female',
        ]);

        ExamRegistration::create([
            'exam_id' => $exam->id,
            'student_id' => $studentProfile->id,
            'center_id' => $center->id,
            'admit_card_token' => 'TEST_ADMIT_EXAM_FORCE_TOKEN',
            'status' => 'registered',
        ]);

        $response = $this->deleteJson("/api/admin/exams/{$exam->id}?force=true");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('exams', ['id' => $exam->id]);
        $this->assertDatabaseMissing('exam_registrations', ['exam_id' => $exam->id]);
    }
}
