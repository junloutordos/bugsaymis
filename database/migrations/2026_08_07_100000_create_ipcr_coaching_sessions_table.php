<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipcr_coaching_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_ipcr_id')->constrained('employee_ipcrs')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            $table->string('activity_type'); // monitoring | coaching
            $table->string('mechanism'); // one_on_one | group
            $table->date('meeting_date');
            $table->boolean('channel_memo')->default(false);
            $table->string('channel_others')->nullable();
            $table->text('remarks');

            $table->string('conducted_by_name'); // snapshot of the DC's formal name
            $table->date('conducted_at');
            $table->string('noted_by_name')->nullable();
            $table->date('noted_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipcr_coaching_sessions');
    }
};
