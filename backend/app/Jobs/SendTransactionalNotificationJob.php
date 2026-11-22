<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Notifications\Contracts\NotificationDriverInterface;
use App\Services\Notifications\Drivers\SmtpEmailDriver;
use App\Services\Notifications\Drivers\SslWirelessSmsDriver;
use App\Services\Notifications\Drivers\WhatsAppCloudDriver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class SendTransactionalNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 180];

    public function __construct(
        public string $channel,
        public string $recipient,
        public string $templateCode,
        public string $content,
        public ?string $subject = null,
        public ?string $subscriberId = null,
        public int $tenantId = 1,
        public ?int $logId = null
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $driver = $this->resolveDriver($this->channel);

        try {
            $externalId = $driver->send($this->recipient, $this->content, $this->subject);

            if ($this->logId) {
                DB::table('notification_logs')
                    ->where('id', $this->logId)
                    ->update([
                        'status' => 'sent',
                        'external_id' => $externalId,
                        'sent_at' => now(),
                        'updated_at' => now(),
                    ]);
            }
        } catch (Throwable $e) {
            if ($this->logId) {
                DB::table('notification_logs')
                    ->where('id', $this->logId)
                    ->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                        'updated_at' => now(),
                    ]);
            }
            throw $e;
        }
    }

    private function resolveDriver(string $channel): NotificationDriverInterface
    {
        return match ($channel) {
            'sms', 'ssl_wireless' => app(SslWirelessSmsDriver::class),
            'whatsapp', 'whatsapp_cloud' => app(WhatsAppCloudDriver::class),
            'email', 'smtp' => app(SmtpEmailDriver::class),
            default => throw new InvalidArgumentException("Unsupported notification channel: {$channel}"),
        };
    }
}
