import { MaterialCommunityIcons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import { Image, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { MaliStripe } from '@/components/AppHeader';
import { useAuth } from '@/context/AuthContext';
import { api } from '@/lib/api';
import { formatFcfa } from '@/lib/format';
import { colors, tints } from '@/lib/theme';

interface Summary {
  toute_la_mairie: boolean;
  aujourd_hui: { montant: number; nombre: number };
  a_verser: { montant: number }[];
}

type Tint = (typeof tints)[keyof typeof tints];

// Photo de la mairie (assets/images/mairie.jpg).
const MAIRIE = require('../../assets/images/mairie.jpg');
// La même mairie en filigrane vert menthe, fondue dans le fond (bas de l'accueil).
const MAIRIE_FILIGRANE = require('../../assets/images/mairie-filigrane.png');

/**
 * Accueil : carte de bienvenue (nom, fonction, mairie, illustration de la mairie), chiffres du jour, et grandes
 * cartes vers les guichets permis au compte (vignette, acte de naissance, caisse).
 */
export default function AccueilScreen() {
  const router = useRouter();
  const { user, refreshUser } = useAuth();
  const [summary, setSummary] = useState<Summary | null>(null);
  const [refreshing, setRefreshing] = useState(false);
  // L'accueil ne défile que si les cartes ne tiennent pas dans l'écran (très petit téléphone).
  const [viewHeight, setViewHeight] = useState(0);
  const [contentHeight, setContentHeight] = useState(0);
  const can = useCallback((permission: string) => user?.permissions.includes(permission) ?? false, [user]);

  const load = useCallback(async () => {
    if (!can('caisse.view_own')) {
      return;
    }
    try {
      const { data } = await api.get<Summary>('/caisse');
      setSummary(data);
    } catch {
      setSummary(null);
    }
  }, [can]);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  const refresh = async () => {
    setRefreshing(true);
    await refreshUser().catch(() => undefined);
    await load();
    setRefreshing(false);
  };

  const hour = new Date().getHours();
  const aVerser = summary?.a_verser.reduce((total, row) => total + row.montant, 0) ?? 0;

  const actions = [
    can('demandes-vgt.create') && { tint: tints.green, icon: 'card-account-details-outline', deco: 'motorbike', label: 'Vendre une vignette', hint: 'Saisir le N° Engin, encaisser, imprimer', href: '/vignette' },
    can('actes-naissance.view') && { tint: tints.blue, icon: 'baby-face-outline', deco: 'file-document-outline', label: 'Acte de naissance', hint: 'Saisir un acte ou imprimer un extrait', href: '/actes' },
    can('actes-mariage.view') && { tint: tints.red, icon: 'ring', deco: 'heart-outline', label: 'Acte de mariage', hint: 'Saisir un acte ou imprimer un extrait', href: '/mariages' },
    can('caisse.view_own') && { tint: tints.yellow, icon: 'cash-register', deco: 'circle-multiple', label: summary?.toute_la_mairie ? 'Caisse de la mairie' : 'Ma caisse', hint: 'Recettes du jour et versements', href: '/caisse' },
  ].filter(Boolean) as { tint: Tint; icon: string; deco: string; label: string; hint: string; href: string }[];

  return (
    <View style={styles.screen}>
      {/* La mairie en filigrane : décor fixe en bas de l'écran, derrière les cartes (ne fait pas défiler). */}
      <View pointerEvents="none" style={styles.watermark}>
        <Image source={MAIRIE_FILIGRANE} style={{ width: '100%', height: '100%' }} resizeMode="contain" />
      </View>
      <ScrollView
        style={{ flex: 1 }}
        contentContainerStyle={styles.content}
        scrollEnabled={contentHeight > viewHeight + 1}
        bounces={false}
        overScrollMode="never"
        onLayout={(event) => setViewHeight(event.nativeEvent.layout.height)}
        onContentSizeChange={(_, height) => setContentHeight(height)}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={refresh} />}>
      {/* Carte de bienvenue */}
      <View>
        <View style={styles.hero}>
          {/* Le bâtiment de la mairie à droite, fondu dans le vert (comme la maquette). */}
          <Image source={MAIRIE} style={styles.heroImage} resizeMode="cover" />
          <LinearGradient
            colors={['#12643a', '#12643aee', '#12643a99', '#12643a00']}
            locations={[0, 0.42, 0.62, 0.95]}
            start={{ x: 0, y: 0.5 }}
            end={{ x: 1, y: 0.5 }}
            style={StyleSheet.absoluteFill}
          />
          <View style={{ flex: 1, gap: 4, maxWidth: '62%' }}>
            <Text style={styles.hello} numberOfLines={2}>{hour >= 18 ? 'Bonsoir' : 'Bonjour'}, {user?.name}</Text>
            <Text style={styles.role}>
              {user?.role?.label}
              {user?.institution ? ` · ${user.institution.name}` : ''}
            </Text>
          </View>
          <View style={styles.heroStripe}>
            <MaliStripe width={190} height={5} />
          </View>
        </View>
      </View>

      {summary ? (
        <View style={styles.stats}>
          <View style={styles.stat}>
            <Text style={styles.statLabel}>Encaissé aujourd'hui</Text>
            <Text style={styles.statValue}>{formatFcfa(summary.aujourd_hui.montant)}</Text>
          </View>
          <View style={styles.stat}>
            <Text style={styles.statLabel}>{summary.toute_la_mairie ? 'À recevoir des agents' : 'À verser'}</Text>
            <Text style={[styles.statValue, aVerser > 0 ? { color: '#b45309' } : null]}>{formatFcfa(aVerser)}</Text>
          </View>
        </View>
      ) : null}

      {actions.map((action) => (
        <Pressable
          key={action.href}
          onPress={() => router.navigate(action.href as never)}
          style={({ pressed }) => [styles.action, { backgroundColor: action.tint.bg, borderLeftColor: action.tint.border }, pressed && { opacity: 0.75 }]}>
          <View style={[styles.actionIcon, { backgroundColor: action.tint.circle }]}>
            <MaterialCommunityIcons name={action.icon as never} size={32} color={action.tint.icon} />
          </View>
          <View style={{ flex: 1 }}>
            <Text style={styles.actionLabel}>{action.label}</Text>
            <Text style={styles.actionHint}>{action.hint}</Text>
          </View>
          <MaterialCommunityIcons name={action.deco as never} size={46} color={action.tint.soft} />
          <MaterialCommunityIcons name="chevron-right" size={28} color={colors.dark} />
        </Pressable>
      ))}

      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  content: { padding: 16, gap: 12 },
  hero: { borderRadius: 18, padding: 18, paddingBottom: 26, flexDirection: 'row', alignItems: 'center', overflow: 'hidden', minHeight: 140, backgroundColor: '#12643a' },
  heroImage: { position: 'absolute', right: 0, top: 0, bottom: 0, width: '62%', height: '100%' },
  hello: { color: '#ffffff', fontSize: 23, fontWeight: '900' },
  role: { color: 'rgba(255,255,255,0.85)', fontSize: 14 },
  heroStripe: { position: 'absolute', right: -10, bottom: 10, transform: [{ rotate: '-6deg' }] },
  stats: { flexDirection: 'row', gap: 10 },
  stat: { flex: 1, backgroundColor: '#ffffff', borderRadius: 14, padding: 12, gap: 2 },
  statLabel: { color: colors.muted, fontSize: 12 },
  statValue: { color: colors.dark, fontSize: 18, fontWeight: '900' },
  action: { flexDirection: 'row', alignItems: 'center', gap: 12, borderRadius: 16, padding: 16, borderLeftWidth: 5, elevation: 1, shadowColor: '#000', shadowOpacity: 0.06, shadowRadius: 4, shadowOffset: { width: 0, height: 2 } },
  actionIcon: { width: 58, height: 58, borderRadius: 29, alignItems: 'center', justifyContent: 'center' },
  actionLabel: { fontSize: 19, fontWeight: '800', color: '#111827' },
  actionHint: { color: '#4b5563', fontSize: 13, marginTop: 2 },
  watermark: { position: 'absolute', left: 0, right: 0, bottom: 0, aspectRatio: 720 / 234 },
});
