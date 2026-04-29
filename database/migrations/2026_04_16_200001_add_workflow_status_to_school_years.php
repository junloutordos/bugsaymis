<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_years', function (Blueprint $table) {
            $table->enum('workflow_status', ['draft', 'proposed', 'approved', 'published', 'archived'])
                  ->default('draft')
                  ->after('status')
                  ->comment('Lifecycle state: draft→proposed→approved→published→archived');

            $table->index('workflow_status');
        });
    }

    public function down(): void
    {
        Schema::table('school_years', function (Blueprint $table) {
            $table->dropIndex(['workflow_status']);
            $table->dropColumn('workflow_status');
        });
    }
};
