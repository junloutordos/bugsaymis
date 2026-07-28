<?php

namespace App\Models\HomeroomAttendance;

use Illuminate\Database\Eloquent\Model;

class AttendanceSetting extends Model
{
    protected $table = 'homeroom_attendance_settings';

    protected $fillable = [
        'setting_key',
        'value',
        'updated_by',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public const DEDUCTION_POINTS_KEY = 'deduction_points';

    /** CIM 7.0 defaults — used whenever no row exists for the key. */
    public const DEDUCTION_POINTS_DEFAULT = [
        'tardy'   => 0.2,
        'cutting' => 0.5,
        'absence' => 1.0,
    ];

    /** Current deduction points, falling back to the CIM 7.0 defaults. */
    public static function deductionPoints(): array
    {
        $setting = static::where('setting_key', self::DEDUCTION_POINTS_KEY)->first();

        return $setting ? array_merge(self::DEDUCTION_POINTS_DEFAULT, $setting->value) : self::DEDUCTION_POINTS_DEFAULT;
    }
}
