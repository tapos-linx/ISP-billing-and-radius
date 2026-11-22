<x-filament-widgets::widget>
    <div class="relative overflow-hidden rounded-xl border border-violet-500/30 bg-slate-900/90 p-5 shadow-2xl backdrop-blur-md">
        <!-- Ambient Prism Violet Glow -->
        <div class="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full bg-violet-600/15 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-16 -left-16 h-48 w-48 rounded-full bg-indigo-600/15 blur-3xl"></div>

        <!-- Header -->
        <div class="relative flex flex-wrap items-center justify-between gap-3 border-b border-slate-800 pb-4">
            <div class="flex items-center space-x-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-violet-600 to-indigo-700 text-white shadow-lg shadow-violet-500/25">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-bold tracking-tight text-white flex items-center gap-2">
                        Prism Violet AI Copilot
                        <span class="inline-flex items-center rounded-full bg-violet-500/20 px-2 py-0.5 text-[10px] font-semibold text-violet-300 border border-violet-500/30 uppercase tracking-wider">
                            Read-Only Diagnostic
                        </span>
                    </h3>
                    <p class="text-xs text-slate-400">Carrier Edge Telemetry & Real-Time Subscriber Triage</p>
                </div>
            </div>

            <!-- Tool Selector -->
            <div class="flex items-center space-x-2">
                <select wire:model="selectedTool" class="rounded-lg border border-slate-700 bg-slate-800/90 px-3 py-1.5 text-xs text-slate-200 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500 font-mono">
                    <option value="getSubscriberRadiusState">RADIUS Session & Status</option>
                    <option value="getOntOpticalPower">TR-069 Optical Power (Rx/Tx)</option>
                    <option value="getRecentInvoices">Billing & Dunning History</option>
                </select>
            </div>
        </div>

        <!-- Diagnostic Input Form -->
        <div class="relative mt-4 flex flex-col sm:flex-row items-center gap-3">
            <div class="relative flex-1 w-full">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input
                    type="text"
                    wire:model="subscriberQuery"
                    wire:keydown.enter="runDiagnostics"
                    placeholder="Enter Subscriber UUID or PPPoE Username (e.g. tapos_test)..."
                    class="w-full rounded-lg border border-slate-700 bg-slate-800/80 pl-9 pr-4 py-2 text-xs text-slate-100 placeholder-slate-500 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500 font-mono"
                />
            </div>

            <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                <button
                    type="button"
                    wire:click="runDiagnostics"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center rounded-lg bg-gradient-to-r from-violet-600 to-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-md shadow-violet-600/30 hover:from-violet-500 hover:to-indigo-500 focus:outline-none disabled:opacity-50 transition-all duration-150"
                >
                    <span wire:loading.remove wire:target="runDiagnostics" class="flex items-center gap-1.5">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Run AI Diagnostics
                    </span>
                    <span wire:loading wire:target="runDiagnostics" class="flex items-center gap-1.5">
                        <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Inspecting Citus...
                    </span>
                </button>

                @if($diagnosticResult || $errorMessage)
                    <button
                        type="button"
                        wire:click="resetDiagnostics"
                        class="rounded-lg border border-slate-700 bg-slate-800/60 px-3 py-2 text-xs font-medium text-slate-400 hover:text-slate-200 hover:bg-slate-700/60 transition"
                    >
                        Clear
                    </button>
                @endif
            </div>
        </div>

        <!-- Diagnostic Feedback Section -->
        @if($errorMessage)
            <div class="mt-4 rounded-lg border border-rose-500/30 bg-rose-950/40 p-3 text-xs text-rose-300 flex items-center gap-2">
                <svg class="h-4 w-4 flex-shrink-0 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ $errorMessage }}</span>
            </div>
        @endif

        @if($diagnosticResult)
            <div class="mt-4 rounded-xl border border-slate-800 bg-slate-950/60 p-4">
                <div class="flex items-center justify-between border-b border-slate-800/80 pb-2 mb-3">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 font-mono">
                        Telemetry Result &bull; {{ $selectedTool }}
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-medium text-emerald-400 border border-emerald-500/20">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                        Allowlisted Audit Logged
                    </span>
                </div>

                <!-- Structured Diagnostic Chips Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2.5">
                    @if(isset($diagnosticResult['status']) && $diagnosticResult['status'] === 'not_found')
                        <div class="col-span-full rounded-lg border border-amber-500/30 bg-amber-950/30 p-3 text-xs text-amber-300">
                            Subscriber record not found in Citus database.
                        </div>
                    @else
                        <!-- Chip 1: Subscriber Identity -->
                        <div class="rounded-lg border border-slate-800 bg-slate-900/60 p-3">
                            <span class="text-[10px] font-medium text-slate-400 uppercase tracking-wider block mb-1">Subscriber</span>
                            <div class="text-xs font-bold text-slate-100 font-mono truncate">
                                {{ $diagnosticResult['username'] ?? ($diagnosticResult['subscriber_id'] ?? 'N/A') }}
                            </div>
                        </div>

                        <!-- Chip 2: Online / Session Status -->
                        @if(array_key_exists('is_online', $diagnosticResult))
                            <div class="rounded-lg border border-slate-800 bg-slate-900/60 p-3">
                                <span class="text-[10px] font-medium text-slate-400 uppercase tracking-wider block mb-1">Session State</span>
                                @if($diagnosticResult['is_online'])
                                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-400">
                                        <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                        Online ({{ $diagnosticResult['ip_address'] ?? 'Active' }})
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-rose-400">
                                        <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                                        Offline / Disconnected
                                    </span>
                                @endif
                            </div>
                        @endif

                        <!-- Chip 3: Dunning / Lifecycle -->
                        @if(isset($diagnosticResult['dunning_state']))
                            <div class="rounded-lg border border-slate-800 bg-slate-900/60 p-3">
                                <span class="text-[10px] font-medium text-slate-400 uppercase tracking-wider block mb-1">Dunning State</span>
                                @php
                                    $dState = (string) $diagnosticResult['dunning_state'];
                                    $badge = match($dState) {
                                        'active' => 'text-emerald-400 bg-emerald-500/10 border-emerald-500/20',
                                        'expired' => 'text-rose-400 bg-rose-500/10 border-rose-500/20',
                                        'grace' => 'text-amber-400 bg-amber-500/10 border-amber-500/20',
                                        default => 'text-cyan-400 bg-cyan-500/10 border-cyan-500/20',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold border {{ $badge }} uppercase font-mono">
                                    {{ $dState }}
                                </span>
                            </div>
                        @endif

                        <!-- Chip 4: Optical Rx Power -->
                        @if(isset($diagnosticResult['rx_power_dbm']))
                            <div class="rounded-lg border border-slate-800 bg-slate-900/60 p-3">
                                <span class="text-[10px] font-medium text-slate-400 uppercase tracking-wider block mb-1">Optical Rx Light</span>
                                @php
                                    $rx = (float) $diagnosticResult['rx_power_dbm'];
                                    $color = $rx >= -24.0 ? 'text-emerald-400' : ($rx >= -27.0 ? 'text-amber-400' : 'text-rose-400');
                                @endphp
                                <span class="text-xs font-bold font-mono {{ $color }}">
                                    {{ number_format($rx, 2) }} dBm
                                </span>
                            </div>
                        @endif

                        <!-- Raw Key-Value Telemetry Dump for Complex Responses -->
                        @if(isset($diagnosticResult['invoices']) && is_array($diagnosticResult['invoices']))
                            <div class="col-span-full rounded-lg border border-slate-800 bg-slate-900/40 p-3">
                                <span class="text-[10px] font-medium text-slate-400 uppercase tracking-wider block mb-2">Recent Invoices</span>
                                <div class="space-y-1.5 font-mono text-xs">
                                    @foreach($diagnosticResult['invoices'] as $inv)
                                        <div class="flex items-center justify-between text-slate-300 border-b border-slate-800/40 pb-1">
                                            <span>#{{ $inv['id'] ?? 'INV' }} &bull; BDT {{ number_format((float) ($inv['amount'] ?? 0), 2) }}</span>
                                            <span class="text-{{ ($inv['status'] ?? '') === 'paid' ? 'emerald' : 'rose' }}-400 uppercase text-[10px] font-bold">
                                                {{ $inv['status'] ?? 'pending' }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
