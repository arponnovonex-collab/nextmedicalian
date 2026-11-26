<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('exams')
            ->where('code', 'NME-26')
            ->orWhere('id', 3)
            ->update([
                'exam_date' => '2026-11-27',
                'start_time' => '11:00:00',
                'end_time' => '12:00:00',
            ]);
    }

    public function down(): void
    {
        DB::table('exams')
            ->where('code', 'NME-26')
            ->orWhere('id', 3)
            ->update([
                'exam_date' => '2026-11-26',
                'start_time' => '10:00:00',
                'end_time' => '11:00:00',
            ]);
    }
};
