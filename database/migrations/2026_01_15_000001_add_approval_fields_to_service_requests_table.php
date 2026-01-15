<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('division_chief_id')->nullable()->after('unit');
            $table->text('decline_reason')->nullable()->after('division_chief_id');
            $table->timestamp('declined_at')->nullable()->after('decline_reason');
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropColumn(['division_chief_id','decline_reason','declined_at']);
        });
    }
};
