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
        'ocd_reviewed_at',
        'ocd_reviewed_by',
        'ocd_remarks',
        'submitted_to_dbm_at',
        'submitted_to_dbm_by',
        'ppmp_type',
        'division_ppmp_id',
        'property_ppmp_id',
    ];

    protected $casts = [
        'submitted_at'                  => 'datetime',
        'approved_at'                   => 'datetime',
        'consolidated_at'               => 'datetime',
        'bac_reviewed_at'               => 'datetime',
        'division_reviewed_at'          => 'datetime',
        'property_officer_reviewed_at'  => 'datetime',
        'budget_officer_reviewed_at'    => 'datetime',
        'ocd_reviewed_at'               => 'datetime',
        'submitted_to_dbm_at'           => 'datetime',
        'fiscal_year'                   => 'integer',
        'is_supplemental'               => 'boolean',
    ];

    // ─── Status constants ─────────────────────────────────────────────────────

    // Unit PPMP flow: draft → pending_division → division_approved
    public const STATUS_DRAFT                       = 'draft';
    public const STATUS_PENDING_DIVISION            = 'pending_division';
    public const STATUS_DIVISION_APPROVED           = 'division_approved';

    // Division PPMP flow: draft → pending_property_officer → property_officer_approved
    public const STATUS_PENDING_PROPERTY_OFFICER    = 'pending_property_officer';
    public const STATUS_PROPERTY_OFFICER_APPROVED   = 'property_officer_approved';

    // Property PPMP flow: draft → pending_budget_officer → pending_ocd → approved → submitted_to_dbm
    public const STATUS_PENDING_BUDGET_OFFICER      = 'pending_budget_officer';
    public const STATUS_PENDING_OCD                 = 'pending_ocd';
    public const STATUS_APPROVED                    = 'approved';
    public const STATUS_SUBMITTED_TO_DBM            = 'submitted_to_dbm';

    // Cross-flow
    public const STATUS_RETURNED                    = 'returned';
    public const STATUS_CONSOLIDATED                = 'consolidated';

    // Legacy — kept for existing records
    public const STATUS_PENDING_HEAD                = 'pending_head';
    public const STATUS_PENDING_BAC                 = 'pending_bac';
    public const STATUS_SUBMITTED                   = 'submitted';

    // PPMP types
    public const PPMP_TYPE_UNIT     = 'unit';
    public const PPMP_TYPE_DIVISION = 'division';
    public const PPMP_TYPE_PROPERTY = 'property';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING_DIVISION,
        self::STATUS_DIVISION_APPROVED,
        self::STATUS_PENDING_PROPERTY_OFFICER,
        self::STATUS_PROPERTY_OFFICER_APPROVED,
        self::STATUS_PENDING_BUDGET_OFFICER,
        self::STATUS_PENDING_OCD,
        self::STATUS_APPROVED,
        self::STATUS_SUBMITTED_TO_DBM,
        self::STATUS_RETURNED,
        self::STATUS_CONSOLIDATED,
        self::STATUS_PENDING_HEAD,
        self::STATUS_PENDING_BAC,
        self::STATUS_SUBMITTED,
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

    public function ocdReviewer()
    {
        return $this->belongsTo(User::class, 'ocd_reviewed_by');
    }

    public function dbmSubmitter()
    {
        return $this->belongsTo(User::class, 'submitted_to_dbm_by');
    }

    public function parentPpmp()
    {
        return $this->belongsTo(Ppmp::class, 'parent_ppmp_id');
    }

    public function supplementals()
    {
        return $this->hasMany(Ppmp::class, 'parent_ppmp_id');
    }

    // Unit → Division PPMP
    public function divisionPpmp()
    {
        return $this->belongsTo(Ppmp::class, 'division_ppmp_id');
    }

    public function unitPpmps()
    {
        return $this->hasMany(Ppmp::class, 'division_ppmp_id');
    }

    // Division → Property PPMP
    public function propertyPpmp()
    {
        return $this->belongsTo(Ppmp::class, 'property_ppmp_id');
    }

    public function divisionPpmps()
    {
        return $this->hasMany(Ppmp::class, 'property_ppmp_id');
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

        // Division and Property PPMPs can submit even without items (consolidated)
        if (in_array($this->ppmp_type, [self::PPMP_TYPE_DIVISION, self::PPMP_TYPE_PROPERTY])) {
            return true;
        }

        return $this->items()->exists();
    }

    public function canDivisionReview(): bool
    {
        return $this->status === self::STATUS_PENDING_DIVISION
            && $this->ppmp_type === self::PPMP_TYPE_UNIT;
    }

    public function canBacReview(): bool
    {
        return $this->status === self::STATUS_PENDING_BAC;
    }

    public function canPropertyOfficerReview(): bool
    {
        return $this->status === self::STATUS_PENDING_PROPERTY_OFFICER
            && $this->ppmp_type === self::PPMP_TYPE_DIVISION;
    }

    public function canBudgetOfficerReview(): bool
    {
        return $this->status === self::STATUS_PENDING_BUDGET_OFFICER
            && $this->ppmp_type === self::PPMP_TYPE_PROPERTY;
    }

    public function canOcdApprove(): bool
    {
        return $this->status === self::STATUS_PENDING_OCD
            && $this->ppmp_type === self::PPMP_TYPE_PROPERTY;
    }

    public function canSubmitToDbm(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && $this->ppmp_type === self::PPMP_TYPE_PROPERTY;
    }

    public function canApprove(): bool
    {
        // Legacy flow
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

        $to = match ($this->ppmp_type) {
            self::PPMP_TYPE_DIVISION => self::STATUS_PENDING_PROPERTY_OFFICER,
            self::PPMP_TYPE_PROPERTY => self::STATUS_PENDING_BUDGET_OFFICER,
            default                  => self::STATUS_PENDING_DIVISION,
        };

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
        $this->status                       = self::STATUS_PROPERTY_OFFICER_APPROVED;
        $this->property_officer_reviewed_at = now();
        $this->property_officer_reviewed_by = $user->id;
        $this->save();

        $this->logStatusChange($from, self::STATUS_PROPERTY_OFFICER_APPROVED, $user);
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
        $this->status                     = self::STATUS_PENDING_OCD;
        $this->budget_officer_reviewed_at = now();
        $this->budget_officer_reviewed_by = $user->id;
        $this->save();

        $this->logStatusChange($from, self::STATUS_PENDING_OCD, $user);
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

    public function ocdApprove(User $user): void
    {
        $from = $this->status;
        $this->status         = self::STATUS_APPROVED;
        $this->approved_at    = now();
        $this->approved_by    = $user->id;
        $this->ocd_reviewed_at = now();
        $this->ocd_reviewed_by = $user->id;
        $this->save();

        $this->logStatusChange($from, self::STATUS_APPROVED, $user);
    }

    public function ocdReturn(User $user, string $remarks): void
    {
        $from = $this->status;
        $this->status          = self::STATUS_RETURNED;
        $this->ocd_reviewed_at = now();
        $this->ocd_reviewed_by = $user->id;
        $this->ocd_remarks     = $remarks;
        $this->save();

        $this->logStatusChange($from, self::STATUS_RETURNED, $user, $remarks);
    }

    public function submitToDbm(User $user): void
    {
        $from = $this->status;
        $this->status              = self::STATUS_SUBMITTED_TO_DBM;
        $this->submitted_to_dbm_at = now();
        $this->submitted_to_dbm_by = $user->id;
        $this->save();

        $this->logStatusChange($from, self::STATUS_SUBMITTED_TO_DBM, $user);
    }

    // Legacy methods
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

    public static function generatePropertyNumber(int $fiscalYear): string
    {
        $count = static::where('fiscal_year', $fiscalYear)
            ->where('ppmp_type', self::PPMP_TYPE_PROPERTY)
            ->count();
        $seq = str_pad($count + 1, 2, '0', STR_PAD_LEFT);

        return "PPMP-{$fiscalYear}-PROP-{$seq}";
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
