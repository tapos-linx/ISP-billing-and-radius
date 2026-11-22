/**
 * Release 2.2: Subscriber Self-Care Mobile Application API Contracts
 * Compatible with React Native (TypeScript) and Flutter (via json_serializable / Dart models)
 */

export interface SubscriberProfileResponse {
  status: 'success';
  data: {
    subscriber_id: string;
    username: string;
    name: string;
    mobile: string;
    status: 'active' | 'expired' | 'suspended';
    bandwidth_profile: string; // e.g. "20M/20M"
    expires_at: string | null;
    days_remaining: number;
    tariff: {
      tariff_id: number;
      name: string;
      rate_limit: string;
      price_bdt: number;
      validity_days: number;
    } | null;
    billing: {
      has_due: boolean;
      due_amount_bdt: number;
      latest_mushak_number?: string;
      due_date?: string;
    };
    network: {
      ip_address: string;
      connection_type: string; // e.g. "FTTH GPON PPPoE"
      service_status: 'ONLINE' | 'RESTRICTED';
    };
  };
}

export interface BandwidthUsageResponse {
  status: 'success';
  data: {
    subscriber_id: string;
    used_bytes: number;
    used_gb: number;
    total_limit_bytes: number;
    total_limit_gb: number;
    percent_used: number;
    is_throttled: boolean;
    cycle_resets_at: string;
    daily_trend: Array<{
      date: string;
      download_gb: number;
      upload_gb: number;
    }>;
  };
}

export interface OntDiagnosticsResponse {
  status: 'success';
  data: {
    subscriber_id: string;
    ont_model: string;
    optical_rx_power_dbm: number; // e.g. -21.45 dBm
    optical_status: 'OPTIMAL' | 'CRITICAL_DEGRADED';
    wifi_status: {
      ssid_24ghz: string;
      ssid_5ghz: string;
      active_devices: number;
      channel_interference: 'LOW' | 'MEDIUM' | 'HIGH';
    };
    upstream_pon: string;
    recommendation: string;
    tested_at: string;
  };
}

export interface SpeedBoostRequest {
  subscriber_id?: string;
  boost_speed_mbps: number; // e.g. 50, 100, 200
  duration_hours: number;   // e.g. 1 to 24
}

export interface SpeedBoostResponse {
  status: 'success';
  message: string;
  data: {
    success: boolean;
    boost_rate_limit: string; // e.g. "100M/100M"
    duration_hours: number;
    expires_at: string;
    coa_dispatched: boolean;
  };
}

export interface DeviceRegistrationRequest {
  subscriber_id: string;
  tenant_id?: number;
  device_uuid: string;
  platform: 'android' | 'ios';
  fcm_token: string;
  device_name?: string;
  app_version?: string;
}

export interface PushNotificationRecord {
  id: number;
  subscriber_id: string;
  channel: 'billing' | 'quota' | 'network' | 'ticket' | 'promo';
  title: string;
  body: string;
  data_payload: Record<string, unknown>;
  status: 'queued' | 'sent' | 'delivered' | 'failed';
  sent_at: string | null;
  created_at: string;
}
