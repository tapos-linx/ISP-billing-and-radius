import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import Svg, { Circle } from 'react-native-svg';

interface FupDataRingProps {
  usedGb: number;
  totalGb: number;
  percentUsed: number;
  isThrottled: boolean;
  resetsAt: string;
}

export const FupDataRing: React.FC<FupDataRingProps> = ({
  usedGb,
  totalGb,
  percentUsed,
  isThrottled,
  resetsAt,
}) => {
  const size = 180;
  const strokeWidth = 14;
  const radius = (size - strokeWidth) / 2;
  const circumference = 2 * Math.PI * radius;
  const strokeDashoffset = circumference - (Math.min(100, Math.max(0, percentUsed)) / 100) * circumference;

  const ringColor = isThrottled
    ? '#ef4444' // Red
    : percentUsed > 80
    ? '#f59e0b' // Amber
    : '#06b6d4'; // Cyan

  return (
    <View style={styles.container}>
      <View style={styles.ringWrapper}>
        <Svg width={size} height={size}>
          {/* Background Track */}
          <Circle
            stroke="#1e293b"
            fill="none"
            cx={size / 2}
            cy={size / 2}
            r={radius}
            strokeWidth={strokeWidth}
          />
          {/* Progress Indicator */}
          <Circle
            stroke={ringColor}
            fill="none"
            cx={size / 2}
            cy={size / 2}
            r={radius}
            strokeWidth={strokeWidth}
            strokeDasharray={`${circumference} ${circumference}`}
            strokeDashoffset={strokeDashoffset}
            strokeLinecap="round"
            transform={`rotate(-90 ${size / 2} ${size / 2})`}
          />
        </Svg>
        <View style={styles.centerContent}>
          <Text style={styles.usedGbText}>{usedGb.toFixed(1)}</Text>
          <Text style={styles.totalGbText}>/ {totalGb} GB</Text>
          <Text style={[styles.percentText, { color: ringColor }]}>
            {percentUsed.toFixed(0)}% Used
          </Text>
        </View>
      </View>

      {isThrottled ? (
        <View style={styles.throttledBadge}>
          <Text style={styles.throttledText}>⚠️ FUP Throttled to 1M/1M</Text>
        </View>
      ) : (
        <Text style={styles.resetText}>Resets on: {new Date(resetsAt).toLocaleDateString()}</Text>
      )}
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    alignItems: 'center',
    paddingVertical: 16,
    backgroundColor: '#0f172a',
    borderRadius: 16,
    marginVertical: 8,
    borderWidth: 1,
    borderColor: '#1e293b',
  },
  ringWrapper: {
    width: 180,
    height: 180,
    justifyContent: 'center',
    alignItems: 'center',
  },
  centerContent: {
    position: 'absolute',
    alignItems: 'center',
  },
  usedGbText: {
    fontSize: 32,
    fontWeight: 'bold',
    color: '#ffffff',
  },
  totalGbText: {
    fontSize: 14,
    color: '#94a3b8',
    marginTop: -4,
  },
  percentText: {
    fontSize: 12,
    fontWeight: '600',
    marginTop: 4,
  },
  throttledBadge: {
    backgroundColor: '#7f1d1d',
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 12,
    marginTop: 12,
  },
  throttledText: {
    color: '#fca5a5',
    fontWeight: '600',
    fontSize: 12,
  },
  resetText: {
    color: '#64748b',
    fontSize: 12,
    marginTop: 10,
  },
});
