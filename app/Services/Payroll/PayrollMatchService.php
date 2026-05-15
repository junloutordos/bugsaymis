<?php

namespace App\Services\Payroll;

use App\Models\User;
use App\Models\Payroll\PayrollNameAlias;
use Illuminate\Support\Collection;

class PayrollMatchService
{
    private Collection $users;
    private array      $aliasMap;
    private PayrollParserService $parser;

    public function __construct(PayrollParserService $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Match an array of raw parsed items against users.
     * Returns items with match_status + matched_user_id populated.
     */
    public function matchItems(array $items): array
    {
        $this->boot();

        return array_map(fn($item) => $this->matchOne($item), $items);
    }

    private function matchOne(array $item): array
    {
        $rawName = $item['employee_name_raw'] ?? '';
        $empNo   = trim($item['employee_no'] ?? '');

        // 1 — employee_no exact match
        if ($empNo !== '') {
            $user = $this->users->firstWhere('employee_no', $empNo);
            if ($user) {
                return $this->resolved($item, $user, 'matched');
            }
        }

        // 2 — normalized full-name exact match
        $normalized = $this->parser->normalizeName($rawName);
        $user = $this->users->first(fn($u) => $this->normUser($u) === $normalized);
        if ($user) {
            return $this->resolved($item, $user, 'matched');
        }

        // 3 — alias table
        if (isset($this->aliasMap[$normalized])) {
            $user = $this->users->find($this->aliasMap[$normalized]);
            if ($user) {
                return $this->resolved($item, $user, 'matched');
            }
        }

        // 4 — last name + first letter of first name (probable)
        [$lastName, $firstInitial] = $this->parseLastNameInitial($rawName);
        if ($lastName) {
            $candidates = $this->users->filter(function ($u) use ($lastName, $firstInitial) {
                [$uLast, $uInit] = $this->parseLastNameInitialFromUser($u);
                return $uLast === $lastName && $uInit === $firstInitial;
            });

            if ($candidates->count() === 1) {
                return $this->resolved($item, $candidates->first(), 'probable');
            }
        }

        return array_merge($item, [
            'match_status'    => 'unmatched',
            'matched_user_id' => null,
        ]);
    }

    private function resolved(array $item, User $user, string $status): array
    {
        return array_merge($item, [
            'match_status'    => $status,
            'matched_user_id' => $user->id,
            'position'        => $item['position'] ?: ($user->position ?? ''),
            'employee_no'     => $item['employee_no'] ?: ($user->employee_no ?? ''),
            'office_division' => $user->division?->division_name ?? $user->office ?? '',
        ]);
    }

    private function parseLastNameInitial(string $raw): array
    {
        $normalized = $this->parser->normalizeName($raw);
        // Format: "LASTNAME, Firstname M." → after normalize: "LASTNAME, FIRSTNAME M"
        if (!str_contains($normalized, ',')) return ['', ''];
        [$last, $rest] = explode(',', $normalized, 2);
        $rest  = trim($rest);
        $first = explode(' ', $rest)[0] ?? '';
        return [trim($last), substr($first, 0, 1)];
    }

    private function parseLastNameInitialFromUser(User $user): array
    {
        // Users store name as "Firstname M. Lastname" — normalize and try to extract
        $normalized = $this->parser->normalizeName($user->name ?? '');
        $parts      = explode(' ', $normalized);
        $last       = end($parts);
        $first      = $parts[0] ?? '';
        return [$last, substr($first, 0, 1)];
    }

    private function normUser(User $user): string
    {
        // Try to reconstruct "LASTNAME, FIRSTNAME M" from user.name for comparison
        // Users may be stored as "First Last" or "Last, First" — normalize both ways
        $normalized = $this->parser->normalizeName($user->name ?? '');
        return $normalized;
    }

    private function boot(): void
    {
        if (!isset($this->users)) {
            $this->users = User::with('division')
                ->where('status', '<>', 'inactive')
                ->get(['id', 'name', 'employee_no', 'position', 'division_id', 'office']);
        }

        if (!isset($this->aliasMap)) {
            $this->aliasMap = PayrollNameAlias::pluck('user_id', 'alias_name')->all();
        }
    }

    /**
     * Save a manual match as an alias for future uploads.
     */
    public function saveAlias(string $rawName, int $userId, int $createdBy): void
    {
        $normalized = $this->parser->normalizeName($rawName);
        PayrollNameAlias::updateOrCreate(
            ['alias_name' => $normalized],
            ['user_id' => $userId, 'created_by' => $createdBy]
        );
    }
}
