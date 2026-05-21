<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_routings', function (Blueprint $table) {
            $table->text('instructions')->nullable()->after('remarks');
            $table->boolean('is_terminal')->default(false)->after('instructions');
            $table->timestamp('returned_at')->nullable()->after('forwarded_at');
            $table->text('return_reason')->nullable()->after('returned_at');
        });
    }

    public function down(): void
    {
        Schema::table('document_routings', function (Blueprint $table) {
            $table->dropColumn(['instructions', 'is_terminal', 'returned_at', 'return_reason']);
        });
    }
};
