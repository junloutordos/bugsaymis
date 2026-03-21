<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_items', function (Blueprint $table) {
            $table->text('education')->nullable()->after('qualification_standards');
            $table->text('experience')->nullable()->after('education');
            $table->text('training')->nullable()->after('experience');
            $table->text('eligibility')->nullable()->after('training');
            $table->json('competencies')->nullable()->after('eligibility');
        });
    }

    public function down(): void
    {
        Schema::table('job_items', function (Blueprint $table) {
            $table->dropColumn(['education', 'experience', 'training', 'eligibility', 'competencies']);
        });
    }
};
