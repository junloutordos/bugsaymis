<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('work_requests', 'expected_completion_date')) {
            return;
        }

        Schema::table('work_requests', function (Blueprint $table) {
            $table->date('expected_completion_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('work_requests', 'expected_completion_date')) {
            return;
        }

        Schema::table('work_requests', function (Blueprint $table) {
            $table->date('expected_completion_date')->nullable(false)->change();
        });
    }
};
