<?php

namespace Tests\Feature\HR;

use App\Models\HR\BiometricLog;
use App\Models\User;
use App\Services\HR\BiometricImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BiometricImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_reads_a_file_resolves_known_badges_and_flags_unknown_ones(): void
    {
        $user = User::factory()->create(['badge_id' => '101']);

        $path = tempnam(sys_get_temp_dir(), 'attlog');
        file_put_contents($path, "101\t2026-07-23 07:58:03\t1\t0\n999\t2026-07-23 08:00:00\t1\t0\n");

        $stats = app(BiometricImportService::class)->parse($path, 'batch-1', 'guardhouse-gt200');

        $this->assertSame(2, $stats['inserted']);
        $this->assertSame(1, $stats['resolved']);
        $this->assertSame(1, $stats['unresolved']);
        $this->assertSame(0, $stats['duplicates']);

        $this->assertDatabaseHas('biometric_logs', [
            'device_employee_id' => '101',
            'user_id' => $user->id,
            'device_id' => 'guardhouse-gt200',
            'log_datetime' => '2026-07-23 07:58:03',
            'log_type' => 'time_in',
            'source' => 'biometric',
            'import_batch' => 'batch-1',
            'is_resolved' => 1,
        ]);

        $this->assertDatabaseHas('biometric_logs', [
            'device_employee_id' => '999',
            'user_id' => null,
            'is_resolved' => 0,
        ]);

        unlink($path);
    }

    public function test_parse_deduplicates_against_existing_rows(): void
    {
        $user = User::factory()->create(['badge_id' => '101']);

        BiometricLog::create([
            'device_employee_id' => '101',
            'user_id' => $user->id,
            'device_id' => 'guardhouse-gt200',
            'log_datetime' => '2026-07-23 07:58:03',
            'log_type' => 'time_in',
            'source' => 'biometric',
            'is_resolved' => true,
            'is_duplicate' => false,
        ]);

        $path = tempnam(sys_get_temp_dir(), 'attlog');
        file_put_contents($path, "101\t2026-07-23 07:58:03\t1\t0\n");

        $stats = app(BiometricImportService::class)->parse($path, 'batch-2', 'guardhouse-gt200');

        $this->assertSame(0, $stats['inserted']);
        $this->assertSame(1, $stats['duplicates']);
        $this->assertSame(1, BiometricLog::count());

        unlink($path);
    }

    public function test_ingest_api_punches_writes_rows_with_api_source_and_no_import_batch(): void
    {
        $user = User::factory()->create(['badge_id' => '101']);

        $stats = app(BiometricImportService::class)->ingestApiPunches(
            "101\t2026-07-23 07:58:03\t1\t0",
            'guardhouse-gt200'
        );

        $this->assertSame(1, $stats['inserted']);
        $this->assertCount(1, $stats['new_rows']);
        $this->assertSame($user->id, $stats['new_rows'][0]['user_id']);
        $this->assertSame('time_in', $stats['new_rows'][0]['log_type']);

        $this->assertDatabaseHas('biometric_logs', [
            'device_employee_id' => '101',
            'device_id' => 'guardhouse-gt200',
            'source' => 'api',
            'import_batch' => null,
        ]);
    }

    public function test_ingest_api_punches_deduplicates_against_a_prior_file_import_of_the_same_punch(): void
    {
        $user = User::factory()->create(['badge_id' => '101']);

        BiometricLog::create([
            'device_employee_id' => '101',
            'user_id' => $user->id,
            'device_id' => 'guardhouse-gt200',
            'log_datetime' => '2026-07-23 07:58:03',
            'log_type' => 'time_in',
            'source' => 'biometric',
            'is_resolved' => true,
            'is_duplicate' => false,
        ]);

        $stats = app(BiometricImportService::class)->ingestApiPunches(
            "101\t2026-07-23 07:58:03\t1\t0",
            'guardhouse-gt200'
        );

        $this->assertSame(0, $stats['inserted']);
        $this->assertSame(1, $stats['duplicates']);
        $this->assertSame(1, BiometricLog::count());
    }
}
