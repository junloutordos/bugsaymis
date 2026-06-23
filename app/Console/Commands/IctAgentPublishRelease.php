<?php

namespace App\Console\Commands;

use App\Models\IctAgentRelease;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class IctAgentPublishRelease extends Command
{
    protected $signature = 'ict-agent:publish-release {version} {zipPath}';
    protected $description = 'Upload a new ICT Agent release zip to S3 and mark it as the latest version for fleet auto-update';

    public function handle(): int
    {
        $version = $this->argument('version');
        $zipPath = $this->argument('zipPath');

        if (! is_file($zipPath)) {
            $this->error("File not found: {$zipPath}");
            return self::FAILURE;
        }

        if (IctAgentRelease::where('version', $version)->exists()) {
            $this->error("Version {$version} has already been published.");
            return self::FAILURE;
        }

        $sha256 = hash_file('sha256', $zipPath);
        $s3Key = "ict-agent-releases/{$version}.zip";

        $this->info("Uploading to s3://{$s3Key} ...");
        Storage::disk('s3')->put($s3Key, fopen($zipPath, 'r'));

        IctAgentRelease::create([
            'version' => $version,
            's3_key' => $s3Key,
            'sha256' => $sha256,
            'release_notes' => $this->ask('Release notes (optional)', null),
        ]);

        $this->info("Published {$version} — enrolled devices will self-update within their next checkin window.");

        return self::SUCCESS;
    }
}
