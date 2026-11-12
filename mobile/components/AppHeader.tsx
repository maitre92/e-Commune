import { MaterialCommunityIcons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { StyleSheet, Text, View } from 'react-native';
import { colors, mali } from '@/lib/theme';

/** Liseré tricolore vert-jaune-rouge (drapeau malien), sous le nom de l'application. */
export function MaliStripe({ width = 150, height = 4 }: { width?: number; height?: number }) {
  return (
    <View style={{ flexDirection: 'row', width, height, borderRadius: height, overflow: 'hidden' }}>
      <View style={{ flex: 1, backgroundColor: mali.green }} />
      <View style={{ flex: 1, backgroundColor: mali.yellow }} />
      <View style={{ flex: 1, backgroundColor: mali.red }} />
    </View>
  );
}

/** Titre d'en-tête : logo (bâtiment) + « e-Commune », liseré tricolore, puis le nom de l'écran. */
export function HeaderTitle({ title }: { title: string }) {
  return (
    <View style={styles.wrap}>
      <View style={styles.row}>
        <MaterialCommunityIcons name="bank" size={30} color="#ffffff" />
        <Text style={styles.brand}>e-Commune</Text>
      </View>
      <MaliStripe width={170} />
      <Text style={styles.title}>{title}</Text>
    </View>
  );
}

/** Fond vert dégradé de l'en-tête. */
export function HeaderBackground() {
  return <LinearGradient colors={['#0f6b3d', colors.dark]} start={{ x: 0, y: 0 }} end={{ x: 1, y: 1 }} style={StyleSheet.absoluteFill} />;
}

const styles = StyleSheet.create({
  wrap: { alignItems: 'center', gap: 4 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  brand: { color: '#ffffff', fontSize: 26, fontWeight: '800', letterSpacing: 0.3 },
  title: { color: '#ffffff', fontSize: 17, fontWeight: '700', marginTop: 2 },
});
