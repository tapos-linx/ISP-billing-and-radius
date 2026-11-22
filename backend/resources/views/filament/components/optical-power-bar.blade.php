@php
    $rawVal = (isset($getState) && is_callable($getState))
        ? $getState()
        : ($rxPower ?? ($rx_power ?? ($rx_power_dbm ?? null)));
    $val = is_numeric($rawVal) ? (float) $rawVal : null;

    if ($val !== null) {
        // Linear scale mapping: -8 dBm (0%) to -32 dBm (100%)
        $percent = max(0, min(100, (($val - (-8.0)) / (-32.0 - (-8.0))) * 100));

        if ($val >= -24.0 && $val <= -14.0) {
            $badgeColor = 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30';
            $statusLabel = 'Optimal';
        } elseif ($val > -14.0) {
            $badgeColor = 'bg-amber-500/20 text-amber-400 border-amber-500/30';
            $statusLabel = 'Too High';
        } elseif ($val >= -27.0) {
            $badgeColor = 'bg-amber-500/20 text-amber-400 border-amber-500/30';
            $statusLabel = 'Degraded';
        } else {
            $badgeColor = 'bg-rose-500/20 text-rose-400 border-rose-500/30';
            $statusLabel = 'LOS / Critical';
        }
    } else {
        $percent = 100;
        $badgeColor = 'bg-slate-500/20 text-slate-400 border-slate-500/30';
        $statusLabel = 'Offline';
    }
@endphp

<div class="w-full min-w-[180px] flex items-center space-x-2.5 text-xs font-mono select-none" data-testid="optical-power-spectrum-bar">
    <div class="relative flex-1 h-3 rounded-full overflow-hidden bg-slate-900 border border-slate-700/60 p-[1px]">
        <!-- Fiber Prism Gradient Spectrum -->
        <div class="w-full h-full rounded-full bg-gradient-to-r from-emerald-500 via-amber-400 to-rose-600 opacity-85"></div>
        <!-- Live Marker Pin -->
        @if($val !== null)
            <div class="absolute top-0 bottom-0 w-1 bg-white shadow-[0_0_8px_#ffffff] rounded-full transform -translate-x-1/2 transition-all duration-300"
                 style="left: {{ $percent }}%;"
                 title="{{ $val }} dBm ({{ $statusLabel }})"></div>
        @endif
    </div>
    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold border {{ $badgeColor }}">
        {{ $val !== null ? number_format($val, 1) . ' dBm' : 'Offline' }}
    </span>
</div>
