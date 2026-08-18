<?php

namespace App\Jobs\HR;

use App\Services\HR\BiometricImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessBiometricImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries   = 3;

    public function __construct(
        public readonly string  $filePath,
        public readonly string  $importBatch,
        public readonly ?string $deviceId = null,
    ) {
        // Parses a whole biometric device file (timeout=300s) — keep off
        // 'default' so it never blocks fast single-unit jobs.
        $this->onQueue('bulk');
    }

    public function handle(BiometricImportService $service): void
    {
        $fullPath = Storage::path($this->filePath);

        $result = $service->parse($fullPath, $this->importBatch, $this->deviceId);

        Log::info('BiometricImport complete', array_merge($result, ['batch' => $this->importBatch]));
    }

    public function failed(\Throwable $e): void
    {
        Log::error('BiometricImport failed', [
            'batch' => $this->importBatch,
            'path'  => $this->filePath,
            'error' => $e->getMessage(),
        ]);
    }
}
