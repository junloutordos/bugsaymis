<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_batches', function (Blueprint $table) {
            $table->char('release_id', 36)->nullable()->after('notes')->index();
            $table->boolean('is_primary')->default(true)->after('release_id');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_batches', function (Blueprint $table) {
            $table->dropIndex(['release_id']);
            $table->dropColumn(['release_id', 'is_primary']);
        });
    }
};
