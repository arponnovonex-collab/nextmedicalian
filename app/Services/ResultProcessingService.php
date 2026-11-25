<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Exam;
use App\Models\ExamRegistration;
use App\Models\Marks;
use App\Models\MarksCorrectionRequest;
use App\Models\ResultSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ResultProcessingService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Preview calculated merit list and detect publication warnings for an exam.
     */
    public function previewResults(int $examId): array
    {
        $exam = Exam::findOrFail($examId);

        $totalRegistrations = ExamRegistration::where('exam_id', $exam->id)->count();
        $evaluatedCount = ExamRegistration::where('exam_id', $exam->id)->whereHas('marks')->count();
        $unevaluatedCount = $totalRegistrations - $evaluatedCount;

        $pendingCorrectionsCount = MarksCorrectionRequest::whereHas('marks.registration', fn($q) => $q->where('exam_id', $exam->id))
            ->where('status', 'pending')
            ->count();

        $absentCount = AttendanceRecord::whereHas('registration', fn($q) => $q->where('exam_id', $exam->id))
            ->where('is_present', false)
            ->count();

        $unrecordedAttendanceCount = $totalRegistrations - AttendanceRecord::whereHas('registration', fn($q) => $q->where('exam_id', $exam->id))->count();

        $warnings = [];
        if ($unevaluatedCount > 0) {
            $warnings[] = "There are {$unevaluatedCount} students without evaluated marks.";
        }
        if ($pendingCorrectionsCount > 0) {
            $warnings[] = "There are {$pendingCorrectionsCount} pending marks correction requests.";
        }
        if ($unrecordedAttendanceCount > 0) {
            $warnings[] = "Attendance is unconfirmed for {$unrecordedAttendanceCount} registered candidates.";
        }

        // Calculate ranked list in-memory for preview
        $rankedItems = $this->calculateRanks($exam);

        $highestMarks = !empty($rankedItems) ? max(array_column($rankedItems, 'obtained_marks')) : 0;
        $lowestMarks = !empty($rankedItems) ? min(array_column($rankedItems, 'obtained_marks')) : 0;
        $avgMarks = !empty($rankedItems) ? round(array_sum(array_column($rankedItems, 'obtained_marks')) / count($rankedItems), 2) : 0;
        $passCount = count(array_filter($rankedItems, fn($i) => $i['is_passed']));
        $failCount = count($rankedItems) - $passCount;

        return [
            'exam' => $exam,
            'warnings' => $warnings,
            'summary' => [
                'total_registered' => $totalRegistrations,
                'total_evaluated' => $evaluatedCount,
                'unevaluated_count' => $unevaluatedCount,
                'pending_corrections' => $pendingCorrectionsCount,
                'highest_marks' => $highestMarks,
                'lowest_marks' => $lowestMarks,
                'average_marks' => $avgMarks,
                'pass_count' => $passCount,
                'fail_count' => $failCount,
                'pass_percentage' => count($rankedItems) > 0 ? round(($passCount / count($rankedItems)) * 100, 1) : 0,
            ],
            'merit_list_preview' => array_slice($rankedItems, 0, 50),
        ];
    }

    /**
     * Compute merit ranks using strict tie-breaking logic.
     * Tie-breakers:
     * 1. Obtained Final Marks (Descending)
     * 2. Total Correct Answers (Descending)
     * 3. Total Incorrect Answers (Ascending)
     * 4. Equal ranks assigned; next rank skips.
     */
    public function calculateRanks(Exam $exam): array
    {
        $registrations = ExamRegistration::where('exam_id', $exam->id)
            ->whereHas('marks')
            ->with(['student.user', 'center.zone', 'room', 'marks'])
            ->get();

        if ($registrations->isEmpty()) {
            return [];
        }

        // Map into sortable records
        $items = [];
        foreach ($registrations as $reg) {
            $marks = $reg->marks;
            $finalMarks = (float) $marks->final_marks;
            $pct = round(($finalMarks / (float) $exam->total_marks) * 100, 2);
            $isPassed = $finalMarks >= (float) $exam->pass_marks;

            $items[] = [
                'registration_id' => $reg->id,
                'student_id' => $reg->student_id,
                'student_name' => $reg->student->user->name,
                'registration_number' => $reg->student->registration_number,
                'institution' => $reg->student->institution,
                'zone_id' => $reg->center?->zone_id,
                'zone_name' => $reg->center?->zone?->name ?? 'N/A',
                'center_id' => $reg->center_id,
                'center_name' => $reg->center?->name ?? 'N/A',
                'obtained_marks' => $finalMarks,
                'percentage' => $pct,
                'is_passed' => $isPassed,
                'correct_count' => $marks->correct_answers,
                'incorrect_count' => $marks->incorrect_answers,
                'unanswered_count' => $marks->unanswered_questions,
                'evaluation_mode' => $marks->evaluation_mode,
            ];
        }

        // Sorter function
        $sorter = function ($a, $b) {
            // 1. Higher final marks
            if ($a['obtained_marks'] != $b['obtained_marks']) {
                return $a['obtained_marks'] > $b['obtained_marks'] ? -1 : 1;
            }

            // 2. Higher number of correct answers (if present)
            $aCorrect = $a['correct_count'] ?? -1;
            $bCorrect = $b['correct_count'] ?? -1;
            if ($aCorrect !== $bCorrect && $aCorrect !== -1 && $bCorrect !== -1) {
                return $aCorrect > $bCorrect ? -1 : 1;
            }

            // 3. Lower number of incorrect answers (if present)
            $aIncorrect = $a['incorrect_count'] ?? -1;
            $bIncorrect = $b['incorrect_count'] ?? -1;
            if ($aIncorrect !== $bIncorrect && $aIncorrect !== -1 && $bIncorrect !== -1) {
                return $aIncorrect < $bIncorrect ? -1 : 1;
            }

            // Shared rank - preserve consistent registration number sort
            return strcmp($a['registration_number'], $b['registration_number']);
        };

        // Sort overall
        usort($items, $sorter);

        // Assign Overall Merit Ranks (handling ties)
        $currentRank = 1;
        foreach ($items as $index => &$item) {
            if ($index > 0) {
                $prev = $items[$index - 1];
                $isTie = ($item['obtained_marks'] == $prev['obtained_marks'])
                    && (($item['correct_count'] ?? null) === ($prev['correct_count'] ?? null))
                    && (($item['incorrect_count'] ?? null) === ($prev['incorrect_count'] ?? null));

                if (! $isTie) {
                    $currentRank = $index + 1;
                }
            }
            $item['overall_merit'] = $currentRank;
        }
        unset($item);

        // Group by Zone and assign Zone Merit Ranks
        $zoneGroups = [];
        foreach ($items as $index => $item) {
            $zId = $item['zone_id'] ?? 0;
            $zoneGroups[$zId][] = $index;
        }

        foreach ($zoneGroups as $zId => $indices) {
            $zRank = 1;
            foreach ($indices as $pos => $idx) {
                if ($pos > 0) {
                    $prevIdx = $indices[$pos - 1];
                    $prev = $items[$prevIdx];
                    $curr = $items[$idx];

                    $isTie = ($curr['obtained_marks'] == $prev['obtained_marks'])
                        && (($curr['correct_count'] ?? null) === ($prev['correct_count'] ?? null))
                        && (($curr['incorrect_count'] ?? null) === ($prev['incorrect_count'] ?? null));

                    if (! $isTie) {
                        $zRank = $pos + 1;
                    }
                }
                $items[$idx]['zone_merit'] = $zRank;
            }
        }

        // Group by Center and assign Center Merit Ranks
        $centerGroups = [];
        foreach ($items as $index => $item) {
            $cId = $item['center_id'] ?? 0;
            $centerGroups[$cId][] = $index;
        }

        foreach ($centerGroups as $cId => $indices) {
            $cRank = 1;
            foreach ($indices as $pos => $idx) {
                if ($pos > 0) {
                    $prevIdx = $indices[$pos - 1];
                    $prev = $items[$prevIdx];
                    $curr = $items[$idx];

                    $isTie = ($curr['obtained_marks'] == $prev['obtained_marks'])
                        && (($curr['correct_count'] ?? null) === ($prev['correct_count'] ?? null))
                        && (($curr['incorrect_count'] ?? null) === ($prev['incorrect_count'] ?? null));

                    if (! $isTie) {
                        $cRank = $pos + 1;
                    }
                }
                $items[$idx]['center_merit'] = $cRank;
            }
        }

        return $items;
    }

    /**
     * Publish results: calculate final merit ranks and snapshot them into result_snapshots.
     */
    public function publishResults(int $examId, User $adminUser): array
    {
        $exam = Exam::findOrFail($examId);
        $rankedItems = $this->calculateRanks($exam);

        if (empty($rankedItems)) {
            throw new \Exception('Cannot publish results: No evaluated marks found for this examination.');
        }

        DB::beginTransaction();
        try {
            // Determine version number
            $currentVersion = ResultSnapshot::where('exam_id', $exam->id)->max('version') ?? 0;
            $newVersion = $currentVersion + 1;

            $now = now();
            foreach ($rankedItems as $item) {
                ResultSnapshot::create([
                    'exam_id' => $exam->id,
                    'exam_registration_id' => $item['registration_id'],
                    'student_id' => $item['student_id'],
                    'obtained_marks' => $item['obtained_marks'],
                    'percentage' => $item['percentage'],
                    'overall_merit' => $item['overall_merit'],
                    'zone_merit' => $item['zone_merit'],
                    'center_merit' => $item['center_merit'],
                    'is_passed' => $item['is_passed'],
                    'correct_count' => $item['correct_count'],
                    'incorrect_count' => $item['incorrect_count'],
                    'unanswered_count' => $item['unanswered_count'],
                    'version' => $newVersion,
                    'published_at' => $now,
                ]);
            }

            // Update exam status to result_published
            $exam->update([
                'status' => 'result_published',
                'result_publish_at' => $now,
            ]);

            $this->auditLogService->log(
                user: $adminUser,
                action: 'exam_results_published',
                auditableType: Exam::class,
                auditableId: $exam->id,
                examId: $exam->id,
                newValues: [
                    'version' => $newVersion,
                    'total_published' => count($rankedItems),
                    'published_at' => $now->toIso8601String(),
                ]
            );

            DB::commit();

            return [
                'success' => true,
                'version' => $newVersion,
                'published_count' => count($rankedItems),
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
