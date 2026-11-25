<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_exam_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('exam_id')->constrained('exams')->onDelete('cascade');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['teacher_id', 'exam_id']);
        });

        Schema::create('marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_registration_id')->unique()->constrained('exam_registrations')->onDelete('cascade');
            $table->foreignId('evaluated_by')->constrained('users')->onDelete('restrict');
            $table->enum('evaluation_mode', ['direct', 'auto'])->default('auto');
            $table->unsignedInteger('correct_answers')->nullable();
            $table->unsignedInteger('incorrect_answers')->nullable();
            $table->unsignedInteger('unanswered_questions')->nullable();
            $table->decimal('positive_marks', 6, 2)->default(0.00);
            $table->decimal('negative_marks', 6, 2)->default(0.00);
            $table->decimal('unanswered_marks', 6, 2)->default(0.00);
            $table->decimal('final_marks', 6, 2);
            $table->boolean('is_locked')->default(true);
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();

            $table->index(['final_marks']);
            $table->index(['evaluated_by']);
        });

        Schema::create('marks_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marks_id')->constrained('marks')->onDelete('cascade');
            $table->foreignId('requested_by')->constrained('users')->onDelete('restrict');
            $table->decimal('old_marks', 6, 2);
            $table->decimal('proposed_marks', 6, 2);
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('admin_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marks_correction_requests');
        Schema::dropIfExists('marks');
        Schema::dropIfExists('teacher_exam_assignments');
    }
};
