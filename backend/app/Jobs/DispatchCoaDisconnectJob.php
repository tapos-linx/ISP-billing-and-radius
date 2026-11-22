<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Radius\RadiusCoaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchCoaDisconnectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 5;

    public function __construct(
        public string $username,
        public ?string $subscriberId = null,
        public int $tenantId = 1
    ) {
        $this->onQueue('critical');
    }

    public function handle(RadiusCoaService $coaService): void
    {
        try {
            $success = $coaService->disconnect($this->username, $this->subscriberId);
            Log::info("DispatchCoaDisconnectJob executed for {$this->username}: ".($success ? 'SUCCESS' : 'FAILED'));
        } catch (Throwable $e) {
            Log::error("DispatchCoaDisconnectJob failed for {$this->username}: {$e->getMessage()}");
            throw $e;
        }
    }
}
