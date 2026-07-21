<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Jobs\NotifyAnnouncementJob;
use App\Models\Administration\Announcement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(): Response
    {
        $user      = Auth::user();
        $canManage = $user->isSuperAdmin() || $user->hasPermission('announcements.manage');

        $query = Announcement::with(['createdBy:id,name', 'targets:id,name'])
            ->orderByDesc(DB::raw('COALESCE(published_at, created_at)'));

        if (! $canManage) {
            $query->visibleTo($user);
        }

        $announcements = $query->get()->map(fn ($a) => [
            'id'           => $a->id,
            'title'        => $a->title,
            'body'         => $a->body,
            'poster_path'  => $a->poster_path,
            'audience'     => $a->audience,
            'status'       => $a->status,
            'published_at' => $a->published_at?->toIso8601String(),
            'created_at'   => $a->created_at?->toIso8601String(),
            'created_by'   => $a->createdBy?->name,
            'target_ids'   => $a->audience === 'specific' ? $a->targets->pluck('id')->all() : [],
            'target_count' => $a->audience === 'specific' ? $a->targets->count() : null,
        ]);

        return Inertia::render('Administration/Announcements/Index', [
            'announcements' => $announcements,
            'employees'     => $canManage
                ? User::employees()->where('status', '<>', 'inactive')->orderBy('name')->get(['id', 'name', 'position'])
                : [],
            'canManage'     => $canManage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateAnnouncement($request);

        DB::transaction(function () use ($data) {
            $announcement = Announcement::create([
                'title'      => $data['title'],
                'body'       => $data['body'],
                'audience'   => $data['audience'],
                'created_by' => Auth::id(),
            ]);

            if ($data['audience'] === 'specific') {
                $announcement->targets()->sync($data['targets']);
            }

            $this->storePoster($announcement, $data);
        });

        return back()->with('success', 'Announcement created as draft. Publish it when ready.');
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $data = $this->validateAnnouncement($request);

        DB::transaction(function () use ($announcement, $data) {
            $announcement->update([
                'title'    => $data['title'],
                'body'     => $data['body'],
                'audience' => $data['audience'],
            ]);

            $announcement->targets()->sync($data['audience'] === 'specific' ? $data['targets'] : []);

            $this->storePoster($announcement, $data);
        });

        return back()->with('success', 'Announcement updated.');
    }

    public function publish(Announcement $announcement): RedirectResponse
    {
        if ($announcement->isPublished()) {
            return back()->withErrors(['status' => 'This announcement is already published.']);
        }

        $announcement->update(['status' => 'published', 'published_at' => now()]);

        NotifyAnnouncementJob::dispatch($announcement->id);

        return back()->with('success', 'Announcement published. Recipients are being notified.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        if ($announcement->poster_path) {
            try {
                Storage::disk('s3')->delete($announcement->poster_path);
            } catch (\Throwable $e) {
                logger()->warning('Announcement poster delete failed', ['id' => $announcement->id, 'error' => $e->getMessage()]);
            }
        }

        $announcement->delete(); // pivot cascades

        return back()->with('success', 'Announcement deleted.');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function validateAnnouncement(Request $request): array
    {
        return $request->validate([
            'title'         => 'required|string|max:255',
            'body'          => 'required|string|max:10000',
            'audience'      => 'required|in:all,specific',
            'targets'       => 'required_if:audience,specific|array',
            'targets.*'     => 'integer|exists:users,id',
            'poster_base64' => 'nullable|string',
            'poster_mime'   => 'nullable|string|in:image/jpeg,image/png,image/webp',
        ], [
            'targets.required_if' => 'Select at least one recipient for a specific-audience announcement.',
        ]);
    }

    /** Decode base64 poster (Cloudflare-safe JSON upload) and store in S3. */
    private function storePoster(Announcement $announcement, array $data): void
    {
        if (empty($data['poster_base64'])) {
            return;
        }

        $raw = base64_decode(preg_replace('/^data:[^;]+;base64,/', '', $data['poster_base64']), true);
        if ($raw === false) {
            return;
        }

        $ext  = match ($data['poster_mime'] ?? 'image/jpeg') {
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => 'jpg',
        };
        $path = "announcements/{$announcement->id}_" . time() . ".{$ext}";

        Storage::disk('s3')->put($path, $raw);

        if ($announcement->poster_path && $announcement->poster_path !== $path) {
            try {
                Storage::disk('s3')->delete($announcement->poster_path);
            } catch (\Throwable) {
            }
        }

        $announcement->update(['poster_path' => $path]);
    }
}
