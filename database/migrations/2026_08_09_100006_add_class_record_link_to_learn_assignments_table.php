<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learn_assignments', function (Blueprint $table) {
            $table->foreignId('class_record_assessment_id')->nullable()->after('due_at')
                  ->constrained('class_record_assessments')->nullOnDelete();
            $table->timestamp('pushed_at')->nullable()->after('class_record_assessment_id');
        });
    }

    public function down(): void
    {
        Schema::table('learn_assignments', function (Blueprint $table) {
            $table->dropForeign(['class_record_assessment_id']);
            $table->dropColumn(['class_record_assessment_id', 'pushed_at']);
        });
    }
};
