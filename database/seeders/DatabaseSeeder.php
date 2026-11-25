<?php

namespace Database\Seeders;

use App\Models\Center;
use App\Models\Exam;
use App\Models\ExamCenter;
use App\Models\ExamRoom;
use App\Models\SystemSetting;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Default Admin
        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'System Administrator',
                'phone' => '01700000000',
                'email' => 'admin@edgenie.org',
                'password' => Hash::make('admin123456'),
                'role' => 'admin',
                'is_active' => true,
                'must_change_password' => false,
            ]
        );

        // 2. Create System Settings
        $settings = [
            ['key' => 'app_name', 'value' => 'NEXT MEDICALIAN', 'group' => 'branding'],
            ['key' => 'organization_name', 'value' => 'EdGenie Medical Academy', 'group' => 'branding'],
            ['key' => 'support_phone', 'value' => '+8801700000000', 'group' => 'contact'],
            ['key' => 'support_email', 'value' => 'support@edgenie.org', 'group' => 'contact'],
            ['key' => 'primary_color', 'value' => '#0F172A', 'group' => 'theme'],
            ['key' => 'admit_card_instructions', 'value' => "1. Examinees must bring their original physical Admit Card and HSC Registration Card.\n2. No digital devices, smartwatches, or programmable calculators are allowed in the examination hall.\n3. Examinees must occupy their assigned seats at least 20 minutes before the commencement of the examination.\n4. Use Black Ballpoint Pen only for bubbling physical OMR answer scripts.", 'group' => 'admit_card'],
            ['key' => 'authorized_signature_title', 'value' => 'Controller of Examinations, EdGenie', 'group' => 'admit_card'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::firstOrCreate(['key' => $setting['key']], $setting);
        }

        // 3. Seed Bangladesh Examination Zones
        $zonesData = [
            ['name' => 'Dhaka', 'code' => 'DHK'],
            ['name' => 'Chattogram', 'code' => 'CTG'],
            ['name' => 'Rajshahi', 'code' => 'RAJ'],
            ['name' => 'Khulna', 'code' => 'KHL'],
            ['name' => 'Sylhet', 'code' => 'SYL'],
            ['name' => 'Barishal', 'code' => 'BAR'],
            ['name' => 'Rangpur', 'code' => 'RNG'],
            ['name' => 'Mymensingh', 'code' => 'MYM'],
            ['name' => 'Cumilla', 'code' => 'COM'],
        ];

        $zones = [];
        foreach ($zonesData as $zd) {
            $zones[$zd['code']] = Zone::firstOrCreate(['code' => $zd['code']], $zd);
        }

        // 4. Seed Examination Centers
        $centersData = [
            [
                'zone_id' => $zones['DHK']->id,
                'name' => 'Dhaka Central Model College',
                'code' => 'DHK-01',
                'address' => 'Farmgate, Tejgaon, Dhaka-1215',
                'google_maps_url' => 'https://maps.google.com/?q=Farmgate,Dhaka',
                'google_maps_embed_url' => 'https://maps.google.com/maps?q=Farmgate,Dhaka&t=&z=15&ie=UTF8&iwloc=&output=embed',
                'capacity' => 500,
                'contact_person' => 'Prof. Dr. Rafiqul Islam',
                'contact_phone' => '01711223344',
                'special_instructions' => 'Main gate entry from Green Road.',
                'rooms' => [
                    ['room_number' => 'Room 101', 'floor' => '1st Floor', 'capacity' => 60],
                    ['room_number' => 'Room 102', 'floor' => '1st Floor', 'capacity' => 60],
                    ['room_number' => 'Room 201', 'floor' => '2nd Floor', 'capacity' => 80],
                ]
            ],
            [
                'zone_id' => $zones['DHK']->id,
                'name' => 'Dhanmondi Ideal High School & College',
                'code' => 'DHK-02',
                'address' => 'Road 9/A, Dhanmondi, Dhaka-1209',
                'google_maps_url' => 'https://maps.google.com/?q=Dhanmondi,Dhaka',
                'google_maps_embed_url' => 'https://maps.google.com/maps?q=Dhanmondi,Dhaka&t=&z=15&ie=UTF8&iwloc=&output=embed',
                'capacity' => 400,
                'contact_person' => 'Nazmul Huda',
                'contact_phone' => '01819283746',
                'rooms' => [
                    ['room_number' => 'Auditorium A', 'floor' => 'Ground Floor', 'capacity' => 120],
                    ['room_number' => 'Room 304', 'floor' => '3rd Floor', 'capacity' => 50],
                ]
            ],
            [
                'zone_id' => $zones['CTG']->id,
                'name' => 'Chattogram City College Center',
                'code' => 'CTG-01',
                'address' => 'Ice Factory Road, Chattogram',
                'google_maps_url' => 'https://maps.google.com/?q=Chattogram+City+College',
                'google_maps_embed_url' => 'https://maps.google.com/maps?q=Chattogram+City+College&t=&z=15&ie=UTF8&iwloc=&output=embed',
                'capacity' => 350,
                'contact_person' => 'Tareq Mahmud',
                'contact_phone' => '01611223344',
                'rooms' => [
                    ['room_number' => 'Room 101', 'floor' => '1st Floor', 'capacity' => 50],
                    ['room_number' => 'Room 102', 'floor' => '1st Floor', 'capacity' => 50],
                ]
            ],
        ];

        $createdCenters = [];
        foreach ($centersData as $cd) {
            $rooms = $cd['rooms'];
            unset($cd['rooms']);

            $center = Center::firstOrCreate(['code' => $cd['code']], $cd);
            $createdCenters[] = $center;

            foreach ($rooms as $r) {
                ExamRoom::firstOrCreate(
                    ['center_id' => $center->id, 'room_number' => $r['room_number']],
                    ['floor' => $r['floor'], 'capacity' => $r['capacity']]
                );
            }
        }

        // 5. Seed Teachers
        $teachersData = [
            [
                'name' => 'Dr. Mahbubur Rahman',
                'username' => 'teacher1',
                'phone' => '01711002233',
                'email' => 'mahbub@edgenie.org',
                'designation' => 'Senior Faculty, Biology',
                'institution' => 'Dhaka Medical College',
            ],
            [
                'name' => 'Dr. Nusrat Jahan',
                'username' => 'teacher2',
                'phone' => '01811002233',
                'email' => 'nusrat@edgenie.org',
                'designation' => 'Lecturer, Chemistry',
                'institution' => 'Sir Salimullah Medical College',
            ],
        ];

        $teachers = [];
        foreach ($teachersData as $td) {
            $tUser = User::firstOrCreate(
                ['username' => $td['username']],
                [
                    'name' => $td['name'],
                    'phone' => $td['phone'],
                    'email' => $td['email'],
                    'password' => Hash::make('teacher123456'),
                    'role' => 'teacher',
                    'is_active' => true,
                    'must_change_password' => false,
                ]
            );

            TeacherProfile::firstOrCreate(
                ['user_id' => $tUser->id],
                [
                    'designation' => $td['designation'],
                    'institution' => $td['institution'],
                ]
            );

            $teachers[] = $tUser;
        }

        // 6. Seed Sample Examination
        $exam = Exam::firstOrCreate(
            ['code' => 'MED-2026-01'],
            [
                'title' => 'NEXT MEDICALIAN Central Grand Mock Test 2026',
                'description' => 'Comprehensive offline medical admission mock examination covering Biology, Chemistry, Physics, and English.',
                'exam_date' => now()->addDays(5)->toDateString(),
                'start_time' => '10:00:00',
                'end_time' => '11:00:00',
                'duration_minutes' => 60,
                'total_marks' => 100.00,
                'total_questions' => 100,
                'marks_per_correct' => 1.00,
                'negative_marks_per_incorrect' => 0.25,
                'marks_per_unanswered' => 0.00,
                'pass_marks' => 40.00,
                'instructions' => 'Physical OMR answer sheet will be distributed at 9:45 AM. Read instructions carefully before filling up test paper code.',
                'registration_deadline' => now()->addDays(2),
                'admit_card_publish_at' => now()->subDay(), // Published for testing
                'evaluation_deadline' => now()->addDays(7),
                'result_publish_at' => now()->addDays(8),
                'status' => 'evaluation_in_progress',
                'created_by' => $admin->id,
            ]
        );

        // Assign centers to exam
        foreach ($createdCenters as $cc) {
            ExamCenter::firstOrCreate(
                ['exam_id' => $exam->id, 'center_id' => $cc->id],
                ['allocated_capacity' => $cc->capacity]
            );
        }

        // Assign teachers to exam
        foreach ($teachers as $t) {
            \App\Models\TeacherExamAssignment::firstOrCreate(
                ['teacher_id' => $t->id, 'exam_id' => $exam->id],
                ['assigned_by' => $admin->id]
            );
        }
    }
}
