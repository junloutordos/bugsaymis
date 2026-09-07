<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipcr_v2_core_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('self_quality_rating')->nullable()->after('remarks');
            $table->unsignedTinyInteger('self_efficiency_rating')->nullable()->after('self_quality_rating');
            $table->unsignedTinyInteger('self_student_feedback_rating')->nullable()->after('self_efficiency_rating');
            $table->unsignedTinyInteger('self_supervisor_feedback_rating')->nullable()->after('self_student_feedback_rating');
            $table->unsignedTinyInteger('self_im_development_rating')->nullable()->after('self_supervisor_feedback_rating');
            $table->unsignedTinyInteger('self_timeliness_rating')->nullable()->after('self_im_development_rating');
            $table->decimal('self_row_average', 4, 2)->nullable()->after('self_timeliness_rating');
        });

        Schema::table('ipcr_v2_support_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('self_quality_rating')->nullable()->after('remarks');
            $table->unsignedTinyInteger('self_efficiency_rating')->nullable()->after('self_quality_rating');
            $table->unsignedTinyInteger('self_timeliness_rating')->nullable()->after('self_efficiency_rating');
            $table->decimal('self_row_average', 4, 2)->nullable()->after('self_timeliness_rating');
        });
    }

    public function down(): void
    {
        Schema::table('ipcr_v2_core_items', function (Blueprint $table) {
            $table->dropColumn([
                'self_quality_rating', 'self_efficiency_rating', 'self_student_feedback_rating',
                'self_supervisor_feedback_rating', 'self_im_development_rating',
                'self_timeliness_rating', 'self_row_average',
            ]);
        });

        Schema::table('ipcr_v2_support_items', function (Blueprint $table) {
            $table->dropColumn(['self_quality_rating', 'self_efficiency_rating', 'self_timeliness_rating', 'self_row_average']);
        });
    }
};
