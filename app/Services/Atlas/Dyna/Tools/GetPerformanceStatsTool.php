<?php

namespace App\Services\Atlas\Dyna\Tools;

class GetPerformanceStatsTool extends ExecutiveDashboardAdapterTool
{
    public function name(): string { return 'get_performance_stats'; }
    public function description(): string { return 'Returns IPCR performance data: submission funnel, compliance rate by division, and rating distribution for the current rating period.'; }
    protected function sectionKey(): string { return 'performance'; }
    protected function exposesDivisionFilter(): bool { return true; }
}
