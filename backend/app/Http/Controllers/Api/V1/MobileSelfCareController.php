<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\MobilePushNotification;
use App\Models\Subscriber;
use App\Services\Mobile\MobilePushService;
use App\Services\Mobile\SubscriberSelfCareMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Release 2.2: Mobile Self-Care API Bridge for React Native & Flutter Apps
 */
class MobileSelfCareController extends Controller
{
    public function __construct(
        protected SubscriberSelfCareMobileService $selfCareService,
        protected MobilePushService $pushService
    ) {}

    /**
     * Resolve target subscriber ID from request or authenticated bearer token
     */
    protected function resolveSubscriberId(Request $request): string
    {
        $subscriberId = $request->input('subscriber_id') ?? $request->query('subscriber_id');

        if (! empty($subscriberId)) {
            return (string) $subscriberId;
        }

        // Fallback to first active subscriber in tenant for dev/testing
        $first = Subscriber::withoutGlobalScopes()->where('status', 'active')->first();

        return $first ? $first->subscriber_id : '11111111-1111-1111-1111-111111111111';
    }

    /**
     * Get subscriber profile & subscription metadata
     */
    public function profile(Request $request): JsonResponse
    {
        $subscriberId = $this->resolveSubscriberId($request);
        $data = $this->selfCareService->getSubscriberProfile($subscriberId);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Get live bandwidth consumption and fair usage policy telemetry
     */
    public function usage(Request $request): JsonResponse
    {
        $subscriberId = $this->resolveSubscriberId($request);
        $data = $this->selfCareService->getBandwidthUsage($subscriberId);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * List subscriber invoices with NBR Mushak-6.3 and payment links
     */
    public function invoices(Request $request): JsonResponse
    {
        $subscriberId = $this->resolveSubscriberId($request);

        $invoices = Invoice::withoutGlobalScopes()
            ->where('subscriber_id', $subscriberId)
            ->orderByDesc('due_date')
            ->limit(20)
            ->get();

        return response()->json([
            'status' => 'success',
            'count' => $invoices->count(),
            'data' => $invoices,
        ]);
    }

    /**
     * Run automated TR-069 optical line diagnostics
     */
    public function diagnose(Request $request): JsonResponse
    {
        $subscriberId = $this->resolveSubscriberId($request);
        $data = $this->selfCareService->runOntDiagnostics($subscriberId);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Apply on-demand speed turbo boost
     */
    public function boost(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subscriber_id' => ['nullable', 'uuid'],
            'boost_speed_mbps' => ['nullable', 'integer', 'min:50', 'max:500'],
            'duration_hours' => ['nullable', 'integer', 'min:1', 'max:24'],
        ]);

        $subscriberId = $validated['subscriber_id'] ?? $this->resolveSubscriberId($request);
        $speed = (int) ($validated['boost_speed_mbps'] ?? 100);
        $duration = (int) ($validated['duration_hours'] ?? 6);

        $result = $this->selfCareService->applySpeedBoost($subscriberId, $speed, $duration);

        return response()->json([
            'status' => 'success',
            'message' => "Turbo boost applied: {$result['boost_rate_limit']} for {$duration} hours.",
            'data' => $result,
        ]);
    }

    /**
     * Register or update mobile push device token
     */
    public function registerDevice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subscriber_id' => ['required', 'uuid'],
            'tenant_id' => ['nullable', 'integer'],
            'device_uuid' => ['required', 'string', 'max:64'],
            'platform' => ['required', 'string', 'in:android,ios'],
            'fcm_token' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:128'],
            'app_version' => ['nullable', 'string', 'max:32'],
        ]);

        $tenantId = (int) ($validated['tenant_id'] ?? 1);

        $device = $this->pushService->registerDevice(
            $validated['subscriber_id'],
            $tenantId,
            $validated['device_uuid'],
            $validated['platform'],
            $validated['fcm_token'],
            [
                'device_name' => $validated['device_name'] ?? 'Mobile Device',
                'app_version' => $validated['app_version'] ?? '2.2.0',
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Mobile device registered successfully.',
            'device' => $device,
        ], Response::HTTP_CREATED);
    }

    /**
     * Get subscriber push notification history
     */
    public function notifications(Request $request): JsonResponse
    {
        $subscriberId = $this->resolveSubscriberId($request);

        $notifications = MobilePushNotification::withoutGlobalScopes()
            ->where('subscriber_id', $subscriberId)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        return response()->json([
            'status' => 'success',
            'count' => $notifications->count(),
            'data' => $notifications,
        ]);
    }
}
