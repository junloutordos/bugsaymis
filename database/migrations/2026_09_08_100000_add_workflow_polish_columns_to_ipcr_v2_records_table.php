<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipcr_v2_records', function (Blueprint $table) {
            $table->text('remarks')->nullable()->after('status');
            $table->timestamp('locked_at')->nullable()->after('director_signature');
            $table->foreignId('locked_by_id')->nullable()->after('locked_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable()->after('locked_by_id');
            $table->foreignId('reopened_by_id')->nullable()->after('reopened_at')->constrained('users')->nullOnDelete();
            $table->text('reopen_reason')->nullable()->after('reopened_by_id');
            $table->text('comments_recommendations')->nullable()->after('reopen_reason');
        });
    }

    public function down(): void
    {
        Schema::table('ipcr_v2_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('locked_by_id');
            $table->dropConstrainedForeignId('reopened_by_id');
            $table->dropColumn(['remarks', 'locked_at', 'reopened_at', 'reopen_reason', 'comments_recommendations']);
        });
    }
};
