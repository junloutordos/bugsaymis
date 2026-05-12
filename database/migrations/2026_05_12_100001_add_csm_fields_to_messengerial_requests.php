<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messengerial_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
            $table->timestamp('rated_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('messengerial_requests', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'rated_at']);
        });
    }
};
