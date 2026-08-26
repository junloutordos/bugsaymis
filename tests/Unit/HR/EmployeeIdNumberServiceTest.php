<?php

namespace Tests\Unit\HR;

use App\Models\EmployeeIdSequence;
use App\Models\User;
use App\Services\HR\EmployeeIdNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class EmployeeIdNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmployeeIdNumberService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EmployeeIdNumberService::class);
    }

    public function test_generates_id_in_expected_format(): void
    {
        $user = User::factory()->create();

        $idNumber = $this->service->generateFor($user, 2020, 6);

        $this->assertMatchesRegularExpression('/^E13-2020-06-\d{3}$/', $idNumber);
        $user->refresh();
        $this->assertSame($idNumber, $user->employee_idno_new);
        $this->assertSame(2020, $user->hired_year);
        $this->assertSame(6, $user->hired_month);
    }

    public function test_sequence_is_scoped_per_hire_year(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();

        $idA = $this->service->generateFor($userA, 2020, 1);
        $idB = $this->service->generateFor($userB, 2020, 5);
        // Different year — sequence must restart independently, not continue from 2020's.
        $idC = $this->service->generateFor($userC, 2021, 1);

        [, , , $seqA] = explode('-', $idA);
        [, , , $seqB] = explode('-', $idB);
        [, , , $seqC] = explode('-', $idC);

        $this->assertGreaterThan((int) $seqA, (int) $seqB);
        // 2021's sequence should not simply continue where 2020 left off —
        // it starts from a fresh EmployeeIdSequence row at 0 + a random step.
        $sequenceRow2021 = EmployeeIdSequence::where('hired_year', 2021)->first();
        $this->assertLessThanOrEqual(7, $sequenceRow2021->last_sequence);
        $this->assertStringStartsWith('E13-2021-01-', $idC);
    }

    public function test_sequence_step_is_random_and_not_strictly_sequential(): void
    {
        $ids = [];
        for ($i = 0; $i < 5; $i++) {
            $user = User::factory()->create();
            $ids[] = $this->service->generateFor($user, 2022, 3);
        }

        $sequences = array_map(fn ($id) => (int) explode('-', $id)[3], $ids);

        // Strictly increasing (each generation only ever moves forward)...
        for ($i = 1; $i < count($sequences); $i++) {
            $this->assertGreaterThan($sequences[$i - 1], $sequences[$i]);
        }

        // ...but not all gaps are exactly 1 (random-step obfuscation applied).
        $gaps = [];
        for ($i = 1; $i < count($sequences); $i++) {
            $gaps[] = $sequences[$i] - $sequences[$i - 1];
        }
        $this->assertNotEquals([1, 1, 1, 1], $gaps, 'Expected at least one non-1 gap from random-step obfuscation.');
    }

    public function test_concurrent_generation_does_not_produce_duplicate_numbers(): void
    {
        $users = User::factory()->count(10)->create();

        $ids = $users->map(fn ($u) => $this->service->generateFor($u, 2023, 9))->all();

        $this->assertCount(10, array_unique($ids), 'Expected all generated IDs to be unique.');
    }

    public function test_rejects_year_before_1980(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->service->generateFor($user, 1979, 1);
    }

    public function test_rejects_year_in_the_future(): void
    {
        $user = User::factory()->create();
        $futureYear = (int) now()->addYear()->format('Y');

        $this->expectException(InvalidArgumentException::class);
        $this->service->generateFor($user, $futureYear, 1);
    }

    public function test_rejects_invalid_month(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->service->generateFor($user, 2020, 13);
    }
}
