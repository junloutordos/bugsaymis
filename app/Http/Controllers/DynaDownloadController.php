<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

class DynaDownloadController extends Controller
{
    private const S3_PREFIX = 'dyna-releases';

    /**
     * GET /downloads/dyna/appcast.xml
     *
     * Sparkle's auto-updater polls this feed to learn about new Dyna versions.
     * Served directly (not redirected) since Sparkle expects the feed body itself,
     * not a 302 to it.
     */
    public function appcast()
    {
        $key = self::S3_PREFIX.'/appcast.xml';

        if (! Storage::disk('s3')->exists($key)) {
            abort(404);
        }

        return response(Storage::disk('s3')->get($key), 200)
            ->header('Content-Type', 'application/xml');
    }

    /**
     * GET /downloads/dyna/download/{filename}
     *
     * Public, unauthenticated download of a signed Dyna release DMG — the
     * enclosure URL referenced by appcast.xml. S3 object is private; redirect
     * to a short-lived presigned URL rather than exposing a raw bucket URL,
     * same pattern as the AtlasGo APK download.
     */
    public function download(string $filename)
    {
        if (! preg_match('/^Dyna-[\w.\-]+\.dmg$/', $filename)) {
            abort(404);
        }

        $key = self::S3_PREFIX.'/'.$filename;

        if (! Storage::disk('s3')->exists($key)) {
            abort(404);
        }

        return redirect()->away(
            Storage::disk('s3')->temporaryUrl($key, now()->addMinutes(10))
        );
    }
}
