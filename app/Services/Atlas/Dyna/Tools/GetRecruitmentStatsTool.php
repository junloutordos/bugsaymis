<?php

namespace App\Services\Atlas\Dyna\Tools;

class GetRecruitmentStatsTool extends ExecutiveDashboardAdapterTool
{
    public function name(): string { return 'get_recruitment_stats'; }
    public function description(): string { return 'Returns campus-wide recruitment stats: open vacancies, applicant pipeline by stage, applications this month, and pending placements.'; }
    protected function sectionKey(): string { return 'recruitment'; }
    protected function exposesDivisionFilter(): bool { return false; }
}
