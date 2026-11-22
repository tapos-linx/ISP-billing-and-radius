<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Contracts\Notifications\NotificationDispatcherInterface;
use App\Jobs\SendTransactionalNotificationJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionalNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.testing' => true]);
    }

    public function test_dispatcher_queues_sms_job_and_logs_audit_record(): void
    {
        Queue::fake([SendTransactionalNotificationJob::class]);

        $dispatcher = app(NotificationDispatcherInterface::class);
        $subscriberId = (string) Str::uuid();

        // 1. Create a notification template
        DB::table('notification_templates')->updateOrInsert(
            ['tenant_id' => 1, 'channel' => 'sms', 'code' => 'BILLING_INVOICE_DUE'],
            [
                'subject' => 'Invoice Due Notice',
                'body' => 'Dear {{ name }}, your ISP bill of BDT {{ amount }} is due on {{ due_date }}.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $dispatched = $dispatcher->dispatch(
            channel: 'sms',
            recipient: '+8801711000001',
            templateCode: 'BILLING_INVOICE_DUE',
            variables: ['name' => 'Akbar', 'amount' => '1050', 'due_date' => '2026-11-30'],
            subscriberId: $subscriberId,
            tenantId: 1
        );

        $this->assertTrue($dispatched);

        // Verify audit log row created with status queued
        $log = DB::table('notification_logs')
            ->where('subscriber_id', $subscriberId)
            ->where('template_code', 'BILLING_INVOICE_DUE')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('queued', $log->status);
        $this->assertStringContainsString('Dear Akbar, your ISP bill of BDT 1050 is due on 2026-11-30.', $log->content);

        // Verify Job was pushed to 'notifications' queue
        Queue::assertPushedOn('notifications', SendTransactionalNotificationJob::class);
    }

    public function test_send_transactional_notification_job_executes_successfully(): void
    {
        $subscriberId = (string) Str::uuid();

        $logId = (int) DB::table('notification_logs')->insertGetId([
            'tenant_id' => 1,
            'subscriber_id' => $subscriberId,
            'channel' => 'whatsapp',
            'recipient' => '+8801812345678',
            'template_code' => 'PAYMENT_SUCCESS',
            'content' => 'Thank you for your payment of BDT 1050.',
            'status' => 'queued',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $job = new SendTransactionalNotificationJob(
            channel: 'whatsapp',
            recipient: '+8801812345678',
            templateCode: 'PAYMENT_SUCCESS',
            content: 'Thank you for your payment of BDT 1050.',
            subject: 'Payment Confirmation',
            subscriberId: $subscriberId,
            tenantId: 1,
            logId: $logId
        );

        $job->handle();

        $updatedLog = DB::table('notification_logs')->where('id', $logId)->first();
        $this->assertNotNull($updatedLog);
        $this->assertSame('sent', $updatedLog->status);
        $this->assertNotNull($updatedLog->external_id);
        $this->assertNotNull($updatedLog->sent_at);
    }
}
