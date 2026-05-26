<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen the issuances.title column from VARCHAR(255) to VARCHAR(1000).
     *
     * The backend validates max:500, but government issuance titles (e.g. Special
     * Orders with full program names) routinely exceed 255 characters. This aligns
     * the DB constraint with the validation rule and prevents SQLSTATE[22001] errors.
     */
    public function up(): void
    {
        Schema::table('issuances', function (Blueprint $table) {
            $table->string('title', 1000)->change();
        });
    }

    public function down(): void
    {
        Schema::table('issuances', function (Blueprint $table) {
            $table->string('title', 255)->change();
        });
    }
};
