<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spms_ipcrs', function (Blueprint $table) {
            $table->foreign('dpcr_id', 'spms_ipcrs_dpcr_id_foreign')
                ->references('id')->on('spms_dpcrs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('spms_ipcrs', function (Blueprint $table) {
            $table->dropForeign('spms_ipcrs_dpcr_id_foreign');
        });
    }
};
