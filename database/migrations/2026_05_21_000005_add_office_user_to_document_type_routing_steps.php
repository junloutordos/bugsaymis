<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_type_routing_steps', function (Blueprint $table) {
            $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete()->after('role_name');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete()->after('office_id');
        });
    }

    public function down(): void
    {
        Schema::table('document_type_routing_steps', function (Blueprint $table) {
            $table->dropForeign(['office_id']);
            $table->dropForeign(['assigned_user_id']);
            $table->dropColumn(['office_id', 'assigned_user_id']);
        });
    }
};
