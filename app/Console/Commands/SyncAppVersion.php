<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SyncAppVersion extends Command
{
    protected $signature   = 'app:version-sync';
    protected $description = 'Sync the version from composer.json into the app_versions table.';

    public function handle(): int
    {
        $composerPath = base_path('composer.json');

        if (! file_exists($composerPath)) {
            $this->error('composer.json not found.');
            return self::FAILURE;
        }

        $composer = json_decode(file_get_contents($composerPath), true);
        $version  = $composer['version'] ?? null;

        if (! $version) {
            $this->warn('No "version" key found in composer.json — skipping.');
            return self::SUCCESS;
        }

        $exists = DB::table('app_versions')->where('version', $version)->exists();

        if (! $exists) {
            DB::table('app_versions')->insert([
                'version'    => $version,
                'date'       => now()->toDateString(),
                'remarks'    => '',
                'is_current' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->info("New version {$version} inserted into app_versions.");
        } else {
            $this->info("Version {$version} already exists — marking as current.");
        }

        // Mark this version as current, demote all others
        DB::table('app_versions')->where('version', '!=', $version)->update(['is_current' => false]);
        DB::table('app_versions')->where('version', $version)->update(['is_current' => true]);

        Cache::forget('app.version');

        $this->info("app:version-sync complete — current version: {$version}");

        return self::SUCCESS;
    }
}
