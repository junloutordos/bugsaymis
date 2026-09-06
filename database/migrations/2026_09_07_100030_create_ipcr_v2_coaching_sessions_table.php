<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipcr_v2_coaching_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipcr_v2_id')->constrained('ipcr_v2_records')->cascadeOnDelete();
            $table->enum('activity_type', ['monitoring', 'coaching']);
            $table->enum('mechanism', ['one_on_one', 'group']);
            $table->date('meeting_date');
            $table->boolean('channel_memo')->default(false);
            $table->string('channel_others', 255)->nullable();
            $table->text('remarks')->nullable();
            $table->string('conducted_by_name', 255)->nullable();
            $table->timestamp('conducted_at')->nullable();
            $table->string('noted_by_name', 255)->nullable();
            $table->timestamp('noted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipcr_v2_coaching_sessions');
    }
};
