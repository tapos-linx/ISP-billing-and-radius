<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Network\RouterOsApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncNasRouterStateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public int $nasId,
        public int $tenantId = 1
    ) {
        $this->onQueue('network');
    }

    public function handle(RouterOsApiService $apiService): void
    {
        try {
            $apiService->syncNasState($this->nasId);
            Log::info("SyncNasRouterStateJob completed successfully for NAS ID: {$this->nasId}");
        } catch (\Throwable $e) {
            Log::error("SyncNasRouterStateJob failed for NAS ID {$this->nasId}: {$e->getMessage()}");
            throw $e;
        }
    }
}
