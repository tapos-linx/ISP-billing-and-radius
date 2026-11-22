<?php

declare(strict_types=1);

namespace App\Services\Notifications\Drivers;

use App\Services\Notifications\Contracts\NotificationDriverInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class WhatsAppCloudDriver implements NotificationDriverInterface
{
    public function send(string $recipient, string $content, ?string $subject = null): string
    {
        $phoneId = (string) config('services.whatsapp.phone_number_id', '100200300');
        $token = (string) config('services.whatsapp.token', 'test-wa-token');

        if (config('app.testing') || str_starts_with($token, 'test-')) {
            return 'wamid.'.Str::random(24);
        }

        $url = "https://graph.facebook.com/v19.0/{$phoneId}/messages";
        $response = Http::withToken($token)->timeout(10)->post($url, [
            'messaging_product' => 'whatsapp',
            'to' => $recipient,
            'type' => 'text',
            'text' => ['body' => $content],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("WhatsApp Cloud API delivery failed: {$response->body()}");
        }

        $data = $response->json();

        return (string) ($data['messages'][0]['id'] ?? Str::random(24));
    }
}
