<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_ipcrs', function (Blueprint $table) {
            $table->timestamp('target_approved_at')->nullable()->after('submitted_for_review_at');
            
        });
    }

    public function down(): void
    {
        Schema::table('employee_ipcrs', function (Blueprint $table) {
            $table->dropColumn('target_approved_at');
        
        });
    }
};
