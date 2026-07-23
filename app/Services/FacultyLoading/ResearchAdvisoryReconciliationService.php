<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\ResearchAdvisory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ResearchAdvisoryReconciliationService
{
    public function __construct(private readonly LoadComputationService $loads) {}

    /**
     * @param  array<int,array{title:string,grade:int,units:float|int,faculty:string}>  $target
     * @param  array<string,int>  $userIdOverrides
     * @return array{operations:array<int,array<string,mixed>>,counts:array<string,int>}
     */
    public function reconcile(
        AcademicTerm $term,
        array $target,
        bool $execute = false,
        ?int $createdBy = null,
        array $userIdOverrides = [],
    ): array {
        $resolvedTarget = $this->resolveTarget($target, $userIdOverrides);
        $this->validateUniqueGroups($resolvedTarget);

        $existing = ResearchAdvisory::with('faculty:id,name')
            ->where('academic_term_id', $term->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        $claimedIds = [];
        $operations = [];
        $recomputePairs = [];

        foreach ($resolvedTarget as $desired) {
            $row = $this->bestExistingMatch($existing, $claimedIds, $desired);

            $this->queuePair($recomputePairs, $desired['user_id'], $desired['grade']);

            if (! $row) {
                $operations[] = [
                    'action' => 'create',
                    'target' => $desired,
                ];

                continue;
            }

            $claimedIds[$row->id] = true;
            $changes = [];

            if ((int) $row->user_id !== $desired['user_id']) {
                $changes['user_id'] = $desired['user_id'];
                $this->queuePair($recomputePairs, (int) $row->user_id, (int) $row->grade_level);
            }
            if ($this->normalizeTitle($row->research_title) !== $this->normalizeTitle($desired['title'])) {
                $changes['research_title'] = $desired['title'];
            }
            if ((int) $row->grade_level !== $desired['grade']) {
                $changes['grade_level'] = $desired['grade'];
                $this->queuePair($recomputePairs, (int) $row->user_id, (int) $row->grade_level);
            }
            if (abs((float) $row->load_units - $desired['units']) > 0.001) {
                $changes['load_units'] = $desired['units'];
            }

            $operations[] = [
                'action' => $changes ? 'update' : 'keep',
                'id' => $row->id,
                'current' => [
                    'faculty' => $row->faculty?->name,
                    'title' => $row->research_title,
                    'grade' => (int) $row->grade_level,
                    'units' => (float) $row->load_units,
                ],
                'target' => $desired,
                'changes' => $changes,
            ];
        }

        foreach ($existing as $row) {
            if (isset($claimedIds[$row->id])) {
                continue;
            }

            $operations[] = [
                'action' => 'drop',
                'id' => $row->id,
                'current' => [
                    'faculty' => $row->faculty?->name,
                    'title' => $row->research_title,
                    'grade' => (int) $row->grade_level,
                    'units' => (float) $row->load_units,
                ],
            ];
            $this->queuePair($recomputePairs, (int) $row->user_id, (int) $row->grade_level);
        }

        if ($execute) {
            $this->assertLoadsAreUnlocked($term, $recomputePairs);

            DB::transaction(function () use ($term, $operations, $recomputePairs, $createdBy): void {
                $this->applyOperations($term, $operations);
                $this->recomputeLoads($term, $recomputePairs, $createdBy);
            });

            $this->assertTargetMatches($term, $resolvedTarget);
        }

        $counts = collect($operations)->countBy('action')->all();

        return [
            'operations' => $operations,
            'counts' => [
                'keep' => (int) ($counts['keep'] ?? 0),
                'update' => (int) ($counts['update'] ?? 0),
                'create' => (int) ($counts['create'] ?? 0),
                'drop' => (int) ($counts['drop'] ?? 0),
            ],
        ];
    }

    /** @param array<int,array<string,mixed>> $target */
    private function resolveTarget(array $target, array $userIdOverrides): array
    {
        $resolved = [];

        foreach ($target as $row) {
            $user = isset($userIdOverrides[$row['faculty']])
                ? User::find($userIdOverrides[$row['faculty']])
                : $this->resolveUser($row['faculty']);

            if (! $user || $user->status === 'inactive') {
                throw new RuntimeException("Unable to resolve active faculty account: {$row['faculty']}");
            }

            $resolved[] = [
                'title' => trim($row['title']),
                'grade' => (int) $row['grade'],
                'units' => (float) $row['units'],
                'faculty' => $user->name,
                'user_id' => (int) $user->id,
            ];
        }

        return $resolved;
    }

    private function resolveUser(string $rawName): ?User
    {
        $exact = User::where('status', '<>', 'inactive')->where('name', $rawName)->first();
        if ($exact) {
            return $exact;
        }

        [$lastName, $rest] = array_pad(explode(',', $rawName, 2), 2, '');
        $firstName = explode(' ', trim($rest))[0] ?? '';

        return User::where('status', '<>', 'inactive')
            ->where('name', 'like', '%'.trim($lastName).'%')
            ->when($firstName, fn ($query) => $query->where('name', 'like', "%{$firstName}%"))
            ->orderBy('id')
            ->first();
    }

    /** @param array<int,array<string,mixed>> $target */
    private function validateUniqueGroups(array $target): void
    {
        $seen = [];

        foreach ($target as $row) {
            foreach ($this->groupKeys($row['title'], $row['grade']) as $key) {
                if (isset($seen[$key])) {
                    throw new RuntimeException("Duplicate target research group {$key}: {$seen[$key]} and {$row['title']}");
                }
                $seen[$key] = $row['title'];
            }
        }
    }

    private function bestExistingMatch($existing, array $claimedIds, array $desired): ?ResearchAdvisory
    {
        $available = $existing->reject(fn (ResearchAdvisory $row) => isset($claimedIds[$row->id]));
        $title = $this->normalizeTitle($desired['title']);

        $exact = $available->filter(fn (ResearchAdvisory $row) => $this->normalizeTitle($row->research_title) === $title);

        if ($exact->isNotEmpty()) {
            return $exact->firstWhere('user_id', $desired['user_id']) ?? $exact->first();
        }

        $desiredGroups = $this->groupKeys($desired['title'], $desired['grade']);

        $candidate = $available
            ->filter(fn (ResearchAdvisory $row) => (int) $row->user_id === $desired['user_id']
                && (int) $row->grade_level === $desired['grade']
            )
            ->map(fn (ResearchAdvisory $row) => [
                'row' => $row,
                'overlap' => count(array_intersect(
                    $desiredGroups,
                    $this->groupKeys($row->research_title, (int) $row->grade_level),
                )),
            ])
            ->filter(fn (array $candidate) => $candidate['overlap'] > 0)
            ->sortByDesc('overlap')
            ->first();

        return $candidate['row'] ?? null;
    }

    private function applyOperations(AcademicTerm $term, array $operations): void
    {
        foreach ($operations as $operation) {
            if ($operation['action'] === 'keep') {
                continue;
            }

            if ($operation['action'] === 'drop') {
                ResearchAdvisory::whereKey($operation['id'])->update([
                    'status' => 'dropped',
                    'load_assignment_id' => null,
                ]);

                continue;
            }

            $target = $operation['target'];

            if ($operation['action'] === 'create') {
                ResearchAdvisory::create([
                    'user_id' => $target['user_id'],
                    'school_year_id' => $term->school_year_id,
                    'academic_term_id' => $term->id,
                    'research_title' => $target['title'],
                    'grade_level' => $target['grade'],
                    'advisory_role' => 'lead',
                    'research_type' => 'science_research',
                    'load_units' => $target['units'],
                    'status' => 'active',
                    'load_assignment_id' => null,
                ]);

                continue;
            }

            ResearchAdvisory::whereKey($operation['id'])->update([
                ...$operation['changes'],
                'status' => 'active',
                'load_assignment_id' => null,
            ]);
        }
    }

    private function assertLoadsAreUnlocked(AcademicTerm $term, array $pairs): void
    {
        $locked = FacultyLoad::with('faculty:id,name')
            ->where('academic_term_id', $term->id)
            ->whereIn('user_id', collect($pairs)->pluck('user_id')->unique()->all())
            ->where('is_locked', true)
            ->get();

        if ($locked->isNotEmpty()) {
            throw new RuntimeException('Research reconciliation blocked by locked faculty loads: '.$locked->pluck('faculty.name')->join(', '));
        }
    }

    private function recomputeLoads(AcademicTerm $term, array $pairs, ?int $createdBy): void
    {
        $loadsByUser = [];

        foreach ($pairs as $pair) {
            $load = $this->loads->findOrCreateFacultyLoad($pair['user_id'], $term->school_year_id, $term->id);
            $loadsByUser[$pair['user_id']] = $load;
            $assignment = $this->loads->recomputeResearchGrade(
                $pair['user_id'],
                $term->id,
                $pair['grade'],
                $load,
                $createdBy,
            );

            ResearchAdvisory::where('user_id', $pair['user_id'])
                ->where('academic_term_id', $term->id)
                ->where('grade_level', $pair['grade'])
                ->where('status', 'active')
                ->update(['load_assignment_id' => $assignment?->id]);
        }

        foreach ($loadsByUser as $load) {
            $this->loads->syncLoad($load->fresh());
        }
    }

    private function assertTargetMatches(AcademicTerm $term, array $target): void
    {
        $actual = ResearchAdvisory::where('academic_term_id', $term->id)
            ->where('status', 'active')
            ->get()
            ->map(fn (ResearchAdvisory $row) => $this->signature(
                $row->research_title,
                (int) $row->grade_level,
                (float) $row->load_units,
                (int) $row->user_id,
            ))
            ->sort()
            ->values();

        $expected = collect($target)
            ->map(fn (array $row) => $this->signature($row['title'], $row['grade'], $row['units'], $row['user_id']))
            ->sort()
            ->values();

        if ($actual->all() !== $expected->all()) {
            throw new RuntimeException('Post-reconciliation verification failed: active research advisories do not match the CID target.');
        }

        $unlinked = ResearchAdvisory::where('academic_term_id', $term->id)
            ->where('status', 'active')
            ->whereNull('load_assignment_id')
            ->count();

        if ($unlinked > 0) {
            throw new RuntimeException("Post-reconciliation verification failed: {$unlinked} active advisories are not linked to a load assignment.");
        }
    }

    private function signature(string $title, int $grade, float $units, int $userId): string
    {
        return implode('|', [$this->normalizeTitle($title), $grade, number_format($units, 2, '.', ''), $userId]);
    }

    private function normalizeTitle(?string $title): string
    {
        return preg_replace('/[^A-Z0-9]+/', '', strtoupper((string) $title));
    }

    /** @return array<int,string> */
    private function groupKeys(string $title, int $grade): array
    {
        if (! preg_match('/GROUPS?\s+(.+)$/i', $title, $match)) {
            return ["G{$grade}:".$this->normalizeTitle($title)];
        }

        preg_match_all('/\d+\s*-\s*\d+|\d+/', $match[1], $tokens);
        $groups = [];

        foreach ($tokens[0] as $token) {
            if (str_contains($token, '-')) {
                [$start, $end] = array_map('intval', preg_split('/\s*-\s*/', $token));
                foreach (range($start, $end) as $number) {
                    $groups[] = "G{$grade}:{$number}";
                }
            } else {
                $groups[] = "G{$grade}:".(int) $token;
            }
        }

        return array_values(array_unique($groups));
    }

    private function queuePair(array &$pairs, int $userId, int $grade): void
    {
        $pairs["{$userId}:{$grade}"] = ['user_id' => $userId, 'grade' => $grade];
    }
}
