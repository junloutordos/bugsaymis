<?php

namespace App\Jobs;

use App\Models\Administration\Announcement;
use App\Services\NoticeAudienceResolver;
use App\Services\NotificationService;
use App\Services\StudentAttendance\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class NotifyAnnouncementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 600;

    // Single attempt — a retry would double-notify every targeted recipient.
    public int $tries = 1;

    public function __construct(public int $announcementId)
    {
        $this->onQueue('bulk');
    }

    public function handle(NoticeAudienceResolver $resolver, FcmService $fcm): void
    {
        $announcement = Announcement::find($this->announcementId);

        if (! $announcement || ! $announcement->isPublished()) {
            logger()->error('NotifyAnnouncementJob: announcement missing or unpublished', [
                'announcement_id' => $this->announcementId,
            ]);
            return;
        }

        if ($announcement->audience === 'specific') {
            $users = $announcement->targets()->where('status', '<>', 'inactive')->get();
            $students = collect();
            $parents = collect();
        } else {
            $resolved = $resolver->resolve($announcement->audience);
            $users = $resolved['users'];
            $students = $resolved['students'];
            $parents = $resolved['parents'];
        }

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

        $pushData = ['type' => 'announcement', 'announcement_id' => (string) $announcement->id];

        foreach ($students as $student) {
            $token = $student->credential?->fcm_device_token;
            if (! $token) {
                continue;
            }
            try {
                $fcm->send($token, 'New Announcement', $announcement->title, $pushData);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                logger()->warning('NotifyAnnouncementJob: student push failed', [
                    'announcement_id' => $announcement->id, 'student_id' => $student->id, 'error' => $e->getMessage(),
                ]);
            }
        }

        foreach ($parents as $parent) {
            if (! $parent->wantsPushNotification()) {
                continue;
            }
            try {
                $fcm->send($parent->fcm_device_token, 'New Announcement', $announcement->title, $pushData);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                logger()->warning('NotifyAnnouncementJob: parent push failed', [
                    'announcement_id' => $announcement->id, 'parent_contact_id' => $parent->id, 'error' => $e->getMessage(),
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
