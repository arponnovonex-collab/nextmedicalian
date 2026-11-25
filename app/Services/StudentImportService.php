<?php

namespace App\Services;

use App\Models\Center;
use App\Models\Exam;
use App\Models\ExamRegistration;
use App\Models\ExamRoom;
use App\Models\StudentProfile;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class StudentImportService
{
    public function __construct(
        protected PhoneNormalizationService $phoneService,
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Parse spreadsheet file into rows and extract headers.
     */
    public function parseFile(UploadedFile|string $file): array
    {
        $filePath = is_string($file) ? $file : $file->getRealPath();

        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray(null, true, true, false);

        if (empty($rows)) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = array_shift($rows);
        $cleanHeaders = array_map(fn($h) => trim((string)$h), $headers);

        $dataRows = [];
        foreach ($rows as $rowIndex => $row) {
            // Check if row is completely empty
            $nonEmpty = array_filter($row, fn($cell) => $cell !== null && trim((string)$cell) !== '');
            if (empty($nonEmpty)) {
                continue;
            }

            $mappedRow = [];
            foreach ($cleanHeaders as $colIndex => $header) {
                if ($header !== '') {
                    $mappedRow[$header] = isset($row[$colIndex]) ? trim((string)$row[$colIndex]) : '';
                }
            }
            $mappedRow['_row_index'] = $rowIndex + 2; // spreadsheet 1-based + 1 header
            $dataRows[] = $mappedRow;
        }

        return [
            'headers' => array_values(array_filter($cleanHeaders, fn($h) => $h !== '')),
            'rows' => $dataRows,
        ];
    }

    /**
     * Preview and validate uploaded student import file for an exam.
     */
    public function preview(UploadedFile $file, int $examId, array $columnMapping = []): array
    {
        $exam = Exam::findOrFail($examId);
        $parsed = $this->parseFile($file);
        $headers = $parsed['headers'];
        $rows = $parsed['rows'];

        // Store file temporarily for confirmation step
        $cacheToken = 'import_' . Str::random(32);
        $storedPath = $file->storeAs('imports', $cacheToken . '.' . $file->getClientOriginalExtension(), 'local');

        $validatedRows = [];
        $errors = [];
        $existingStudentsCount = 0;
        $duplicateInExamCount = 0;
        $validNewCount = 0;

        // Preload centers for this exam
        $examCenters = Center::whereHas('examCenters', fn($q) => $q->where('exam_id', $exam->id))
            ->orWhereIn('id', $exam->registrations()->pluck('center_id'))
            ->get()
            ->keyBy(fn($c) => strtoupper($c->code));

        $allCenters = Center::all()->keyBy(fn($c) => strtoupper($c->code));
        $allCentersByName = Center::all()->keyBy(fn($c) => strtoupper(trim($c->name)));

        foreach ($rows as $row) {
            $rowErrors = [];
            $mapped = $this->applyMapping($row, $columnMapping);

            $name = $mapped['name'] ?? '';
            $rawPhone = $mapped['phone'] ?? '';

            if (empty($name)) {
                $rowErrors[] = 'Student Name is required.';
            }

            if (empty($rawPhone)) {
                $rowErrors[] = 'Phone Number is required.';
            }

            $parsedPhone = $this->phoneService->parse($rawPhone);
            if (! $parsedPhone) {
                $rowErrors[] = "Invalid Bangladeshi phone number: '{$rawPhone}'. Must be 11 digits (e.g., 01712345678).";
            }

            $isExistingStudent = false;
            $isDuplicateInExam = false;

            if ($parsedPhone) {
                $existingProfile = StudentProfile::where('canonical_phone', $parsedPhone['canonical_phone'])
                    ->orWhere('registration_number', $parsedPhone['registration_number'])
                    ->first();

                if ($existingProfile) {
                    $isExistingStudent = true;
                    // Check if already enrolled in this examination
                    $alreadyRegistered = ExamRegistration::where('exam_id', $exam->id)
                        ->where('student_id', $existingProfile->id)
                        ->exists();

                    if ($alreadyRegistered) {
                        $isDuplicateInExam = true;
                        $rowErrors[] = "Student is already registered for this examination ({$parsedPhone['registration_number']}).";
                    }
                }
            }

            // Center matching (optional)
            $centerInput = strtoupper(trim($mapped['center'] ?? $mapped['center_code'] ?? ''));
            $matchedCenter = null;
            if ($centerInput !== '') {
                $matchedCenter = $examCenters[$centerInput] ?? $allCenters[$centerInput] ?? $allCentersByName[$centerInput] ?? null;
                if (! $matchedCenter) {
                    $rowErrors[] = "Unknown examination center code/name: '{$centerInput}'.";
                }
            }

            $rowSummary = [
                'row_number' => $row['_row_index'],
                'name' => $name,
                'phone' => $rawPhone,
                'canonical_phone' => $parsedPhone['canonical_phone'] ?? null,
                'registration_number' => $parsedPhone['registration_number'] ?? null,
                'institution' => $mapped['institution'] ?? '',
                'hsc_batch' => $mapped['hsc_batch'] ?? '',
                'center' => $matchedCenter?->name ?? $centerInput,
                'center_id' => $matchedCenter?->id ?? null,
                'room_number' => $mapped['room_number'] ?? '',
                'seat_number' => $mapped['seat_number'] ?? '',
                'is_existing_student' => $isExistingStudent,
                'is_duplicate_in_exam' => $isDuplicateInExam,
                'is_valid' => empty($rowErrors),
                'errors' => $rowErrors,
            ];

            if ($isDuplicateInExam) {
                $duplicateInExamCount++;
            } elseif ($isExistingStudent) {
                $existingStudentsCount++;
            } elseif (empty($rowErrors)) {
                $validNewCount++;
            }

            if (! empty($rowErrors)) {
                $errors[] = [
                    'row' => $row['_row_index'],
                    'name' => $name,
                    'phone' => $rawPhone,
                    'messages' => $rowErrors,
                ];
            }

            $validatedRows[] = $rowSummary;
        }

        return [
            'cache_token' => $cacheToken,
            'headers' => $headers,
            'total_rows' => count($rows),
            'valid_rows' => count($rows) - count($errors),
            'error_rows_count' => count($errors),
            'valid_new_count' => $validNewCount,
            'existing_students_count' => $existingStudentsCount,
            'duplicate_in_exam_count' => $duplicateInExamCount,
            'errors' => $errors,
            'preview' => array_slice($validatedRows, 0, 25),
        ];
    }

    /**
     * Confirm and execute the student import batch.
     */
    public function confirm(string $cacheToken, int $examId, array $columnMapping, User $adminUser): array
    {
        $exam = Exam::findOrFail($examId);

        // Find cached file
        $files = Storage::disk('local')->files('imports');
        $matching = array_filter($files, fn($f) => str_contains($f, $cacheToken));

        if (empty($matching)) {
            throw new \Exception('Import cache file expired or not found. Please upload again.');
        }

        $filePath = Storage::disk('local')->path(reset($matching));
        $parsed = $this->parseFile($filePath);
        $rows = $parsed['rows'];

        $createdCredentials = [];
        $importedCount = 0;
        $reusedCount = 0;
        $skippedCount = 0;

        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                $mapped = $this->applyMapping($row, $columnMapping);
                $name = trim($mapped['name'] ?? '');
                $rawPhone = trim($mapped['phone'] ?? '');

                if (empty($name) || empty($rawPhone)) {
                    $skippedCount++;
                    continue;
                }

                $parsedPhone = $this->phoneService->parse($rawPhone);
                if (! $parsedPhone) {
                    $skippedCount++;
                    continue;
                }

                $canonicalPhone = $parsedPhone['canonical_phone'];
                $regNo = $parsedPhone['registration_number'];

                // Check if student profile already exists
                $studentProfile = StudentProfile::where('canonical_phone', $canonicalPhone)
                    ->orWhere('registration_number', $regNo)
                    ->first();

                $isNewStudent = false;
                $tempPassword = null;

                if (! $studentProfile) {
                    // Create User Account with secure temporary password
                    $tempPassword = 'Med' . random_int(10000, 99999);
                    $user = User::create([
                        'name' => $name,
                        'username' => $regNo,
                        'phone' => $canonicalPhone,
                        'email' => !empty($mapped['email']) ? $mapped['email'] : null,
                        'password' => Hash::make($tempPassword),
                        'role' => 'student',
                        'is_active' => true,
                        'must_change_password' => true,
                    ]);

                    // Create Student Profile
                    $studentProfile = StudentProfile::create([
                        'user_id' => $user->id,
                        'registration_number' => $regNo,
                        'canonical_phone' => $canonicalPhone,
                        'institution' => $mapped['institution'] ?? null,
                        'hsc_batch' => $mapped['hsc_batch'] ?? null,
                        'gender' => in_array(strtolower($mapped['gender'] ?? ''), ['male', 'female', 'other']) ? strtolower($mapped['gender']) : null,
                        'address' => $mapped['address'] ?? null,
                        'guardian_phone' => $mapped['guardian_phone'] ?? null,
                    ]);

                    $isNewStudent = true;
                    $createdCredentials[] = [
                        'name' => $name,
                        'registration_number' => $regNo,
                        'phone' => $canonicalPhone,
                        'temporary_password' => $tempPassword,
                        'institution' => $mapped['institution'] ?? '',
                        'hsc_batch' => $mapped['hsc_batch'] ?? '',
                    ];
                } else {
                    $reusedCount++;
                }

                // Check if already registered for this exam
                $existingReg = ExamRegistration::where('exam_id', $exam->id)
                    ->where('student_id', $studentProfile->id)
                    ->first();

                if ($existingReg) {
                    $skippedCount++;
                    continue; // Do not create duplicate registration
                }

                // Resolve Center & Room
                $centerId = null;
                $roomId = null;
                $centerInput = strtoupper(trim($mapped['center'] ?? $mapped['center_code'] ?? ''));
                if ($centerInput !== '') {
                    $center = Center::where('code', $centerInput)->orWhere('name', $centerInput)->first();
                    if ($center) {
                        $centerId = $center->id;
                        $roomInput = trim($mapped['room_number'] ?? '');
                        if ($roomInput !== '') {
                            $room = ExamRoom::where('center_id', $center->id)
                                ->where('room_number', $roomInput)
                                ->first();
                            $roomId = $room?->id;
                        }
                    }
                }

                // Create Exam Registration
                ExamRegistration::create([
                    'exam_id' => $exam->id,
                    'student_id' => $studentProfile->id,
                    'center_id' => $centerId,
                    'room_id' => $roomId,
                    'seat_number' => $mapped['seat_number'] ?? null,
                    'admit_card_token' => Str::random(40),
                    'status' => 'registered',
                ]);

                $importedCount++;
            }

            // Save credentials export file
            $exportToken = 'creds_' . Str::random(32);
            Storage::disk('local')->put(
                'exports/' . $exportToken . '.json',
                json_encode($createdCredentials, JSON_PRETTY_PRINT)
            );

            $this->auditLogService->log(
                user: $adminUser,
                action: 'student_import_executed',
                auditableType: Exam::class,
                auditableId: $exam->id,
                examId: $exam->id,
                newValues: [
                    'imported_count' => $importedCount,
                    'reused_count' => $reusedCount,
                    'skipped_count' => $skippedCount,
                    'new_accounts_created' => count($createdCredentials),
                ]
            );

            DB::commit();

            // Clean up temporary upload
            Storage::disk('local')->delete(reset($matching));

            return [
                'success' => true,
                'imported_count' => $importedCount,
                'reused_students_count' => $reusedCount,
                'skipped_count' => $skippedCount,
                'new_accounts_count' => count($createdCredentials),
                'credential_export_token' => count($createdCredentials) > 0 ? $exportToken : null,
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Map arbitrary spreadsheet headers to known system fields.
     */
    protected function applyMapping(array $row, array $customMapping = []): array
    {
        $defaultMap = [
            'name' => ['student name', 'name', 'full name', 'candidate name'],
            'phone' => ['phone', 'phone number', 'mobile', 'mobile number', 'contact'],
            'email' => ['email', 'email address'],
            'institution' => ['institution', 'college', 'college name', 'school'],
            'hsc_batch' => ['hsc batch', 'batch', 'session', 'hsc'],
            'gender' => ['gender', 'sex'],
            'address' => ['address', 'present address', 'permanent address'],
            'center' => ['center', 'exam center', 'center code', 'center name'],
            'center_code' => ['center code', 'center id'],
            'room_number' => ['room', 'room number', 'room no'],
            'seat_number' => ['seat', 'seat number', 'seat no'],
            'guardian_phone' => ['guardian phone', 'guardian contact', 'parent phone'],
        ];

        $result = [];

        // Apply custom mapping first
        foreach ($customMapping as $targetField => $sourceHeader) {
            if (isset($row[$sourceHeader])) {
                $result[$targetField] = $row[$sourceHeader];
            }
        }

        // Fallback to fuzzy header matching if not yet set
        foreach ($defaultMap as $field => $variants) {
            if (! isset($result[$field])) {
                foreach ($row as $header => $val) {
                    $headerLower = strtolower(trim((string)$header));
                    if (in_array($headerLower, $variants)) {
                        $result[$field] = $val;
                        break;
                    }
                }
            }
        }

        return $result;
    }
}
