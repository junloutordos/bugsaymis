<?php

namespace App\Http\Controllers\SPMS;

use App\Http\Controllers\Controller;
use App\Models\SPMS\MovChecklistItem;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MovChecklistController extends Controller
{
    public function show(MovChecklistItem $movChecklistItem): Response
    {
        $movChecklistItem->loadMissing('target.ipcr');
        $ipcr = $movChecklistItem->target->ipcr;

        $user = Auth::user();
        abort_unless(
            $ipcr->user_id === $user->id || $user->hasPermission('spms.ipcr.review') || $user->isSuperAdmin(),
            403
        );

        abort_unless($movChecklistItem->s3_key && Storage::disk('s3')->exists($movChecklistItem->s3_key), 404);

        $contents = Storage::disk('s3')->get($movChecklistItem->s3_key);
        $extension = strtolower(pathinfo($movChecklistItem->s3_key, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };

        return response($contents, 200)->header('Content-Type', $mime);
    }
}
