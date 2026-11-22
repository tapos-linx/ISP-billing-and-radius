<?php

declare(strict_types=1);

namespace App\Services\SelfCare;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriberUsageService
{
    /**
     * Compute aggregated monthly bandwidth consumption from RADIUS accounting (radacct).
     *
     * @return array{
     *     subscriber_id: string,
     *     billing_period: string,
     *     download_bytes: int,
     *     upload_bytes: int,
     *     total_bytes: int,
     *     download_gb: float,
     *     upload_gb: float,
     *     total_gb: float,
     *     session_count: int,
     *     total_uptime_hours: float
     * }
     */
    public function getMonthlySummary(string $subscriberId, ?string $yearMonth = null): array
    {
        $subscriber = DB::table('subscribers')->where('subscriber_id', $subscriberId)->first();
        $username = $subscriber ? (string) $subscriber->username : '';

        $period = $yearMonth ? Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth() : now()->startOfMonth();
        $startOfMonth = $period->toDateTimeString();
        $endOfMonth = (clone $period)->endOfMonth()->toDateTimeString();

        $query = DB::table('radacct')
            ->where('username', $username)
            ->whereBetween('acctstarttime', [$startOfMonth, $endOfMonth]);

        $stats = $query->selectRaw('
            COALESCE(SUM(acctinputoctets), 0) as total_upload_bytes,
            COALESCE(SUM(acctoutputoctets), 0) as total_download_bytes,
            COALESCE(SUM(EXTRACT(EPOCH FROM (COALESCE(acctstoptime, NOW()) - acctstarttime))), 0) as total_session_seconds,
            COUNT(*) as session_count
        ')->first();

        $downloadBytes = (int) ($stats->total_download_bytes ?? 0);
        $uploadBytes = (int) ($stats->total_upload_bytes ?? 0);
        $totalBytes = $downloadBytes + $uploadBytes;
        $totalSeconds = (int) ($stats->total_session_seconds ?? 0);
        $sessionCount = (int) ($stats->session_count ?? 0);

        return [
            'subscriber_id' => $subscriberId,
            'billing_period' => $period->format('Y-m'),
            'download_bytes' => $downloadBytes,
            'upload_bytes' => $uploadBytes,
            'total_bytes' => $totalBytes,
            'download_gb' => round($downloadBytes / (1024 * 1024 * 1024), 2),
            'upload_gb' => round($uploadBytes / (1024 * 1024 * 1024), 2),
            'total_gb' => round($totalBytes / (1024 * 1024 * 1024), 2),
            'session_count' => $sessionCount,
            'total_uptime_hours' => round($totalSeconds / 3600, 2),
        ];
    }
}
