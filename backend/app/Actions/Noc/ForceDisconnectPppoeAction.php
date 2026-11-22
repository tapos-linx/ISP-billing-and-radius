<?php

declare(strict_types=1);

namespace App\Actions\Noc;

use App\Jobs\ForceDisconnectSessionJob;
use Illuminate\Auth\Access\AuthorizationException;

class ForceDisconnectPppoeAction
{
    /**
     * Execute NOC force-disconnect action for an active PPPoE session.
     *
     * @param  array<string, mixed>  $userContext
     * @return array{status: string, message: string}
     */
    public function execute(
        string $username,
        ?string $subscriberId = null,
        ?string $reason = null,
        array $userContext = []
    ): array {
        // Enforce NOC operator permission
        $permissions = (array) ($userContext['permissions'] ?? ['noc.disconnect_session']);
        if (! in_array('noc.disconnect_session', $permissions, true)) {
            throw new AuthorizationException('Unauthorized: Missing noc.disconnect_session permission.');
        }

        $userId = isset($userContext['user_id']) ? (int) $userContext['user_id'] : null;
        $tenantId = isset($userContext['tenant_id']) ? (int) $userContext['tenant_id'] : 1;

        ForceDisconnectSessionJob::dispatch(
            $username,
            $subscriberId,
            $reason ?? 'NOC Operator immediate session kick',
            $userId,
            $tenantId
        )->onQueue('critical');

        return [
            'status' => 'queued',
            'message' => "Force-disconnect requested on critical queue for {$username}",
        ];
    }
}
