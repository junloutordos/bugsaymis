<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CsmResponse extends Model
{
    protected $table = 'csm_responses';

    protected $fillable = [
        'respondable_type',
        'respondable_id',
        'user_id',
        'client_type',
        'sex',
        'age',
        'region_of_residence',
        'date_of_transaction',
        'office_availed',
        'service_availed',
        'service_availed_other',
        'cc1',
        'cc2',
        'cc3',
        'sqd0', 'sqd1', 'sqd2', 'sqd3', 'sqd4',
        'sqd5', 'sqd6', 'sqd7', 'sqd8',
        'suggestions',
    ];

    protected $casts = [
        'service_availed'    => 'array',
        'date_of_transaction' => 'date:Y-m-d',
        'age'  => 'integer',
        'cc1'  => 'integer', 'cc2' => 'integer', 'cc3' => 'integer',
        'sqd0' => 'integer', 'sqd1' => 'integer', 'sqd2' => 'integer',
        'sqd3' => 'integer', 'sqd4' => 'integer', 'sqd5' => 'integer',
        'sqd6' => 'integer', 'sqd7' => 'integer', 'sqd8' => 'integer',
    ];

    public function respondable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Compute a 1–5 star rating from the SQD responses (non-N/A only).
     * Used to backfill the legacy `rating` column on IT Job Requests.
     */
    public function derivedRating(): ?float
    {
        $scores = [];
        for ($i = 0; $i <= 8; $i++) {
            $val = $this->{"sqd{$i}"};
            if ($val && $val < 6) { // exclude N/A (6)
                $scores[] = $val;
            }
        }
        if (empty($scores)) return null;

        // SQD uses 1–5 scale — same as star rating, just average it
        return round(array_sum($scores) / count($scores), 1);
    }
}
