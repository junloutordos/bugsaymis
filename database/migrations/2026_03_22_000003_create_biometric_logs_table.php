<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_logs', function (Blueprint $table) {
            $table->id();

            // Raw device ID — may not match user_id until resolved
            $table->string('device_employee_id')->index()
                ->comment('Raw employee ID from biometric device (e.g. badge number)');

            // Resolved FK to users — nullable until import resolves
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('device_id')->nullable()->index()
                ->comment('Biometric device/terminal identifier');

            $table->dateTime('log_datetime')->index();

            $table->enum('log_type', ['time_in', 'time_out', 'auto'])
                ->default('auto')
                ->comment('auto = determined by DTRService based on sequence');

            $table->enum('source', ['biometric', 'manual', 'api'])->default('biometric');

            $table->boolean('is_resolved')->default(false)->index()
                ->comment('True when user_id has been matched');
            $table->boolean('is_duplicate')->default(false)
                ->comment('Flagged duplicates within same minute');

            $table->string('import_batch')->nullable()->index()
                ->comment('UUID of the import session that created this record');

            $table->timestamp('imported_at')->useCurrent();
            $table->timestamps();

            // Prevent exact duplicate log entries from same device
            $table->unique(['device_employee_id', 'log_datetime', 'device_id'], 'bio_log_unique');
            $table->index(['user_id', 'log_datetime']);
            $table->index(['is_resolved', 'log_datetime']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_logs');
    }
};
