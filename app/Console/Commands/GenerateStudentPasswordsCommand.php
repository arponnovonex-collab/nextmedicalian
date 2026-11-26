<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Models\ExamRegistration;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class GenerateStudentPasswordsCommand extends Command
{
    protected $signature = 'students:generate-passwords 
                            {--exam= : Target Exam ID or Code (e.g. NME-26)} 
                            {--force : Force regenerate even if student already has a 4-digit password}';

    protected $description = 'Generate 4-digit numeric passwords for students and save to student_profiles.temp_password for SMS broadcasting';

    public function handle(): int
    {
        $examInput = $this->option('exam');
        $force = (bool) $this->option('force');

        $query = StudentProfile::query();

        if ($examInput) {
            $exam = Exam::where('code', $examInput)->orWhere('id', $examInput)->first();
            if (! $exam) {
                $this->error("Exam not found with identifier: {$examInput}");
                return 1;
            }
            $studentIds = ExamRegistration::where('exam_id', $exam->id)->pluck('student_id');
            $query->whereIn('id', $studentIds);
            $this->info("Targeting students for Exam: [{$exam->code}] {$exam->title}");
        }

        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('temp_password')
                  ->orWhere('temp_password', '')
                  ->orWhereRaw('LENGTH(temp_password) != 4');
            });
        }

        $total = $query->count();
        if ($total === 0) {
            $this->info('All students already have a valid 4-digit password. Use --force to regenerate.');
            return 0;
        }

        $this->info("Generating 4-digit passwords for {$total} students...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $chunkSize = 500;
        $updated = 0;

        $query->chunkById($chunkSize, function ($profiles) use ($bar, &$updated) {
            DB::transaction(function () use ($profiles, $bar, &$updated) {
                foreach ($profiles as $profile) {
                    // Generate a 4-digit numeric PIN (1000 - 9999)
                    $pin = (string) mt_rand(1000, 9999);
                    $profile->temp_password = $pin;
                    $profile->save();

                    $updated++;
                    $bar->advance();
                }
            });
        });

        $bar->finish();
        $this->newLine();
        $this->info("Successfully generated 4-digit passwords for {$updated} students!");

        return 0;
    }
}
