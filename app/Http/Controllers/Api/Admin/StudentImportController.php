<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\StudentImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentImportController extends Controller
{
    public function __construct(
        protected StudentImportService $importService
    ) {}

    public function template(): \Illuminate\Http\Response
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="student_import_template.csv"',
        ];

        $columns = [
            'Student Name',
            'Phone Number',
            'Email',
            'Institution',
            'HSC Batch',
            'Gender',
            'Address',
            'Center Code',
            'Room Number',
            'Seat Number',
            'Guardian Phone',
        ];

        $sampleRows = [
            [
                'Anika Tabassum',
                '01712345678',
                'anika@example.com',
                'Viqarunnisa Noon College',
                'HSC-2026',
                'female',
                'Dhanmondi, Dhaka',
                'DHK-01',
                'Room 101',
                'A-12',
                '01700112233',
            ],
            [
                'Tanvir Hasan',
                '01812345678',
                'tanvir@example.com',
                'Notre Dame College',
                'HSC-2026',
                'male',
                'Mirpur, Dhaka',
                'DHK-02',
                'Auditorium A',
                'B-05',
                '01800112233',
            ],
        ];

        $output = fopen('php://temp', 'r+');
        fputcsv($output, $columns);
        foreach ($sampleRows as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return response($csv, 200, $headers);
    }

    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
            'exam_id' => 'required|exists:exams,id',
            'mapping' => 'nullable|array',
        ]);

        $file = $request->file('file');
        $examId = (int) $request->exam_id;
        $mapping = $request->input('mapping', []);

        $result = $this->importService->preview($file, $examId, $mapping);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'cache_token' => 'required|string',
            'exam_id' => 'required|exists:exams,id',
            'mapping' => 'nullable|array',
        ]);

        $cacheToken = $request->cache_token;
        $examId = (int) $request->exam_id;
        $mapping = $request->input('mapping', []);

        try {
            $result = $this->importService->confirm($cacheToken, $examId, $mapping, $request->user());
            return response()->json([
                'success' => true,
                'message' => "Import completed successfully. {$result['imported_count']} registrations created.",
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import execution failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function exportCredentials(string $token): StreamedResponse|JsonResponse
    {
        $filePath = 'exports/' . $token . '.json';

        if (! Storage::disk('local')->exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Credentials file expired or already accessed.',
            ], 404);
        }

        $content = Storage::disk('local')->get($filePath);
        $credentials = json_decode($content, true);

        // Delete immediately after one-time access for security
        Storage::disk('local')->delete($filePath);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="student_temporary_credentials.csv"',
        ];

        return response()->stream(function () use ($credentials) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Student Name', 'Registration Number (Username)', 'Phone Number', 'Temporary Password', 'Institution', 'HSC Batch']);
            foreach ($credentials as $c) {
                fputcsv($handle, [
                    $c['name'],
                    $c['registration_number'],
                    $c['phone'],
                    $c['temporary_password'],
                    $c['institution'],
                    $c['hsc_batch'],
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }
}
