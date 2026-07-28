<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\PersonNameFormatter;
use App\Services\SnapshotService;
use Tests\TestCase;

class SnapshotServiceTest extends TestCase
{
    public function test_resolve_user_name_snapshot_is_unchanged_when_no_titles_set(): void
    {
        $user = new User(['name' => 'Cruz, Juan', 'position' => 'Teacher I']);
        $user->setRelation('division', null);
        $user->setRelation('office', null);

        $service = new SnapshotService(new PersonNameFormatter);
        $data = $service->resolveUser($user);

        $this->assertSame('Cruz, Juan', $data['name_snapshot']);
    }

    public function test_resolve_user_includes_nominal_titles_in_name_snapshot(): void
    {
        $user = new User([
            'name' => 'Cruz, Juan',
            'prenominal_title' => 'Dr.',
            'postnominal_title' => 'Ph.D.',
        ]);
        $user->setRelation('division', null);
        $user->setRelation('office', null);

        $service = new SnapshotService(new PersonNameFormatter);
        $data = $service->resolveUser($user);

        $this->assertSame('Dr. Cruz, Juan, Ph.D.', $data['name_snapshot']);
    }
}
