<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\StudentProfileChangeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentProfileChangeRequestControllerTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'Administrator']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_guest_cannot_view_change_requests(): void
    {
        $this->get(route('students.change-requests.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_approve_a_pending_request(): void
    {
        $studentId = DB::table('students')->insertGetId(['lastname' => 'Reyes', 'firstname' => 'Ana', 'status' => 'active', 'contactno1' => '09170000000']);
        $changeRequest = StudentProfileChangeRequest::create(['student_id' => $studentId, 'requested_changes' => ['contactno1' => '09171234567'], 'status' => 'pending']);

        $this->actingAs($this->adminUser())
            ->post(route('students.change-requests.approve', $changeRequest))
            ->assertRedirect();

        $this->assertSame('approved', $changeRequest->fresh()->status);
        $this->assertSame('09171234567', DB::table('students')->where('id', $studentId)->value('contactno1'));
    }

    public function test_admin_reject_requires_notes(): void
    {
        $studentId = DB::table('students')->insertGetId(['lastname' => 'Reyes', 'firstname' => 'Ana', 'status' => 'active']);
        $changeRequest = StudentProfileChangeRequest::create(['student_id' => $studentId, 'requested_changes' => ['contactno1' => '09171234567'], 'status' => 'pending']);

        $this->actingAs($this->adminUser())
            ->post(route('students.change-requests.reject', $changeRequest), [])
            ->assertSessionHasErrors('review_notes');
    }
}
