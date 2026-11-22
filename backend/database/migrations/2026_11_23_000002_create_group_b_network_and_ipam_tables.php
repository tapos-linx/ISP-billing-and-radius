<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. NAS RouterOS v7 API Columns
        Schema::table('nas', function (Blueprint $table) {
            if (! Schema::hasColumn('nas', 'api_host')) {
                $table->string('api_host', 128)->nullable();
            }
            if (! Schema::hasColumn('nas', 'api_port')) {
                $table->unsignedInteger('api_port')->default(8728);
            }
            if (! Schema::hasColumn('nas', 'api_username')) {
                $table->string('api_username', 64)->nullable();
            }
            if (! Schema::hasColumn('nas', 'api_password_encrypted')) {
                $table->text('api_password_encrypted')->nullable();
            }
            if (! Schema::hasColumn('nas', 'api_use_tls')) {
                $table->boolean('api_use_tls')->default(true);
            }
            if (! Schema::hasColumn('nas', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable();
            }
            if (! Schema::hasColumn('nas', 'sync_status')) {
                $table->string('sync_status', 32)->default('pending');
            }
        });

        // 2. ONT Devices Optical & TR-069 Columns
        if (Schema::hasTable('ont_devices')) {
            Schema::table('ont_devices', function (Blueprint $table) {
                if (! Schema::hasColumn('ont_devices', 'tx_power_dbm')) {
                    $table->decimal('tx_power_dbm', 5, 2)->nullable();
                }
                if (! Schema::hasColumn('ont_devices', 'tr069_device_id')) {
                    $table->string('tr069_device_id', 128)->nullable();
                }
            });
        }

        // 3. IP Allocations
        if (! Schema::hasTable('ip_allocations')) {
            Schema::create('ip_allocations', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id')->default(1)->index();
                $table->unsignedInteger('ip_pool_id')->index();
                $table->uuid('subscriber_id')->nullable()->index();
                $table->string('ip_address', 45);
                $table->string('mac_address', 17)->nullable();
                $table->string('status', 32)->default('allocated'); // 'allocated', 'released', 'reserved'
                $table->timestamp('allocated_at')->useCurrent();
                $table->timestamp('released_at')->nullable();
                $table->timestamps();

                $table->unique(['ip_pool_id', 'ip_address']);
            });
        }

        // 4. Device Health Metrics (SNMP / ICMP Polling)
        if (! Schema::hasTable('device_health_metrics')) {
            Schema::create('device_health_metrics', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id')->default(1)->index();
                $table->string('device_type', 32); // 'nas', 'olt', 'switch'
                $table->string('device_id', 64)->index();
                $table->decimal('cpu_usage_percent', 5, 2)->nullable();
                $table->decimal('memory_usage_percent', 5, 2)->nullable();
                $table->decimal('temperature_celsius', 5, 2)->nullable();
                $table->decimal('ping_rtt_ms', 8, 2)->nullable();
                $table->decimal('packet_loss_percent', 5, 2)->nullable();
                $table->string('status', 32)->default('healthy');
                $table->timestamp('polled_at')->index();
                $table->timestamps();

                $table->index(['tenant_id', 'device_type', 'device_id']);
            });
        }

        // 5. WireGuard Peers for Hardened FreeRADIUS
        if (! Schema::hasTable('wireguard_peers')) {
            Schema::create('wireguard_peers', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id')->default(1)->index();
                $table->string('shortname', 32);
                $table->string('wireguard_ip', 45);
                $table->string('public_key', 128);
                $table->text('radius_secret_encrypted');
                $table->string('status', 32)->default('active');
                $table->timestamp('last_handshake_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'wireguard_ip']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wireguard_peers');
        Schema::dropIfExists('device_health_metrics');
        Schema::dropIfExists('ip_allocations');

        Schema::table('nas', function (Blueprint $table) {
            $cols = ['api_host', 'api_port', 'api_username', 'api_password_encrypted', 'api_use_tls', 'last_synced_at', 'sync_status'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('nas', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        if (Schema::hasTable('ont_devices')) {
            Schema::table('ont_devices', function (Blueprint $table) {
                if (Schema::hasColumn('ont_devices', 'tx_power_dbm')) {
                    $table->dropColumn('tx_power_dbm');
                }
                if (Schema::hasColumn('ont_devices', 'tr069_device_id')) {
                    $table->dropColumn('tr069_device_id');
                }
            });
        }
    }
};
