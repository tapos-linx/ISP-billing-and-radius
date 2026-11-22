import React, { useState } from 'react';
import { View, Text, TouchableOpacity, ActivityIndicator, StyleSheet, Alert } from 'react-native';
import { api } from '../services/api';

interface SpeedBoostCardProps {
  currentPlan: string;
  onBoostActivated?: () => void;
}

export const SpeedBoostCard: React.FC<SpeedBoostCardProps> = ({ currentPlan, onBoostActivated }) => {
  const [selectedSpeed, setSelectedSpeed] = useState<number>(50);
  const [selectedDuration, setSelectedDuration] = useState<number>(1);
  const [loading, setLoading] = useState<boolean>(false);
  const [activeBoost, setActiveBoost] = useState<string | null>(null);

  const handleActivateBoost = async () => {
    try {
      setLoading(true);
      const response = await api.triggerSpeedBoost({
        boost_speed_mbps: selectedSpeed,
        duration_hours: selectedDuration,
      });

      if (response.data.success) {
        setActiveBoost(response.data.boost_rate_limit);
        Alert.alert(
          '🚀 Speed Boost Activated!',
          `Your connection has been boosted to ${response.data.boost_rate_limit} for ${response.data.duration_hours} hour(s) via RFC 5176 CoA.`,
          [{ text: 'Great' }]
        );
        if (onBoostActivated) {
          onBoostActivated();
        }
      }
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to trigger speed boost';
      Alert.alert('Activation Failed', message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <View style={styles.card}>
      <View style={styles.header}>
        <Text style={styles.title}>⚡ Turbo Speed Boost (CoA)</Text>
        <Text style={styles.currentSpeedBadge}>Current: {currentPlan}</Text>
      </View>

      <Text style={styles.subtitle}>
        Instantly multiply your download speed without resetting your router. Powered by RFC 5176 Change of Authorization.
      </Text>

      {/* Speed Options */}
      <Text style={styles.sectionLabel}>Select Boost Speed</Text>
      <View style={styles.optionsRow}>
        {[50, 100, 200].map((speed) => (
          <TouchableOpacity
            key={speed}
            style={[styles.chip, selectedSpeed === speed && styles.chipActive]}
            onPress={() => setSelectedSpeed(speed)}
          >
            <Text style={[styles.chipText, selectedSpeed === speed && styles.chipTextActive]}>
              {speed} Mbps
            </Text>
          </TouchableOpacity>
        ))}
      </View>

      {/* Duration Options */}
      <Text style={styles.sectionLabel}>Select Duration</Text>
      <View style={styles.optionsRow}>
        {[1, 3, 6].map((hours) => (
          <TouchableOpacity
            key={hours}
            style={[styles.chip, selectedDuration === hours && styles.chipActive]}
            onPress={() => setSelectedDuration(hours)}
          >
            <Text style={[styles.chipText, selectedDuration === hours && styles.chipTextActive]}>
              {hours} Hour{hours > 1 ? 's' : ''}
            </Text>
          </TouchableOpacity>
        ))}
      </View>

      {/* Action Button */}
      <TouchableOpacity
        style={[styles.boostButton, loading && styles.boostButtonDisabled]}
        onPress={handleActivateBoost}
        disabled={loading}
      >
        {loading ? (
          <ActivityIndicator color="#0f172a" />
        ) : (
          <Text style={styles.boostButtonText}>
            {activeBoost ? `Active: ${activeBoost}` : `Activate ${selectedSpeed} Mbps Boost (1-Tap)`}
          </Text>
        )}
      </TouchableOpacity>
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
    borderColor: '#3b82f644',
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  title: {
    fontSize: 16,
    fontWeight: '700',
    color: '#60a5fa',
  },
  currentSpeedBadge: {
    fontSize: 11,
    backgroundColor: '#1e293b',
    color: '#94a3b8',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
  },
  subtitle: {
    fontSize: 12,
    color: '#94a3b8',
    marginVertical: 8,
    lineHeight: 18,
  },
  sectionLabel: {
    fontSize: 11,
    fontWeight: '600',
    color: '#64748b',
    marginTop: 8,
    marginBottom: 6,
    textTransform: 'uppercase',
  },
  optionsRow: {
    flexDirection: 'row',
    gap: 8,
    marginBottom: 6,
  },
  chip: {
    flex: 1,
    paddingVertical: 8,
    backgroundColor: '#1e293b',
    borderRadius: 8,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#334155',
  },
  chipActive: {
    backgroundColor: '#1d4ed8',
    borderColor: '#3b82f6',
  },
  chipText: {
    color: '#94a3b8',
    fontSize: 12,
    fontWeight: '600',
  },
  chipTextActive: {
    color: '#ffffff',
  },
  boostButton: {
    backgroundColor: '#3b82f6',
    borderRadius: 10,
    paddingVertical: 12,
    alignItems: 'center',
    marginTop: 14,
  },
  boostButtonDisabled: {
    opacity: 0.6,
  },
  boostButtonText: {
    color: '#ffffff',
    fontSize: 14,
    fontWeight: '700',
  },
});
