<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_advisories', function (Blueprint $table) {
            $table->foreignId('research_group_id')->nullable()->after('load_assignment_id')
                ->constrained('research_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('research_advisories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('research_group_id');
        });
    }
};
