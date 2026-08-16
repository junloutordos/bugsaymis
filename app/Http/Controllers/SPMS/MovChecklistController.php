<?php

namespace App\Http\Controllers\SPMS;

use App\Http\Controllers\Controller;
use App\Services\SPMS\MovChecklistService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class MovChecklistController extends Controller
{
    public function show(string $fileId): Response
    {
        abort_unless(preg_match('/^[a-zA-Z0-9_.=-]+$/', $fileId), 400);
        abort_unless(str_starts_with($fileId, 's3.'), 400);

        $s3Key = MovChecklistService::decodeFileId($fileId);

        abort_unless(Storage::disk('s3')->exists($s3Key), 404);

        $contents = Storage::disk('s3')->get($s3Key);
        $extension = strtolower(pathinfo($s3Key, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };

        return response($contents, 200)->header('Content-Type', $mime);
    }
}
