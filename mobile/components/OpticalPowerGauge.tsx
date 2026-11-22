import React from 'react';
import { View, Text, StyleSheet } from 'react-native';

interface OpticalPowerGaugeProps {
  rxPowerDbm: number;
  ontModel: string;
  opticalStatus: 'OPTIMAL' | 'CRITICAL_DEGRADED';
  upstreamPon: string;
  recommendation: string;
}

export const OpticalPowerGauge: React.FC<OpticalPowerGaugeProps> = ({
  rxPowerDbm,
  ontModel,
  opticalStatus,
  upstreamPon,
  recommendation,
}) => {
  // Normalize optical scale: min -32 dBm, max -8 dBm
  const minDbm = -32.0;
  const maxDbm = -8.0;
  const clampedDbm = Math.min(maxDbm, Math.max(minDbm, rxPowerDbm));
  const percentage = ((clampedDbm - minDbm) / (maxDbm - minDbm)) * 100;

  const isOptimal = rxPowerDbm >= -24.0 && rxPowerDbm <= -8.0;
  const isWarning = rxPowerDbm < -24.0 && rxPowerDbm >= -27.0;
  const isCritical = rxPowerDbm < -27.0 || opticalStatus === 'CRITICAL_DEGRADED';

  const statusColor = isOptimal ? '#10b981' : isWarning ? '#f59e0b' : '#ef4444';
  const statusLabel = isOptimal ? 'Optimal Signal' : isWarning ? 'Signal Degraded' : 'Critical Low Light';

  return (
    <View style={styles.card}>
      <View style={styles.headerRow}>
        <Text style={styles.title}>Fiber Optical Health (TR-069)</Text>
        <View style={[styles.badge, { backgroundColor: `${statusColor}22`, borderColor: statusColor }]}>
          <Text style={[styles.badgeText, { color: statusColor }]}>{statusLabel}</Text>
        </View>
      </View>

      <View style={styles.valueRow}>
        <Text style={[styles.valueText, { color: statusColor }]}>{rxPowerDbm.toFixed(2)}</Text>
        <Text style={styles.unitText}>dBm Rx Power</Text>
      </View>

      {/* Visual Bar Gauge */}
      <View style={styles.gaugeContainer}>
        <View style={styles.gaugeTrack}>
          <View style={[styles.gaugeIndicator, { left: `${percentage}%`, backgroundColor: statusColor }]} />
        </View>
        <View style={styles.scaleLabels}>
          <Text style={styles.scaleText}>-32 dBm (Cut)</Text>
          <Text style={styles.scaleText}>-25 dBm</Text>
          <Text style={styles.scaleText}>-8 dBm (Ideal)</Text>
        </View>
      </View>

      {/* Device & PON Details */}
      <View style={styles.detailGrid}>
        <View style={styles.detailItem}>
          <Text style={styles.detailLabel}>ONT Hardware</Text>
          <Text style={styles.detailValue}>{ontModel}</Text>
        </View>
        <View style={styles.detailItem}>
          <Text style={styles.detailLabel}>Connected PON</Text>
          <Text style={styles.detailValue}>{upstreamPon}</Text>
        </View>
      </View>

      {/* Advisory Note */}
      {isCritical && (
        <View style={styles.warningBox}>
          <Text style={styles.warningText}>⚠️ {recommendation}</Text>
        </View>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  card: {
    backgroundColor: '#0f172a',
    borderRadius: 16,
    padding: 16,
    marginVertical: 8,
    borderWidth: 1,
    borderColor: '#1e293b',
  },
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  title: {
    fontSize: 14,
    fontWeight: '600',
    color: '#cbd5e1',
  },
  badge: {
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 8,
    borderWidth: 1,
  },
  badgeText: {
    fontSize: 11,
    fontWeight: '700',
  },
  valueRow: {
    flexDirection: 'row',
    alignItems: 'baseline',
    marginVertical: 6,
  },
  valueText: {
    fontSize: 34,
    fontWeight: 'bold',
  },
  unitText: {
    fontSize: 14,
    color: '#94a3b8',
    marginLeft: 8,
  },
  gaugeContainer: {
    marginVertical: 12,
  },
  gaugeTrack: {
    height: 8,
    backgroundColor: '#1e293b',
    borderRadius: 4,
    position: 'relative',
    justifyContent: 'center',
  },
  gaugeIndicator: {
    width: 14,
    height: 14,
    borderRadius: 7,
    position: 'absolute',
    marginLeft: -7,
    borderWidth: 2,
    borderColor: '#ffffff',
  },
  scaleLabels: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: 6,
  },
  scaleText: {
    fontSize: 10,
    color: '#64748b',
  },
  detailGrid: {
    flexDirection: 'row',
    borderTopWidth: 1,
    borderTopColor: '#1e293b',
    paddingTop: 12,
    marginTop: 8,
  },
  detailItem: {
    flex: 1,
  },
  detailLabel: {
    fontSize: 11,
    color: '#64748b',
  },
  detailValue: {
    fontSize: 13,
    fontWeight: '600',
    color: '#e2e8f0',
    marginTop: 2,
  },
  warningBox: {
    backgroundColor: '#450a0a',
    borderRadius: 8,
    padding: 10,
    marginTop: 12,
    borderWidth: 1,
    borderColor: '#991b1b',
  },
  warningText: {
    color: '#fca5a5',
    fontSize: 12,
    lineHeight: 16,
  },
});
