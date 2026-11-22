<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Radius\RadiusCoaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ForceDisconnectSessionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 5;

    public function __construct(
        public string $username,
        public ?string $subscriberId = null,
        public ?string $reason = null,
        public ?int $userId = null,
        public int $tenantId = 1
    ) {
        $this->onQueue('critical');
    }

    public function handle(RadiusCoaService $coaService): void
    {
        try {
            $coaService->disconnect($this->username, $this->subscriberId);

            DB::table('noc_action_audits')->insert([
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId,
                'action' => 'force_disconnect_pppoe',
                'target_type' => 'subscriber',
                'target_id' => $this->subscriberId ?? $this->username,
                'reason' => $this->reason ?? 'Manual NOC operator kick',
                'metadata' => json_encode(['username' => $this->username]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info("ForceDisconnectSessionJob executed successfully for user: {$this->username}");
        } catch (Throwable $e) {
            Log::error("ForceDisconnectSessionJob failed for {$this->username}: {$e->getMessage()}");
            throw $e;
        }
    }
}
