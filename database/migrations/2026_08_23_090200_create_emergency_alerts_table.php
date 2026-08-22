<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message');
            $table->string('severity', 20)->default('warning'); // info | warning | critical
            $table->string('audience', 20)->default('all');     // all | employees | students | parents
            $table->string('status', 20)->default('active');    // active | resolved
            $table->string('source', 20)->default('manual');    // manual | escalated
            $table->foreignId('sos_alert_id')->nullable()->constrained('sos_alerts')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_alerts');
    }
};
