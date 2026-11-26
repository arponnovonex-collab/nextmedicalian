<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('exams') && ! Schema::hasColumn('exams', 'signature_path')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->longText('signature_path')->nullable()->after('logo_path');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('exams') && Schema::hasColumn('exams', 'signature_path')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->dropColumn('signature_path');
            });
        }
    }
};
