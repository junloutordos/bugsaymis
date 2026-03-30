<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'biometric_id')) {
            // Column already exists (added manually); just ensure the unique index is present.
            Schema::table('users', function (Blueprint $table) {
                if (! collect(Schema::getIndexes('users'))
                    ->pluck('name')
                    ->contains('users_biometric_id_unique')
                ) {
                    $table->unique('biometric_id');
                }
            });
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('biometric_id')->nullable()->unique()->after('badge_id')
                  ->comment('Biometric device employee ID used for log matching');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_biometric_id_unique');
            $table->dropColumn('biometric_id');
        });
    }
};
