import React, { useState, useEffect, useCallback } from 'react';
import {
  View,
  Text,
  ScrollView,
  RefreshControl,
  ActivityIndicator,
  StyleSheet,
  SafeAreaView,
  StatusBar,
} from 'react-native';
import { api } from '../services/api';
import {
  SubscriberProfileResponse,
  BandwidthUsageResponse,
  OntDiagnosticsResponse,
} from '../types/selfcare-api';
import { FupDataRing } from '../components/FupDataRing';
import { OpticalPowerGauge } from '../components/OpticalPowerGauge';
import { SpeedBoostCard } from '../components/SpeedBoostCard';
import { QuickPayCard } from '../components/QuickPayCard';

export const DashboardScreen: React.FC = () => {
  const [profile, setProfile] = useState<SubscriberProfileResponse['data'] | null>(null);
  const [usage, setUsage] = useState<BandwidthUsageResponse['data'] | null>(null);
  const [diagnostics, setDiagnostics] = useState<OntDiagnosticsResponse['data'] | null>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [refreshing, setRefreshing] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  const fetchDashboardData = useCallback(async () => {
    try {
      setError(null);
      const [profileRes, usageRes, diagRes] = await Promise.all([
        api.getDashboard().catch(() => null),
        api.getUsage().catch(() => null),
        api.getOpticalDiagnostics().catch(() => null),
      ]);

      if (profileRes?.data) setProfile(profileRes.data);
      if (usageRes?.data) setUsage(usageRes.data);
      if (diagRes?.data) setDiagnostics(diagRes.data);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Error fetching telemetry data');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    fetchDashboardData();
  }, [fetchDashboardData]);

  const onRefresh = () => {
    setRefreshing(true);
    fetchDashboardData();
  };

  if (loading) {
    return (
      <SafeAreaView style={styles.centerContainer}>
        <ActivityIndicator size="large" color="#3b82f6" />
        <Text style={styles.loadingText}>Connecting to Carrier Core...</Text>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      <StatusBar barStyle="light-content" backgroundColor="#0f172a" />
      <ScrollView
        contentContainerStyle={styles.scrollContent}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor="#3b82f6" />
        }
      >
        {/* Subscriber Header */}
        <View style={styles.headerCard}>
          <View>
            <Text style={styles.greeting}>Welcome back,</Text>
            <Text style={styles.subscriberName}>{profile?.name || 'Carrier Subscriber'}</Text>
            <Text style={styles.metaText}>
              ID: {profile?.subscriber_id || 'SUB-1001'}  •  {profile?.network.connection_type || 'FTTH PPPoE'}
            </Text>
          </View>
          <View style={[styles.statusPill, profile?.status === 'active' ? styles.activePill : styles.suspendedPill]}>
            <Text style={styles.statusPillText}>
              {(profile?.status || 'ONLINE').toUpperCase()}
            </Text>
          </View>
        </View>

        {error && (
          <View style={styles.errorBanner}>
            <Text style={styles.errorText}>Notice: Operating in offline mode. {error}</Text>
          </View>
        )}

        {/* 1. Quick Pay Card (bKash / Nagad) */}
        <QuickPayCard
          hasDue={profile?.billing.has_due ?? false}
          dueAmountBdt={profile?.billing.due_amount_bdt ?? 0}
          latestMushakNumber={profile?.billing.latest_mushak_number}
          dueDate={profile?.billing.due_date}
          subscriberId={profile?.subscriber_id || 'SUB-1001'}
          onPaymentSuccess={fetchDashboardData}
        />

        {/* 2. Visual FUP Data Ring */}
        <FupDataRing
          usedGb={usage?.used_gb ?? 142.5}
          totalGb={usage?.total_limit_gb ?? 300}
          percentUsed={usage?.percent_used ?? 47.5}
          isThrottled={usage?.is_throttled ?? false}
          resetsAt={usage?.cycle_resets_at ?? '2026-12-01'}
        />

        {/* 3. Turbo Speed Boost (CoA) */}
        <SpeedBoostCard
          currentPlan={profile?.bandwidth_profile || '20M/20M'}
          onBoostActivated={fetchDashboardData}
        />

        {/* 4. Live Optical Light Gauge (TR-069) */}
        <OpticalPowerGauge
          rxPowerDbm={diagnostics?.optical_rx_power_dbm ?? -21.45}
          ontModel={diagnostics?.ont_model ?? 'Huawei HG8546M (GPON)'}
          opticalStatus={diagnostics?.optical_status ?? 'OPTIMAL'}
          upstreamPon={diagnostics?.upstream_pon ?? 'OLT-DHK-01 / PON-4'}
          recommendation={diagnostics?.recommendation ?? 'Optical signal is within standard ITU-T G.984 parameters.'}
        />
      </ScrollView>
    </SafeAreaView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#020617',
  },
  centerContainer: {
    flex: 1,
    backgroundColor: '#020617',
    justifyContent: 'center',
    alignItems: 'center',
  },
  loadingText: {
    marginTop: 12,
    color: '#94a3b8',
    fontSize: 14,
  },
  scrollContent: {
    padding: 16,
    paddingBottom: 40,
  },
  headerCard: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: '#0f172a',
    borderRadius: 16,
    padding: 16,
    marginBottom: 8,
    borderWidth: 1,
    borderColor: '#1e293b',
  },
  greeting: {
    fontSize: 12,
    color: '#94a3b8',
  },
  subscriberName: {
    fontSize: 20,
    fontWeight: 'bold',
    color: '#f8fafc',
    marginTop: 2,
  },
  metaText: {
    fontSize: 11,
    color: '#64748b',
    marginTop: 4,
  },
  statusPill: {
    paddingHorizontal: 10,
    paddingVertical: 5,
    borderRadius: 12,
  },
  activePill: {
    backgroundColor: '#064e3b',
  },
  suspendedPill: {
    backgroundColor: '#7f1d1d',
  },
  statusPillText: {
    fontSize: 11,
    fontWeight: '700',
    color: '#6ee7b7',
  },
  errorBanner: {
    backgroundColor: '#3b82f622',
    borderColor: '#3b82f6',
    borderWidth: 1,
    borderRadius: 8,
    padding: 10,
    marginVertical: 6,
  },
  errorText: {
    color: '#93c5fd',
    fontSize: 11,
  },
});
