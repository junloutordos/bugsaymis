<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sub-categories under grading categories. A category is a LEAF (carries the
 * weight + max_assessments and holds assessments) or a PARENT (organizational
 * group; parent_id points to it from its children). Existing rows have
 * parent_id = null → all leaves, unchanged behaviour. Additive/safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grading_categories', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('grading_option_id')
                ->constrained('grading_categories')->nullOnDelete();
            $table->index(['grading_option_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::table('grading_categories', function (Blueprint $table) {
            $table->dropIndex(['grading_option_id', 'parent_id']);
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
