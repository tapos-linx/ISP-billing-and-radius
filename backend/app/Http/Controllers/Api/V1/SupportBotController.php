<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Support\Tier1AiSupportBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportBotController extends Controller
{
    public function __construct(
        protected Tier1AiSupportBotService $botService
    ) {}

    /**
     * Web Copilot Query Endpoint
     */
    public function query(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|max:500',
            'subscriber_id' => 'nullable|string',
            'mobile' => 'nullable|string',
            'channel' => 'nullable|string|in:whatsapp,web_copilot',
        ]);

        $channel = $validated['channel'] ?? 'web_copilot';
        $result = $this->botService->handleQuery([
            'query' => $validated['query'],
            'subscriber_id' => $validated['subscriber_id'] ?? null,
            'mobile' => $validated['mobile'] ?? null,
            'channel' => $channel,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }

    /**
     * WhatsApp Webhook Ingress (Meta / Twilio compatible)
     */
    public function whatsappWebhook(Request $request): JsonResponse
    {
        // Support webhook verification handshake
        if ($request->isMethod('get') && $request->has('hub_challenge')) {
            return response()->json((int) $request->input('hub_challenge'));
        }

        $messageText = $request->input('entry.0.changes.0.value.messages.0.text.body')
            ?? $request->input('Body')
            ?? $request->input('message')
            ?? 'hello';

        $fromMobile = $request->input('entry.0.changes.0.value.messages.0.from')
            ?? $request->input('From')
            ?? $request->input('mobile');

        $result = $this->botService->handleQuery([
            'query' => (string) $messageText,
            'mobile' => $fromMobile ? preg_replace('/[^0-9]/', '', (string) $fromMobile) : null,
            'channel' => 'whatsapp',
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }
}
