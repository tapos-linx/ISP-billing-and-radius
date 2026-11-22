<?php

declare(strict_types=1);

namespace App\Services\Ipam;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class IpamAllocatorService
{
    /**
     * Allocate the next sequential available IP address from a given IP pool using exclusive row locking.
     */
    public function allocateNextIp(int $ipPoolId, string $subscriberId, int $tenantId = 1): string
    {
        return DB::transaction(function () use ($ipPoolId, $subscriberId, $tenantId): string {
            /** @var object|null $pool */
            $pool = DB::table('ip_pools')
                ->where('id', $ipPoolId)
                ->lockForUpdate()
                ->first();

            if (! $pool) {
                throw new InvalidArgumentException("IP pool with ID {$ipPoolId} does not exist.");
            }

            $startLong = ip2long((string) $pool->range_start);
            $endLong = ip2long((string) $pool->range_end);

            if ($startLong === false || $endLong === false || $startLong > $endLong) {
                throw new InvalidArgumentException("Invalid IP range configuration for pool {$pool->pool_name}");
            }

            // Fetch already allocated IPs for this pool
            $allocatedIps = DB::table('ip_allocations')
                ->where('ip_pool_id', $ipPoolId)
                ->where('status', 'allocated')
                ->pluck('ip_address')
                ->all();

            $allocatedSet = array_flip($allocatedIps);

            $selectedIp = null;
            for ($curr = $startLong; $curr <= $endLong; $curr++) {
                $candidate = long2ip($curr);
                if (! isset($allocatedSet[$candidate])) {
                    $selectedIp = $candidate;
                    break;
                }
            }

            if (! $selectedIp) {
                throw new RuntimeException("IP pool {$pool->pool_name} is completely exhausted (0 available leases).");
            }

            // Insert into ip_allocations
            DB::table('ip_allocations')->insert([
                'tenant_id' => $tenantId,
                'ip_pool_id' => $ipPoolId,
                'subscriber_id' => $subscriberId,
                'ip_address' => $selectedIp,
                'status' => 'allocated',
                'allocated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Increment pool allocated counter
            DB::table('ip_pools')
                ->where('id', $ipPoolId)
                ->increment('allocated_ips');

            return $selectedIp;
        });
    }

    /**
     * Release an allocated IP address back into the pool.
     */
    public function releaseIp(int $ipPoolId, string $ipAddress): bool
    {
        return DB::transaction(function () use ($ipPoolId, $ipAddress): bool {
            $updated = DB::table('ip_allocations')
                ->where('ip_pool_id', $ipPoolId)
                ->where('ip_address', $ipAddress)
                ->where('status', 'allocated')
                ->update([
                    'status' => 'released',
                    'released_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($updated > 0) {
                DB::table('ip_pools')
                    ->where('id', $ipPoolId)
                    ->where('allocated_ips', '>', 0)
                    ->decrement('allocated_ips');

                return true;
            }

            return false;
        });
    }
}
