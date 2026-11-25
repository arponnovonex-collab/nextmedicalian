<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationAndRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed Admin, Teacher, Student
        User::create([
            'name' => 'Admin User',
            'username' => 'admin',
            'phone' => '01700000000',
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Teacher User',
            'username' => 'teacher1',
            'phone' => '01711002233',
            'password' => Hash::make('password123'),
            'role' => 'teacher',
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Student User',
            'username' => '1712345678',
            'phone' => '01712345678',
            'password' => Hash::make('password123'),
            'role' => 'student',
            'is_active' => true,
        ]);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => 'admin',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'role' => 'admin',
                ],
            ]);

        $this->assertNotNull($response->json('data.token'));
    }

    public function test_student_can_login_with_phone_or_reg_number(): void
    {
        // Login using phone
        $resPhone = $this->postJson('/api/auth/login', [
            'username' => '01712345678',
            'password' => 'password123',
        ]);
        $resPhone->assertStatus(200)->assertJsonPath('data.role', 'student');

        // Login using registration number (without leading zero)
        $resReg = $this->postJson('/api/auth/login', [
            'username' => '1712345678',
            'password' => 'password123',
        ]);
        $resReg->assertStatus(200)->assertJsonPath('data.role', 'student');
    }

    public function test_deactivated_user_cannot_login(): void
    {
        $user = User::where('username', 'teacher1')->first();
        $user->update(['is_active' => false]);

        $response = $this->postJson('/api/auth/login', [
            'username' => 'teacher1',
            'password' => 'password123',
        ]);

        $response->assertStatus(403);
    }

    public function test_teacher_cannot_access_admin_endpoints(): void
    {
        $teacher = User::where('role', 'teacher')->first();
        $token = $teacher->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/dashboard-stats');

        $response->assertStatus(403);
    }

    public function test_student_cannot_access_teacher_endpoints(): void
    {
        $student = User::where('role', 'student')->first();
        $token = $student->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/teacher/assigned-exams');

        $response->assertStatus(403);
    }
}
