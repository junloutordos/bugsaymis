<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SyncAppVersion extends Command
{
    protected $signature   = 'app:version-sync';
    protected $description = 'Sync the current release (version.json baked in by CI, or composer.json fallback) into the app_versions table.';

    public function handle(): int
    {
        $release = $this->readVersionJson() ?? $this->readComposerJson();

        if (! $release) {
            $this->warn('No version.json or composer.json version found — skipping.');
            return self::SUCCESS;
        }

        $version = $release['version'];
        $exists  = DB::table('app_versions')->where('version', $version)->exists();

        if (! $exists) {
            DB::table('app_versions')->insert([
                'version'    => $version,
                'date'       => $release['date'] ?? now()->toDateString(),
                'remarks'    => $release['remarks'] ?? '',
                'changes'    => isset($release['changes']) ? json_encode($release['changes']) : null,
                'is_current' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->info("New version {$version} inserted into app_versions.");
        } else {
            // Redeploy or rollback of a known version — keep its (possibly
            // admin-edited) content untouched, just flip the current flag.
            $this->info("Version {$version} already exists — marking as current.");
        }

        DB::table('app_versions')->where('version', '!=', $version)->update(['is_current' => false]);
        DB::table('app_versions')->where('version', $version)->update(['is_current' => true]);

        Cache::forget('app.version');

        $this->info("app:version-sync complete — current version: {$version}");

        return self::SUCCESS;
    }

    /**
     * CI-generated release manifest baked into the image by deploy.yml.
     */
    private function readVersionJson(): ?array
    {
        $path = base_path('version.json');

        if (! file_exists($path)) {
            return null;
        }

        $release = json_decode(file_get_contents($path), true);

        if (empty($release['version'])) {
            $this->warn('version.json is present but has no "version" key — ignoring.');
            return null;
        }

        $changes = $release['changes'] ?? [];
        $counts  = array_filter([
            'new feature' => count($changes['features'] ?? []),
            'fix'         => count($changes['fixes'] ?? []),
            'improvement' => count($changes['improvements'] ?? []),
        ]);

        $release['remarks'] = $counts
            ? implode(', ', array_map(
                fn ($label, $n) => $n . ' ' . $label . ($n > 1 ? 's' : ''),
                array_keys($counts),
                $counts
            ))
            : 'Maintenance release.';

        return $release;
    }

    /**
     * Dev fallback — containers built without a CI release manifest.
     */
    private function readComposerJson(): ?array
    {
        $composerPath = base_path('composer.json');

        if (! file_exists($composerPath)) {
            return null;
        }

        $composer = json_decode(file_get_contents($composerPath), true);
        $version  = $composer['version'] ?? null;

        return $version ? ['version' => $version] : null;
    }
}
