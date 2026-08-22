<?php

namespace Tests\Feature\Notices;

use App\Models\StudentAttendance\ParentContact;
use App\Models\StudentCredential;
use App\Models\User;
use App\Services\NoticeAudienceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NoticeAudienceResolverTest extends TestCase
{
    use RefreshDatabase;

    private function seedOneOfEach(): array
    {
        $employee = User::factory()->create(['account_type' => 'employee', 'status' => 'active']);

        $studentId = DB::table('students')->insertGetId([
            'pisaysystemID' => 'TEST-RES-1', 'firstname' => 'Res', 'lastname' => 'Olver',
        ]);
        StudentCredential::create([
            'student_id' => $studentId, 'email' => 'res-student@example.com',
            'password' => bcrypt('x'), 'status' => 'active', 'email_verified_at' => now(),
        ]);

        $parent = ParentContact::create([
            'name' => 'Res Parent', 'email' => 'res-parent@example.com',
            'password' => bcrypt('x'), 'status' => 'active',
        ]);

        return [$employee, $studentId, $parent];
    }

    public function test_employees_audience_returns_only_employees(): void
    {
        [$employee] = $this->seedOneOfEach();

        $result = app(NoticeAudienceResolver::class)->resolve('employees');

        $this->assertTrue($result['users']->contains('id', $employee->id));
        $this->assertCount(0, $result['students']);
        $this->assertCount(0, $result['parents']);
    }

    public function test_students_audience_only_includes_students_with_an_atlasgo_credential(): void
    {
        [, $studentIdWithApp] = $this->seedOneOfEach();
        $studentIdWithoutApp = DB::table('students')->insertGetId([
            'pisaysystemID' => 'TEST-RES-2', 'firstname' => 'No', 'lastname' => 'App',
        ]);

        $result = app(NoticeAudienceResolver::class)->resolve('students');

        $this->assertTrue($result['students']->contains('id', $studentIdWithApp));
        $this->assertFalse($result['students']->contains('id', $studentIdWithoutApp));
    }

    public function test_parents_audience_returns_only_parents(): void
    {
        [, , $parent] = $this->seedOneOfEach();

        $result = app(NoticeAudienceResolver::class)->resolve('parents');

        $this->assertTrue($result['parents']->contains('id', $parent->id));
        $this->assertCount(0, $result['users']);
        $this->assertCount(0, $result['students']);
    }

    public function test_all_audience_returns_the_union_of_all_three_recipient_types(): void
    {
        [$employee, $studentId, $parent] = $this->seedOneOfEach();

        $result = app(NoticeAudienceResolver::class)->resolve('all');

        $this->assertTrue($result['users']->contains('id', $employee->id));
        $this->assertTrue($result['students']->contains('id', $studentId));
        $this->assertTrue($result['parents']->contains('id', $parent->id));
    }
}
