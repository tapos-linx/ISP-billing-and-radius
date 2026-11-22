<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use Illuminate\Support\Facades\DB;

class CgnatPbaExportService
{
    /**
     * Record a Carrier-Grade NAT Port Block Allocation (RFC 6888 / BTRC compliance).
     */
    public function logPortBlockAllocation(
        string $subscriberId,
        string $privateIp,
        string $publicIp,
        int $portStart,
        int $portEnd,
        int $tenantId = 1
    ): int {
        return (int) DB::table('cgnat_pba_logs')->insertGetId([
            'tenant_id' => $tenantId,
            'subscriber_id' => $subscriberId,
            'private_ip' => $privateIp,
            'public_ip' => $publicIp,
            'port_start' => $portStart,
            'port_end' => $portEnd,
            'protocol' => 'ALL',
            'allocated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Lawful Intercept: Resolve which subscriber had a given Public IP and Port at a specific timestamp.
     *
     * @return array<string, mixed>|null
     */
    public function resolveSubscriber(string $publicIp, int $port, string|\DateTimeInterface $timestamp): ?array
    {
        $targetTime = $timestamp instanceof \DateTimeInterface ? $timestamp->format('Y-m-d H:i:s') : (string) $timestamp;

        $binding = DB::table('cgnat_pba_logs as pba')
            ->leftJoin('subscribers as sub', 'sub.subscriber_id', '=', 'pba.subscriber_id')
            ->where('pba.public_ip', $publicIp)
            ->where('pba.port_start', '<=', $port)
            ->where('pba.port_end', '>=', $port)
            ->where('pba.allocated_at', '<=', $targetTime)
            ->where(function ($q) use ($targetTime): void {
                $q->whereNull('pba.deallocated_at')
                    ->orWhere('pba.deallocated_at', '>=', $targetTime);
            })
            ->orderByDesc('pba.allocated_at')
            ->orderByDesc('pba.id')
            ->select([
                'pba.id as allocation_id',
                'pba.tenant_id',
                'pba.subscriber_id',
                'sub.username',
                'sub.status as subscriber_status',
                'pba.private_ip',
                'pba.public_ip',
                'pba.port_start',
                'pba.port_end',
                'pba.protocol',
                'pba.allocated_at',
                'pba.deallocated_at',
            ])
            ->first();

        return $binding ? (array) $binding : null;
    }

    /**
     * Export BTRC Lawful Intercept Port Block Allocation report as CSV string.
     */
    public function exportBtrcPbaAuditCsv(string $startDate, string $endDate, int $tenantId = 1): string
    {
        $records = DB::table('cgnat_pba_logs as pba')
            ->leftJoin('subscribers as sub', 'sub.subscriber_id', '=', 'pba.subscriber_id')
            ->where('pba.tenant_id', $tenantId)
            ->whereBetween('pba.allocated_at', [$startDate, $endDate])
            ->select([
                'pba.allocated_at',
                'pba.deallocated_at',
                'pba.public_ip',
                'pba.port_start',
                'pba.port_end',
                'pba.private_ip',
                'pba.subscriber_id',
                'sub.username',
            ])
            ->get();

        $rows = [];
        $rows[] = 'allocated_at,deallocated_at,public_ip,port_start,port_end,private_ip,subscriber_id,username';

        foreach ($records as $r) {
            $rows[] = sprintf(
                '"%s","%s","%s",%d,%d,"%s","%s","%s"',
                $r->allocated_at,
                $r->deallocated_at ?? 'ACTIVE',
                $r->public_ip,
                $r->port_start,
                $r->port_end,
                $r->private_ip,
                $r->subscriber_id,
                $r->username ?? 'UNKNOWN'
            );
        }

        return implode("\n", $rows);
    }
}
