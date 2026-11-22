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
        // 1. CGNAT PBA Logs (RFC 6888 / BTRC Compliance)
        if (! Schema::hasTable('cgnat_pba_logs')) {
            Schema::create('cgnat_pba_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id')->default(1)->index();
                $table->uuid('subscriber_id')->index();
                $table->string('private_ip', 45)->index();
                $table->string('public_ip', 45)->index();
                $table->unsignedInteger('port_start');
                $table->unsignedInteger('port_end');
                $table->string('protocol', 8)->default('ALL');
                $table->timestamp('allocated_at')->index();
                $table->timestamp('deallocated_at')->nullable();
                $table->timestamps();

                // Composite index required by BTRC Lawful Intercept & RFC 6888 queries:
                $table->index(['public_ip', 'port_start', 'port_end', 'allocated_at'], 'idx_cgnat_pba_lookup');
            });
        }

        // 2. Automated Encrypted Backup Runs
        if (! Schema::hasTable('backup_runs')) {
            Schema::create('backup_runs', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id')->default(1)->index();
                $table->uuid('run_uuid')->unique();
                $table->string('status', 32)->default('pending'); // 'pending', 'running', 'completed', 'failed'
                $table->string('file_path', 255)->nullable();
                $table->string('checksum_sha256', 64)->nullable();
                $table->unsignedBigInteger('bytes_size')->default(0);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
        Schema::dropIfExists('cgnat_pba_logs');
    }
};
