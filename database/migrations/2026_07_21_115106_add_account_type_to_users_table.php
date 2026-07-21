<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Explicit account classification (employee / parent / student), set once at
 * account-creation time — decouples "what kind of account this is" from
 * "what roles it currently holds" (an employee who is later also tagged
 * with the Parent role, e.g. a staff member whose own child is enrolled,
 * must still be classified as an employee everywhere).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_type', 20)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('account_type');
        });
    }
};
