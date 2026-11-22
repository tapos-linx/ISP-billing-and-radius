<?php

declare(strict_types=1);

namespace App\Services\Cpe;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenieAcsTr069Service
{
    public function __construct(
        private readonly ?GenieAcsClient $client = null
    ) {}

    private function getClient(): GenieAcsClient
    {
        return $this->client ?? app(GenieAcsClient::class);
    }

    /**
     * Provision WAN PPPoE credentials and 802.1Q VLAN tag onto subscriber ONU via TR-069 CWMP.
     *
     * @return array<string, mixed>
     */
    public function provisionPppoe(
        string $deviceId,
        string $username,
        string $password,
        int $vlanId = 100,
        ?string $subscriberId = null
    ): array {
        if (config('app.testing') || config('services.genieacs.mock', true)) {
            $taskId = 'task_'.Str::random(16);
            $result = [
                'status' => 'queued',
                'task_id' => $taskId,
                'device_id' => $deviceId,
                'username' => $username,
                'vlan_id' => $vlanId,
            ];
        } else {
            $result = $this->getClient()->provisionWanPppoe($deviceId, $username, $password, $vlanId);
        }

        if ($subscriberId) {
            DB::table('ont_devices')->updateOrInsert(
                [
                    'subscriber_id' => $subscriberId,
                    'serial_number' => $deviceId,
                ],
                [
                    'tr069_device_id' => $deviceId,
                    'last_seen_at' => now(),
                ]
            );
        }

        return $result;
    }
}
