<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $table = 'schedule';
    public $timestamps = true;

    protected $fillable = [
        'badgeNumber',
        'm_timein','m_breakout','m_breakin','m_timeout',
        't_timein','t_breakout','t_breakin','t_timeout',
        'w_timein','w_breakout','w_breakin','w_timeout',
        'th_timein','th_breakout','th_breakin','th_timeout',
        'f_timein','f_breakout','f_breakin','f_timeout',
        'sat_timein','sat_breakout','sat_breakin','sat_timeout',
        'sun_timein','sun_breakout','sun_breakin','sun_timeout',
    ];
}
