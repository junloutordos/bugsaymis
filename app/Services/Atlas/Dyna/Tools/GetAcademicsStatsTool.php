<?php

namespace App\Services\Atlas\Dyna\Tools;

class GetAcademicsStatsTool extends ExecutiveDashboardAdapterTool
{
    public function name(): string { return 'get_academics_stats'; }
    public function description(): string { return 'Returns campus-wide academic snapshot: enrollment by grade level, faculty load status distribution, class record status, and today\'s gate scan volume.'; }
    protected function sectionKey(): string { return 'academics'; }
    protected function exposesDivisionFilter(): bool { return false; }
}
