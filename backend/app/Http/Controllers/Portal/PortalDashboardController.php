<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\SelfCare\SubscriberUsageService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PortalDashboardController extends Controller
{
    public function __construct(
        protected SubscriberUsageService $usageService
    ) {}

    /**
     * Render the React/Inertia Subscriber Self-Care Portal Dashboard.
     */
    public function index(Request $request): InertiaResponse
    {
        $subscriberId = (string) ($request->query('subscriber_id') ?? $request->header('X-Subscriber-Id') ?? '');
        $subscriber = null;

        if ($subscriberId !== '') {
            $subscriber = DB::table('subscribers')
                ->where('subscriber_id', $subscriberId)
                ->first();
        }

        if (! $subscriber) {
            $subscriber = DB::table('subscribers')->first();
            $subscriberId = $subscriber ? (string) $subscriber->subscriber_id : 'demo-subscriber-01';
        }

        $statusData = $this->resolveStatusPayload($subscriberId, $subscriber);

        return Inertia::render('Portal/SubscriberDashboard', [
            'initialData' => $statusData,
        ]);
    }

    /**
     * JSON Telemetry Endpoint for Subscriber Status & Usage.
     */
    public function status(Request $request): JsonResponse
    {
        $subscriberId = (string) ($request->query('subscriber_id') ?? $request->header('X-Subscriber-Id') ?? '');
        $subscriber = null;

        if ($subscriberId !== '') {
            $subscriber = DB::table('subscribers')
                ->where('subscriber_id', $subscriberId)
                ->first();
        }

        if (! $subscriber) {
            $username = (string) $request->query('username', '');
            if ($username !== '') {
                $subscriber = DB::table('subscribers')->where('username', $username)->first();
            }
        }

        if (! $subscriber) {
            $subscriber = DB::table('subscribers')->first();
            $subscriberId = $subscriber ? (string) $subscriber->subscriber_id : 'demo-subscriber-01';
        } else {
            $subscriberId = (string) $subscriber->subscriber_id;
        }

        $payload = $this->resolveStatusPayload($subscriberId, $subscriber);

        return response()->json($payload);
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveStatusPayload(string $subscriberId, ?object $subscriber): array
    {
        $username = $subscriber ? (string) $subscriber->username : 'subscriber_guest';
        $dunningState = $subscriber && isset($subscriber->dunning_state) ? (string) $subscriber->dunning_state : 'active';
        $status = $subscriber && isset($subscriber->status) ? (string) $subscriber->status : 'active';
        $plan = $subscriber && isset($subscriber->bandwidth_profile) ? (string) $subscriber->bandwidth_profile : '50M/50M Fiber Fast';
        $expiresAt = $subscriber && isset($subscriber->expires_at) ? (string) $subscriber->expires_at : null;

        $daysRemaining = 0;
        if ($expiresAt !== null) {
            $exp = Carbon::parse($expiresAt);
            $daysRemaining = max(0, (int) now()->diffInDays($exp, false));
        }

        $activeSession = DB::table('radacct')
            ->where('username', $username)
            ->whereNull('acctstoptime')
            ->first();

        $isOnline = $activeSession !== null;
        $activeIp = $activeSession && isset($activeSession->framedipaddress) ? (string) $activeSession->framedipaddress : null;

        $ont = DB::table('ont_devices')
            ->where('subscriber_id', $subscriberId)
            ->first();

        $rxPowerDbm = $ont && isset($ont->rx_power_dbm) ? (float) $ont->rx_power_dbm : -19.4;

        $usage = $this->usageService->getMonthlySummary($subscriberId);

        return [
            'subscriber_id' => $subscriberId,
            'username' => $username,
            'status' => $status,
            'dunning_state' => $dunningState,
            'speed_tier' => $plan,
            'is_online' => $isOnline,
            'active_ip' => $activeIp,
            'expires_at' => $expiresAt,
            'days_remaining' => $daysRemaining,
            'optical_rx_dbm' => $rxPowerDbm,
            'usage' => [
                'download_bytes' => $usage['download_bytes'],
                'upload_bytes' => $usage['upload_bytes'],
                'total_bytes' => $usage['total_bytes'],
                'download_gb' => $usage['download_gb'],
                'upload_gb' => $usage['upload_gb'],
                'total_gb' => $usage['total_gb'],
                'uptime_hours' => $usage['total_uptime_hours'],
                'billing_period' => $usage['billing_period'],
            ],
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
