<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $sectionIdType = strtolower((string) DB::selectOne("SHOW COLUMNS FROM sections WHERE Field = 'id'")->Type);
        $sectionIdIsUnsigned = str_contains($sectionIdType, 'unsigned');

        Schema::create('class_schedule_scope_locks', function (Blueprint $table) use ($sectionIdIsUnsigned) {
            $table->id();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->cascadeOnDelete();
            $table->string('scope_type', 20);
            $table->string('scope_key', 40);
            $table->unsignedTinyInteger('grade_level')->nullable();
            $sectionIdIsUnsigned
                ? $table->unsignedInteger('section_id')->nullable()
                : $table->integer('section_id')->nullable();
            $table->foreignId('locked_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('locked_at')->useCurrent();
            $table->timestamps();

            $table->unique(['academic_term_id', 'scope_key'], 'schedule_scope_locks_term_scope_unique');
            $table->index(['academic_term_id', 'scope_type'], 'schedule_scope_locks_term_type_index');
            $table->foreign('section_id')->references('id')->on('sections')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_schedule_scope_locks');
    }
};
