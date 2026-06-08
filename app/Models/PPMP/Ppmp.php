<?php

namespace App\Models\PPMP;

use App\Models\Division;
use App\Models\Office;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Ppmp extends Model
{
    protected $table = 'ppmp';

    protected $fillable = [
        'ppmp_number',
        'title',
        'division_id',
        'office_id',
        'prepared_by',
        'status',
        'fiscal_year',
        'remarks',
        'submitted_at',
        'approved_at',
        'approved_by',
        'consolidated_at',
        'is_supplemental',
        'parent_ppmp_id',
        'bac_reviewed_at',
        'bac_reviewed_by',
        'bac_remarks',
        'division_reviewed_at',
        'division_reviewed_by',
        'division_remarks',
        'property_officer_reviewed_at',
        'property_officer_reviewed_by',
        'property_officer_remarks',
        'budget_officer_reviewed_at',
        'budget_officer_reviewed_by',
        'budget_officer_remarks',
        'ppmp_type',
        'division_ppmp_id',
    ];

    protected $casts = [
        'submitted_at'        => 'datetime',
        'approved_at'         => 'datetime',
        'consolidated_at'     => 'datetime',
        'bac_reviewed_at'     => 'datetime',
        'division_reviewed_at'              => 'datetime',
        'property_officer_reviewed_at'      => 'datetime',
        'budget_officer_reviewed_at'        => 'datetime',
        'fiscal_year'         => 'integer',
        'is_supplemental'     => 'boolean',
    ];

    // ─── Status constants ─────────────────────────────────────────────────────

    public const STATUS_DRAFT                        = 'draft';
    public const STATUS_PENDING_DIVISION             = 'pending_division';
    public const STATUS_DIVISION_APPROVED            = 'division_approved';
    public const STATUS_PENDING_PROPERTY_OFFICER     = 'pending_property_officer';
    public const STATUS_PENDING_BUDGET_OFFICER       = 'pending_budget_officer';
    public const STATUS_PENDING_HEAD                 = 'pending_head';
    // Legacy — kept for existing records
    public const STATUS_PENDING_BAC                  = 'pending_bac';
    public const STATUS_SUBMITTED                    = 'submitted';
    public const STATUS_RETURNED                     = 'returned';
    public const STATUS_APPROVED                     = 'approved';
    public const STATUS_CONSOLIDATED                 = 'consolidated';

    public const PPMP_TYPE_UNIT     = 'unit';
    public const PPMP_TYPE_DIVISION = 'division';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING_DIVISION,
        self::STATUS_DIVISION_APPROVED,
        self::STATUS_PENDING_BAC,
        self::STATUS_SUBMITTED,
        self::STATUS_RETURNED,
        self::STATUS_APPROVED,
        self::STATUS_CONSOLIDATED,
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function items()
    {
        return $this->hasMany(PpmpItem::class, 'ppmp_id')
            ->orderByRaw("FIELD(category, 'goods', 'infrastructure', 'consulting_services')")
            ->orderBy('sort_order');
    }

    public function division()
    {
        return $this->belongsTo(Division::class);
    }

    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    public function preparer()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function bacReviewer()
    {
        return $this->belongsTo(User::class, 'bac_reviewed_by');
    }

    public function divisionReviewer()
    {
        return $this->belongsTo(User::class, 'division_reviewed_by');
    }

    public function propertyOfficerReviewer()
    {
        return $this->belongsTo(User::class, 'property_officer_reviewed_by');
    }

    public function budgetOfficerReviewer()
    {
        return $this->belongsTo(User::class, 'budget_officer_reviewed_by');
    }

    public function parentPpmp()
    {
        return $this->belongsTo(Ppmp::class, 'parent_ppmp_id');
    }

    public function supplementals()
    {
        return $this->hasMany(Ppmp::class, 'parent_ppmp_id');
    }

    public function divisionPpmp()
    {
        return $this->belongsTo(Ppmp::class, 'division_ppmp_id');
    }

    public function unitPpmps()
    {
        return $this->hasMany(Ppmp::class, 'division_ppmp_id');
    }

    public function statusHistory()
    {
        return $this->hasMany(PpmpStatusHistory::class, 'ppmp_id')->orderBy('created_at');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForDivision($query, int $divisionId)
    {
        return $query->where('division_id', $divisionId);
    }

    public function scopeForYear($query, int $fiscalYear)
    {
        return $query->where('fiscal_year', $fiscalYear);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    // ─── Status helpers ───────────────────────────────────────────────────────

    public function canEdit(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_RETURNED]);
    }

    public function canSubmit(): bool
    {
        if (!in_array($this->status, [self::STATUS_DRAFT, self::STATUS_RETURNED])) {
            return false;
        }

        if ($this->ppmp_type === self::PPMP_TYPE_DIVISION) {
            return true;
        }

        return $this->items()->exists();
    }

    public function canDivisionReview(): bool
    {
        return $this->status === self::STATUS_PENDING_DIVISION;
    }

    public function canBacReview(): bool
    {
        return $this->status === self::STATUS_PENDING_BAC;
    }

    public function canPropertyOfficerReview(): bool
    {
        return $this->status === self::STATUS_PENDING_PROPERTY_OFFICER;
    }

    public function canBudgetOfficerReview(): bool
    {
        return $this->status === self::STATUS_PENDING_BUDGET_OFFICER;
    }

    public function canApprove(): bool
    {
        // New flow: pending_head; legacy: submitted / pending_bac
        return in_array($this->status, [
            self::STATUS_PENDING_HEAD,
            self::STATUS_SUBMITTED,
            self::STATUS_PENDING_BAC,
        ]);
    }

    // ─── Workflow actions ─────────────────────────────────────────────────────

    public function submit(User $user): void
    {
        $from = $this->status;

        if ($this->ppmp_type === self::PPMP_TYPE_DIVISION) {
            $to = self::STATUS_PENDING_PROPERTY_OFFICER;
        } else {
            $to = self::STATUS_PENDING_DIVISION;
        }

        $this->status = $to;
        $this->submitted_at = now();
        $this->save();

        $this->logStatusChange($from, $to, $user);
    }

    public function divisionEndorse(User $user): void
    {
        $from = $this->status;
        $this->status = self::STATUS_DIVISION_APPROVED;
        $this->division_reviewed_at = now();
        $this->division_reviewed_by = $user->id;
        $this->save();

        $this->logStatusChange($from, self::STATUS_DIVISION_APPROVED, $user);
    }

    public function divisionReturn(User $user, string $remarks): void
    {
        $from = $this->status;
        $this->status = self::STATUS_RETURNED;
        $this->division_reviewed_at = now();
        $this->division_reviewed_by = $user->id;
        $this->division_remarks = $remarks;
        $this->save();

        $this->logStatusChange($from, self::STATUS_RETURNED, $user, $remarks);
    }

    public function propertyOfficerEndorse(User $user): void
    {
        $from = $this->status;
        $this->status                         = self::STATUS_PENDING_BUDGET_OFFICER;
        $this->property_officer_reviewed_at   = now();
        $this->property_officer_reviewed_by   = $user->id;
        $this->save();

        $this->logStatusChange($from, self::STATUS_PENDING_BUDGET_OFFICER, $user);
    }

    public function propertyOfficerReturn(User $user, string $remarks): void
    {
        $from = $this->status;
        $this->status                       = self::STATUS_RETURNED;
        $this->property_officer_reviewed_at = now();
        $this->property_officer_reviewed_by = $user->id;
        $this->property_officer_remarks     = $remarks;
        $this->save();

        $this->logStatusChange($from, self::STATUS_RETURNED, $user, $remarks);
    }

    public function budgetOfficerEndorse(User $user): void
    {
        $from = $this->status;
        $this->status                       = self::STATUS_PENDING_HEAD;
        $this->budget_officer_reviewed_at   = now();
        $this->budget_officer_reviewed_by   = $user->id;
        $this->save();

        $this->logStatusChange($from, self::STATUS_PENDING_HEAD, $user);
    }

    public function budgetOfficerReturn(User $user, string $remarks): void
    {
        $from = $this->status;
        $this->status                     = self::STATUS_RETURNED;
        $this->budget_officer_reviewed_at = now();
        $this->budget_officer_reviewed_by = $user->id;
        $this->budget_officer_remarks     = $remarks;
        $this->save();

        $this->logStatusChange($from, self::STATUS_RETURNED, $user, $remarks);
    }

    public function endorse(User $user): void
    {
        $from = $this->status;
        $this->status = self::STATUS_SUBMITTED;
        $this->bac_reviewed_at = now();
        $this->bac_reviewed_by = $user->id;
        $this->save();

        $this->logStatusChange($from, self::STATUS_SUBMITTED, $user);
    }

    public function bacReturn(User $user, string $remarks): void
    {
        $from = $this->status;
        $this->status = self::STATUS_RETURNED;
        $this->bac_remarks = $remarks;
        $this->save();

        $this->logStatusChange($from, self::STATUS_RETURNED, $user, $remarks);
    }

    public function approve(User $user): void
    {
        $from = $this->status;
        $this->status = self::STATUS_APPROVED;
        $this->approved_at = now();
        $this->approved_by = $user->id;
        $this->save();

        $this->logStatusChange($from, self::STATUS_APPROVED, $user);
    }

    public function returnForRevision(User $user, string $remarks): void
    {
        $from = $this->status;
        $this->status = self::STATUS_RETURNED;
        $this->remarks = $remarks;
        $this->save();

        $this->logStatusChange($from, self::STATUS_RETURNED, $user, $remarks);
    }

    public function consolidate(User $user): void
    {
        $from = $this->status;
        $this->status = self::STATUS_CONSOLIDATED;
        $this->consolidated_at = now();
        $this->save();

        $this->logStatusChange($from, self::STATUS_CONSOLIDATED, $user);
    }

    // ─── PPMP Number generation ───────────────────────────────────────────────

    public static function generateNumber(int $fiscalYear, Division $division, bool $isSupplemental = false, ?string $parentNumber = null): string
    {
        $acronym = $division->acronym ?? 'UNIT';

        if ($isSupplemental && $parentNumber) {
            $suppCount = static::where('fiscal_year', $fiscalYear)
                ->where('division_id', $division->id)
                ->where('is_supplemental', true)
                ->count();
            return "{$parentNumber}-S" . ($suppCount + 1);
        }

        $count = static::where('fiscal_year', $fiscalYear)
            ->where('division_id', $division->id)
            ->where('is_supplemental', false)
            ->count();
        $seq = str_pad($count + 1, 3, '0', STR_PAD_LEFT);

        return "PPMP-{$fiscalYear}-{$acronym}-{$seq}";
    }

    public static function generateDivisionNumber(int $fiscalYear, Division $division): string
    {
        $acronym = $division->acronym ?? 'DIV';
        $count = static::where('fiscal_year', $fiscalYear)
            ->where('division_id', $division->id)
            ->where('ppmp_type', self::PPMP_TYPE_DIVISION)
            ->count();
        $seq = str_pad($count + 1, 2, '0', STR_PAD_LEFT);

        return "PPMP-{$fiscalYear}-{$acronym}-DIV-{$seq}";
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function grandTotal(): float
    {
        return (float) $this->items()->sum('total_cost');
    }

    private function logStatusChange(string $from, string $to, User $user, ?string $remarks = null): void
    {
        PpmpStatusHistory::create([
            'ppmp_id'     => $this->id,
            'from_status' => $from,
            'to_status'   => $to,
            'acted_by'    => $user->id,
            'remarks'     => $remarks,
        ]);
    }
}
