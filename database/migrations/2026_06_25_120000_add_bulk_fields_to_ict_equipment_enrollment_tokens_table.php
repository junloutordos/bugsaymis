<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ict_equipment_enrollment_tokens', function (Blueprint $table) {
            if (! Schema::hasColumn('ict_equipment_enrollment_tokens', 'label')) {
                $table->string('label', 100)->nullable()->after('token');
            }
            if (! Schema::hasColumn('ict_equipment_enrollment_tokens', 'max_uses')) {
                $table->unsignedInteger('max_uses')->nullable()->after('expires_at');
            }
            if (! Schema::hasColumn('ict_equipment_enrollment_tokens', 'uses_count')) {
                $table->unsignedInteger('uses_count')->default(0)->after('max_uses');
            }
            if (! Schema::hasColumn('ict_equipment_enrollment_tokens', 'revoked_at')) {
                $table->timestamp('revoked_at')->nullable()->after('used_at');
            }
        });

        Schema::table('ict_equipment_devices', function (Blueprint $table) {
            if (! Schema::hasColumn('ict_equipment_devices', 'mac_address')) {
                return;
            }
            $indexes = Schema::getIndexes('ict_equipment_devices');
            $hasMacIndex = collect($indexes)->contains(fn ($idx) => $idx['columns'] === ['mac_address']);
            if (! $hasMacIndex) {
                $table->index('mac_address');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ict_equipment_enrollment_tokens', function (Blueprint $table) {
            $table->dropColumn(['label', 'max_uses', 'uses_count', 'revoked_at']);
        });

        Schema::table('ict_equipment_devices', function (Blueprint $table) {
            $table->dropIndex(['mac_address']);
        });
    }
};
