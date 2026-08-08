<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\File as LearnFile;
use App\Services\Learn\CourseFileService;
use Illuminate\Support\Facades\Auth;

class FileController extends Controller
{
    public function __construct(private CourseFileService $files)
    {
    }

    /** GET /learn/file/{fileId} */
    public function show(string $fileId)
    {
        $s3Key = $this->files->decodeFileId($fileId);
        abort_if(! $s3Key, 404);

        $file = LearnFile::where('s3_key', $s3Key)->firstOrFail();
        $course = $file->moduleItem?->module->course;
        abort_if(! $course, 404);
        abort_unless($course->canView(Auth::user()), 403);

        return $this->files->streamResponse($file);
    }
}
