<?php

namespace App\Services\Atlas\Dyna\Tools;

class GetSatisfactionStatsTool extends ExecutiveDashboardAdapterTool
{
    public function name(): string { return 'get_satisfaction_stats'; }
    public function description(): string { return 'Returns campus-wide CSM (client satisfaction) survey results: per-dimension averages, overall adjectival rating, and top offices by response volume.'; }
    protected function sectionKey(): string { return 'satisfaction'; }
    protected function exposesDivisionFilter(): bool { return false; }
}
