<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_registration_id')->unique()->constrained('exam_registrations')->onDelete('cascade');
            $table->boolean('is_present')->default(true);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('admit_card_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_registration_id')->constrained('exam_registrations')->onDelete('cascade');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('downloaded_at')->useCurrent();
        });

        Schema::create('result_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->onDelete('cascade');
            $table->foreignId('exam_registration_id')->constrained('exam_registrations')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('student_profiles')->onDelete('cascade');
            $table->decimal('obtained_marks', 6, 2);
            $table->decimal('percentage', 5, 2);
            $table->unsignedInteger('overall_merit');
            $table->unsignedInteger('zone_merit');
            $table->unsignedInteger('center_merit');
            $table->boolean('is_passed');
            $table->unsignedInteger('correct_count')->nullable();
            $table->unsignedInteger('incorrect_count')->nullable();
            $table->unsignedInteger('unanswered_count')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('published_at')->useCurrent();
            $table->timestamps();

            $table->unique(['exam_id', 'exam_registration_id', 'version']);
            $table->index(['exam_id', 'overall_merit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_snapshots');
        Schema::dropIfExists('admit_card_downloads');
        Schema::dropIfExists('attendance_records');
    }
};
