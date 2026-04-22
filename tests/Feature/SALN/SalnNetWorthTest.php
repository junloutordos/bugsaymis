<?php

namespace Tests\Feature\SALN;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SALN\SalnRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for net worth auto-computation (recomputeTotals).
 */
class SalnNetWorthTest extends TestCase
{
    use RefreshDatabase;

    protected function employeeWithSaln(): array
    {
        $role = Role::create(['name' => 'TestRole_' . uniqid()]);

        foreach (['saln.create', 'saln.submit'] as $name) {
            $perm = Permission::firstOrCreate(['name' => $name], ['module' => 'SALN', 'description' => $name]);
            $role->permissions()->attach($perm->id);
        }

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role->id);

        $saln = SalnRecord::create([
            'user_id'    => $user->id,
            'year'       => 2024,
            'as_of_date' => '2024-12-31',
            'status'     => 'draft',
        ]);

        return [$user, $saln];
    }

    public function test_net_worth_is_zero_on_empty_saln(): void
    {
        [, $saln] = $this->employeeWithSaln();

        $saln->recomputeTotals();
        $saln->refresh();

        $this->assertEquals(0, $saln->total_assets);
        $this->assertEquals(0, $saln->total_liabilities);
        $this->assertEquals(0, $saln->net_worth);
    }

    public function test_net_worth_equals_assets_minus_liabilities(): void
    {
        [$user, $saln] = $this->employeeWithSaln();

        // Add real property: ₱2,000,000
        $this->actingAs($user)->post(route('saln.real-properties.store', $saln), [
            'kind'                      => 'House and Lot',
            'exact_location'            => 'Butuan City',
            'current_fair_market_value' => 2000000,
            'acquisition_mode'          => 'purchased',
            'owner'                     => 'self',
        ]);

        // Add personal property: ₱300,000
        $this->actingAs($user)->post(route('saln.personal-properties.store', $saln), [
            'description'     => 'Toyota Vios',
            'category'        => 'motor_vehicle',
            'acquisition_cost'=> 300000,
            'owner'           => 'self',
        ]);

        // Add liability: ₱500,000
        $this->actingAs($user)->post(route('saln.liabilities.store', $saln), [
            'nature'              => 'housing_loan',
            'creditor_name'       => 'Land Bank',
            'outstanding_balance' => 500000,
        ]);

        $saln->refresh();

        $this->assertEquals(2000000, $saln->total_real_properties);
        $this->assertEquals(300000,  $saln->total_personal_properties);
        $this->assertEquals(2300000, $saln->total_assets);
        $this->assertEquals(500000,  $saln->total_liabilities);
        $this->assertEquals(1800000, $saln->net_worth); // 2,300,000 - 500,000
    }

    public function test_deleting_a_property_reduces_totals(): void
    {
        [$user, $saln] = $this->employeeWithSaln();

        $this->actingAs($user)->post(route('saln.real-properties.store', $saln), [
            'kind'                      => 'Lot',
            'exact_location'            => 'Cagayan de Oro',
            'current_fair_market_value' => 1000000,
            'acquisition_mode'          => 'inherited',
            'owner'                     => 'self',
        ]);

        $saln->refresh();
        $this->assertEquals(1000000, $saln->total_real_properties);

        $propertyId = $saln->realProperties()->first()->id;

        $this->actingAs($user)->delete(
            route('saln.real-properties.destroy', [$saln->id, $propertyId])
        );

        $saln->refresh();
        $this->assertEquals(0, $saln->total_real_properties);
        $this->assertEquals(0, $saln->total_assets);
        $this->assertEquals(0, $saln->net_worth);
    }

    public function test_net_worth_can_be_negative(): void
    {
        [$user, $saln] = $this->employeeWithSaln();

        // Assets: ₱100,000 personal property
        $this->actingAs($user)->post(route('saln.personal-properties.store', $saln), [
            'description'      => 'Laptop',
            'category'         => 'others',
            'acquisition_cost' => 100000,
            'owner'            => 'self',
        ]);

        // Liabilities: ₱500,000
        $this->actingAs($user)->post(route('saln.liabilities.store', $saln), [
            'nature'              => 'personal_loan',
            'creditor_name'       => 'BPI',
            'outstanding_balance' => 500000,
        ]);

        $saln->refresh();

        $this->assertEquals(100000,  $saln->total_assets);
        $this->assertEquals(500000,  $saln->total_liabilities);
        $this->assertEquals(-400000, $saln->net_worth);
    }

    public function test_saln_record_model_status_helpers(): void
    {
        $saln = new SalnRecord(['status' => 'draft']);
        $this->assertTrue($saln->isDraft());
        $this->assertTrue($saln->isEditable());
        $this->assertTrue($saln->isSubmittable());

        $saln->status = 'submitted';
        $this->assertTrue($saln->isSubmitted());
        $this->assertFalse($saln->isEditable());

        $saln->status = 'under_review';
        $this->assertTrue($saln->isUnderReview());
        $this->assertFalse($saln->isEditable());

        $saln->status = 'approved';
        $this->assertTrue($saln->isApproved());
        $this->assertFalse($saln->isEditable());

        $saln->status = 'returned';
        $this->assertTrue($saln->isReturned());
        $this->assertTrue($saln->isEditable()); // returned → editable again

        $saln->status = 'filed';
        $this->assertTrue($saln->isFiled());
        $this->assertFalse($saln->isEditable());
    }
}
