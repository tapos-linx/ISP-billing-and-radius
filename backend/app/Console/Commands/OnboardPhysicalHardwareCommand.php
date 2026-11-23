<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Olt;
use App\Services\Radius\ClientsConfGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Onboard Physical BNG Router (MikroTik RouterOS v7) or GPON OLT into Carrier Cluster
 */
class OnboardPhysicalHardwareCommand extends Command
{
    protected $signature = 'carrier:onboard-hardware
        {--type=mikrotik : Device category: mikrotik or olt}
        {--name= : Device short name or hostname (e.g. CCR2004-CORE-01)}
        {--ip= : Management IP address of the device (must be /32 point-to-point)}
        {--secret= : RADIUS shared secret for AAA and CoA}
        {--server-ip=127.0.0.1 : BSS Server IP address that BNG connects to}
        {--vendor=mikrotik : Vendor: mikrotik, huawei, zte, vsol, cdata}
        {--tenant-id=1 : Tenant organization ID}
        {--export-rsc= : Optional file path to export RouterOS .rsc script}';

    protected $description = 'Onboard physical BNG Router or GPON OLT, register in Citus NAS directory, and generate RouterOS v7 configuration';

    public function handle(ClientsConfGenerator $confGenerator): int
    {
        $type = strtolower((string) ($this->option('type') ?: 'mikrotik'));
        $name = (string) ($this->option('name') ?: 'CCR2004-BNG-'.strtoupper(Str::random(4)));
        $ip = trim((string) ($this->option('ip') ?: '10.10.0.2'));
        $secret = (string) ($this->option('secret') ?: 'RadiusSecret#'.Str::random(8));
        $serverIp = trim((string) ($this->option('server-ip') ?: '127.0.0.1'));
        $vendor = strtolower((string) ($this->option('vendor') ?: 'mikrotik'));
        $tenantId = (int) ($this->option('tenant-id') ?: 1);

        $this->info('================================================================================');
        $this->info("   CARRIER HARDWARE ONBOARDING: {$name} ({$type})");
        $this->info('================================================================================');

        // 1. Strict Security & IP Validation (Fail-closed on 0.0.0.0)
        try {
            $confGenerator->validateIpAddress($ip);
            $confGenerator->validateIpAddress($serverIp);
        } catch (InvalidArgumentException $e) {
            $this->error('  SECURITY VIOLATION: '.$e->getMessage());

            return 1;
        }

        if (strlen($secret) < 8) {
            $this->error('  SECURITY VIOLATION: RADIUS secret must be at least 8 characters.');

            return 1;
        }

        if ($type === 'mikrotik') {
            // 2. Register / Update NAS in Citus Database
            $this->line("  [1/4] Registering BNG in Citus 'nas' table (IP: {$ip}, Tenant: {$tenantId})...");
            DB::table('nas')->updateOrInsert(
                ['nasname' => $ip],
                [
                    'shortname' => $name,
                    'type' => 'mikrotik',
                    'ports' => 1812,
                    'secret' => $secret,
                    'server' => 'default',
                    'coa_port' => 3799,
                    'description' => "Physical MikroTik BNG {$name} onboarded via carrier CLI",
                    'tenant_id' => $tenantId,
                    'api_port' => 8728,
                    'api_use_tls' => true,
                    'sync_status' => 'active',
                    'last_synced_at' => now(),
                ]
            );
            $this->line('  ✓ Citus SQL NAS entry registered successfully.');

            // 3. Render FreeRADIUS clients.conf Entry
            $this->newLine();
            $this->line('  [2/4] Generating FreeRADIUS 3.2 clients.conf entry...');
            $this->line("    client {$name} {");
            $this->line("        ipaddr = {$ip}");
            $this->line("        secret = {$secret}");
            $this->line('        require_message_authenticator = yes');
            $this->line('        nas_type = mikrotik');
            $this->line('        coa_port = 3799');
            $this->line('    }');
            $this->line('  ✓ FreeRADIUS clients.conf entry ready.');

            // 4. Generate Production MikroTik RouterOS v7 Configuration Block
            $this->newLine();
            $this->line('  [3/4] Generating Production MikroTik RouterOS v7 Setup Commands...');
            $rscContent = $this->buildRouterOsScript($name, $ip, $serverIp, $secret);

            $this->line('--------------------------------------------------------------------------------');
            $this->line($rscContent);
            $this->line('--------------------------------------------------------------------------------');

            // 5. Export to file if requested
            $exportPath = $this->option('export-rsc');
            if ($exportPath && is_string($exportPath)) {
                file_put_contents($exportPath, $rscContent);
                $this->line("  ✓ RouterOS script saved to: {$exportPath}");
            }
        } elseif ($type === 'olt') {
            // Register OLT in Citus Database
            $this->line("  [1/3] Registering GPON OLT in Citus 'olts' table (Vendor: {$vendor}, IP: {$ip})...");
            $oltId = (string) Str::uuid();

            Olt::withoutGlobalScopes()->updateOrCreate(
                ['ip_address' => $ip],
                [
                    'olt_id' => $oltId,
                    'tenant_id' => $tenantId,
                    'name' => $name,
                    'vendor' => $vendor,
                    'snmp_community' => 'carrier_ro_2026',
                    'management_port' => 22,
                    'status' => 'online',
                ]
            );
            $this->line('  ✓ Citus OLT entry registered.');

            // Generate OLT Configuration Block
            $this->newLine();
            $this->line("  [2/3] Generating {$vendor} GPON OLT Uplink & TR-069 ACS Directives...");
            $oltConfig = $this->buildOltConfig($vendor, $name, $ip, $serverIp);
            $this->line('--------------------------------------------------------------------------------');
            $this->line($oltConfig);
            $this->line('--------------------------------------------------------------------------------');
        } else {
            $this->error("  Unsupported device type: {$type}. Supported: mikrotik, olt");

            return 1;
        }

        $this->newLine();
        $this->info('================================================================================');
        $this->info("   HARDWARE ONBOARDING COMPLETE: {$name} READY FOR CARRIER TRAFFIC");
        $this->info('================================================================================');

        return 0;
    }

    protected function buildRouterOsScript(string $name, string $nasIp, string $serverIp, string $secret): string
    {
        return <<<ROUTEROS
# ==============================================================================
# CARRIER BNG ROUTEROS v7 CONFIGURATION SCRIPT
# Device: {$name} (IP: {$nasIp})
# BSS RADIUS & CoA Server: {$serverIp}
# Generated by: Carrier BSS/OSS Hardware Onboarding Engine
# ==============================================================================

# 1. Restrict RFC 5176 CoA UDP 3799 strictly to Carrier BSS server IP
/ip firewall filter
add chain=input protocol=udp dst-port=3799 src-address={$serverIp} action=accept place-before=0 \
    comment="Carrier BSS RFC 5176 CoA Listener"
add chain=input protocol=udp dst-port=3799 action=drop place-before=1 \
    comment="Drop Unauthorized CoA Packets (Security Rule)"

# 2. Enable Incoming RADIUS Disconnect & CoA Requests
/radius incoming
set accept=yes port=3799

# 3. Register Carrier FreeRADIUS 3.2 Server
/radius
add address={$serverIp} \
    service=ppp,hotspot,login \
    authentication-port=1812 \
    accounting-port=1813 \
    timeout=3s \
    secret="{$secret}" \
    comment="FreeRADIUS 3.2 Carrier Primary ({$name})"

# 4. Enable PPP AAA Authentication & 5-Minute Interim Accounting
/ppp aaa
set use-radius=yes accounting=yes interim-update=5m

# 5. Define Carrier Subscriber & Walled Garden IP Pools
/ip pool
add name=pppoe_active_pool ranges=100.64.0.10-100.64.15.254 comment="Carrier Active CGNAT Pool"
add name=walled_garden_pool ranges=10.250.0.10-10.250.0.254 comment="Walled Garden Isolation Pool"

# 6. Walled Garden Bill-Pay Redirection NAT (TCP 80 -> Self-Care Portal)
/ip firewall nat
add action=dst-nat chain=dstnat dst-port=80 protocol=tcp src-address=10.250.0.0/24 \
    to-addresses={$serverIp} to-ports=8000 comment="Walled Garden Bill-Pay Redirect"
ROUTEROS;
    }

    protected function buildOltConfig(string $vendor, string $name, string $oltIp, string $serverIp): string
    {
        return match ($vendor) {
            'huawei' => <<<CONFIG
# HUAWEI GPON OLT CONFIGURATION DIRECTIVE: {$name} ({$oltIp})
sysman server snmp {$serverIp}
snmp-agent community read carrier_ro_2026
snmp-agent target-host trap-paramsname carrier_bss v1 securityname carrier_ro_2026
snmp-agent target-host trap-hostname {$serverIp} address {$serverIp} trap-paramsname carrier_bss
# TR-069 ACS Server Integration
tr069 server url http://{$serverIp}:7547/
tr069 server auth-type basic
CONFIG,
            'zte' => <<<CONFIG
! ZTE C300/C320 GPON OLT CONFIGURATION DIRECTIVE: {$name} ({$oltIp})
snmp-server community carrier_ro_2026 view AllView rw
snmp-server host {$serverIp} version 2c carrier_ro_2026
! TR-069 Remote Management
gpon
  tr069 server-url http://{$serverIp}:7547/
CONFIG,
            default => <<<CONFIG
# GENERIC GPON OLT DIRECTIVE: {$name} ({$oltIp})
# SNMP Manager: {$serverIp} (Community: carrier_ro_2026)
# GenieACS TR-069 Server: http://{$serverIp}:7547/
CONFIG,
        };
    }
}
