<?php

namespace App\Services\Atlas\Dyna\Tools;

class GetFinanceStatsTool extends ExecutiveDashboardAdapterTool
{
    public function name(): string { return 'get_finance_stats'; }
    public function description(): string { return 'Returns campus-wide finance snapshot: latest payroll run summary, 6-month net-pay trend, and purchase request / disbursement voucher status counts.'; }
    protected function sectionKey(): string { return 'finance'; }
    protected function exposesDivisionFilter(): bool { return false; }
}
