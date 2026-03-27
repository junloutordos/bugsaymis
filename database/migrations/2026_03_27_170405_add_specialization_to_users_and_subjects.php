<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('specialization', 150)->nullable()->after('position');
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->string('specialization_tags', 500)->nullable()->after('description')
                ->comment('Comma-separated keywords matching faculty specialization, e.g. "mathematics,algebra,calculus"');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('specialization');
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('specialization_tags');
        });
    }
};
