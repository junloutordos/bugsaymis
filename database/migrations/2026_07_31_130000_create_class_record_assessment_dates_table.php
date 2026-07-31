<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_record_assessment_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_record_assessment_id')
                ->constrained('class_record_assessments')
                ->cascadeOnDelete();
            $table->date('activity_date');
            $table->timestamp('plotted_at')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(
                ['class_record_assessment_id', 'activity_date'],
                'crad_assessment_date_unique'
            );
            $table->index(['activity_date', 'class_record_assessment_id'], 'crad_date_assessment_idx');
        });

        DB::table('class_record_assessment_dates')->insertUsing(
            [
                'class_record_assessment_id',
                'activity_date',
                'plotted_at',
                'is_primary',
                'created_at',
                'updated_at',
            ],
            DB::table('class_record_assessments')
                ->whereNotNull('activity_date')
                ->select([
                    'id',
                    'activity_date',
                    'plotted_at',
                    DB::raw('1'),
                    'created_at',
                    'updated_at',
                ])
        );

        Schema::table('class_record_assessment_deletion_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('class_record_assessment_date_id')
                ->nullable()
                ->after('class_record_assessment_id');
            $table->foreign('class_record_assessment_date_id', 'cradr_assessment_date_fk')
                ->references('id')
                ->on('class_record_assessment_dates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('class_record_assessment_deletion_requests', function (Blueprint $table) {
            $table->dropForeign('cradr_assessment_date_fk');
            $table->dropColumn('class_record_assessment_date_id');
        });
        Schema::dropIfExists('class_record_assessment_dates');
    }
};
