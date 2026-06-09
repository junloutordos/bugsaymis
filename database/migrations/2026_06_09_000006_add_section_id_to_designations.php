<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('designations', 'section_id')) {
            Schema::table('designations', function (Blueprint $table) {
                $table->unsignedInteger('section_id')
                      ->nullable()
                      ->after('designation_category_id')
                      ->comment('Set for HRA-/HAC- designations — references the owning section');

                $table->foreign('section_id')
                      ->references('id')
                      ->on('sections')
                      ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('designations', 'section_id')) {
            Schema::table('designations', function (Blueprint $table) {
                $table->dropForeign(['section_id']);
                $table->dropColumn('section_id');
            });
        }
    }
};
