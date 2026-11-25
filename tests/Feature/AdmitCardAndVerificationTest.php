<?php

namespace Tests\Feature;

use App\Models\Center;
use App\Models\Exam;
use App\Models\ExamRegistration;
use App\Models\ExamRoom;
use App\Models\Marks;
use App\Models\ResultSnapshot;
use App\Models\StudentProfile;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdmitCardAndVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $studentUser;
    protected StudentProfile $studentProfile;
    protected Exam $exam;
    protected ExamRegistration $registration;

    protected function setUp(): void
    {
        parent::setUp();

        $zone = Zone::create(['name' => 'Dhaka', 'code' => 'DHK']);
        $center = Center::create([
            'zone_id' => $zone->id,
            'name' => 'Dhaka College',
            'code' => 'DHK-01',
            'address' => 'Mirpur Road, Dhaka',
            'capacity' => 500,
        ]);
        $room = ExamRoom::create([
            'center_id' => $center->id,
            'room_number' => 'R-101',
            'capacity' => 50,
        ]);

        $this->exam = Exam::create([
            'title' => 'Medical Admission Mock 2026',
            'code' => 'MOCK-2026',
            'exam_date' => now()->addDays(5)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'duration_minutes' => 60,
            'total_marks' => 100,
            'total_questions' => 100,
            'marks_per_correct' => 1.0,
            'negative_marks_per_incorrect' => 0.25,
            'marks_per_unanswered' => 0.0,
            'pass_marks' => 40,
            'status' => 'admit_card_published',
            'admit_card_release_time' => now()->subHour(),
        ]);

        $this->studentUser = User::create([
            'name' => 'Tanvir Ahmed',
            'username' => '1812345678',
            'phone' => '01812345678',
            'password' => Hash::make('password123'),
            'role' => 'student',
            'is_active' => true,
        ]);

        $this->studentProfile = StudentProfile::create([
            'user_id' => $this->studentUser->id,
            'registration_number' => '1812345678',
            'canonical_phone' => '01812345678',
            'institution' => 'Notre Dame College',
            'hsc_batch' => '2025',
        ]);

        $this->registration = ExamRegistration::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->studentProfile->id,
            'center_id' => $center->id,
            'room_id' => $room->id,
            'seat_number' => 'SEAT-01',
            'admit_card_token' => 'VALID_TEST_ADMIT_TOKEN_12345',
            'status' => 'registered',
        ]);
    }

    public function test_student_can_download_admit_card_pdf(): void
    {
        Sanctum::actingAs($this->studentUser);

        $response = $this->get('/api/student/exams/' . $this->exam->id . '/admit-card');

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));

        // Verify download was logged
        $this->assertDatabaseHas('admit_card_downloads', [
            'exam_registration_id' => $this->registration->id,
        ]);
    }

    public function test_public_can_verify_admit_card_via_token(): void
    {
        $response = $this->getJson('/api/public/verify/admit/' . $this->registration->admit_card_token);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_valid', true)
            ->assertJsonPath('data.registration_number', '1812345678')
            ->assertJsonPath('data.examination_title', 'Medical Admission Mock 2026')
            ->assertJsonPath('data.examination_center', 'Dhaka College')
            ->assertJsonPath('data.room_number', 'R-101')
            ->assertJsonPath('data.seat_number', 'SEAT-01');
    }

    public function test_public_verify_invalid_admit_token_fails(): void
    {
        $response = $this->getJson('/api/public/verify/admit/INVALID_TOKEN_99999');

        $response->assertStatus(404);
    }

    public function test_public_can_verify_published_result(): void
    {
        ResultSnapshot::create([
            'exam_id' => $this->exam->id,
            'exam_registration_id' => $this->registration->id,
            'student_id' => $this->studentProfile->id,
            'obtained_marks' => 82.50,
            'percentage' => 82.50,
            'overall_merit' => 1,
            'zone_merit' => 1,
            'center_merit' => 1,
            'is_passed' => true,
            'correct_count' => 85,
            'incorrect_count' => 10,
            'unanswered_count' => 5,
            'version' => 1,
            'published_at' => now(),
        ]);

        $this->exam->update(['status' => 'result_published']);

        $response = $this->getJson('/api/public/verify/result/' . $this->registration->admit_card_token);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('is_valid', true)
            ->assertJsonPath('data.registration_number', '1812345678')
            ->assertJsonPath('data.obtained_marks', '82.50')
            ->assertJsonPath('data.overall_merit', 1)
            ->assertJsonPath('data.is_passed', true);
    }
}
