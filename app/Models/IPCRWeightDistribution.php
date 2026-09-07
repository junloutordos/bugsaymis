<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IPCRWeightDistribution extends Model
{
    // Eloquent's naming convention would otherwise mangle this to
    // `i_p_c_r_weight_distributions` (splits on every capital in "IPCR"),
    // not the real `ipcr_weight_distributions` table from the migration.
    protected $table = 'ipcr_weight_distributions';

    protected $fillable = ['division_id','strategic','core','support'];

    public function division()
    {
        return $this->belongsTo(Division::class);
    }
}
