<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give parent_contacts its own login credentials so AtlasGo parent
     * accounts no longer need a row in the main Atlas `users` table.
     * Additive/nullable only — safe for blue/green (old code ignores these
     * columns, new code can start relying on them once deployed).
     */
    public function up(): void
    {
        Schema::table('parent_contacts', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email')
                ->comment('Hashed password for AtlasGo self-registered parent accounts; null for front-desk-only contacts');
            $table->string('status', 30)->nullable()->after('password')
                ->comment('pending_verification / active / inactive — mirrors users.status for parent login accounts');
            $table->timestamp('email_verified_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('parent_contacts', function (Blueprint $table) {
            $table->dropColumn(['password', 'status', 'email_verified_at']);
        });
    }
};
