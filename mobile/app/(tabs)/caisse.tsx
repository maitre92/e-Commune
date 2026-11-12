import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { Alert, RefreshControl, ScrollView, StyleSheet, View } from 'react-native';
import { Button, Card, HelperText, Text } from 'react-native-paper';
import { api, apiErrorMessage } from '@/lib/api';
import { formatFcfa } from '@/lib/format';
import { colors, statusColors } from '@/lib/theme';

interface CaisseState {
  toute_la_mairie: boolean;
  peut_recevoir: boolean;
  types: ('vignette' | 'extrait' | 'extrait_mariage')[];
  aujourd_hui: { montant: number; nombre: number; vignettes: number; extraits: number; extraits_mariage: number };
  a_verser: { agent_id: number; agent: string; fonction: string | null; nombre: number; montant: number }[];
  recettes: { quittance: string; heure: string; objet: string; agent: string | null; montant: number; versee: boolean }[];
}

/**
 * Caisse : l'agent voit ce qu'il a encaissé aujourd'hui et ce qu'il doit verser ; le maire (ou le secrétaire
 * général, ou le caissier) voit toute la caisse de la mairie et reçoit le versement de chaque agent.
 */
export default function CaisseScreen() {
  const [state, setState] = useState<CaisseState | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    setError(null);
    try {
      const { data } = await api.get<CaisseState>('/caisse');
      setState(data);
    } catch (e) {
      setError(apiErrorMessage(e));
    }
  }, []);

  // Rechargée à chaque fois que l'onglet s'affiche (nouvelles ventes, versements).
  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  const refresh = async () => {
    setRefreshing(true);
    await load();
    setRefreshing(false);
  };

  const receive = (agentId: number, agent: string, montant: number) =>
    Alert.alert('Recevoir le versement ?', `Confirmez avoir reçu ${formatFcfa(montant)} de ${agent}.`, [
      { text: 'Annuler', style: 'cancel' },
      {
        text: 'Argent reçu',
        onPress: async () => {
          try {
            const { data } = await api.post<{ message: string }>('/caisse/versements', { agent_id: agentId });
            Alert.alert('Versement enregistré', data.message);
            load();
          } catch (e) {
            setError(apiErrorMessage(e));
          }
        },
      },
    ]);

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content} refreshControl={<RefreshControl refreshing={refreshing} onRefresh={refresh} />}>
      <HelperText type="error" visible={!!error} style={{ fontSize: 14 }}>
        {error}
      </HelperText>

      {state ? (
        <>
          <Card style={[styles.card, { backgroundColor: colors.primary }]}>
            <Card.Content style={{ gap: 2 }}>
              <Text style={styles.heroLabel}>{state.toute_la_mairie ? 'Encaissé aujourd\'hui — toute la mairie' : 'J\'ai encaissé aujourd\'hui'}</Text>
              <Text style={styles.heroAmount}>{formatFcfa(state.aujourd_hui.montant)}</Text>
              <Text style={styles.heroSub}>
                {[
                  state.types.includes('vignette') ? `${state.aujourd_hui.vignettes} vignette(s)` : null,
                  state.types.includes('extrait') ? `${state.aujourd_hui.extraits} extrait(s) de naissance` : null,
                  state.types.includes('extrait_mariage') ? `${state.aujourd_hui.extraits_mariage} extrait(s) de mariage` : null,
                ]
                  .filter(Boolean)
                  .join(' · ')}
              </Text>
            </Card.Content>
          </Card>

          <Card style={styles.card}>
            <Card.Title title={state.toute_la_mairie ? 'À verser par agent' : 'Ce que je dois verser'} titleStyle={styles.cardTitle} />
            <Card.Content style={{ gap: 8 }}>
              {state.a_verser.length === 0 ? <Text style={styles.muted}>Rien à verser : tout a été versé.</Text> : null}
              {state.a_verser.map((row) => (
                <View key={row.agent_id} style={styles.agentRow}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.strong}>{row.agent}</Text>
                    <Text style={styles.muted}>{row.fonction} · {row.nombre} recette(s)</Text>
                  </View>
                  <Text style={styles.amount}>{formatFcfa(row.montant)}</Text>
                  {state.peut_recevoir ? (
                    <Button mode="contained" compact onPress={() => receive(row.agent_id, row.agent, row.montant)}>
                      Reçu
                    </Button>
                  ) : null}
                </View>
              ))}
            </Card.Content>
          </Card>

          <Card style={styles.card}>
            <Card.Title title="Recettes du jour" titleStyle={styles.cardTitle} />
            <Card.Content>
              {state.recettes.length === 0 ? <Text style={styles.muted}>Aucune recette aujourd'hui.</Text> : null}
              {state.recettes.map((r) => (
                <View key={r.quittance} style={styles.receipt}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.strong}>{r.quittance} · {r.heure}</Text>
                    <Text style={styles.muted}>{r.objet}{state.toute_la_mairie && r.agent ? ` · ${r.agent}` : ''}</Text>
                  </View>
                  <View style={{ alignItems: 'flex-end' }}>
                    <Text style={styles.strong}>{formatFcfa(r.montant)}</Text>
                    <Text style={[styles.tag, r.versee ? styles.tagOk : styles.tagWait]}>{r.versee ? 'Versée' : 'Chez l\'agent'}</Text>
                  </View>
                </View>
              ))}
            </Card.Content>
          </Card>
        </>
      ) : null}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  content: { padding: 16, gap: 12 },
  card: { borderRadius: 14 },
  cardTitle: { fontWeight: '800' },
  heroLabel: { color: 'rgba(255,255,255,0.85)' },
  heroAmount: { color: '#ffffff', fontSize: 30, fontWeight: '900' },
  heroSub: { color: 'rgba(255,255,255,0.85)', fontSize: 13 },
  agentRow: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingVertical: 6, borderBottomWidth: 1, borderBottomColor: colors.border },
  receipt: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: colors.border },
  strong: { fontWeight: '700', color: colors.text },
  muted: { color: colors.muted, fontSize: 13 },
  amount: { fontWeight: '800', color: colors.dark },
  tag: { fontSize: 11, fontWeight: '700', paddingHorizontal: 8, paddingVertical: 2, borderRadius: 10, overflow: 'hidden', marginTop: 2 },
  tagOk: { backgroundColor: statusColors.success.bg, color: statusColors.success.text },
  tagWait: { backgroundColor: statusColors.warning.bg, color: statusColors.warning.text },
});
