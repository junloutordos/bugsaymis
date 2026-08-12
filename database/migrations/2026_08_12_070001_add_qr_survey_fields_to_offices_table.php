<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->string('qr_survey_token', 32)->nullable()->unique()->after('unit_head');
            $table->boolean('qr_survey_enabled')->default(true)->after('qr_survey_token');
        });

        // Backfill opaque tokens for existing rows so QR codes can be generated immediately.
        DB::table('offices')->whereNull('qr_survey_token')->orderBy('id')->select('id')->get()
            ->each(function ($row) {
                DB::table('offices')->where('id', $row->id)->update([
                    'qr_survey_token' => Str::random(22),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->dropColumn(['qr_survey_token', 'qr_survey_enabled']);
        });
    }
};
