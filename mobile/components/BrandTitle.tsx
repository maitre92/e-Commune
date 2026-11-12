import { StyleSheet, Text, View } from 'react-native';
import { colors } from '@/lib/theme';

/** Logo textuel e-Commune, bicolore comme sur la plateforme Web. */
export default function BrandTitle({ size = 34, onDark = false }: { size?: number; onDark?: boolean }) {
  return (
    <View style={styles.row}>
      <Text style={[styles.text, { fontSize: size, color: onDark ? colors.lightBlue : colors.primary }]}>e-</Text>
      <Text style={[styles.text, { fontSize: size, color: colors.accent }]}>COMMUNE</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'baseline' },
  text: { fontWeight: '900', letterSpacing: 1 },
});
