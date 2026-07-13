<?php

namespace App\Http\Controllers\AMS;

use App\Http\Controllers\Controller;
use App\Models\AMS\Activity;
use App\Models\AMS\ActivityCoProponent;
use App\Services\AMS\ActivityFileService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Private-S3 proxy for AMS Activity attachments, mirroring the WFH photo
 * proxy pattern. Also serves legacy Storage::disk('public') files that
 * predate the S3 migration.
 */
class ActivityFileController extends Controller
{
    private const ALLOWED_FIELDS = ['banner', 'special_order', 'activity_report', 'official_documentation'];

    public function __construct(private ActivityFileService $fileService) {}

    public function show(Activity $activity, string $field)
    {
        if (! in_array($field, self::ALLOWED_FIELDS, true)) {
            abort(404);
        }

        $this->authorizeView($activity);

        $stored = $activity->$field;
        if (! $stored) abort(404);

        if (str_starts_with($stored, 's3.')) {
            $s3Key = $this->fileService->decodeS3Key($stored);
            if (! $s3Key || ! Storage::disk('s3')->exists($s3Key)) abort(404);

            $contents = Storage::disk('s3')->get($s3Key);
            if (! $contents) abort(404);

            $ext  = strtolower(pathinfo($s3Key, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'png'   => 'image/png',
                'pdf'   => 'application/pdf',
                default => 'image/jpeg',
            };

            return response($contents, 200)
                ->header('Content-Type', $mime)
                ->header('Cache-Control', 'private, max-age=3600');
        }

        // Legacy pre-refactor upload on Storage::disk('public')
        if (! Storage::disk('public')->exists($stored)) abort(404);

        return redirect(Storage::disk('public')->url($stored));
    }

    private function authorizeView(Activity $activity): void
    {
        $user = Auth::user();
        if ($user->isSuperAdmin() || $user->hasAnyPermission(['activities.view_all', 'activities.monitor', 'activities.evaluation_committee'])) return;

        $isOwner = $activity->user_id === $user->id;
        $isCo    = ActivityCoProponent::where('activity_id', $activity->id)->where('employee_id', $user->id)->exists();
        if (! $isOwner && ! $isCo) abort(403);
    }
}
