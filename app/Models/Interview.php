<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Interview extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'interview_date',
        'panel_members',
        'venue',
        'format',
        'rating',
        'remarks',
        'status',
    ];

    protected $casts = [
        'interview_date' => 'datetime',
        'panel_members'  => 'array',
        'rating'         => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Resolve the panel_members IDs to User models.
     */
    public function panelUsers()
    {
        return User::whereIn('id', $this->panel_members ?? [])->get();
    }
}
