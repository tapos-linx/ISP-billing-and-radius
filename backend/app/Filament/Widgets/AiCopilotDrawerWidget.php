<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Support\CopilotToolRegistry;
use Filament\Widgets\Widget;
use Throwable;

class AiCopilotDrawerWidget extends Widget
{
    protected static string $view = 'filament.widgets.ai-copilot-drawer-widget';

    protected int|string|array $columnSpan = 'full';

    public string $subscriberQuery = '';

    public string $selectedTool = 'getSubscriberRadiusState';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $diagnosticResult = null;

    public ?string $errorMessage = null;

    public function runDiagnostics(CopilotToolRegistry $registry): void
    {
        $this->errorMessage = null;
        $this->diagnosticResult = null;

        $target = trim($this->subscriberQuery);
        if ($target === '') {
            $this->errorMessage = 'Subscriber ID or Username is required to run AI diagnostics.';

            return;
        }

        try {
            $params = [
                'subscriber_id' => $target,
                'username' => $target,
            ];

            $this->diagnosticResult = $registry->execute(
                toolName: $this->selectedTool,
                parameters: $params,
                subscriberId: $target,
            );
        } catch (Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function resetDiagnostics(): void
    {
        $this->diagnosticResult = null;
        $this->errorMessage = null;
        $this->subscriberQuery = '';
    }
}
