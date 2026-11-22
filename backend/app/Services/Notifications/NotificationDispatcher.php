<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Contracts\Notifications\NotificationDispatcherInterface;
use App\Jobs\SendTransactionalNotificationJob;
use Illuminate\Support\Facades\DB;

class NotificationDispatcher implements NotificationDispatcherInterface
{
    /**
     * @param  array<string, mixed>  $variables
     */
    public function dispatch(
        string $channel,
        string $recipient,
        string $templateCode,
        array $variables = [],
        ?string $subscriberId = null,
        int $tenantId = 1
    ): bool {
        // 1. Fetch template if present
        $template = DB::table('notification_templates')
            ->where('tenant_id', $tenantId)
            ->where('code', $templateCode)
            ->first();

        $rawBody = $template ? (string) $template->body : "Notification for {$templateCode}: ".json_encode($variables);
        $subject = $template ? (string) $template->subject : 'Carrier Notification';

        // 2. Variable replacement (e.g., {{ name }} -> John)
        $renderedContent = $rawBody;
        foreach ($variables as $key => $val) {
            $renderedContent = str_replace(
                ['{{'.$key.'}}', '{{ '.$key.' }}'],
                (string) $val,
                $renderedContent
            );
        }

        // 3. Create audit log row
        $logId = (int) DB::table('notification_logs')->insertGetId([
            'tenant_id' => $tenantId,
            'subscriber_id' => $subscriberId,
            'channel' => $channel,
            'recipient' => $recipient,
            'template_code' => $templateCode,
            'content' => $renderedContent,
            'status' => 'queued',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Dispatch async queue job on 'notifications' queue
        SendTransactionalNotificationJob::dispatch(
            $channel,
            $recipient,
            $templateCode,
            $renderedContent,
            $subject,
            $subscriberId,
            $tenantId,
            $logId
        )->onQueue('notifications');

        return true;
    }
}
