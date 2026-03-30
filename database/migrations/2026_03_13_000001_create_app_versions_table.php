<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version', 30)->unique();
            $table->date('date');
            $table->text('remarks');
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        // Seed existing version history from version.json
        DB::table('app_versions')->insert([
            [
                'version'    => '1.0.0',
                'date'       => '2026-03-04',
                'remarks'    => 'Initial release — Employee management, General Services, IPCR, Library, Document Tracking, and Dashboard analytics.',
                'is_current' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'version'    => '1.0.1',
                'date'       => '2026-03-05',
                'remarks'    => 'IPCR: fixed weighted average calculations (Strategic 30%, Core 55%, Support 15%) across DC and PMT modules; PMT Show page redesigned to match Employee IPCR format; print no longer includes sidebar; status badges unified across all PM pages; OCD now sees all Division Chiefs plus own division subordinates; Memo Report rating period now populated from database.',
                'is_current' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'version'    => '1.1.0',
                'date'       => '2026-03-12',
                'remarks'    => 'IPCR Workflow: inserted HR review stage between Division Chief and PMT; added Director (OCD) signing stage after PMT approval with electronic signature support; new statuses \'Submitted to HR\' and \'Director Signed\'; DC batch-submit to HR by rating period; HR IPCR Review module (index + show + submit to PMT); email notifications at every workflow transition; double-submission prevention added across 22+ Vue pages.',
                'is_current' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};
