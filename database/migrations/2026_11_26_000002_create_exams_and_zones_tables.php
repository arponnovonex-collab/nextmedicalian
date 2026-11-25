<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('code', 20)->unique();
            $table->timestamps();
        });

        Schema::create('centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('zones')->onDelete('restrict');
            $table->string('name', 150);
            $table->string('code', 50)->unique();
            $table->text('address');
            $table->text('google_maps_url')->nullable();
            $table->text('google_maps_embed_url')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('capacity')->default(0);
            $table->string('contact_person', 100)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->text('special_instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('exam_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->onDelete('cascade');
            $table->string('room_number', 50);
            $table->string('floor', 50)->nullable();
            $table->unsignedInteger('capacity')->default(30);
            $table->timestamps();

            $table->unique(['center_id', 'room_number']);
        });

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->date('exam_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->decimal('total_marks', 6, 2)->default(100.00);
            $table->unsignedInteger('total_questions')->default(100);
            $table->decimal('marks_per_correct', 5, 2)->default(1.00);
            $table->decimal('negative_marks_per_incorrect', 5, 2)->default(0.25);
            $table->decimal('marks_per_unanswered', 5, 2)->default(0.00);
            $table->decimal('pass_marks', 6, 2)->default(40.00);
            $table->text('instructions')->nullable();
            $table->dateTime('registration_deadline')->nullable();
            $table->dateTime('admit_card_publish_at')->nullable();
            $table->dateTime('evaluation_deadline')->nullable();
            $table->dateTime('result_publish_at')->nullable();
            $table->enum('status', [
                'draft',
                'registration_open',
                'registration_closed',
                'admit_card_published',
                'evaluation_in_progress',
                'evaluation_completed',
                'result_published',
                'archived'
            ])->default('draft')->index();
            $table->string('logo_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        Schema::create('exam_centers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->onDelete('cascade');
            $table->foreignId('center_id')->constrained('centers')->onDelete('cascade');
            $table->unsignedInteger('allocated_capacity')->default(0);
            $table->timestamps();

            $table->unique(['exam_id', 'center_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_centers');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('exam_rooms');
        Schema::dropIfExists('centers');
        Schema::dropIfExists('zones');
    }
};
