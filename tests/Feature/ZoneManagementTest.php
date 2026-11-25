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

class ZoneManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'username' => 'admin_zone_test',
            'phone' => '01799999999',
            'email' => 'admin_zone@test.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        Sanctum::actingAs($this->admin);
    }

    public function test_can_delete_empty_zone(): void
    {
        $zone = Zone::create(['name' => 'Barishal', 'code' => 'BAR-01']);

        $response = $this->deleteJson("/api/admin/zones/{$zone->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('zones', ['id' => $zone->id]);
    }

    public function test_cannot_delete_zone_with_centers_without_force(): void
    {
        $zone = Zone::create(['name' => 'Sylhet', 'code' => 'SYL-01']);
        Center::create([
            'zone_id' => $zone->id,
            'name' => 'Sylhet Test Center',
            'code' => 'SYL-C01',
            'address' => 'Sylhet Town',
            'capacity' => 100,
        ]);

        $response = $this->deleteJson("/api/admin/zones/{$zone->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'has_centers' => true,
            ]);

        $this->assertDatabaseHas('zones', ['id' => $zone->id]);
    }

    public function test_can_force_delete_zone_with_empty_centers(): void
    {
        $zone = Zone::create(['name' => 'Khulna', 'code' => 'KHU-01']);
        $center = Center::create([
            'zone_id' => $zone->id,
            'name' => 'Khulna Center',
            'code' => 'KHU-C01',
            'address' => 'Khulna City',
            'capacity' => 150,
        ]);

        $response = $this->deleteJson("/api/admin/zones/{$zone->id}?force=true");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('zones', ['id' => $zone->id]);
        $this->assertDatabaseMissing('centers', ['id' => $center->id]);
    }

    public function test_cannot_force_delete_zone_if_center_has_registered_students(): void
    {
        $zone = Zone::create(['name' => 'Chattogram', 'code' => 'CTG-01']);
        $center = Center::create([
            'zone_id' => $zone->id,
            'name' => 'CTG Medical College',
            'code' => 'CTG-C01',
            'address' => 'Panchlaish, CTG',
            'capacity' => 500,
        ]);

        $studentUser = User::create([
            'name' => 'Candidate One',
            'username' => '1712345678',
            'phone' => '01712345678',
            'password' => Hash::make('password123'),
            'role' => 'student',
            'is_active' => true,
        ]);

        $studentProfile = StudentProfile::create([
            'user_id' => $studentUser->id,
            'registration_number' => '1712345678',
            'canonical_phone' => '01712345678',
            'gender' => 'male',
        ]);

        $exam = Exam::create([
            'title' => 'Pre-Med 2026',
            'code' => 'PM-2026',
            'exam_date' => now()->addDays(5)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
            'total_marks' => 100,
            'total_questions' => 100,
            'marks_per_correct' => 1.0,
            'negative_marks_per_incorrect' => 0.25,
            'pass_marks' => 40,
        ]);

        ExamRegistration::create([
            'exam_id' => $exam->id,
            'student_id' => $studentProfile->id,
            'center_id' => $center->id,
            'admit_card_token' => 'VALID_TEST_ADMIT_TOKEN_12345',
            'status' => 'registered',
        ]);

        $response = $this->deleteJson("/api/admin/zones/{$zone->id}?force=true");

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseHas('zones', ['id' => $zone->id]);
        $this->assertDatabaseHas('centers', ['id' => $center->id]);
    }
}
