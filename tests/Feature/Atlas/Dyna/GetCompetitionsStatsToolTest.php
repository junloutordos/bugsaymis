<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\CID\Competition;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetCompetitionsStatsTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetCompetitionsStatsToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_competition_counts_by_level(): void
    {
        // created_by is a required FK (no default) — confirmed via
        // database/migrations/*_create_competitions_table.php.
        $creator = User::factory()->create();

        Competition::create(['title' => 'Math Olympiad', 'level' => 'regional', 'date_from' => '2026-07-01', 'created_by' => $creator->id]);
        Competition::create(['title' => 'Science Fair', 'level' => 'regional', 'date_from' => '2026-07-15', 'created_by' => $creator->id]);
        Competition::create(['title' => 'Robotics Cup', 'level' => 'national', 'date_from' => '2026-06-01', 'created_by' => $creator->id]);

        $user = User::factory()->create();

        $result = (new GetCompetitionsStatsTool())->execute($user, []);

        $this->assertEquals(['regional' => 2, 'national' => 1], $result);
    }
}
