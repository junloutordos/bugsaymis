<?php

namespace App\Services\Atlas\Dyna\Tools;

class GetOperationsStatsTool extends ExecutiveDashboardAdapterTool
{
    public function name(): string { return 'get_operations_stats'; }
    public function description(): string { return 'Returns operations stats: open/overdue document routings, issuances this month, open error reports, and committee task progress.'; }
    protected function sectionKey(): string { return 'operations'; }
    protected function exposesDivisionFilter(): bool { return true; }
}
