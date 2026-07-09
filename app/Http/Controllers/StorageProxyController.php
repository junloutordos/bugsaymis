<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorageProxyController extends Controller
{
    /**
     * Serve a private S3 file through the app.
     * Requires authentication — prevents unauthenticated scraping.
     *
     * GET /media/{path}
     */
    public function serve(Request $request, string $path): StreamedResponse|\Illuminate\Http\Response
    {
        if (! Storage::disk('s3')->exists($path)) {
            abort(404);
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            'svg'         => 'image/svg+xml',
            'pdf'         => 'application/pdf',
            default       => 'application/octet-stream',
        };

        // svg can carry inline <script> — never render it inline. Everything
        // that isn't a raster image is forced to download, using the caller's
        // display name (falls back to the storage path's basename).
        $isRasterImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
        $downloadName  = $request->query('name') ?: basename($path);

        $headers = [
            'Content-Type'           => $mime,
            'Cache-Control'          => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if (! $isRasterImage) {
            $headers['Content-Disposition'] = 'attachment; filename="' . addslashes($downloadName) . '"';
        }

        return response()->stream(function () use ($path) {
            $stream = Storage::disk('s3')->readStream($path);
            fpassthru($stream);
            fclose($stream);
        }, 200, $headers);
    }
}
