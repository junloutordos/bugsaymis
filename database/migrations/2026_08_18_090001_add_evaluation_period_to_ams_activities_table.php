<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ams_activities', function (Blueprint $table) {
            $table->boolean('evaluation_open')->default(true)->after('qr_token');
            $table->timestamp('evaluation_status_changed_at')->nullable()->after('evaluation_open');
            $table->foreignId('evaluation_status_changed_by')
                ->nullable()
                ->after('evaluation_status_changed_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ams_activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('evaluation_status_changed_by');
            $table->dropColumn(['evaluation_open', 'evaluation_status_changed_at']);
        });
    }
};
