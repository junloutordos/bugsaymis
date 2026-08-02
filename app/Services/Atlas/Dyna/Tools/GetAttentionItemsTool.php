<?php

namespace App\Services\Atlas\Dyna\Tools;

class GetAttentionItemsTool extends ExecutiveDashboardAdapterTool
{
    public function name(): string { return 'get_attention_items'; }
    public function description(): string { return 'Returns a flagged list of items needing action: overdue routings, stuck committee tasks, open error reports, requests open past 7 days, leave pending past 5 days, and employees missing an IPCR for the current period.'; }
    protected function sectionKey(): string { return 'attention'; }
    protected function exposesDivisionFilter(): bool { return true; }
}
