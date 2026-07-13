<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * qr_token gives the public walk-in evaluation route a non-guessable
 * identifier (mirrors Issuance::qr_token / CoaCertificate::qr_token) instead
 * of the sequential activity id, so a stranger can't enumerate activity ids
 * to spam-evaluate an arbitrary activity via the QR link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ams_activities', function (Blueprint $table) {
            $table->uuid('qr_token')->nullable()->unique()->after('id');
        });

        // Backfill existing rows — the model's creating() hook only covers new ones.
        DB::table('ams_activities')->whereNull('qr_token')->orderBy('id')->select('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('ams_activities')->where('id', $row->id)->update(['qr_token' => (string) Str::uuid()]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('ams_activities', function (Blueprint $table) {
            $table->dropColumn('qr_token');
        });
    }
};
