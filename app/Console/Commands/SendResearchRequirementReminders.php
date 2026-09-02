<?php

namespace App\Console\Commands;

use App\Mail\ResearchRequirementMail;
use App\Models\FacultyLoading\ResearchAdvisory;
use App\Models\FacultyLoading\ResearchRequirementAssignment;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendResearchRequirementReminders extends Command
{
    protected $signature = 'research:send-requirement-reminders';

    protected $description = 'Send deadline reminders and overdue notices for pending/returned research requirement assignments';

    public function handle(): int
    {
        $reminded = $this->sendReminders();
        $overdue  = $this->sendOverdue();

        $this->info("Reminders sent: {$reminded}. Overdue notices sent: {$overdue}.");

        return self::SUCCESS;
    }

    private function sendReminders(): int
    {
        $assignments = ResearchRequirementAssignment::visible()
            ->whereIn('status', ['pending', 'returned'])
            ->whereNull('reminder_sent_at')
            ->whereHas('requirement', fn ($q) => $q->where('status', 'active')->whereBetween('due_at', [now(), now()->addDays(3)]))
            ->with(['requirement', 'researchGroup'])
            ->get();

        foreach ($assignments as $assignment) {
            $this->notifyGroup(
                $assignment,
                'Reminder: Research Requirement Due Soon',
                "\"{$assignment->requirement->title}\" is due on {$assignment->requirement->due_at->format('F j, Y g:i A')}.",
            );
            $assignment->update(['reminder_sent_at' => now()]);
        }

        return $assignments->count();
    }

    private function sendOverdue(): int
    {
        $assignments = ResearchRequirementAssignment::visible()
            ->whereIn('status', ['pending', 'returned'])
            ->whereNull('overdue_notified_at')
            ->whereHas('requirement', fn ($q) => $q->where('status', 'active')->where('due_at', '<', now()))
            ->with(['requirement.createdBy', 'researchGroup'])
            ->get();

        foreach ($assignments as $assignment) {
            $this->notifyGroup(
                $assignment,
                'Overdue: Research Requirement',
                "\"{$assignment->requirement->title}\" was due on {$assignment->requirement->due_at->format('F j, Y g:i A')} and has not been submitted.",
            );

            if ($coordinator = $assignment->requirement->createdBy) {
                $this->notifyUser(
                    $coordinator,
                    'Overdue: Research Requirement',
                    "\"{$assignment->researchGroup->title}\" has not submitted \"{$assignment->requirement->title}\" (was due {$assignment->requirement->due_at->format('F j, Y')}).",
                );
            }

            $assignment->update(['overdue_notified_at' => now()]);
        }

        return $assignments->count();
    }

    private function notifyGroup(ResearchRequirementAssignment $assignment, string $subject, string $lead): void
    {
        $advisers = ResearchAdvisory::where('research_group_id', $assignment->research_group_id)
            ->where('status', '<>', 'dropped')
            ->with('faculty')
            ->get()
            ->pluck('faculty')
            ->filter();

        foreach ($advisers as $user) {
            $this->notifyUser($user, $subject, $lead);
        }
    }

    private function notifyUser(User $user, string $subject, string $lead): void
    {
        try {
            NotificationService::notifyUser($user, 'Research Requirement', $subject, $lead, route('faculty-loading.my-research-requirements.index'));

            if ($user->email) {
                Mail::to($user->email)->send(new ResearchRequirementMail(
                    recipientName: $user->name,
                    headerTitle: $subject,
                    lead: $lead,
                    actionUrl: route('faculty-loading.my-research-requirements.index'),
                    actionLabel: 'View',
                ));
            }
        } catch (\Throwable $e) {
            logger()->warning('SendResearchRequirementReminders: notify failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }
}
