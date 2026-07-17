<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_schedule_approval_batches', function (Blueprint $table) {
            $table->string('approval_type', 30)->default('full')->after('status');
            $table->char('baseline_hash', 64)->nullable()->after('schedule_hash');
            $table->json('change_set')->nullable()->after('baseline_hash');
            $table->foreignId('supersedes_batch_id')->nullable()->after('change_set')
                ->constrained('class_schedule_approval_batches');
        });

        Schema::create('class_schedule_swap_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_term_id')->constrained('academic_terms');
            $table->foreignId('source_schedule_id')->nullable()->constrained('class_schedules')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users');
            $table->text('reason');
            $table->json('preferences')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->foreignId('target_schedule_id')->nullable()->constrained('class_schedules')->nullOnDelete();
            $table->json('selected_proposal')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approval_batch_id')->nullable()->constrained('class_schedule_approval_batches');
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['academic_term_id', 'status']);
        });

        $this->updateVersionFeatures(true);
    }

    public function down(): void
    {
        $this->updateVersionFeatures(false);
        Schema::dropIfExists('class_schedule_swap_requests');

        Schema::table('class_schedule_approval_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supersedes_batch_id');
            $table->dropColumn(['approval_type', 'baseline_hash', 'change_set']);
        });
    }

    private function updateVersionFeatures(bool $add): void
    {
        if (! Schema::hasTable('app_versions')) {
            return;
        }

        $release = DB::table('app_versions')->where('version', '1.0.0')->first();
        if (! $release) {
            return;
        }

        $changes = json_decode($release->changes ?: '{}', true) ?: [];
        $features = $changes['features'] ?? [];
        $swapFeatures = [
            'Faculty schedule swap requests with CID-ranked exact swaps and vacant-slot alternatives',
            'OCD-approved class schedule swap amendments with digital signatures and audit history',
        ];

        $features = $add
            ? array_values(array_unique(array_merge($features, $swapFeatures)))
            : array_values(array_diff($features, $swapFeatures));
        $changes['features'] = $features;

        DB::table('app_versions')->where('id', $release->id)->update([
            'changes' => json_encode($changes),
            'updated_at' => now(),
        ]);
    }
};
