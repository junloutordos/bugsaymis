<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_time_punches', function (Blueprint $table) {
            $table->string('geofence_status')->nullable()->after('longitude')
                ->comment('inside, outside, no_permission, unconfigured');
            $table->decimal('distance_meters', 8, 2)->nullable()->after('geofence_status');

            $table->string('liveness_status')->nullable()->after('liveness_confidence')
                ->comment('passed, failed, skipped');
            $table->string('liveness_challenge_type')->nullable()->after('liveness_status')
                ->comment('turn_head, blink');
            $table->json('challenge_frames_s3_keys')->nullable()->after('liveness_challenge_type');
        });
    }

    public function down(): void
    {
        Schema::table('online_time_punches', function (Blueprint $table) {
            $table->dropColumn([
                'geofence_status',
                'distance_meters',
                'liveness_status',
                'liveness_challenge_type',
                'challenge_frames_s3_keys',
            ]);
        });
    }
};
