<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

class AtlasGoDownloadController extends Controller
{
    /**
     * GET /downloads/atlasgo
     *
     * Public, unauthenticated download of the signed AtlasGo release APK —
     * direct-sideload distribution while the Play Store listing is pending
     * verification. S3 object is private; redirect to a short-lived
     * presigned URL rather than exposing a raw bucket URL, same pattern as
     * the Atlas Sentinel release download.
     */
    public function download()
    {
        $key = config('atlasgo.apk_s3_key');

        if (! Storage::disk('s3')->exists($key)) {
            abort(404);
        }

        return redirect()->away(
            Storage::disk('s3')->temporaryUrl($key, now()->addMinutes(10))
        );
    }
}
