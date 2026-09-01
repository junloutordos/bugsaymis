<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dtr_records', function (Blueprint $table) {
            $table->boolean('time_manually_edited')->default(false)->after('remarks')
                ->comment('True once an admin edits time_in/out via edit() — protects the record from being overwritten by generate() on the next resync');
        });
    }

    public function down(): void
    {
        Schema::table('dtr_records', function (Blueprint $table) {
            $table->dropColumn('time_manually_edited');
        });
    }
};
