<?php

namespace App\Services\Atlas\Dyna\Tools;

class GetRequestsStatsTool extends ExecutiveDashboardAdapterTool
{
    public function name(): string { return 'get_requests_stats'; }
    public function description(): string { return 'Returns IT/Facility/Vehicle/Service/Work/Travel request stats: totals, this month, completion rate, and how many are open past 7 days.'; }
    protected function sectionKey(): string { return 'requests'; }
    protected function exposesDivisionFilter(): bool { return true; }
}
