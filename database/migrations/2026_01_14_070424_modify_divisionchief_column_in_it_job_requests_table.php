<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('it_job_requests', function (Blueprint $table) {

            // Drop old string column if it exists
            if (Schema::hasColumn('it_job_requests', 'divisionchief')) {
                $table->dropColumn('divisionchief');
            }

            // Add foreign key column if it does not exist
            if (!Schema::hasColumn('it_job_requests', 'divisionchief_id')) {
                $table->foreignId('divisionchief_id')
                    ->after('status')
                    ->constrained('users')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('it_job_requests', function (Blueprint $table) {

            // Drop FK + column safely
            if (Schema::hasColumn('it_job_requests', 'divisionchief_id')) {
                $table->dropForeign(['divisionchief_id']);
                $table->dropColumn('divisionchief_id');
            }

            // Restore old column
            if (!Schema::hasColumn('it_job_requests', 'divisionchief')) {
                $table->string('divisionchief')->nullable();
            }
        });
    }
};
