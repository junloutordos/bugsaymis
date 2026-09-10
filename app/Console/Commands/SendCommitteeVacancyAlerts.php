<?php

namespace App\Console\Commands;

use App\Models\Committee;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyCommitteeAssignment;
use App\Models\User;
use App\Services\PerformanceManagement\CommitteeNotificationService;
use Illuminate\Console\Command;

/**
 * Notifies admins (Administrator/DivisionChief/OCD/HR) when an active,
 * non-revoked committee's active-member count for the current term is
 * below its configured max_members — a vacancy that would otherwise only
 * surface if someone happened to open that committee's page.
 */
class SendCommitteeVacancyAlerts extends Command
{
    protected $signature = 'committees:vacancy-alerts';
    protected $description = 'Notify admins of committees below their max_members capacity for the current term';

    public function handle(CommitteeNotificationService $notifications): int
    {
        if (! config('committees.vacancy_alert_enabled', true)) {
            $this->line('Vacancy alerts disabled via config — skipping.');
            return self::SUCCESS;
        }

        $term = AcademicTerm::where('is_current', true)->first();
        if (! $term) {
            $this->warn('No academic term marked current — skipping.');
            return self::SUCCESS;
        }

        $admins = User::whereHas('roles', fn ($q) => $q->whereIn('roles.name', ['Administrator', 'DivisionChief', 'OCD', 'HR']))
            ->where('status', '<>', 'inactive')
            ->get();

        if ($admins->isEmpty()) {
            $this->warn('No admin recipients found — skipping.');
            return self::SUCCESS;
        }

        $vacant = Committee::notRevoked()
            ->whereNotNull('max_members')
            ->whereNull('parent_committee_id')
            ->get()
            ->filter(function (Committee $committee) use ($term) {
                $activeCount = FacultyCommitteeAssignment::where('committee_id', $committee->id)
                    ->where('academic_term_id', $term->id)
                    ->where('status', 'active')
                    ->count();

                return $activeCount < $committee->max_members;
            });

        foreach ($vacant as $committee) {
            $activeCount = FacultyCommitteeAssignment::where('committee_id', $committee->id)
                ->where('academic_term_id', $term->id)
                ->where('status', 'active')
                ->count();

            foreach ($admins as $admin) {
                $notifications->vacancyAlert($admin, $committee, $activeCount, $committee->max_members);
            }

            $this->line("Vacancy alert sent: {$committee->name} ({$activeCount}/{$committee->max_members})");
        }

        if ($vacant->isEmpty()) {
            $this->line('No committees below capacity — nothing to send.');
        }

        return self::SUCCESS;
    }
}
