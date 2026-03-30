<?php

namespace App\Services\Recruitment;

use App\Mail\ApplicationStatusMail;
use App\Models\Application;
use App\Models\Applicant;
use App\Models\JobVacancy;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ApplicationWorkflowService
{
    /**
     * Valid forward transitions per stage.
     * Rejection and withdrawal are always available from any active stage.
     */
    private const TRANSITIONS = [
        'submitted'  => ['screening'],
        'screening'  => ['exam', 'interview', 'ranking'],  // exam/interview optional by type
        'exam'       => ['interview', 'ranking'],
        'interview'  => ['ranking'],
        'ranking'    => ['selection'],
        'selection'  => ['placement'],
        'placement'  => [],
    ];

    private const TERMINAL = ['rejected', 'withdrawn'];

    /**
     * Submit a new application.
     */
    public function apply(Applicant $applicant, JobVacancy $vacancy, array $extra = []): Application
    {
        return DB::transaction(function () use ($applicant, $vacancy, $extra) {
            // Enforce one application per vacancy per applicant
            if (Application::where('applicant_id', $applicant->id)
                           ->where('job_vacancy_id', $vacancy->id)
                           ->exists()) {
                throw new \RuntimeException('Applicant has already applied for this vacancy.');
            }

            if ($vacancy->status !== 'open') {
                throw new \RuntimeException('This vacancy is no longer accepting applications.');
            }

            $application = Application::create([
                'applicant_id'       => $applicant->id,
                'job_vacancy_id'     => $vacancy->id,
                'recruitment_type_id'=> $vacancy->jobItem->recruitment_type_id,
                'application_date'   => now()->toDateString(),
                'current_stage'      => 'submitted',
                'is_internal'        => $extra['is_internal'] ?? false,
                'remarks'            => $extra['remarks'] ?? null,
            ]);

            AuditLogger::logModelEvent($application, 'created');

            return $application->load(['applicant', 'jobVacancy.jobItem', 'recruitmentType']);
        });
    }

    /**
     * Advance an application to the next stage.
     */
    public function advance(Application $application, string $toStage, ?string $remarks = null): Application
    {
        $this->guardTerminal($application);

        $allowed = self::TRANSITIONS[$application->current_stage] ?? [];

        if (! in_array($toStage, $allowed)) {
            throw new \InvalidArgumentException(
                "Cannot transition from '{$application->current_stage}' to '{$toStage}'."
            );
        }

        // Type-driven guard: skip stages the type doesn't require
        $type = $application->recruitmentType;
        if ($toStage === 'exam' && ! $type->has_exam) {
            throw new \InvalidArgumentException('This recruitment type does not have an exam stage.');
        }
        if ($toStage === 'interview' && ! $type->has_interview) {
            throw new \InvalidArgumentException('This recruitment type does not have an interview stage.');
        }

        return DB::transaction(function () use ($application, $toStage, $remarks) {
            $application->update([
                'current_stage' => $toStage,
                'remarks'       => $remarks ?? $application->remarks,
            ]);

            AuditLogger::logModelEvent($application, 'updated');

            $fresh = $application->fresh(['applicant', 'jobVacancy.jobItem', 'recruitmentType']);

            if ($fresh->applicant?->email) {
                Mail::to($fresh->applicant->email)
                    ->queue(new ApplicationStatusMail($fresh, $toStage, $remarks));
            }

            return $fresh;
        });
    }

    /**
     * Reject an application at any active stage.
     */
    public function reject(Application $application, string $reason): Application
    {
        $this->guardTerminal($application);

        return DB::transaction(function () use ($application, $reason) {
            $application->update([
                'current_stage' => 'rejected',
                'remarks'       => $reason,
            ]);

            AuditLogger::logModelEvent($application, 'updated');

            $fresh = $application->fresh(['applicant', 'jobVacancy.jobItem']);

            if ($fresh->applicant?->email) {
                Mail::to($fresh->applicant->email)
                    ->queue(new ApplicationStatusMail($fresh, 'rejected', $reason));
            }

            return $fresh;
        });
    }

    /**
     * Mark application as withdrawn (applicant-initiated).
     */
    public function withdraw(Application $application, string $reason = ''): Application
    {
        $this->guardTerminal($application);

        return DB::transaction(function () use ($application, $reason) {
            $application->update([
                'current_stage' => 'withdrawn',
                'remarks'       => $reason,
            ]);

            AuditLogger::logModelEvent($application, 'updated');

            $fresh = $application->fresh(['applicant', 'jobVacancy.jobItem']);

            if ($fresh->applicant?->email) {
                Mail::to($fresh->applicant->email)
                    ->queue(new ApplicationStatusMail($fresh, 'withdrawn', $reason));
            }

            return $fresh;
        });
    }

    /**
     * Get valid next stages for an application (respects type config).
     */
    public function availableTransitions(Application $application): array
    {
        if (in_array($application->current_stage, self::TERMINAL)) {
            return [];
        }

        $type = $application->recruitmentType;
        $stages = self::TRANSITIONS[$application->current_stage] ?? [];

        return array_values(array_filter($stages, function ($stage) use ($type) {
            if ($stage === 'exam'      && ! $type->has_exam)      return false;
            if ($stage === 'interview' && ! $type->has_interview)  return false;
            return true;
        }));
    }

    private function guardTerminal(Application $application): void
    {
        if (in_array($application->current_stage, self::TERMINAL)) {
            throw new \RuntimeException(
                "Application is already in a terminal stage: {$application->current_stage}."
            );
        }
    }
}
