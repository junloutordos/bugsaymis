<?php

namespace App\Models\FacultyLoading;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchAdvisoryMember extends Model
{
    protected $table = 'research_advisory_members';

    protected $fillable = [
        'research_advisory_id',
        'student_id',
        'student_name',
    ];

    protected $casts = [
        'student_id' => 'integer',
    ];

    public function researchAdvisory(): BelongsTo
    {
        return $this->belongsTo(ResearchAdvisory::class);
    }
}
