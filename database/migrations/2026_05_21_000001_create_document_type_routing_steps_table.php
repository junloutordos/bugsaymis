<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            $table->string('routing_type')->default('sequential')->after('is_active'); // sequential|parallel|manual
        });

        Schema::create('document_type_routing_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('step_order')->default(1);
            $table->string('role_name')->nullable();
            $table->string('action_required');
            $table->unsignedInteger('lead_time_hours')->default(24);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->index(['document_type_id', 'step_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_type_routing_steps');
        Schema::table('document_types', function (Blueprint $table) {
            $table->dropColumn('routing_type');
        });
    }
};
