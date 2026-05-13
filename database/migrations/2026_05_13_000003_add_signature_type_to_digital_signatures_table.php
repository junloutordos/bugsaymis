<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('digital_signatures', function (Blueprint $table) {
            $table->enum('signature_type', ['hmac', 'kms'])->default('hmac')->after('signed_at');
        });
    }

    public function down(): void
    {
        Schema::table('digital_signatures', function (Blueprint $table) {
            $table->dropColumn('signature_type');
        });
    }
};
