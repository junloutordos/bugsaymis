<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->uuid('nfc_uuid')
                  ->nullable()
                  ->unique()
                  ->after('remarks')
                  ->comment('Encoded in the NFC tag URL — /class-tap/{nfc_uuid}');
        });

        // Generate a UUID for every existing classroom row
        \DB::table('classrooms')->get()->each(function ($row) {
            \DB::table('classrooms')
                ->where('id', $row->id)
                ->update(['nfc_uuid' => Str::uuid()->toString()]);
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropColumn('nfc_uuid');
        });
    }
};
