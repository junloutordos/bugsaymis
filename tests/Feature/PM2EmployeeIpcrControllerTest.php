<?php

namespace Tests\Feature;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use App\Models\PM2\IpcrRatingPeriodV2;
use App\Models\PM2\OpcrTemplate;
use App\Models\PM2\OpcrTemplateItem;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PM2EmployeeIpcrControllerTest extends TestCase
{
    use RefreshDatabase;

    private function employeeWithPermissions(): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'PmV2Employee_'.uniqid()]);
        foreach (['ipcr.v2.view', 'ipcr.v2.create', 'ipcr.v2.update', 'ipcr.v2.submit'] as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['module' => 'PM V2', 'description' => $name]);
            $role->permissions()->attach($permission->id);
        }
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_store_creates_ipcr_and_generates_strategic_and_core_rows(): void
    {
        $sy = SchoolYear::create(['name' => '2026-2027', 'is_current' => true, 'start_date' => '2026-06-01', 'end_date' => '2027-03-31']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'is_current' => true]);
        $user = $this->employeeWithPermissions();
        $facultyLoad = FacultyLoad::create(['user_id' => $user->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id]);
        $subject = Subject::create(['school_year_id' => $sy->id, 'code' => 'MATH1-'.uniqid(), 'name' => 'Math 1', 'grade_level' => 7]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $user->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id, 'load_units' => 3,
        ]);

        $period = IpcrRatingPeriodV2::create(['label' => 'Jul-Dec 2026', 'year' => 2026, 'is_current' => true]);
        $template = OpcrTemplate::create(['ipcr_rating_period_v2_id' => $period->id, 'is_current' => true]);
        OpcrTemplateItem::create([
            'opcr_template_id' => $template->id, 'strategy_label' => 'Strategy 1',
            'output_outcome' => 'STEM', 'weight_percent' => 30,
        ]);

        $response = $this->actingAs($user)->post(route('pm2.employee-ipcr.store'), [
            'rating_period_id' => $period->id,
            'title'            => 'Jul-Dec 2026 IPCR',
        ]);

        $response->assertRedirect();
        $ipcr = \App\Models\PM2\EmployeeIpcrV2::where('user_id', $user->id)->firstOrFail();
        $this->assertCount(1, $ipcr->rows()->where('function_type', 'strategic')->get());
        $this->assertCount(1, $ipcr->rows()->where('function_type', 'core')->get());
    }
}
