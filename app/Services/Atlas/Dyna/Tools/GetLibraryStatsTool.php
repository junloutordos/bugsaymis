<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\Borrowing;
use App\Models\User;

class GetLibraryStatsTool implements DynaTool
{
    public function name(): string { return 'get_library_stats'; }

    public function description(): string
    {
        return 'Returns current library circulation stats: how many books are currently borrowed, how many are overdue, and how many distinct active borrowers there are.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function execute(User $user, array $input): array
    {
        $borrowed = Borrowing::where('status', 'Borrowed');

        return [
            'currently_borrowed' => (clone $borrowed)->count(),
            'overdue' => (clone $borrowed)->where('due_date', '<', now())->count(),
            // borrower is polymorphic (borrower_type + borrower_id) — a plain
            // distinct('borrower_id') would undercount by conflating IDs across types.
            'active_borrowers' => (clone $borrowed)->select('borrower_type', 'borrower_id')->distinct()->get()->count(),
        ];
    }
}
