<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('student_profiles')->onDelete('cascade');
            $table->foreignId('center_id')->nullable()->constrained('centers')->onDelete('set null');
            $table->foreignId('room_id')->nullable()->constrained('exam_rooms')->onDelete('set null');
            $table->string('seat_number', 50)->nullable();
            $table->string('admit_card_token', 64)->unique();
            $table->unsignedInteger('admit_card_downloaded_count')->default(0);
            $table->timestamp('first_admit_download_at')->nullable();
            $table->enum('status', ['registered', 'admit_issued', 'cancelled'])->default('registered');
            $table->timestamps();

            $table->unique(['exam_id', 'student_id']);
            $table->index(['exam_id', 'center_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_registrations');
    }
};
