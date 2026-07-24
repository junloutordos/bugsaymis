<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-quarter applicability for grading options. NULL = applies to all
 * quarters (the default, and the state of every existing option). Otherwise a
 * JSON array of the quarters it applies to, e.g. [1,2]. Additive/safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grading_options', function (Blueprint $table) {
            $table->json('applicable_quarters')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('grading_options', function (Blueprint $table) {
            $table->dropColumn('applicable_quarters');
        });
    }
};
