<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassScheduleScopeLock;
use App\Models\FacultyLoading\Section;
use App\Models\User;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassScheduleScopeLockService
{
    public function assertTermHasNoLocks(int $termId): void
    {
        $lock = ClassScheduleScopeLock::with('lockedBy:id,name')
            ->where('academic_term_id', $termId)
            ->first();
        if ($lock) {
            throw ValidationException::withMessages([
                'schedule_lock' => 'Release all collaborative schedule locks before submitting the complete schedule.',
            ]);
        }
    }

    /**
     * Serialize a schedule write with lock/unlock operations for the same
     * term, then re-check every source and destination section immediately
     * before the mutation is committed.
     *
     * @param  array<int, array<int|null>>  $sectionsByTerm
     */
    public function runUnlocked(array $sectionsByTerm, Closure $callback): mixed
    {
        return DB::transaction(function () use ($sectionsByTerm, $callback) {
            ksort($sectionsByTerm);
            foreach ($sectionsByTerm as $termId => $sectionIds) {
                AcademicTerm::whereKey($termId)->lockForUpdate()->firstOrFail();
                $this->assertSectionsUnlocked((int) $termId, $sectionIds);
            }

            return $callback();
        });
    }

    public function create(AcademicTerm $term, string $scopeType, ?int $grade, ?Section $section, User $actor): ClassScheduleScopeLock
    {
        [$scopeKey, $grade, $sectionId] = $this->normalizeScope($scopeType, $grade, $section);

        return DB::transaction(function () use ($term, $scopeType, $scopeKey, $grade, $sectionId, $actor) {
            AcademicTerm::whereKey($term->id)->lockForUpdate()->firstOrFail();
            ClassScheduleScopeLock::where('academic_term_id', $term->id)->lockForUpdate()->get(['id']);
            $sectionIds = $this->sectionIdsForScope($term, $scopeType, $grade, $sectionId);
            $existing = $this->firstOverlappingLock((int) $term->id, $scopeType, $scopeKey, $sectionIds);

            if ($existing) {
                throw ValidationException::withMessages([
                    'schedule_lock' => $this->lockedMessage($existing),
                ]);
            }

            return ClassScheduleScopeLock::create([
                'academic_term_id' => $term->id,
                'scope_type' => $scopeType,
                'scope_key' => $scopeKey,
                'grade_level' => $grade,
                'section_id' => $sectionId,
                'locked_by' => $actor->id,
                'locked_at' => now(),
            ])->load('lockedBy:id,name');
        });
    }

    public function delete(ClassScheduleScopeLock $lock, User $actor): void
    {
        if ((int) $lock->locked_by !== (int) $actor->id && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'schedule_lock' => 'Only the user who locked this scope or an Administrator can unlock it.',
            ]);
        }

        $lock->delete();
    }

    public function assertSectionUnlocked(int $termId, ?int $sectionId): void
    {
        if (! $sectionId) {
            $this->assertNoWholeScheduleLock($termId);

            return;
        }

        $lock = $this->firstEffectiveLock($termId, [$sectionId]);
        if ($lock) {
            throw ValidationException::withMessages(['schedule_lock' => $this->lockedMessage($lock)]);
        }
    }

    public function assertSectionsUnlocked(int $termId, array|Collection $sectionIds): void
    {
        $ids = collect($sectionIds)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($ids === []) {
            $this->assertNoWholeScheduleLock($termId);

            return;
        }

        $lock = $this->firstEffectiveLock($termId, $ids);
        if ($lock) {
            throw ValidationException::withMessages(['schedule_lock' => $this->lockedMessage($lock)]);
        }
    }

    public function assertScopeUnlocked(AcademicTerm $term, string $scopeType, ?int $grade = null, ?int $sectionId = null): void
    {
        $this->assertSectionsUnlocked(
            (int) $term->id,
            $this->sectionIdsForScope($term, $scopeType, $grade, $sectionId),
        );
    }

    public function locksForSections(int $termId, Collection $sections): array
    {
        $locks = ClassScheduleScopeLock::with('lockedBy:id,name')
            ->where('academic_term_id', $termId)
            ->get();
        $whole = $locks->firstWhere('scope_type', 'whole');

        return $sections->mapWithKeys(function (Section $section) use ($locks, $whole) {
            $lock = $whole
                ?? $locks->first(fn ($item) => $item->scope_type === 'grade' && (int) $item->grade_level === (int) $section->levelid)
                ?? $locks->first(fn ($item) => $item->scope_type === 'section' && (int) $item->section_id === (int) $section->id);

            return [$section->id => $lock ? $this->present($lock) : null];
        })->all();
    }

    public function listForTerm(int $termId): array
    {
        return ClassScheduleScopeLock::with(['lockedBy:id,name', 'section:id,sectionname,levelid'])
            ->where('academic_term_id', $termId)
            ->orderByRaw("FIELD(scope_type, 'whole', 'grade', 'section')")
            ->orderBy('grade_level')
            ->get()
            ->map(fn (ClassScheduleScopeLock $lock) => $this->present($lock))
            ->all();
    }

    private function firstEffectiveLock(int $termId, array $sectionIds): ?ClassScheduleScopeLock
    {
        $sections = Section::whereIn('id', $sectionIds)->get(['id', 'levelid']);
        $keys = ['whole'];
        foreach ($sections as $section) {
            $keys[] = 'grade:'.(int) $section->levelid;
            $keys[] = 'section:'.(int) $section->id;
        }

        return ClassScheduleScopeLock::with('lockedBy:id,name')
            ->where('academic_term_id', $termId)
            ->whereIn('scope_key', array_values(array_unique($keys)))
            ->orderByRaw("FIELD(scope_type, 'whole', 'grade', 'section')")
            ->first();
    }

    private function assertNoWholeScheduleLock(int $termId): void
    {
        $lock = ClassScheduleScopeLock::with('lockedBy:id,name')
            ->where('academic_term_id', $termId)
            ->where('scope_key', 'whole')
            ->first();

        if ($lock) {
            throw ValidationException::withMessages(['schedule_lock' => $this->lockedMessage($lock)]);
        }
    }

    private function firstOverlappingLock(int $termId, string $scopeType, string $scopeKey, array $sectionIds): ?ClassScheduleScopeLock
    {
        $query = ClassScheduleScopeLock::with('lockedBy:id,name')->where('academic_term_id', $termId);

        if ($scopeType === 'whole') {
            return $query->first();
        }
        if ($scopeType === 'section') {
            return $this->firstEffectiveLock($termId, $sectionIds);
        }

        return $query->where(function ($nested) use ($scopeKey, $sectionIds) {
            $nested->whereIn('scope_key', ['whole', $scopeKey]);
            if ($sectionIds !== []) {
                $nested->orWhere(fn ($sectionLocks) => $sectionLocks
                    ->where('scope_type', 'section')
                    ->whereIn('section_id', $sectionIds));
            }
        })->orderByRaw("FIELD(scope_type, 'whole', 'grade', 'section')")->first();
    }

    private function sectionIdsForScope(AcademicTerm $term, string $scopeType, ?int $grade, ?int $sectionId): array
    {
        return Section::where('school_year_id', $term->school_year_id)
            ->where('is_active', true)
            ->when($scopeType === 'grade', fn ($query) => $query->where('levelid', $grade))
            ->when($scopeType === 'section', fn ($query) => $query->whereKey($sectionId))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function normalizeScope(string $scopeType, ?int $grade, ?Section $section): array
    {
        if ($scopeType === 'whole') {
            return ['whole', null, null];
        }
        if ($scopeType === 'grade' && $grade >= 7 && $grade <= 12) {
            return ["grade:{$grade}", $grade, null];
        }
        if ($scopeType === 'section' && $section) {
            return ['section:'.$section->id, null, (int) $section->id];
        }

        throw ValidationException::withMessages(['scope_type' => 'Select a valid section, grade level, or whole-schedule scope.']);
    }

    private function lockedMessage(ClassScheduleScopeLock $lock): string
    {
        $scope = match ($lock->scope_type) {
            'whole' => 'The whole schedule',
            'grade' => 'Grade '.$lock->grade_level,
            default => 'This section',
        };

        return $scope.' is locked by '.($lock->lockedBy?->name ?? 'another user').'. Unlock it before making changes.';
    }

    private function present(ClassScheduleScopeLock $lock): array
    {
        return [
            'id' => $lock->id,
            'scope_type' => $lock->scope_type,
            'scope_key' => $lock->scope_key,
            'grade_level' => $lock->grade_level,
            'section_id' => $lock->section_id,
            'section_name' => $lock->section?->sectionname,
            'section_grade' => $lock->section?->levelid,
            'locked_by' => $lock->locked_by,
            'locked_by_name' => $lock->lockedBy?->name,
            'locked_at' => $lock->locked_at?->toIso8601String(),
        ];
    }
}
