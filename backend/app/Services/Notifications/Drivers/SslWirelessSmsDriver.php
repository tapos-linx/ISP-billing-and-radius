<?php

declare(strict_types=1);

namespace App\Services\Notifications\Drivers;

use App\Services\Notifications\Contracts\NotificationDriverInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class SslWirelessSmsDriver implements NotificationDriverInterface
{
    public function send(string $recipient, string $content, ?string $subject = null): string
    {
        $apiUrl = (string) config('services.ssl_wireless.url', 'https://smsplus.sslwireless.com/api/v3/send-sms');
        $apiToken = (string) config('services.ssl_wireless.token', 'test-ssl-token');
        $sid = (string) config('services.ssl_wireless.sid', 'CARRIER_ISP');

        if (config('app.testing') || str_starts_with($apiToken, 'test-')) {
            // Emulated sandbox response for zero-risk testing
            return 'ssl_msg_'.Str::random(16);
        }

        $response = Http::timeout(10)->post($apiUrl, [
            'api_token' => $apiToken,
            'sid' => $sid,
            'msisdn' => $recipient,
            'sms' => $content,
            'csms_id' => Str::uuid()->toString(),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("SSL Wireless SMS delivery failed: {$response->body()}");
        }

        $data = $response->json();

        return (string) ($data['smsinfo'][0]['smsid'] ?? Str::uuid()->toString());
    }
}
