<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Opaque token behind the Employee Digital ID QR code. The QR encodes
     * only /verify/employee/{token} — no PII. Generated lazily on first ID
     * render; regenerating it revokes any previously printed card.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('id_verification_token', 64)->nullable()->unique()->after('signature_pin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('id_verification_token');
        });
    }
};
