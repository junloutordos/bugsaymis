<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which enrolled devices are on the document-backup schedule. A device
        // with no row here (or enabled=false) is never sent backup work.
        Schema::create('ict_backup_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id')->unique();
            $table->boolean('enabled')->default(true);
            $table->string('frequency', 10)->default('daily'); // daily | weekly
            $table->unsignedTinyInteger('weekly_day')->nullable(); // 0=Sun..6=Sat, weekly only
            $table->unsignedTinyInteger('preferred_hour')->default(12); // PHT hour the run becomes due
            $table->boolean('include_downloads')->default(false);
            $table->unsignedInteger('max_file_mb')->default(100);
            $table->timestamp('last_dispatched_at')->nullable();
            $table->timestamp('run_requested_at')->nullable(); // "Back up now" flag, cleared on dispatch
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('enabled');
        });

        // One row per backup attempt — the auditable unit shown in the UI.
        Schema::create('ict_backup_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->unsignedBigInteger('schedule_id')->nullable();
            // dispatched → running → completed | completed_with_errors | failed
            $table->string('status', 30)->default('dispatched');
            $table->json('config')->nullable(); // settings snapshot sent to the agent
            $table->unsignedInteger('files_scanned')->nullable();
            $table->unsignedInteger('files_uploaded')->default(0);
            $table->unsignedInteger('files_skipped')->nullable();
            $table->unsignedBigInteger('bytes_uploaded')->default(0);
            $table->json('errors')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'status']);
        });

        // Latest known backed-up state of every file — the manifest diff runs
        // against this. path_hash (sha1 of relative_path) keeps the unique key
        // small; deep Windows paths overflow an indexed varchar.
        Schema::create('ict_backup_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->char('path_hash', 40);
            $table->text('relative_path');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->char('content_sha256', 64);
            $table->string('file_mtime', 40)->nullable();
            $table->string('drive_file_id')->nullable();
            $table->timestamp('last_backed_up_at')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'path_hash']);
        });

        // Drive folder-ID cache so we don't hit the Drive API to re-resolve
        // "device root / user / Documents / …" on every upload session.
        Schema::create('ict_backup_folders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->char('path_hash', 40); // sha1 of folder path, '' = device root
            $table->text('path');
            $table->string('drive_folder_id');
            $table->timestamps();

            $table->unique(['device_id', 'path_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ict_backup_folders');
        Schema::dropIfExists('ict_backup_files');
        Schema::dropIfExists('ict_backup_runs');
        Schema::dropIfExists('ict_backup_schedules');
    }
};
