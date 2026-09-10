<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * so_number — free-text fallback for committees whose Special/Office
     * Order predates the Issuance module (or was never digitized).
     * issuance_id — optional link to an actual Issuance record when one
     * exists; nullable, no cascade delete (a committee must never be
     * silently destroyed by deleting an unrelated issuance).
     */
    public function up(): void
    {
        Schema::table('committees', function (Blueprint $table) {
            if (! Schema::hasColumn('committees', 'so_number')) {
                $table->string('so_number', 100)->nullable()->after('description');
            }
            if (! Schema::hasColumn('committees', 'issuance_id')) {
                $table->foreignId('issuance_id')->nullable()->after('so_number')
                      ->constrained('issuances')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('committees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('issuance_id');
            $table->dropColumn('so_number');
        });
    }
};
