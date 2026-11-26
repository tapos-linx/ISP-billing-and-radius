<?php

declare(strict_types=1);

namespace Tests\Feature\ClickHouse;

use App\Jobs\IngestAccountingBatchToClickHouseJob;
use App\Services\ClickHouse\ClickHouseService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IngestAccountingBatchToClickHouseTest extends TestCase
{
    public function test_job_is_explicitly_assigned_to_default_queue(): void
    {
        $job = new IngestAccountingBatchToClickHouseJob([], 1);

        $this->assertSame('default', $job->queue);
        $this->assertSame(3, $job->tries);
        $this->assertSame(10, $job->backoff);
    }

    public function test_dispatches_accounting_batch_job_to_horizon_queue(): void
    {
        Queue::fake();

        $records = [
            [
                'radacctid' => 101,
                'username' => 'batch_sub_01',
                'acctsessionid' => 'SESS-BATCH-01',
                'framedipaddress' => '100.64.1.10',
                'acctinputoctets' => 1000000,
                'acctoutputoctets' => 5000000,
            ],
        ];

        IngestAccountingBatchToClickHouseJob::dispatch($records, 1);

        Queue::assertPushed(IngestAccountingBatchToClickHouseJob::class, function ($job) {
            return $job->queue === 'default'
                && count($job->records) === 1
                && $job->records[0]['username'] === 'batch_sub_01'
                && $job->tenantId === 1;
        });
    }

    public function test_executes_batch_ingestion_successfully_with_http_fake(): void
    {
        Http::fake([
            'http://clickhouse:8123/*' => Http::response('Ok.', 200),
            'http://localhost:8123/*' => Http::response('Ok.', 200),
            'http://127.0.0.1:8123/*' => Http::response('Ok.', 200),
        ]);

        $clickHouseService = app(ClickHouseService::class);

        $records = [
            [
                'radacctid' => 201,
                'username' => 'radacct_sub_01',
                'acctsessionid' => 'SESS-201',
                'framedipaddress' => '100.64.2.10',
                'acctinputoctets' => 2048000,
                'acctoutputoctets' => 8192000,
            ],
            [
                'radacctid' => 202,
                'username' => 'radacct_sub_02',
                'acctsessionid' => 'SESS-202',
                'framedipaddress' => '100.64.2.11',
                'acctinputoctets' => 1024000,
                'acctoutputoctets' => 4096000,
            ],
        ];

        $job = new IngestAccountingBatchToClickHouseJob($records, 1);
        $job->handle($clickHouseService);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'radacct_telemetry')
                && str_contains($request->body(), 'radacct_sub_01')
                && str_contains($request->body(), 'radacct_sub_02');
        });
    }

    public function test_throws_exception_on_clickhouse_http_failure_for_retry(): void
    {
        Http::fake([
            'http://clickhouse:8123/*' => Http::response('Internal ClickHouse Error', 500),
            'http://localhost:8123/*' => Http::response('Internal ClickHouse Error', 500),
            'http://127.0.0.1:8123/*' => Http::response('Internal ClickHouse Error', 500),
        ]);

        $clickHouseService = app(ClickHouseService::class);

        $records = [
            [
                'radacctid' => 301,
                'username' => 'fail_sub_01',
                'acctsessionid' => 'SESS-FAIL-01',
                'framedipaddress' => '100.64.3.10',
            ],
        ];

        $job = new IngestAccountingBatchToClickHouseJob($records, 1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ClickHouse radacct batch ingestion failed');

        $job->handle($clickHouseService);
    }

    public function test_handles_empty_records_without_network_call(): void
    {
        Http::fake();

        $clickHouseService = app(ClickHouseService::class);
        $job = new IngestAccountingBatchToClickHouseJob([], 1);
        $job->handle($clickHouseService);

        Http::assertNothingSent();
    }
}
