<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_routings', function (Blueprint $table) {
            $table->timestamp('reviewed_at')->nullable()->after('action_taken_at');
        });
    }

    public function down(): void
    {
        Schema::table('document_routings', function (Blueprint $table) {
            $table->dropColumn('reviewed_at');
        });
    }
};
