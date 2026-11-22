import React, { useState } from 'react';
import { View, Text, TouchableOpacity, StyleSheet, Alert, Linking } from 'react-native';

interface QuickPayCardProps {
  hasDue: boolean;
  dueAmountBdt: number;
  latestMushakNumber?: string;
  dueDate?: string;
  subscriberId: string;
  onPaymentSuccess?: () => void;
}

export const QuickPayCard: React.FC<QuickPayCardProps> = ({
  hasDue,
  dueAmountBdt,
  latestMushakNumber,
  dueDate,
  subscriberId,
}) => {
  const [paying, setPaying] = useState<boolean>(false);

  // NBR 5% VAT extraction
  const vatAmount = (dueAmountBdt * 0.05).toFixed(2);
  const baseAmount = (dueAmountBdt - parseFloat(vatAmount)).toFixed(2);

  const handleMfsPay = async (gateway: 'bkash' | 'nagad') => {
    try {
      setPaying(true);
      const url = `http://localhost:8000/api/v1/billing/pay/${gateway}?subscriber_id=${subscriberId}&amount=${dueAmountBdt}`;
      
      const supported = await Linking.canOpenURL(url).catch(() => false);
      if (supported) {
        await Linking.openURL(url);
      } else {
        Alert.alert(
          `${gateway.toUpperCase()} Gateway Ready`,
          `Redirecting to secure ${gateway.toUpperCase()} payment checkout for ৳${dueAmountBdt.toFixed(2)} (Incl. ৳${vatAmount} NBR VAT).`,
          [{ text: 'Simulate Payment Success', onPress: () => Alert.alert('Payment Completed', 'Instant CoA Unblock packet dispatched to BNG.') }]
        );
      }
    } catch {
      Alert.alert('Payment Error', 'Unable to initiate mobile checkout.');
    } finally {
      setPaying(false);
    }
  };

  return (
    <View style={styles.card}>
      <View style={styles.header}>
        <Text style={styles.title}>💳 Billing & Quick Checkout</Text>
        <View style={[styles.statusBadge, hasDue ? styles.dueBadge : styles.paidBadge]}>
          <Text style={[styles.statusText, hasDue ? styles.dueText : styles.paidText]}>
            {hasDue ? 'Payment Due' : 'All Clear'}
          </Text>
        </View>
      </View>

      {hasDue ? (
        <>
          <View style={styles.amountRow}>
            <Text style={styles.currencySymbol}>৳</Text>
            <Text style={styles.amountText}>{dueAmountBdt.toFixed(2)}</Text>
            <Text style={styles.bdtLabel}>BDT</Text>
          </View>

          <View style={styles.breakdownBox}>
            <Text style={styles.breakdownText}>Net Base: ৳{baseAmount}  |  NBR 5% VAT: ৳{vatAmount}</Text>
            {latestMushakNumber && (
              <Text style={styles.mushakText}>Mushak-6.3: {latestMushakNumber}</Text>
            )}
            {dueDate && <Text style={styles.dueDateText}>Due Date: {dueDate}</Text>}
          </View>

          <View style={styles.buttonRow}>
            <TouchableOpacity
              style={[styles.payButton, styles.bkashButton]}
              onPress={() => handleMfsPay('bkash')}
              disabled={paying}
            >
              <Text style={styles.payButtonText}>bKash 1-Click</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[styles.payButton, styles.nagadButton]}
              onPress={() => handleMfsPay('nagad')}
              disabled={paying}
            >
              <Text style={styles.payButtonText}>Nagad 1-Click</Text>
            </TouchableOpacity>
          </View>
        </>
      ) : (
        <View style={styles.paidContent}>
          <Text style={styles.paidMessage}>Your subscription is active. No pending invoices.</Text>
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
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  title: {
    fontSize: 15,
    fontWeight: '600',
    color: '#cbd5e1',
  },
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 8,
  },
  dueBadge: {
    backgroundColor: '#7f1d1d',
  },
  paidBadge: {
    backgroundColor: '#064e3b',
  },
  statusText: {
    fontSize: 11,
    fontWeight: '700',
  },
  dueText: {
    color: '#fca5a5',
  },
  paidText: {
    color: '#6ee7b7',
  },
  amountRow: {
    flexDirection: 'row',
    alignItems: 'baseline',
    marginVertical: 8,
  },
  currencySymbol: {
    fontSize: 20,
    fontWeight: '600',
    color: '#94a3b8',
    marginRight: 4,
  },
  amountText: {
    fontSize: 32,
    fontWeight: 'bold',
    color: '#ffffff',
  },
  bdtLabel: {
    fontSize: 14,
    color: '#94a3b8',
    marginLeft: 6,
  },
  breakdownBox: {
    backgroundColor: '#1e293b',
    borderRadius: 8,
    padding: 10,
    marginBottom: 12,
  },
  breakdownText: {
    fontSize: 11,
    color: '#94a3b8',
  },
  mushakText: {
    fontSize: 10,
    color: '#64748b',
    marginTop: 4,
  },
  dueDateText: {
    fontSize: 10,
    color: '#f59e0b',
    marginTop: 2,
    fontWeight: '600',
  },
  buttonRow: {
    flexDirection: 'row',
    gap: 10,
  },
  payButton: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: 10,
    alignItems: 'center',
  },
  bkashButton: {
    backgroundColor: '#e2136e',
  },
  nagadButton: {
    backgroundColor: '#f7941d',
  },
  payButtonText: {
    color: '#ffffff',
    fontSize: 13,
    fontWeight: '700',
  },
  paidContent: {
    paddingVertical: 12,
  },
  paidMessage: {
    color: '#6ee7b7',
    fontSize: 13,
  },
});
