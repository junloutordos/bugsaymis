<?php

namespace Tests\Feature\StudentAttendance;

use App\Models\StudentAttendance\ParentContact;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Regression coverage for the 2026-08-26 App Store rejection: ParentContact
// was split out as its own Sanctum-authenticated model (2f1d8959) but never
// implemented Authenticatable, so every mobile request from a logged-in
// parent crashed inside OpenTelemetry::collectUserContext($request->user()),
// which is strictly typed to the Authenticatable contract. See
// IctEquipmentDevice for the same fix applied earlier for device tokens.
class MobileParentAuthenticatableTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_contact_implements_authenticatable(): void
    {
        $this->assertInstanceOf(Authenticatable::class, new ParentContact);
    }

    public function test_authenticated_parent_can_list_students_without_crashing(): void
    {
        config(['opentelemetry.user_context' => true]);

        $parent = ParentContact::create([
            'name'   => 'Regression Parent',
            'email'  => 'regression-parent@example.com',
            'status' => 'active',
        ]);

        Sanctum::actingAs($parent, ['*']);

        $this->getJson(route('mobile.students.index'))->assertOk();
    }
}
