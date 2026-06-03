<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $renames = [
        'egcu_extracurricular1' => 'egcu_club_memberships',
        'egcu_extracurricular2' => 'egcu_activity_participations',
        'egcu_extracurricular3' => 'egcu_awards',
        'egcu_extracurricular4' => 'egcu_external_activities',
        'egcu_social'           => 'egcu_friend_age_patterns',
        'egcu_social1'          => 'egcu_friend_group_patterns',
        'egcu_general_makeup'   => 'egcu_personality_traits',
        'egcu_selstatements'    => 'egcu_self_statements',
        'egcu_educbackground'   => 'egcu_academic_preferences',
        'egcu_educbackground1'  => 'egcu_academic_standing',
    ];

    public function up(): void
    {
        foreach ($this->renames as $from => $to) {
            if (Schema::hasTable($from) && ! Schema::hasTable($to)) {
                DB::statement("RENAME TABLE `{$from}` TO `{$to}`");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->renames as $from => $to) {
            if (Schema::hasTable($to) && ! Schema::hasTable($from)) {
                DB::statement("RENAME TABLE `{$to}` TO `{$from}`");
            }
        }
    }
};
