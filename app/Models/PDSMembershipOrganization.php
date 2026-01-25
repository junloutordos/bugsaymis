<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PDSMembershipOrganization extends Model
{
    protected $table = 'pds_membership_organizations'; // <-- your actual table name
    
    protected $fillable = [
        'pds_id', 'organization_name'
    ];

    public function pds()
    {
        return $this->belongsTo(PDS::class);
    }
}
