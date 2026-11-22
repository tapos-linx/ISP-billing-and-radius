<?php

declare(strict_types=1);

namespace App\Services\Support;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class CopilotToolRegistry
{
    private const ALLOWED_TOOLS = [
        'getSubscriberRadiusState',
        'getOntOpticalPower',
        'getRecentInvoices',
    ];

    /**
     * Execute an allowlisted read-only diagnostic tool for AI Copilot.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    public function execute(string $toolName, array $parameters, ?string $subscriberId = null, int $tenantId = 1): array
    {
        if (! in_array($toolName, self::ALLOWED_TOOLS, true)) {
            throw new InvalidArgumentException("Tool '{$toolName}' is not allowed or violates read-only copilot security boundaries.");
        }

        $startTime = microtime(true);
        $status = 'success';
        $errorMessage = null;
        $result = [];

        try {
            $result = match ($toolName) {
                'getSubscriberRadiusState' => $this->getSubscriberRadiusState($parameters, $subscriberId),
                'getOntOpticalPower' => $this->getOntOpticalPower($parameters, $subscriberId),
                'getRecentInvoices' => $this->getRecentInvoices($parameters, $subscriberId),
            };
        } catch (Throwable $e) {
            $status = 'error';
            $errorMessage = $e->getMessage();
            throw $e;
        } finally {
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            DB::table('copilot_diagnostic_logs')->insert([
                'tenant_id' => $tenantId,
                'subscriber_id' => $subscriberId,
                'tool_name' => $toolName,
                'parameters' => json_encode($parameters),
                'result' => json_encode($result),
                'status' => $status,
                'latency_ms' => $latencyMs,
                'error_message' => $errorMessage,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function getSubscriberRadiusState(array $params, ?string $subscriberId): array
    {
        $id = $subscriberId ?? (string) ($params['subscriber_id'] ?? '');
        $sub = DB::table('subscribers')->where('subscriber_id', $id)->first();
        if (! $sub) {
            return ['status' => 'not_found'];
        }

        $activeSession = DB::table('radacct')
            ->where('username', $sub->username)
            ->whereNull('acctstoptime')
            ->first();

        return [
            'subscriber_id' => $id,
            'username' => $sub->username,
            'status' => $sub->status,
            'dunning_state' => $sub->dunning_state ?? 'active',
            'expires_at' => $sub->expires_at,
            'is_online' => $activeSession !== null,
            'ip_address' => $activeSession ? $activeSession->framedipaddress : null,
            'nas_ip' => $activeSession ? $activeSession->nasipaddress : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function getOntOpticalPower(array $params, ?string $subscriberId): array
    {
        $id = $subscriberId ?? (string) ($params['subscriber_id'] ?? '');
        $ont = DB::table('ont_devices')->where('subscriber_id', $id)->first();

        if (! $ont) {
            return ['status' => 'no_ont_registered'];
        }

        $rx = (float) ($ont->rx_power_dbm ?? -20.0);
        $opticalHealth = match (true) {
            $rx >= -24.0 && $rx <= -10.0 => 'GOOD',
            $rx < -24.0 && $rx >= -27.0 => 'WARNING',
            default => 'CRITICAL_OUT_OF_SPEC',
        };

        return [
            'subscriber_id' => $id,
            'serial_number' => $ont->serial_number,
            'rx_power_dbm' => $rx,
            'tx_power_dbm' => (float) ($ont->tx_power_dbm ?? 2.5),
            'optical_health' => $opticalHealth,
            'last_seen_at' => $ont->last_seen_at,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<int, array<string, mixed>>
     */
    private function getRecentInvoices(array $params, ?string $subscriberId): array
    {
        $id = $subscriberId ?? (string) ($params['subscriber_id'] ?? '');

        /** @var array<int, array<string, mixed>> $invoices */
        $invoices = DB::table('invoices')
            ->where('subscriber_id', $id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($inv) => (array) $inv)
            ->all();

        return $invoices;
    }
}
