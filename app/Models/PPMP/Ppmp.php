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
    ];

    protected $casts = [
        'submitted_at'    => 'datetime',
        'approved_at'     => 'datetime',
        'consolidated_at' => 'datetime',
        'bac_reviewed_at' => 'datetime',
        'fiscal_year'     => 'integer',
        'is_supplemental' => 'boolean',
    ];

    // ─── Status constants ─────────────────────────────────────────────────────

    public const STATUS_DRAFT        = 'draft';
    public const STATUS_PENDING_BAC  = 'pending_bac';
    public const STATUS_SUBMITTED    = 'submitted';
    public const STATUS_RETURNED     = 'returned';
    public const STATUS_APPROVED     = 'approved';
    public const STATUS_CONSOLIDATED = 'consolidated';

    public const STATUSES = [
        self::STATUS_DRAFT,
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

    public function parentPpmp()
    {
        return $this->belongsTo(Ppmp::class, 'parent_ppmp_id');
    }

    public function supplementals()
    {
        return $this->hasMany(Ppmp::class, 'parent_ppmp_id');
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
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_RETURNED])
            && $this->items()->exists();
    }

    public function canBacReview(): bool
    {
        return $this->status === self::STATUS_PENDING_BAC;
    }

    public function canApprove(): bool
    {
        // Approver can act on both legacy 'submitted' and BAC-endorsed 'submitted'
        // also allow directly approving 'pending_bac' (BAC step skipped by approver)
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_PENDING_BAC]);
    }

    // ─── Workflow actions ─────────────────────────────────────────────────────

    public function submit(User $user): void
    {
        $from = $this->status;
        $this->status = self::STATUS_PENDING_BAC;
        $this->submitted_at = now();
        $this->save();

        $this->logStatusChange($from, self::STATUS_PENDING_BAC, $user);
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
