-- =============================================================================
-- Stage 20: Release 2.2 — Subscriber Self-Care Mobile & Push Gateway
-- Citus 16-Shard Distributed Schema for Mobile Devices & Push Notifications
-- Co-located with 'subscribers' on 'subscriber_id' for sub-millisecond lookups
-- =============================================================================

CREATE TABLE IF NOT EXISTS subscriber_devices (
    id SERIAL,
    subscriber_id UUID NOT NULL,
    tenant_id INT NOT NULL,
    device_uuid VARCHAR(64) NOT NULL,
    platform VARCHAR(16) NOT NULL DEFAULT 'android', -- android | ios
    fcm_token TEXT NOT NULL,
    apns_token TEXT,
    device_name VARCHAR(128),
    app_version VARCHAR(32) DEFAULT '2.2.0',
    biometric_public_key TEXT,
    is_active BOOLEAN NOT NULL DEFAULT true,
    last_seen_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id, subscriber_id)
);

CREATE INDEX IF NOT EXISTS idx_sub_devices_uuid ON subscriber_devices(device_uuid);
CREATE INDEX IF NOT EXISTS idx_sub_devices_fcm ON subscriber_devices(fcm_token);

-- Co-locate with subscribers table on subscriber_id
SELECT create_distributed_table('subscriber_devices', 'subscriber_id', colocate_with := 'subscribers');

CREATE TABLE IF NOT EXISTS mobile_push_notifications (
    id BIGSERIAL,
    subscriber_id UUID NOT NULL,
    tenant_id INT NOT NULL,
    device_uuid VARCHAR(64),
    channel VARCHAR(64) NOT NULL, -- billing | quota | network | ticket | promo
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    data_payload JSONB DEFAULT '{}'::jsonb,
    status VARCHAR(32) NOT NULL DEFAULT 'queued', -- queued | sent | delivered | failed
    fcm_message_id VARCHAR(128),
    error_message TEXT,
    sent_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id, subscriber_id)
);

CREATE INDEX IF NOT EXISTS idx_push_notif_status ON mobile_push_notifications(status);
CREATE INDEX IF NOT EXISTS idx_push_notif_channel ON mobile_push_notifications(channel);

-- Co-locate with subscribers table on subscriber_id
SELECT create_distributed_table('mobile_push_notifications', 'subscriber_id', colocate_with := 'subscribers');
