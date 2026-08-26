<?php

namespace App\Services\HR;

use App\Models\EmployeeIdSequence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Generates employee ID-card numbers in the format E13-YYYY-MM-XXX.
 *
 *  - E13   fixed prefix (Employee, 13th Campus)
 *  - YYYY  year hired at PSHS-CRC (as provided by the employee)
 *  - MM    month hired, zero-padded
 *  - XXX   sequence number, scoped per hire-year, zero-padded to 3 digits
 *
 * The sequence advances by a random step (1-7) instead of a strict +1 so
 * that consecutive numbers issued in the same year are not trivially
 * adjacent to guess from a single printed card. This is a minor obscurity
 * measure only — the actual security boundary is that employee_idno_new is
 * never used as a lookup/verification key (see id_verification_token and
 * the employee.verify route).
 */
class EmployeeIdNumberService
{
    private const PREFIX = 'E13';

    private const MIN_STEP = 1;

    private const MAX_STEP = 7;

    private const SEQUENCE_DIGITS = 3;

    /**
     * Generate and persist a new employee ID number for the given user,
     * based on the supplied hire year/month. Also persists hired_year and
     * hired_month on the user record.
     *
     * Transaction-safe: locks the per-year sequence row so two concurrent
     * generations (e.g. two employees logging in at once) cannot produce
     * the same number.
     */
    public function generateFor(User $user, int $hiredYear, int $hiredMonth): string
    {
        $this->validateYear($hiredYear);
        $this->validateMonth($hiredMonth);

        return DB::transaction(function () use ($user, $hiredYear, $hiredMonth) {
            $sequence = EmployeeIdSequence::where('hired_year', $hiredYear)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                // firstOrCreate isn't lock-safe against a concurrent insert of the
                // same year; rely on the unique constraint + retry on collision.
                try {
                    $sequence = EmployeeIdSequence::create([
                        'hired_year'    => $hiredYear,
                        'last_sequence' => 0,
                    ]);
                    $sequence = EmployeeIdSequence::where('hired_year', $hiredYear)
                        ->lockForUpdate()
                        ->first();
                } catch (\Illuminate\Database\QueryException) {
                    $sequence = EmployeeIdSequence::where('hired_year', $hiredYear)
                        ->lockForUpdate()
                        ->first();
                }
            }

            $step = random_int(self::MIN_STEP, self::MAX_STEP);
            $next = $sequence->last_sequence + $step;

            $sequence->update(['last_sequence' => $next]);

            $idNumber = $this->format($hiredYear, $hiredMonth, $next);

            $user->forceFill([
                'employee_idno_new' => $idNumber,
                'hired_year'        => $hiredYear,
                'hired_month'       => $hiredMonth,
            ])->save();

            return $idNumber;
        });
    }

    private function format(int $hiredYear, int $hiredMonth, int $sequence): string
    {
        $mm  = str_pad((string) $hiredMonth, 2, '0', STR_PAD_LEFT);
        $xxx = str_pad((string) $sequence, self::SEQUENCE_DIGITS, '0', STR_PAD_LEFT);

        return sprintf('%s-%d-%s-%s', self::PREFIX, $hiredYear, $mm, $xxx);
    }

    private function validateYear(int $year): void
    {
        $min = 1980;
        $max = (int) now()->format('Y');

        if ($year < $min || $year > $max) {
            throw new InvalidArgumentException("Hire year must be between {$min} and {$max}.");
        }
    }

    private function validateMonth(int $month): void
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException('Hire month must be between 1 and 12.');
        }
    }
}
