<?php

namespace App\Jobs;

use App\Models\Administration\Announcement;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class NotifyAnnouncementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 600;

    // Single attempt — a retry would double-notify every targeted user.
    public int $tries = 1;

    // Primitive ID, not the model (see ProcessIssuanceRelease).
    public function __construct(public int $announcementId) {}

    public function handle(): void
    {
        $announcement = Announcement::find($this->announcementId);

        if (! $announcement || ! $announcement->isPublished()) {
            logger()->error('NotifyAnnouncementJob: announcement missing or unpublished', [
                'announcement_id' => $this->announcementId,
            ]);
            return;
        }

        $users = $announcement->audience === 'all'
            ? User::employees()->where('status', '<>', 'inactive')->get()
            : $announcement->targets()->where('status', '<>', 'inactive')->get();

        $sent = 0;
        $failed = 0;

        foreach ($users as $user) {
            try {
                NotificationService::notifyUser(
                    $user,
                    'Announcement',
                    '#' . $announcement->id,
                    $announcement->title,
                    route('announcements.index'),
                );
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                logger()->warning('NotifyAnnouncementJob: notify failed', [
                    'announcement_id' => $announcement->id,
                    'user_id'         => $user->id,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        logger()->info('NotifyAnnouncementJob: complete', [
            'announcement_id' => $announcement->id,
            'audience'        => $announcement->audience,
            'sent'            => $sent,
            'failed'          => $failed,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('NotifyAnnouncementJob: job FAILED', [
            'announcement_id' => $this->announcementId,
            'error'           => $e->getMessage(),
        ]);
    }
}
