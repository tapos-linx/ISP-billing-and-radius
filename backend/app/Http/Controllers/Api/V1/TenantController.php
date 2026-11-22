<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\IpPool;
use App\Models\Subscriber;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 6: Global Tenant Management Controller (SuperAdmin only)
 */
class TenantController extends Controller
{
    public function index(): JsonResponse
    {
        $tenants = Tenant::all();

        // Citus Optimization: Aggregate counts separately to prevent cross-partition distributed join limitations
        $subscriberCounts = Subscriber::withoutGlobalScopes()
            ->selectRaw('tenant_id, count(*) as aggregate')
            ->groupBy('tenant_id')
            ->pluck('aggregate', 'tenant_id');

        $userCounts = User::withoutGlobalScopes()
            ->selectRaw('tenant_id, count(*) as aggregate')
            ->groupBy('tenant_id')
            ->pluck('aggregate', 'tenant_id');

        $poolCounts = IpPool::withoutGlobalScopes()
            ->selectRaw('tenant_id, count(*) as aggregate')
            ->groupBy('tenant_id')
            ->pluck('aggregate', 'tenant_id');

        foreach ($tenants as $tenant) {
            $tenant->setAttribute('subscribers_count', (int) ($subscriberCounts[$tenant->id] ?? 0));
            $tenant->setAttribute('users_count', (int) ($userCounts[$tenant->id] ?? 0));
            $tenant->setAttribute('ip_pools_count', (int) ($poolCounts[$tenant->id] ?? 0));
        }

        return response()->json([
            'status' => 'success',
            'tenants' => $tenants,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $tenant = Tenant::with(['ipPools', 'subscribers', 'users'])->find($id);

        if ($tenant === null) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Tenant not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status' => 'success',
            'tenant' => $tenant,
        ]);
    }
}
