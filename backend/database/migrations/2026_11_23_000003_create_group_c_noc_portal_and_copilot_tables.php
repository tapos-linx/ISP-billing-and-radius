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
        // 1. NOC Action Audits (Filament v3 Force Disconnects)
        if (! Schema::hasTable('noc_action_audits')) {
            Schema::create('noc_action_audits', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id')->default(1)->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('action', 64);
                $table->string('target_type', 64);
                $table->string('target_id', 64);
                $table->text('reason')->nullable();
                $table->json('metadata')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'action']);
            });
        }

        // 2. Subscribers Self-Care OTP Columns
        Schema::table('subscribers', function (Blueprint $table) {
            if (! Schema::hasColumn('subscribers', 'phone_verified_at')) {
                $table->timestamp('phone_verified_at')->nullable();
            }
            if (! Schema::hasColumn('subscribers', 'portal_otp_hash')) {
                $table->string('portal_otp_hash', 255)->nullable();
            }
            if (! Schema::hasColumn('subscribers', 'portal_otp_expires_at')) {
                $table->timestamp('portal_otp_expires_at')->nullable();
            }
            if (! Schema::hasColumn('subscribers', 'last_portal_login_at')) {
                $table->timestamp('last_portal_login_at')->nullable();
            }
        });

        // 3. AI Copilot Diagnostic Logs
        if (! Schema::hasTable('copilot_diagnostic_logs')) {
            Schema::create('copilot_diagnostic_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id')->default(1)->index();
                $table->uuid('subscriber_id')->nullable()->index();
                $table->string('tool_name', 64)->index();
                $table->json('parameters')->nullable();
                $table->json('result')->nullable();
                $table->string('status', 32)->default('success'); // 'success', 'error'
                $table->unsignedInteger('latency_ms')->default(0);
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'subscriber_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('copilot_diagnostic_logs');
        Schema::dropIfExists('noc_action_audits');

        Schema::table('subscribers', function (Blueprint $table) {
            $cols = ['phone_verified_at', 'portal_otp_hash', 'portal_otp_expires_at', 'last_portal_login_at'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('subscribers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
