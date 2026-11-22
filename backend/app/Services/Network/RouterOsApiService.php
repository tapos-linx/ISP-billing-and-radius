<?php

declare(strict_types=1);

namespace App\Services\Network;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

class RouterOsApiService
{
    /**
     * Retrieve active PPPoE sessions from a specific NAS router via REST API.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActivePppoeSessions(int $nasId): array
    {
        $nas = DB::table('nas')->where('id', $nasId)->first();
        if (! $nas) {
            throw new InvalidArgumentException("NAS router not found with ID: {$nasId}");
        }

        $host = (string) ($nas->api_host ?: $nas->nasname);
        $port = (int) ($nas->api_port ?: 8728);
        $username = (string) ($nas->api_username ?: 'admin');
        $useTls = (bool) ($nas->api_use_tls ?? true);

        $password = '';
        if (! empty($nas->api_password_encrypted)) {
            try {
                $password = Crypt::decryptString((string) $nas->api_password_encrypted);
            } catch (Throwable) {
                $password = (string) $nas->api_password_encrypted;
            }
        } elseif (! empty($nas->secret)) {
            $password = (string) $nas->secret;
        }

        if (config('app.testing') || config('mikrotik.mock', true)) {
            $mockSessions = [
                [
                    '.id' => '*1',
                    'name' => 'sub_pppoe_101',
                    'service' => 'pppoe',
                    'caller-id' => '00:1A:2B:3C:4D:5E',
                    'address' => '100.64.1.10',
                    'uptime' => '1d02h30m',
                ],
                [
                    '.id' => '*2',
                    'name' => 'sub_pppoe_102',
                    'service' => 'pppoe',
                    'caller-id' => '00:1A:2B:3C:4D:6F',
                    'address' => '100.64.1.11',
                    'uptime' => '05h12m',
                ],
            ];

            DB::table('nas')->where('id', $nasId)->update([
                'last_synced_at' => now(),
                'sync_status' => 'synced',
            ]);

            return $mockSessions;
        }

        $protocol = $useTls ? 'https' : 'http';
        $url = "{$protocol}://{$host}:{$port}/rest/ppp/active";

        $response = Http::withBasicAuth($username, $password)
            ->timeout(5)
            ->withoutVerifying()
            ->get($url);

        if (! $response->successful()) {
            DB::table('nas')->where('id', $nasId)->update([
                'last_synced_at' => now(),
                'sync_status' => 'error',
            ]);

            throw new \RuntimeException("RouterOS API request failed: HTTP {$response->status()}");
        }

        /** @var array<int, array<string, mixed>> $sessions */
        $sessions = $response->json();

        DB::table('nas')->where('id', $nasId)->update([
            'last_synced_at' => now(),
            'sync_status' => 'synced',
        ]);

        return $sessions;
    }

    /**
     * Sync NAS state and record in database.
     */
    public function syncNasState(int $nasId): bool
    {
        $this->getActivePppoeSessions($nasId);

        return true;
    }
}
