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
        // 1. Notification Templates
        if (! Schema::hasTable('notification_templates')) {
            Schema::create('notification_templates', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id')->default(1)->index();
                $table->string('channel', 32); // 'email', 'sms', 'whatsapp'
                $table->string('code', 64);
                $table->string('subject', 255)->nullable();
                $table->text('body');
                $table->json('variables')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'channel', 'code']);
            });
        }

        // 2. Notification Logs
        if (! Schema::hasTable('notification_logs')) {
            Schema::create('notification_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id')->default(1)->index();
                $table->uuid('subscriber_id')->nullable()->index();
                $table->string('channel', 32);
                $table->string('recipient', 255);
                $table->string('template_code', 64)->nullable();
                $table->text('content');
                $table->string('status', 32)->default('queued'); // 'queued', 'sent', 'failed'
                $table->string('external_id', 255)->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'subscriber_id']);
                $table->index(['tenant_id', 'channel', 'status']);
            });
        }

        // 3. Subscribers Dunning Columns
        Schema::table('subscribers', function (Blueprint $table) {
            if (! Schema::hasColumn('subscribers', 'dunning_state')) {
                $table->string('dunning_state', 32)->default('active')->index();
            }
            if (! Schema::hasColumn('subscribers', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable();
            }
            if (! Schema::hasColumn('subscribers', 'grace_until')) {
                $table->timestamp('grace_until')->nullable();
            }
        });

        // 4. Invoices Pro-Rata & PDF Columns
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'is_prorata')) {
                $table->boolean('is_prorata')->default(false);
            }
            if (! Schema::hasColumn('invoices', 'prorata_days')) {
                $table->unsignedInteger('prorata_days')->nullable();
            }
            if (! Schema::hasColumn('invoices', 'pdf_path')) {
                $table->string('pdf_path', 255)->nullable();
            }
        });

        // 5. Credit Notes
        if (! Schema::hasTable('credit_notes')) {
            Schema::create('credit_notes', function (Blueprint $table) {
                $table->id();
                $table->string('credit_note_number', 64)->unique();
                $table->unsignedInteger('tenant_id')->default(1)->index();
                $table->uuid('invoice_id')->index();
                $table->uuid('subscriber_id')->index();
                $table->decimal('subtotal_bdt', 10, 2);
                $table->decimal('vat_bdt', 10, 2)->default(0.00);
                $table->decimal('total_bdt', 10, 2);
                $table->text('reason');
                $table->string('status', 32)->default('issued');
                $table->timestamps();
            });
        }

        // 6. Payment Webhook Logs (Idempotency)
        if (! Schema::hasTable('payment_webhook_logs')) {
            Schema::create('payment_webhook_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('tenant_id')->default(1)->index();
                $table->uuid('subscriber_id')->nullable()->index();
                $table->string('gateway', 32);
                $table->string('idempotency_key', 128);
                $table->string('payload_hash', 64);
                $table->json('payload');
                $table->string('status', 32)->default('processed');
                $table->timestamps();

                $table->unique(['gateway', 'idempotency_key']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_logs');
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notification_templates');

        Schema::table('subscribers', function (Blueprint $table) {
            if (Schema::hasColumn('subscribers', 'dunning_state')) {
                $table->dropColumn('dunning_state');
            }
            if (Schema::hasColumn('subscribers', 'suspended_at')) {
                $table->dropColumn('suspended_at');
            }
        });

        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'is_prorata')) {
                $table->dropColumn('is_prorata');
            }
            if (Schema::hasColumn('invoices', 'prorata_days')) {
                $table->dropColumn('prorata_days');
            }
            if (Schema::hasColumn('invoices', 'pdf_path')) {
                $table->dropColumn('pdf_path');
            }
        });
    }
};
