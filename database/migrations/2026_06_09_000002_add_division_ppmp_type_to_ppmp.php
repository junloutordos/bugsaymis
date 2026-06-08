<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ppmp', function (Blueprint $table) {
            $table->enum('ppmp_type', ['unit', 'division'])->default('unit')->after('is_supplemental');
            $table->unsignedBigInteger('division_ppmp_id')->nullable()->after('ppmp_type');

            $table->foreign('division_ppmp_id')->references('id')->on('ppmp')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ppmp', function (Blueprint $table) {
            $table->dropForeign(['division_ppmp_id']);
            $table->dropColumn(['division_ppmp_id', 'ppmp_type']);
        });
    }
};
