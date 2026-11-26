<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\Exam;
use App\Models\ExamCenter;
use App\Models\ExamRegistration;
use App\Models\StudentProfile;
use App\Models\User;
use App\Models\Zone;
use App\Services\PhoneNormalizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BulkStudentImportController extends Controller
{
    public function import(Request $request, PhoneNormalizationService $phoneService): JsonResponse
    {
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $candidates = [
            database_path('data/next_medicalian_exam_2026.csv'),
            storage_path('app/imports/next_medicalian_exam_2026.csv'),
            base_path('storage/app/imports/next_medicalian_exam_2026.csv'),
        ];

        $filePath = null;
        foreach ($candidates as $cand) {
            if (file_exists($cand)) {
                $filePath = $cand;
                break;
            }
        }

        if (! $filePath) {
            return response()->json([
                'success' => false,
                'message' => 'Dataset CSV not found on server.',
            ], 404);
        }

        $examCode = $request->input('exam_code', 'NME-26');
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

        // Ensure venues & centers
        $centersMap = $this->ensureVenuesAndCenters($exam);

        $defaultPassword = 'NextMed2026!';
        $defaultPasswordHash = Hash::make($defaultPassword);

        $handle = fopen($filePath, 'r');
        if (! $handle) {
            return response()->json(['success' => false, 'message' => 'Failed to open CSV file.'], 500);
        }

        // Skip header
        fgetcsv($handle);

        $totalRows = 0;
        $invalidRows = [];
        $duplicateRowsCount = 0;
        $uniquePhoneMap = [];
        $venueDistribution = [];

        while (($row = fgetcsv($handle)) !== false) {
            $totalRows++;
            if (empty(array_filter($row))) {
                continue;
            }

            $name = trim($row[1] ?? '');
            $rawPhone = trim($row[2] ?? '');
            $college = trim($row[3] ?? '');
            $batch = trim($row[4] ?? '');
            $venueString = trim($row[5] ?? '');
            $email = trim($row[7] ?? '');

            $parsed = $phoneService->parse($rawPhone);
            if (! $parsed) {
                $invalidRows[] = [
                    'row' => $totalRows + 1,
                    'name' => $name,
                    'phone' => $rawPhone,
                ];
                continue;
            }

            $canonicalPhone = $parsed['canonical_phone'];
            $regNo = $parsed['registration_number'];

            if (isset($uniquePhoneMap[$canonicalPhone])) {
                $duplicateRowsCount++;
                continue;
            }

            $centerId = $this->resolveCenterId($venueString, $centersMap);
            $venueKey = $this->resolveVenueKey($venueString);
            $venueDistribution[$venueKey] = ($venueDistribution[$venueKey] ?? 0) + 1;

            $uniquePhoneMap[$canonicalPhone] = [
                'name' => $name,
                'phone' => $canonicalPhone,
                'registration_number' => $regNo,
                'institution' => $college,
                'hsc_batch' => $batch,
                'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? strtolower($email) : null,
                'center_id' => $centerId,
            ];
        }
        fclose($handle);

        $uniqueCandidatesCount = count($uniquePhoneMap);
        $chunks = array_chunk($uniquePhoneMap, 500);
        $newUsersCount = 0;
        $reusedUsersCount = 0;
        $newRegistrationsCount = 0;
        $alreadyRegisteredCount = 0;

        foreach ($chunks as $chunk) {
            DB::transaction(function () use (
                $chunk,
                $exam,
                $defaultPasswordHash,
                &$newUsersCount,
                &$reusedUsersCount,
                &$newRegistrationsCount,
                &$alreadyRegisteredCount
            ) {
                $chunkPhones = array_column($chunk, 'phone');
                $chunkRegNos = array_column($chunk, 'registration_number');

                $existingProfiles = StudentProfile::whereIn('canonical_phone', $chunkPhones)
                    ->orWhereIn('registration_number', $chunkRegNos)
                    ->get()
                    ->keyBy('canonical_phone');

                $existingUsers = User::whereIn('username', $chunkRegNos)
                    ->orWhereIn('phone', $chunkPhones)
                    ->get()
                    ->keyBy('username');

                $existingProfileIds = $existingProfiles->pluck('id')->filter()->all();
                $existingExamRegs = [];
                if (! empty($existingProfileIds)) {
                    $existingExamRegs = ExamRegistration::where('exam_id', $exam->id)
                        ->whereIn('student_id', $existingProfileIds)
                        ->pluck('student_id')
                        ->flip()
                        ->all();
                }

                $now = now();

                foreach ($chunk as $item) {
                    $phone = $item['phone'];
                    $regNo = $item['registration_number'];
                    $name = $item['name'];
                    $institution = $item['institution'];
                    $batch = $item['hsc_batch'];
                    $centerId = $item['center_id'];
                    $email = $item['email'];

                    $profile = $existingProfiles[$phone] ?? null;

                    if (! $profile) {
                        $user = $existingUsers[$regNo] ?? null;
                        if (! $user) {
                            $userEmail = null;
                            if ($email && ! User::where('email', $email)->exists()) {
                                $userEmail = $email;
                            }

                            $pin = (string) mt_rand(1000, 9999);
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
                            $newUsersCount++;
                        } else {
                            $pin = (string) mt_rand(1000, 9999);
                            $reusedUsersCount++;
                        }

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
                            $profile->update(['temp_password' => (string) mt_rand(1000, 9999)]);
                        }
                        $reusedUsersCount++;
                    }

                    if (isset($existingExamRegs[$profile->id])) {
                        $alreadyRegisteredCount++;
                    } else {
                        ExamRegistration::create([
                            'exam_id' => $exam->id,
                            'student_id' => $profile->id,
                            'center_id' => $centerId,
                            'admit_card_token' => Str::random(40) . bin2hex(random_bytes(12)),
                            'status' => 'registered',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                        $newRegistrationsCount++;
                    }
                }
            });
        }

        return response()->json([
            'success' => true,
            'message' => 'NEXT MEDICALIAN EXAM 2026 students imported successfully.',
            'data' => [
                'total_rows_in_csv' => $totalRows,
                'invalid_phone_rows' => count($invalidRows),
                'duplicate_submissions_filtered' => $duplicateRowsCount,
                'unique_candidates' => $uniqueCandidatesCount,
                'new_student_accounts_created' => $newUsersCount,
                'existing_accounts_reused' => $reusedUsersCount,
                'new_exam_enrollments' => $newRegistrationsCount,
                'already_enrolled_skipped' => $alreadyRegisteredCount,
                'total_enrolled_in_exam' => $exam->registrations()->count(),
                'venue_distribution' => $venueDistribution,
                'default_temporary_password' => $defaultPassword,
            ]
        ]);
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

    protected function resolveCenterId(string $venueString, array $centersMap): int
    {
        $key = $this->resolveVenueKey($venueString);
        return $centersMap[$key] ?? reset($centersMap);
    }
}
