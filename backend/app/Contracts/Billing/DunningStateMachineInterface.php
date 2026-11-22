<?php

declare(strict_types=1);

namespace App\Contracts\Billing;

interface DunningStateMachineInterface
{
    /**
     * Transition subscriber to target dunning state ('active', 'grace', 'expired', 'suspended').
     */
    public function transition(string $subscriberId, string $targetState, ?string $reason = null): bool;

    /**
     * Process lifecycle sweep across all overdue or grace-expired subscribers.
     *
     * @return array<string, int>
     */
    public function processDunningLifecycle(): array;
}
