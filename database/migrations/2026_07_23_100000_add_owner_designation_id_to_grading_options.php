<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grading_options', function (Blueprint $table) {
            $table->foreignId('owner_designation_id')
                ->nullable()
                ->after('is_active')
                ->constrained('designations')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('grading_options', function (Blueprint $table) {
            $table->dropForeign(['owner_designation_id']);
            $table->dropColumn('owner_designation_id');
        });
    }
};
