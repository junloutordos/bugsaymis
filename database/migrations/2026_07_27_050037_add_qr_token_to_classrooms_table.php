<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * qr_token is a secret independent of nfc_uuid — the printed QR must
     * never map to the same identifier used by the physical NFC tag, or
     * the QR's gated route could be bypassed by hitting the bare NFC URL
     * with the same uuid. Auto-generated for existing rows, same pattern
     * used when nfc_uuid was originally added.
     */
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->string('qr_token', 64)->nullable()->unique()->after('nfc_uuid');
        });

        DB::table('classrooms')->whereNull('qr_token')->orderBy('id')->pluck('id')->each(function ($id) {
            DB::table('classrooms')->where('id', $id)->update(['qr_token' => Str::random(40)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropColumn('qr_token');
        });
    }
};
