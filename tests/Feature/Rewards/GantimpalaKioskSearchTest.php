<?php

namespace Tests\Feature\Rewards;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the fix to a Medium-severity information
 * disclosure: the public, unauthenticated kiosk search endpoint used to
 * return email and match on any substring (including email), which let an
 * anonymous caller bulk-enumerate the employee directory.
 */
class GantimpalaKioskSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_never_returns_email(): void
    {
        User::factory()->create(['name' => 'Ana Reyes', 'email' => 'ana.reyes@example.test', 'status' => 'active']);

        $response = $this->getJson(route('kiosk.gantimpala.employees.search', ['q' => 'Ana']));

        $response->assertOk();
        $response->assertJsonStructure(['employees' => [['id', 'name']]]);
        $this->assertStringNotContainsString('example.test', $response->getContent());
        $this->assertArrayNotHasKey('email', $response->json('employees.0'));
    }

    public function test_search_matches_a_name_prefix(): void
    {
        User::factory()->create(['name' => 'Ana Reyes', 'status' => 'active']);

        $response = $this->getJson(route('kiosk.gantimpala.employees.search', ['q' => 'Ana']));

        $response->assertJsonFragment(['name' => 'Ana Reyes']);
    }

    public function test_search_does_not_match_a_mid_string_substring(): void
    {
        // "Reyes" would have matched the old "%term%" search — must not anymore.
        User::factory()->create(['name' => 'Ana Reyes', 'status' => 'active']);

        $response = $this->getJson(route('kiosk.gantimpala.employees.search', ['q' => 'Reyes']));

        $response->assertJson(['employees' => []]);
    }

    public function test_search_no_longer_matches_by_email(): void
    {
        User::factory()->create(['name' => 'Ana Reyes', 'email' => 'findme@example.test', 'status' => 'active']);

        $response = $this->getJson(route('kiosk.gantimpala.employees.search', ['q' => 'findme']));

        $response->assertJson(['employees' => []]);
    }

    public function test_search_requires_at_least_three_characters(): void
    {
        User::factory()->create(['name' => 'An Reyes', 'status' => 'active']);

        $response = $this->getJson(route('kiosk.gantimpala.employees.search', ['q' => 'An']));

        $response->assertJson(['employees' => []]);
    }
}
