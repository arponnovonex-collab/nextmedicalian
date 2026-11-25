<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamRegistration;
use App\Models\ResultSnapshot;
use Illuminate\Http\JsonResponse;

class PublicVerificationController extends Controller
{
    /**
     * Public verification endpoint for Admit Card QR codes.
     * Strictly masks personal details to protect student privacy.
     */
    public function verifyAdmitCard(string $token): JsonResponse
    {
        $registration = ExamRegistration::where('admit_card_token', $token)
            ->with(['student.user', 'exam', 'center.zone', 'room'])
            ->first();

        if (! $registration) {
            return response()->json([
                'success' => false,
                'is_valid' => false,
                'message' => 'Invalid or unrecognized Admit Card QR Code.',
            ], 404);
        }

        // Mask name: "Anika Tabassum" -> "A**** T*******"
        $fullName = $registration->student->user->name;
        $nameParts = explode(' ', trim($fullName));
        $maskedParts = array_map(function ($part) {
            if (strlen($part) <= 1) {
                return $part;
            }
            return substr($part, 0, 1) . str_repeat('*', strlen($part) - 1);
        }, $nameParts);
        $maskedName = implode(' ', $maskedParts);

        return response()->json([
            'success' => true,
            'is_valid' => true,
            'data' => [
                'document_type' => 'Official Admit Card',
                'status' => 'Verified Authentic',
                'candidate_masked_name' => $maskedName,
                'registration_number' => $registration->student->registration_number,
                'examination_title' => $registration->exam->title,
                'examination_code' => $registration->exam->code,
                'examination_date' => $registration->exam->exam_date->format('d F, Y'),
                'examination_center' => $registration->center?->name ?? 'Assigned Center',
                'assigned_zone' => $registration->center?->zone?->name ?? 'N/A',
                'room_number' => $registration->room?->room_number ?? 'Assigned',
                'seat_number' => $registration->seat_number ?? 'Assigned',
            ],
        ]);
    }

    /**
     * Public verification endpoint for Result Sheet QR codes.
     */
    public function verifyResult(string $token): JsonResponse
    {
        $registration = ExamRegistration::where('admit_card_token', $token)
            ->with(['student.user', 'exam'])
            ->first();

        if (! $registration) {
            return response()->json([
                'success' => false,
                'is_valid' => false,
                'message' => 'Invalid or unrecognized Result Sheet QR Code.',
            ], 404);
        }

        $latestVersion = ResultSnapshot::where('exam_id', $registration->exam_id)->max('version');
        $result = ResultSnapshot::where('exam_registration_id', $registration->id)
            ->where('version', $latestVersion)
            ->first();

        if (! $result || ! $registration->exam->isResultPublished()) {
            return response()->json([
                'success' => false,
                'is_valid' => false,
                'message' => 'Result is not currently published for this candidate.',
            ], 404);
        }

        $fullName = $registration->student->user->name;
        $nameParts = explode(' ', trim($fullName));
        $maskedParts = array_map(function ($part) {
            if (strlen($part) <= 1) return $part;
            return substr($part, 0, 1) . str_repeat('*', strlen($part) - 1);
        }, $nameParts);
        $maskedName = implode(' ', $maskedParts);

        return response()->json([
            'success' => true,
            'is_valid' => true,
            'data' => [
                'document_type' => 'Official Result Sheet',
                'status' => 'Verified Authentic',
                'candidate_masked_name' => $maskedName,
                'registration_number' => $registration->student->registration_number,
                'examination_title' => $registration->exam->title,
                'obtained_marks' => $result->obtained_marks,
                'total_marks' => $registration->exam->total_marks,
                'percentage' => $result->percentage,
                'overall_merit' => $result->overall_merit,
                'is_passed' => $result->is_passed,
                'published_at' => $result->published_at->format('d F, Y'),
            ],
        ]);
    }
}
