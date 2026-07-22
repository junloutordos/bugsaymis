<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A failed MySQL FK creation can leave this new table behind without
        // recording the migration. Remove that empty, incomplete table before
        // retrying the migration.
        Schema::dropIfExists('section_consultation_overrides');

        $sectionIdType = strtolower((string) DB::selectOne("SHOW COLUMNS FROM sections WHERE Field = 'id'")->Type);
        $sectionIdIsUnsigned = str_contains($sectionIdType, 'unsigned');

        Schema::create('section_consultation_overrides', function (Blueprint $table) use ($sectionIdIsUnsigned) {
            $table->id();
            // Match the legacy sections.id signedness, which differs between
            // older production and freshly-created development databases.
            $sectionIdIsUnsigned
                ? $table->unsignedInteger('section_id')
                : $table->integer('section_id');
            $table->string('day_of_week', 12);
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['section_id', 'day_of_week'], 'section_consultation_day_unique');
            $table->foreign('section_id')->references('id')->on('sections')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('section_consultation_overrides');
    }
};
