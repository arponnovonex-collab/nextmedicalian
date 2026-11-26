<?php

namespace App\Console\Commands;

use App\Models\Center;
use App\Models\Exam;
use App\Models\ExamCenter;
use App\Models\ExamRegistration;
use App\Models\StudentProfile;
use App\Models\User;
use App\Models\Zone;
use App\Services\PhoneNormalizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportMedicalianStudentsCommand extends Command
{
    protected $signature = 'students:import-medicalian 
                            {--csv= : Path to CSV file} 
                            {--exam=NME-26 : Exam Code}';

    protected $description = 'Import or synchronize students from CSV for NEXT MEDICALIAN EXAM without modifying existing students';

    public function handle(PhoneNormalizationService $phoneService): int
    {
        $csvPath = $this->option('csv') ?: database_path('data/next_medicalian_exam_2026.csv');

        if (! file_exists($csvPath)) {
            $this->error("CSV file not found: {$csvPath}");
            return 1;
        }

        $examCode = $this->option('exam') ?: 'NME-26';
        $exam = Exam::where('code', $examCode)->first();
        if (! $exam) {
            $exam = Exam::create([
                'title' => 'NEXT MEDICALIAN EXAM 2026',
                'code' => $examCode,
                'exam_date' => '2026-11-26',
                'start_time' => '11:00:00',
                'end_time' => '12:00:00',
                'duration_minutes' => 60,
                'total_marks' => 100,
                'total_questions' => 100,
                'marks_per_correct' => 1.0,
                'negative_marks_per_incorrect' => 0.25,
                'pass_marks' => 40,
                'instructions' => 'Use Black Ballpoint Pen on OMR sheet. No digital watches or mobile phones.',
                'status' => 'registration_open',
            ]);
        }

        $centerIdMap = $this->ensureVenuesAndCenters($exam);

        $this->info("Exam: [{$exam->code}] {$exam->title} (ID: {$exam->id})");
        $this->info("Reading dataset: {$csvPath}");

        $handle = fopen($csvPath, 'r');
        if (! $handle) {
            $this->error("Failed to open CSV file.");
            return 1;
        }

        // Skip header
        fgetcsv($handle);

        $uniqueMap = [];
        $totalRows = 0;
        $invalidPhones = 0;
        $duplicatesInCsv = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $totalRows++;
            if (empty(array_filter($row))) {
                continue;
            }

            $name = trim($row[1] ?? '');
            $rawPhone = trim($row[2] ?? '');
            $college = trim($row[3] ?? '');
            $batch = trim($row[4] ?? '');
            $venue = trim($row[5] ?? '');
            $email = trim($row[7] ?? '');

            $parsed = $phoneService->parse($rawPhone);
            if (! $parsed) {
                $invalidPhones++;
                continue;
            }

            $phone = $parsed['canonical_phone'];
            $regNo = $parsed['registration_number'];

            if (isset($uniqueMap[$phone])) {
                $duplicatesInCsv++;
                continue;
            }

            $vKey = $this->resolveVenueKey($venue);
            $centerId = $centerIdMap[$vKey] ?? reset($centerIdMap);

            $uniqueMap[$phone] = [
                'name' => $name,
                'phone' => $phone,
                'registration_number' => $regNo,
                'institution' => $college,
                'hsc_batch' => $batch,
                'venue' => $venue,
                'center_id' => $centerId,
                'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? strtolower($email) : null,
            ];
        }
        fclose($handle);

        $this->info("Total rows in CSV: {$totalRows}");
        $this->info("Invalid phone rows: {$invalidPhones}");
        $this->info("In-CSV duplicates: {$duplicatesInCsv}");
        $this->info("Unique valid candidates in CSV: " . count($uniqueMap));

        // Find existing profiles & registrations
        $allPhones = array_keys($uniqueMap);
        $existingProfiles = StudentProfile::whereIn('canonical_phone', $allPhones)->get()->keyBy('canonical_phone');

        $existingRegStudentIds = [];
        if ($existingProfiles->isNotEmpty()) {
            $existingRegStudentIds = ExamRegistration::where('exam_id', $exam->id)
                ->whereIn('student_id', $existingProfiles->pluck('id'))
                ->pluck('student_id')
                ->flip()
                ->all();
        }

        $newToEnroll = [];
        $alreadyEnrolledCount = 0;

        foreach ($uniqueMap as $phone => $data) {
            $prof = $existingProfiles[$phone] ?? null;
            if ($prof && isset($existingRegStudentIds[$prof->id])) {
                $alreadyEnrolledCount++;
            } else {
                $newToEnroll[$phone] = $data;
            }
        }

        $this->info("Already enrolled (strictly untouched): {$alreadyEnrolledCount}");
        $this->info("New students to enroll: " . count($newToEnroll));

        if (empty($newToEnroll)) {
            $this->info("All students are already enrolled! Nothing to add.");
            return 0;
        }

        $defaultPasswordHash = Hash::make('NextMed2026!');
        $now = now();
        $addedCount = 0;

        DB::beginTransaction();
        try {
            foreach ($newToEnroll as $phone => $item) {
                $regNo = $item['registration_number'];
                $name = $item['name'];
                $institution = $item['institution'];
                $batch = $item['hsc_batch'];
                $centerId = $item['center_id'];
                $email = $item['email'];

                // 4-digit PIN for SMS and login
                $pin = (string) mt_rand(1000, 9999);

                $user = User::where('username', $regNo)->orWhere('phone', $phone)->first();
                if (! $user) {
                    $userEmail = null;
                    if ($email && ! User::where('email', $email)->exists()) {
                        $userEmail = $email;
                    }

                    $user = User::create([
                        'name' => $name,
                        'username' => $regNo,
                        'phone' => $phone,
                        'email' => $userEmail,
                        'password' => $defaultPasswordHash,
                        'role' => 'student',
                        'is_active' => true,
                        'must_change_password' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $profile = StudentProfile::where('canonical_phone', $phone)
                    ->orWhere('registration_number', $regNo)
                    ->orWhere('user_id', $user->id)
                    ->first();

                if (! $profile) {
                    $profile = StudentProfile::create([
                        'user_id' => $user->id,
                        'registration_number' => $regNo,
                        'canonical_phone' => $phone,
                        'temp_password' => $pin,
                        'institution' => $institution,
                        'hsc_batch' => $batch,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    if (empty($profile->temp_password)) {
                        $profile->update(['temp_password' => $pin]);
                    }
                }

                ExamRegistration::create([
                    'exam_id' => $exam->id,
                    'student_id' => $profile->id,
                    'center_id' => $centerId,
                    'admit_card_token' => Str::random(40) . bin2hex(random_bytes(12)),
                    'status' => 'registered',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $addedCount++;
            }

            DB::commit();
            $this->info("SUCCESS: Enrolled {$addedCount} new students!");
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Error enrolling students: " . $e->getMessage());
            return 1;
        }

        $this->info("Total students now registered for Exam: " . ExamRegistration::where('exam_id', $exam->id)->count());

        return 0;
    }

    protected function ensureVenuesAndCenters(Exam $exam): array
    {
        $venues = [
            'TEJGAON' => [
                'zone_name' => 'Dhaka',
                'zone_code' => 'DHK',
                'center_name' => 'Tejgaon College, Farmgate',
                'center_code' => 'DHK-FMG',
                'address' => '16 Indira Road, Farmgate, Dhaka 1215',
                'capacity' => 5000,
            ],
            'MOTIJHEEL' => [
                'zone_name' => 'Dhaka',
                'zone_code' => 'DHK',
                'center_name' => 'Motijheel Colony High School',
                'center_code' => 'DHK-MTJ',
                'address' => 'Motijheel Govt Boys School Road, Dhaka',
                'capacity' => 3000,
            ],
            'CHATTOGRAM' => [
                'zone_name' => 'Chattogram',
                'zone_code' => 'CTG',
                'center_name' => 'Kazem Ali School and College',
                'center_code' => 'CTG-01',
                'address' => 'Chawkbazar, Chattogram',
                'capacity' => 3000,
            ],
            'RAJSHAHI' => [
                'zone_name' => 'Rajshahi',
                'zone_code' => 'RAJ',
                'center_name' => 'Rajshahi Loknath High School',
                'center_code' => 'RAJ-01',
                'address' => 'Saheb Bazar, Rajshahi',
                'capacity' => 2000,
            ],
            'MYMENSINGH' => [
                'zone_name' => 'Mymensingh',
                'zone_code' => 'MYM',
                'center_name' => 'Mymensingh Mohila Degree College',
                'center_code' => 'MYM-01',
                'address' => 'College Road, Mymensingh',
                'capacity' => 2000,
            ],
            'RANGPUR' => [
                'zone_name' => 'Rangpur',
                'zone_code' => 'RNG',
                'center_name' => 'Samaj Kalyan Bidyabithi Girls High School & College',
                'center_code' => 'RNG-01',
                'address' => 'Dhap, Rangpur',
                'capacity' => 2000,
            ],
            'KHULNA' => [
                'zone_name' => 'Khulna',
                'zone_code' => 'KHL',
                'center_name' => 'BK Union Institute',
                'center_code' => 'KHL-01',
                'address' => 'South Central Road, Khulna',
                'capacity' => 2000,
            ],
        ];

        $centerIdMap = [];
        foreach ($venues as $key => $v) {
            $zone = Zone::firstOrCreate(
                ['code' => $v['zone_code']],
                ['name' => $v['zone_name']]
            );

            $center = Center::firstOrCreate(
                ['code' => $v['center_code']],
                [
                    'zone_id' => $zone->id,
                    'name' => $v['center_name'],
                    'address' => $v['address'],
                    'capacity' => $v['capacity'],
                    'google_maps_url' => 'https://maps.google.com/?q=' . urlencode($v['center_name'] . ', ' . $v['address']),
                ]
            );

            ExamCenter::firstOrCreate(
                ['exam_id' => $exam->id, 'center_id' => $center->id],
                ['allocated_capacity' => $v['capacity']]
            );

            $centerIdMap[$key] = $center->id;
        }

        return $centerIdMap;
    }

    protected function resolveVenueKey(string $venueString): string
    {
        if (str_contains($venueString, 'ফার্মগেট')) {
            return 'TEJGAON';
        }
        if (str_contains($venueString, 'মতিঝিল')) {
            return 'MOTIJHEEL';
        }
        if (str_contains($venueString, 'চট্টগ্রাম') || str_contains($venueString, 'চট্রগ্রাম')) {
            return 'CHATTOGRAM';
        }
        if (str_contains($venueString, 'রাজশাহী')) {
            return 'RAJSHAHI';
        }
        if (str_contains($venueString, 'ময়মনসিংহ')) {
            return 'MYMENSINGH';
        }
        if (str_contains($venueString, 'রংপুর')) {
            return 'RANGPUR';
        }
        if (str_contains($venueString, 'খুলনা')) {
            return 'KHULNA';
        }

        return 'TEJGAON';
    }
}
