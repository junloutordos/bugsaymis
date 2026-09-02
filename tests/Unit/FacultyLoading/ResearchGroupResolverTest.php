<?php

namespace Tests\Unit\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Services\FacultyLoading\ResearchGroupResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchGroupResolverTest extends TestCase
{
    use RefreshDatabase;

    private function makeTerm(): AcademicTerm
    {
        $sy = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        return AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
    }

    public function test_creates_group_on_first_resolve(): void
    {
        $term = $this->makeTerm();
        $group = (new ResearchGroupResolver())->resolve($term->id, 10, '  The Effects of X  ', 'thesis');

        $this->assertSame('The Effects of X', $group->title);
        $this->assertSame(10, $group->grade_level);
        $this->assertSame('thesis', $group->research_type);
    }

    public function test_reuses_existing_group_on_case_insensitive_title_match(): void
    {
        $term  = $this->makeTerm();
        $resolver = new ResearchGroupResolver();

        $first  = $resolver->resolve($term->id, 10, 'The Effects of X', 'thesis');
        $second = $resolver->resolve($term->id, 10, '  the effects of x', 'thesis');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, \App\Models\FacultyLoading\ResearchGroup::count());
    }

    public function test_different_grade_level_creates_a_separate_group(): void
    {
        $term  = $this->makeTerm();
        $resolver = new ResearchGroupResolver();

        $g10 = $resolver->resolve($term->id, 10, 'Same Title', 'thesis');
        $g11 = $resolver->resolve($term->id, 11, 'Same Title', 'thesis');

        $this->assertNotSame($g10->id, $g11->id);
    }
}
