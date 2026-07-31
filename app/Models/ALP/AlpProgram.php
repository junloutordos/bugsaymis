<?php

namespace App\Models\ALP;

use App\Models\FacultyLoading\Designation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlpProgram extends Model
{
    protected $table = 'alp_programs';

    protected $fillable = ['designation_id', 'code', 'name', 'nature', 'mission', 'objectives', 'logo_file_id', 'status', 'created_by'];

    protected $casts = ['objectives' => 'array'];

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cycles(): HasMany
    {
        return $this->hasMany(AlpProgramCycle::class);
    }
}
