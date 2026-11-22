import React, { useState, useEffect } from 'react';
import {
    Wifi,
    Zap,
    CreditCard,
    ArrowDown,
    ArrowUp,
    CheckCircle2,
    Clock,
    AlertTriangle,
    RefreshCw,
    X,
    Radio,
    ShieldCheck,
    Smartphone
} from 'lucide-react';

export interface UsageData {
    download_bytes: number;
    upload_bytes: number;
    total_bytes: number;
    download_gb: number;
    upload_gb: number;
    total_gb: number;
    uptime_hours: number;
    billing_period: string;
}

export interface SubscriberStatusData {
    subscriber_id: string;
    username: string;
    status: 'active' | 'expired' | 'suspended' | 'grace';
    dunning_state: 'active' | 'grace' | 'expired' | 'walled_garden';
    speed_tier: string;
    is_online: boolean;
    active_ip: string | null;
    expires_at: string | null;
    days_remaining: number;
    optical_rx_dbm: number;
    usage: UsageData;
    timestamp: string;
}

interface Props {
    initialData?: SubscriberStatusData;
}

const DEFAULT_DATA: SubscriberStatusData = {
    subscriber_id: 'sub-001',
    username: 'tapos_fiber_gigabit',
    status: 'active',
    dunning_state: 'active',
    speed_tier: '50M/50M Fiber Fast (Unmetered)',
    is_online: true,
    active_ip: '100.64.12.84',
    expires_at: '2026-12-01 00:00:00',
    days_remaining: 8,
    optical_rx_dbm: -19.4,
    usage: {
        download_bytes: 483183820800,
        upload_bytes: 107374182400,
        total_bytes: 590558003200,
        download_gb: 450.0,
        upload_gb: 100.0,
        total_gb: 550.0,
        uptime_hours: 194.5,
        billing_period: '2026-11'
    },
    timestamp: new Date().toISOString()
};

export default function SubscriberDashboard({ initialData }: Props) {
    const [data, setData] = useState<SubscriberStatusData>(initialData || DEFAULT_DATA);
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [showCheckout, setShowCheckout] = useState(false);
    const [selectedGateway, setSelectedGateway] = useState<'bkash' | 'nagad' | 'sslcommerz' | null>(null);
    const [coAStep, setCoAStep] = useState<number>(0);
    const [boostActive, setBoostActive] = useState(false);

    const isExpired = data.dunning_state === 'expired' || data.dunning_state === 'walled_garden' || data.status === 'expired';

    const fetchLatestStatus = async () => {
        setIsRefreshing(true);
        try {
            const res = await fetch(`/api/v1/portal/status?subscriber_id=${encodeURIComponent(data.subscriber_id)}`);
            if (res.ok) {
                const fresh = await res.json();
                setData(fresh);
            }
        } catch {
            // Keep existing on network failure
        } finally {
            setIsRefreshing(false);
        }
    };

    const handleGatewayPay = (gateway: 'bkash' | 'nagad' | 'sslcommerz') => {
        setSelectedGateway(gateway);
        setCoAStep(1);

        // Simulate 3-Step Live Reconnect Drill
        setTimeout(() => {
            setCoAStep(2); // Step 2: RADIUS Profile Restored
            setTimeout(() => {
                setCoAStep(3); // Step 3: Router CoA Reconnected
                setTimeout(() => {
                    setData(prev => ({
                        ...prev,
                        status: 'active',
                        dunning_state: 'active',
                        is_online: true,
                        days_remaining: 30
                    }));
                    setShowCheckout(false);
                    setSelectedGateway(null);
                    setCoAStep(0);
                }, 1200);
            }, 1000);
        }, 1000);
    };

    const triggerTurboBoost = () => {
        setBoostActive(true);
        setTimeout(() => {
            setBoostActive(false);
        }, 8000);
    };

    // Calculate Usage Ring Math (Circle circumference = 2 * PI * r)
    // r = 68 -> circumference ~ 427.25
    const radius = 68;
    const circumference = 2 * Math.PI * radius;
    const totalCapGb = 1000.0; // 1TB baseline visual reference
    const dlRatio = Math.min(1, data.usage.download_gb / totalCapGb);
    const ulRatio = Math.min(1, data.usage.upload_gb / totalCapGb);
    const dlOffset = circumference - (dlRatio * circumference);
    const ulOffset = circumference - (ulRatio * circumference);

    return (
        <div className="min-h-screen bg-slate-950 text-slate-100 font-sans pb-12 selection:bg-indigo-500 selection:text-white">
            {/* Top Navigation Bar */}
            <header className="sticky top-0 z-40 bg-slate-950/85 backdrop-blur-md border-b border-slate-800/80 px-4 py-3.5 flex items-center justify-between max-w-2xl mx-auto">
                <div className="flex items-center space-x-2.5">
                    <div className="h-9 w-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-cyan-500 flex items-center justify-center shadow-lg shadow-indigo-500/20">
                        <Wifi className="h-5 w-5 text-white" />
                    </div>
                    <div>
                        <h1 className="text-sm font-bold tracking-tight text-white flex items-center gap-1.5">
                            Fiber Prism <span className="text-[10px] uppercase font-mono px-1.5 py-0.5 rounded bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">Self-Care</span>
                        </h1>
                        <p className="text-[11px] text-slate-400 font-mono">@{data.username}</p>
                    </div>
                </div>

                <div className="flex items-center space-x-2">
                    <button
                        onClick={fetchLatestStatus}
                        disabled={isRefreshing}
                        className="p-2 rounded-lg bg-slate-900 border border-slate-800 text-slate-300 hover:text-white hover:bg-slate-800 transition disabled:opacity-50"
                        title="Refresh Status"
                    >
                        <RefreshCw className={`h-4 w-4 ${isRefreshing ? 'animate-spin text-cyan-400' : ''}`} />
                    </button>
                    <div className="hidden sm:flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-900 border border-slate-800 text-[11px] font-mono text-slate-300">
                        <span className={`h-2 w-2 rounded-full ${data.is_online ? 'bg-emerald-400 animate-pulse' : 'bg-rose-500'}`} />
                        <span>{data.is_online ? 'PPPoE Online' : 'Offline'}</span>
                    </div>
                </div>
            </header>

            <main className="max-w-2xl mx-auto px-4 pt-4 space-y-4">
                {/* 1. Gradient Speed-Tier Hero Card */}
                <div
                    className={`relative overflow-hidden rounded-2xl p-5 border shadow-2xl transition-all duration-300 ${
                        isExpired
                            ? 'bg-gradient-to-br from-amber-950/70 via-slate-900 to-rose-950/80 border-rose-500/40'
                            : 'bg-gradient-to-br from-indigo-950/80 via-slate-900 to-cyan-950/70 border-indigo-500/30'
                    }`}
                >
                    {/* Background Ambient Glow */}
                    <div className={`pointer-events-none absolute -right-12 -top-12 h-44 w-44 rounded-full blur-3xl opacity-20 ${
                        isExpired ? 'bg-rose-500' : 'bg-cyan-400'
                    }`} />

                    <div className="flex items-start justify-between">
                        <div>
                            <span className="text-[10px] font-semibold uppercase tracking-wider font-mono text-slate-400 block mb-1">
                                Current Speed Tier
                            </span>
                            <h2 className="text-xl sm:text-2xl font-black tracking-tight text-white">
                                {data.speed_tier}
                            </h2>
                            <p className="text-xs text-slate-400 mt-1 flex items-center gap-2 font-mono">
                                <Radio className="h-3.5 w-3.5 text-cyan-400" />
                                {data.active_ip || 'No Framed IP (Disconnected)'}
                            </p>
                        </div>

                        <span
                            className={`inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold font-mono uppercase tracking-wider border shadow-sm ${
                                isExpired
                                    ? 'bg-rose-500/20 text-rose-300 border-rose-500/40 animate-pulse'
                                    : 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40'
                            }`}
                        >
                            <span className={`h-1.5 w-1.5 rounded-full ${isExpired ? 'bg-rose-400' : 'bg-emerald-400'}`} />
                            {isExpired ? 'Walled Garden' : 'Active'}
                        </span>
                    </div>

                    {/* Expiry & CTA row */}
                    <div className="mt-5 pt-4 border-t border-slate-800/80 flex flex-wrap items-center justify-between gap-3">
                        <div className="flex items-center space-x-2 text-xs font-mono text-slate-300">
                            <Clock className="h-4 w-4 text-slate-400" />
                            <span>
                                {isExpired
                                    ? 'Account expired. Reconnect in <3 seconds.'
                                    : `${data.days_remaining} Days Remaining (${data.expires_at ? data.expires_at.split(' ')[0] : 'Auto-renew'})`
                                }
                            </span>
                        </div>

                        {isExpired ? (
                            <button
                                onClick={() => setShowCheckout(true)}
                                className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-rose-600 via-amber-600 to-rose-600 text-white font-bold text-xs shadow-lg shadow-rose-600/30 hover:brightness-110 active:scale-95 transition"
                            >
                                <Zap className="h-4 w-4 fill-current" />
                                Pay &amp; Reconnect (3s)
                            </button>
                        ) : (
                            <button
                                onClick={() => setShowCheckout(true)}
                                className="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-slate-800/90 hover:bg-slate-700/80 border border-slate-700 text-xs font-semibold text-slate-200 transition"
                            >
                                <CreditCard className="h-3.5 w-3.5 text-cyan-400" />
                                Renew / Pay Ahead
                            </button>
                        )}
                    </div>
                </div>

                {/* 2. Dual-Tone SVG Monthly Usage Ring Card */}
                <div className="rounded-2xl bg-slate-900/90 border border-slate-800/90 p-5 shadow-xl">
                    <div className="flex items-center justify-between mb-4">
                        <h3 className="text-sm font-bold text-white flex items-center gap-2">
                            Monthly Data Consumption
                            <span className="text-[10px] font-mono text-slate-400">({data.usage.billing_period})</span>
                        </h3>
                        <span className="text-xs font-mono text-cyan-400 font-semibold">
                            {data.usage.uptime_hours.toFixed(1)}h Uptime
                        </span>
                    </div>

                    <div className="flex flex-col sm:flex-row items-center justify-around gap-6 py-2">
                        {/* Dual-Tone SVG Ring */}
                        <div className="relative flex items-center justify-center">
                            <svg className="w-44 h-44 transform -rotate-90" viewBox="0 0 160 160">
                                {/* Track Background */}
                                <circle
                                    cx="80"
                                    cy="80"
                                    r={radius}
                                    stroke="currentColor"
                                    strokeWidth="12"
                                    fill="transparent"
                                    className="text-slate-800/80"
                                />
                                {/* Download GB Ring (Fiber Cyan) */}
                                <circle
                                    cx="80"
                                    cy="80"
                                    r={radius}
                                    stroke="#06b6d4"
                                    strokeWidth="12"
                                    strokeDasharray={circumference}
                                    strokeDashoffset={dlOffset}
                                    strokeLinecap="round"
                                    fill="transparent"
                                    className="transition-all duration-1000 ease-out"
                                />
                                {/* Upload GB Inner Ring (Electric Indigo) */}
                                <circle
                                    cx="80"
                                    cy="80"
                                    r={radius - 14}
                                    stroke="#6366f1"
                                    strokeWidth="8"
                                    strokeDasharray={2 * Math.PI * (radius - 14)}
                                    strokeDashoffset={(2 * Math.PI * (radius - 14)) - (ulRatio * (2 * Math.PI * (radius - 14)))}
                                    strokeLinecap="round"
                                    fill="transparent"
                                    className="transition-all duration-1000 ease-out"
                                />
                            </svg>

                            {/* Inner Ring Total Telemetry */}
                            <div className="absolute flex flex-col items-center justify-center text-center">
                                <span className="text-2xl font-black text-white tracking-tight">
                                    {data.usage.total_gb.toFixed(1)}
                                </span>
                                <span className="text-[10px] font-mono uppercase text-slate-400 font-bold">
                                    Total GB
                                </span>
                            </div>
                        </div>

                        {/* Breakdown Metrics */}
                        <div className="space-y-3 w-full sm:w-48">
                            <div className="p-3 rounded-xl bg-slate-950/60 border border-slate-800/80 flex items-center justify-between">
                                <div className="flex items-center space-x-2">
                                    <div className="p-1.5 rounded-lg bg-cyan-500/10 text-cyan-400">
                                        <ArrowDown className="h-4 w-4" />
                                    </div>
                                    <div>
                                        <span className="text-[10px] text-slate-400 uppercase font-bold block">Download</span>
                                        <span className="text-xs font-mono font-bold text-white">{data.usage.download_gb.toFixed(2)} GB</span>
                                    </div>
                                </div>
                                <span className="h-2 w-2 rounded-full bg-cyan-400 shadow-[0_0_8px_#06b6d4]" />
                            </div>

                            <div className="p-3 rounded-xl bg-slate-950/60 border border-slate-800/80 flex items-center justify-between">
                                <div className="flex items-center space-x-2">
                                    <div className="p-1.5 rounded-lg bg-indigo-500/10 text-indigo-400">
                                        <ArrowUp className="h-4 w-4" />
                                    </div>
                                    <div>
                                        <span className="text-[10px] text-slate-400 uppercase font-bold block">Upload</span>
                                        <span className="text-xs font-mono font-bold text-white">{data.usage.upload_gb.toFixed(2)} GB</span>
                                    </div>
                                </div>
                                <span className="h-2 w-2 rounded-full bg-indigo-500 shadow-[0_0_8px_#6366f1]" />
                            </div>
                        </div>
                    </div>
                </div>

                {/* 3. Optical Light Telemetry & Turbo Speed Boost Strip */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    {/* Optical Power Gauge Card */}
                    <div className="p-4 rounded-2xl bg-slate-900/90 border border-slate-800/90 shadow-md">
                        <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider font-mono block mb-1">
                            Fiber Optical Rx Light
                        </span>
                        <div className="flex items-center justify-between mt-2">
                            <span className="text-xl font-bold font-mono text-emerald-400">
                                {data.optical_rx_dbm.toFixed(1)} dBm
                            </span>
                            <span className="px-2 py-0.5 rounded text-[10px] font-bold font-mono uppercase bg-emerald-500/15 text-emerald-300 border border-emerald-500/30">
                                {data.optical_rx_dbm >= -24.0 ? 'Optimal' : 'Marginal'}
                            </span>
                        </div>
                        <p className="text-[11px] text-slate-400 mt-2">
                            Target: -14 dBm to -24 dBm &bull; OLT GPON Port Normal
                        </p>
                    </div>

                    {/* Turbo Speed Boost Card */}
                    <div className="p-4 rounded-2xl bg-slate-900/90 border border-slate-800/90 shadow-md flex flex-col justify-between">
                        <div>
                            <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider font-mono block mb-1">
                                RFC 5176 Turbo Boost
                            </span>
                            <p className="text-xs text-slate-300">
                                Boost bandwidth to 100Mbps via instant RADIUS CoA.
                            </p>
                        </div>
                        <button
                            onClick={triggerTurboBoost}
                            disabled={boostActive || isExpired}
                            className={`mt-3 w-full py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 ${
                                boostActive
                                    ? 'bg-emerald-500 text-slate-950 font-black'
                                    : 'bg-gradient-to-r from-indigo-600 to-cyan-600 hover:from-indigo-500 hover:to-cyan-500 text-white'
                            } disabled:opacity-40`}
                        >
                            <Zap className="h-3.5 w-3.5 fill-current" />
                            {boostActive ? 'Boost Active (100M/100M)' : 'Activate Speed Boost (1-Hour)'}
                        </button>
                    </div>
                </div>
            </main>

            {/* 4. Branded Checkout Bottom Sheet (bKash / Nagad / SSLCOMMERZ + 3-Step Stepper) */}
            {showCheckout && (
                <div className="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/80 backdrop-blur-sm p-0 sm:p-4 transition-opacity">
                    <div className="w-full max-w-lg rounded-t-3xl sm:rounded-3xl bg-slate-900 border border-slate-800 p-6 shadow-2xl space-y-5 animate-in slide-in-from-bottom duration-200">
                        <div className="flex items-center justify-between border-b border-slate-800 pb-3">
                            <div>
                                <h3 className="text-base font-bold text-white flex items-center gap-2">
                                    Instant 1-Click Payment
                                </h3>
                                <p className="text-xs text-slate-400">Zero-touch auto unblock in under 3 seconds</p>
                            </div>
                            <button
                                onClick={() => {
                                    if (coAStep === 0) setShowCheckout(false);
                                }}
                                disabled={coAStep > 0}
                                className="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition disabled:opacity-30"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        {/* Bill Amount Display */}
                        <div className="p-3.5 rounded-xl bg-slate-950/80 border border-slate-800 flex items-center justify-between font-mono">
                            <span className="text-xs text-slate-400">Monthly Renewal Bill (Inc. 5% VAT)</span>
                            <span className="text-base font-bold text-cyan-400">BDT 840.00</span>
                        </div>

                        {/* Payment Stepper when processing */}
                        {coAStep > 0 ? (
                            <div className="py-4 space-y-4">
                                <span className="text-xs font-mono text-slate-300 font-semibold block text-center uppercase tracking-wider">
                                    Live RFC 5176 CoA Reconnect Drill
                                </span>

                                <div className="space-y-3 font-mono text-xs">
                                    {/* Step 1 */}
                                    <div className="flex items-center space-x-3">
                                        <div className={`h-6 w-6 rounded-full flex items-center justify-center text-[10px] font-bold ${
                                            coAStep >= 1 ? 'bg-emerald-500 text-slate-950' : 'bg-slate-800 text-slate-400'
                                        }`}>
                                            {coAStep > 1 ? '✓' : '1'}
                                        </div>
                                        <span className={coAStep >= 1 ? 'text-white font-bold' : 'text-slate-500'}>
                                            Payment Verified (MFS IPN Nonce Settled)
                                        </span>
                                    </div>

                                    {/* Step 2 */}
                                    <div className="flex items-center space-x-3">
                                        <div className={`h-6 w-6 rounded-full flex items-center justify-center text-[10px] font-bold ${
                                            coAStep >= 2 ? 'bg-emerald-500 text-slate-950' : 'bg-slate-800 text-slate-400'
                                        }`}>
                                            {coAStep > 2 ? '✓' : '2'}
                                        </div>
                                        <span className={coAStep >= 2 ? 'text-white font-bold' : 'text-slate-500'}>
                                            RADIUS Profile Restored (radreply 50M/50M)
                                        </span>
                                    </div>

                                    {/* Step 3 */}
                                    <div className="flex items-center space-x-3">
                                        <div className={`h-6 w-6 rounded-full flex items-center justify-center text-[10px] font-bold ${
                                            coAStep >= 3 ? 'bg-emerald-500 text-slate-950' : 'bg-slate-800 text-slate-400'
                                        }`}>
                                            {coAStep >= 3 ? '✓' : '3'}
                                        </div>
                                        <span className={coAStep >= 3 ? 'text-emerald-400 font-bold' : 'text-slate-500'}>
                                            Router CoA Reconnected (PoD Disconnect ACK &lt;3s)
                                        </span>
                                    </div>
                                </div>
                            </div>
                        ) : (
                            /* Gateway Selection Buttons */
                            <div className="space-y-2.5">
                                <span className="text-xs font-semibold text-slate-400 block font-mono">
                                    Select Direct Payment Gateway:
                                </span>

                                {/* bKash */}
                                <button
                                    onClick={() => handleGatewayPay('bkash')}
                                    className="w-full flex items-center justify-between p-3.5 rounded-xl border border-pink-600/30 bg-[#E2136E]/10 hover:bg-[#E2136E]/20 text-white font-semibold text-xs transition"
                                >
                                    <span className="flex items-center gap-2.5 font-bold">
                                        <span className="h-3 w-3 rounded-full bg-[#E2136E]" />
                                        bKash 1-Click Pay
                                    </span>
                                    <span className="text-[11px] font-mono text-pink-300">Instant Unblock &rarr;</span>
                                </button>

                                {/* Nagad */}
                                <button
                                    onClick={() => handleGatewayPay('nagad')}
                                    className="w-full flex items-center justify-between p-3.5 rounded-xl border border-orange-600/30 bg-[#F6921E]/10 hover:bg-[#F6921E]/20 text-white font-semibold text-xs transition"
                                >
                                    <span className="flex items-center gap-2.5 font-bold">
                                        <span className="h-3 w-3 rounded-full bg-[#F6921E]" />
                                        Nagad Direct Pay
                                    </span>
                                    <span className="text-[11px] font-mono text-orange-300">Instant Unblock &rarr;</span>
                                </button>

                                {/* SSLCOMMERZ */}
                                <button
                                    onClick={() => handleGatewayPay('sslcommerz')}
                                    className="w-full flex items-center justify-between p-3.5 rounded-xl border border-blue-600/30 bg-[#2563EB]/10 hover:bg-[#2563EB]/20 text-white font-semibold text-xs transition"
                                >
                                    <span className="flex items-center gap-2.5 font-bold">
                                        <span className="h-3 w-3 rounded-full bg-[#2563EB]" />
                                        Cards &amp; NetBanking (SSLCOMMERZ)
                                    </span>
                                    <span className="text-[11px] font-mono text-blue-300">Visa / MC / Amex &rarr;</span>
                                </button>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
