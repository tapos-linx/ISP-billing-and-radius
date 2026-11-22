/**
 * Carrier Self-Care API Client
 * Manages communication with backend /api/v1/mobile/selfcare endpoints
 */

import {
  SubscriberProfileResponse,
  BandwidthUsageResponse,
  OntDiagnosticsResponse,
  SpeedBoostRequest,
  SpeedBoostResponse,
  DeviceRegistrationRequest,
} from '../types/selfcare-api';

export class SelfCareApiClient {
  private baseUrl: string;
  private authToken: string | null;

  constructor(baseUrl = 'http://localhost:8000/api/v1/mobile/selfcare', authToken: string | null = null) {
    this.baseUrl = baseUrl;
    this.authToken = authToken;
  }

  public setAuthToken(token: string) {
    this.authToken = token;
  }

  private async request<T>(endpoint: string, options: RequestInit = {}): Promise<T> {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...(this.authToken ? { Authorization: `Bearer ${this.authToken}` } : {}),
      ...(options.headers as Record<string, string> || {}),
    };

    const response = await fetch(`${this.baseUrl}${endpoint}`, {
      ...options,
      headers,
    });

    if (!response.ok) {
      const errorBody = await response.json().catch(() => ({ message: 'Network request failed' }));
      throw new Error(errorBody.message || `HTTP error ${response.status}`);
    }

    return response.json();
  }

  /**
   * Fetches subscriber profile, package details, and due status
   */
  public async getDashboard(subscriberId?: string): Promise<SubscriberProfileResponse> {
    const query = subscriberId ? `?subscriber_id=${encodeURIComponent(subscriberId)}` : '';
    return this.request<SubscriberProfileResponse>(`/dashboard${query}`);
  }

  /**
   * Fetches real-time FUP bandwidth consumption and daily trend
   */
  public async getUsage(subscriberId?: string): Promise<BandwidthUsageResponse> {
    const query = subscriberId ? `?subscriber_id=${encodeURIComponent(subscriberId)}` : '';
    return this.request<BandwidthUsageResponse>(`/usage${query}`);
  }

  /**
   * Fetches live TR-069 optical light diagnostics and WiFi telemetry
   */
  public async getOpticalDiagnostics(subscriberId?: string): Promise<OntDiagnosticsResponse> {
    const query = subscriberId ? `?subscriber_id=${encodeURIComponent(subscriberId)}` : '';
    return this.request<OntDiagnosticsResponse>(`/optical-diagnostics${query}`);
  }

  /**
   * Requests dynamic Speed Boost turbo multiplier via RFC 5176 CoA
   */
  public async triggerSpeedBoost(payload: SpeedBoostRequest): Promise<SpeedBoostResponse> {
    return this.request<SpeedBoostResponse>('/speed-boost', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }

  /**
   * Registers mobile device push token (FCM v1 / APNs)
   */
  public async registerDevice(payload: DeviceRegistrationRequest): Promise<{ status: string; message: string }> {
    return this.request<{ status: string; message: string }>('/device/register', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
  }
}

export const api = new SelfCareApiClient();
