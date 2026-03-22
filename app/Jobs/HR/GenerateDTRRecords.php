<?php

namespace App\Jobs\HR;

use App\Services\HR\DTRService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateDTRRecords implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 2;

    public function __construct(
        public readonly int    $userId,
        public readonly string $dateFrom,
        public readonly string $dateTo,
    ) {}

    public function handle(DTRService $service): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $service->generate($this->userId, $this->dateFrom, $this->dateTo);

        Log::info('DTR generation complete', [
            'user_id'   => $this->userId,
            'date_from' => $this->dateFrom,
            'date_to'   => $this->dateTo,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('DTR generation failed', [
            'user_id'   => $this->userId,
            'date_from' => $this->dateFrom,
            'date_to'   => $this->dateTo,
            'error'     => $e->getMessage(),
        ]);
    }
}
