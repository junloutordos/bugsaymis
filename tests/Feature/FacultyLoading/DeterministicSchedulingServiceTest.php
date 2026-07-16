<?php

namespace Tests\Feature\FacultyLoading;

use App\Services\FacultyLoading\DeterministicSchedulingService;
use ReflectionClass;
use Tests\TestCase;

class DeterministicSchedulingServiceTest extends TestCase
{
    private DeterministicSchedulingService $service;

    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DeterministicSchedulingService;
        $this->reflection = new ReflectionClass($this->service);
        $this->invokePrivate('buildGrids', [[8]]);
    }

    public function test_regular_session_cannot_use_grade_8_ilp_only_overflow(): void
    {
        $grid = $this->property('gridByGrade')[8];
        $reserved = array_values(array_filter(
            $grid,
            fn ($slot) => $slot['day'] === 'Monday' && ! $slot['is_ilp_only'],
        ));

        $slot = $this->invokePrivate('findBestSlot', [$this->makeSession('regular', 'Monday'), $reserved]);

        $this->assertNull($slot);
    }

    public function test_ilp_session_can_use_grade_8_ilp_only_overflow(): void
    {
        $grid = $this->property('gridByGrade')[8];
        $reserved = array_values(array_filter(
            $grid,
            fn ($slot) => $slot['day'] === 'Monday' && ! $slot['is_ilp_only'],
        ));

        $slot = $this->invokePrivate('findBestSlot', [$this->makeSession('ilp', 'Monday'), $reserved]);

        $this->assertSame('16:00', $slot['start']);
        $this->assertSame('16:30', $slot['end']);
        $this->assertTrue($slot['is_ilp_only']);
    }

    public function test_overflow_is_ranked_after_standard_periods(): void
    {
        $slot = $this->invokePrivate('findBestSlot', [$this->makeSession('ilp'), []]);

        $this->assertFalse($slot['is_overflow']);
    }

    private function makeSession(string $type, ?string $forcedDay = null): array
    {
        return [
            'grade' => 8,
            'section_id' => 1,
            'subject_id' => 1,
            'faculty_id' => -1,
            'session_type' => $type,
            'is_elective' => false,
            'forced_day' => $forcedDay,
        ];
    }

    private function invokePrivate(string $method, array $arguments): mixed
    {
        return $this->reflection->getMethod($method)->invokeArgs($this->service, $arguments);
    }

    private function property(string $name): mixed
    {
        return $this->reflection->getProperty($name)->getValue($this->service);
    }
}
