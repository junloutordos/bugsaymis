<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_ipcrs', function (Blueprint $table) {
            $table->decimal('final_numeric_rating', 5, 2)->nullable()->after('director_signature');
            $table->string('final_adjectival_rating', 30)->nullable()->after('final_numeric_rating');
            $table->timestamp('appealed_at')->nullable()->after('final_adjectival_rating');
            $table->text('appeal_remarks')->nullable()->after('appealed_at');
        });
    }

    public function down(): void
    {
        Schema::table('employee_ipcrs', function (Blueprint $table) {
            $table->dropColumn(['final_numeric_rating', 'final_adjectival_rating', 'appealed_at', 'appeal_remarks']);
        });
    }
};
