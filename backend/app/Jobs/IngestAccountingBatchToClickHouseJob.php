<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\ClickHouse\ClickHouseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Horizon-Queued Async Accounting Batch Consumer for ClickHouse (TD-001 / TD-004)
 *
 * Dedicated to the 'default' queue as defined in config/horizon.php:
 * 'default: dunning buckets, monthly invoices, CGNAT ClickHouse sync'
 */
class IngestAccountingBatchToClickHouseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 10;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    public function __construct(
        public array $records,
        public int $tenantId = 1
    ) {
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(ClickHouseService $clickHouse): void
    {
        if (empty($this->records)) {
            return;
        }

        // Validate and enrich records with Citus tenant isolation
        $sanitized = [];
        foreach ($this->records as $record) {
            $record['tenant_id'] = (int) ($record['tenant_id'] ?? $this->tenantId);
            $sanitized[] = $record;
        }

        $success = $clickHouse->insertRadacctBatch($sanitized);

        if (! $success) {
            Log::warning('IngestAccountingBatchToClickHouseJob batch failed, scheduling retry', [
                'tenant_id' => $this->tenantId,
                'count' => count($this->records),
                'attempt' => $this->attempts(),
            ]);

            throw new \RuntimeException(sprintf(
                'ClickHouse radacct batch ingestion failed for %d records (tenant %d).',
                count($this->records),
                $this->tenantId
            ));
        }

        Log::info('IngestAccountingBatchToClickHouseJob successfully streamed to ClickHouse', [
            'tenant_id' => $this->tenantId,
            'count' => count($this->records),
        ]);
    }
}
