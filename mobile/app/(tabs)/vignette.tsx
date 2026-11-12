import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useFocusEffect } from 'expo-router';
import { useCallback, useEffect, useRef, useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { Button, Card, Chip, HelperText, Text, TextInput } from 'react-native-paper';
import { api, apiErrorMessage } from '@/lib/api';
import { formatFcfa } from '@/lib/format';
import PreviewModal from '@/components/PreviewModal';
import { colors, statusColors } from '@/lib/theme';
import type { GuichetInfo, Vignette } from '@/types/api';

/**
 * Guichet vignette, comme au guichet réel : l'agent recopie le N° Engin de l'ancienne vignette ou
 * des papiers de la moto, touche la cylindrée (présélectionnée si la moto est connue), le citoyen paie, la
 * vignette s'imprime. Le total suit les tarifs et la pénalité de retard de la mairie. En dessous, les dernières
 * vignettes délivrées, avec l'œil pour les revoir et les réimprimer.
 */
export default function VignetteScreen() {
  // Aperçu avant impression (adresse de la page imprimable, ou null).
  const [preview, setPreview] = useState<string | null>(null);
  const [info, setInfo] = useState<GuichetInfo | null>(null);
  const [ch, setCh] = useState('');
  const [category, setCategory] = useState<string | null>(null);
  const [holder, setHolder] = useState('');
  const [holderPhone, setHolderPhone] = useState('');
  const [arrear, setArrear] = useState(false);
  const [delivered, setDelivered] = useState<Vignette | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  // Correction d'un CH mal recopié sur la vignette tout juste délivrée.
  const [fixing, setFixing] = useState(false);
  const [fixCh, setFixCh] = useState('');
  const [recent, setRecent] = useState<Vignette[]>([]);
  const [known, setKnown] = useState<string | null>(null);
  const lookupTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const load = useCallback(async () => {
    try {
      const { data } = await api.get<GuichetInfo>('/vignettes/guichet');
      setInfo(data);
      setCategory(data.categories.length === 1 ? data.categories[0].nom : null);
    } catch (e) {
      setError(apiErrorMessage(e));
    }
  }, []);

  const loadRecent = useCallback(async () => {
    try {
      const { data } = await api.get<{ data: Vignette[] }>('/vignettes');
      setRecent(data.data.slice(0, 10));
    } catch {
      setRecent([]);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  useFocusEffect(
    useCallback(() => {
      loadRecent();
    }, [loadRecent]),
  );

  // CH déjà connu : sa cylindrée est présélectionnée.
  const changeCh = (value: string) => {
    setCh(value.toUpperCase());
    setKnown(null);
    if (lookupTimer.current) {
      clearTimeout(lookupTimer.current);
    }
    if (value.trim().length < 3) {
      return;
    }
    lookupTimer.current = setTimeout(async () => {
      try {
        const { data } = await api.get<GuichetInfo>('/vignettes/guichet', { params: { cadre: value } });
        if (data.moto) {
          setKnown(`Moto déjà connue${data.moto.derniere_vignette ? ` · dernière vignette ${data.moto.derniere_vignette}` : ''}`);
          if (data.categorie) {
            setCategory(data.categorie);
          }
        }
      } catch {
        // Hors ligne : l'agent choisit lui-même.
      }
    }, 400);
  };

  const year = (info?.annee ?? new Date().getFullYear()) - (arrear ? 1 : 0);
  const chosen = info?.categories.find((c) => c.nom === category) ?? null;
  const late = info?.retard.annees_en_retard.includes(year) ?? false;
  const penalty = !chosen || !late || !info ? 0 : info.retard.mode === 'double' ? chosen.montant : info.retard.mode === 'fixe' ? info.retard.montant_fixe : 0;
  const lateHint = info
    ? `Après le ${info.retard.date_limite ?? '—'} : ${info.retard.mode === 'double' ? 'prix doublé' : info.retard.mode === 'fixe' ? `+ ${formatFcfa(info.retard.montant_fixe)}` : 'pas de pénalité'}`
    : '';

  const deliver = async () => {
    if (!ch.trim()) {
      setError('Saisissez le N° Engin de la moto.');
      return;
    }
    if (info && info.categories.length > 1 && !category) {
      setError('Choisissez la cylindrée de la moto.');
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const { data } = await api.post<{ data: Vignette }>('/vignettes/guichet', {
        chassis_number: ch,
        vgt_year: year,
        type_or_brand: category,
        holder_name: holder || null,
        holder_phone: holderPhone || null,
      });
      setDelivered(data.data);
      setCh('');
      setHolder('');
      setHolderPhone('');
      setArrear(false);
      setKnown(null);
      setCategory(info && info.categories.length === 1 ? info.categories[0].nom : null);
      setPreview(data.data.carte_url);
      loadRecent();
    } catch (e) {
      setError(apiErrorMessage(e));
    } finally {
      setBusy(false);
    }
  };

  const correct = async () => {
    if (!delivered) {
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const { data } = await api.put<{ data: Vignette }>(`/vignettes/${delivered.id}/correction`, { chassis_number: fixCh, holder_name: delivered.detenteur, holder_phone: delivered.telephone_detenteur });
      setDelivered(data.data);
      setFixing(false);
      setPreview(data.data.carte_url);
    } catch (e) {
      setError(apiErrorMessage(e));
    } finally {
      setBusy(false);
    }
  };

  const reprint = async () => {
    if (delivered) {
      try {
        setPreview(delivered.carte_url);
      } catch (e) {
        setError(apiErrorMessage(e, 'Impression impossible.'));
      }
    }
  };

  return (
    <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={{ flex: 1 }}>
      <ScrollView style={styles.screen} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
        <Card style={styles.card}>
          <Card.Content style={{ gap: 12 }}>
            <Text variant="titleMedium" style={styles.title}>Vignette {info?.annee ?? ''}</Text>
            <TextInput
              mode="outlined"
              label="N° Engin"
              value={ch}
              onChangeText={changeCh}
              placeholder="Recopier le N° Engin"
              autoCapitalize="characters"
              autoCorrect={false}
              style={styles.ch}
              onSubmitEditing={deliver}
              returnKeyType="done"
            />

            {known ? <Text style={{ color: statusColors.success.text }}>{known}</Text> : null}

            {info && info.categories.length > 1 ? (
              <View style={{ gap: 6 }}>
                <Text style={styles.strong}>Cylindrée de la moto</Text>
                <View style={styles.chips}>
                  {info.categories.map((c) => (
                    <Chip key={c.nom} selected={category === c.nom} onPress={() => setCategory(c.nom)} showSelectedCheck style={category === c.nom ? styles.chipOn : undefined}>
                      {`${c.nom} · ${formatFcfa(c.montant)}`}
                    </Chip>
                  ))}
                </View>
              </View>
            ) : null}

            <TextInput mode="outlined" dense label="Nom du détenteur (verso, facultatif)" value={holder} onChangeText={setHolder} />
            <TextInput mode="outlined" dense label="Numéro du détenteur (verso, facultatif)" value={holderPhone} onChangeText={setHolderPhone} keyboardType="phone-pad" placeholder="70 00 12 34" />

            <Chip icon={arrear ? 'check' : 'calendar-alert'} selected={arrear} onPress={() => setArrear((v) => !v)} style={{ alignSelf: 'flex-start' }}>
              {`Arriéré ${(info?.annee ?? new Date().getFullYear()) - 1}`}
            </Chip>

            <View style={styles.total}>
              <Text style={styles.muted}>{lateHint}</Text>
              <Text style={styles.totalValue}>
                À payer : {chosen ? formatFcfa(chosen.montant + penalty) : '—'}
                {penalty > 0 ? <Text style={styles.lateText}>{'  '}(dont {formatFcfa(penalty)} de retard)</Text> : null}
              </Text>
            </View>

            <Button mode="contained" icon="printer" onPress={deliver} loading={busy} disabled={busy} contentStyle={styles.big} labelStyle={styles.bigLabel}>
              Encaisser et imprimer
            </Button>
          </Card.Content>
        </Card>

        <HelperText type="error" visible={!!error} style={styles.error}>
          {error}
        </HelperText>

        {delivered ? (
          <Card style={styles.card}>
            <Card.Content style={{ gap: 6 }}>
              <Text variant="titleMedium" style={[styles.title, { color: statusColors.success.text }]}>Vignette {delivered.annee} délivrée</Text>
              <Text style={styles.strong}>{delivered.moto.cadre} · {formatFcfa(delivered.montant.total)}</Text>
              {delivered.categorie ? <Text style={styles.muted}>{delivered.categorie}</Text> : null}
              {delivered.detenteur || delivered.telephone_detenteur ? <Text>{[delivered.detenteur, delivered.telephone_detenteur].filter(Boolean).join(' · ')}</Text> : null}
              <Button mode="outlined" icon="printer" onPress={reprint}>Réimprimer la carte</Button>
              {fixing ? (
                <>
                  <TextInput mode="outlined" dense label="N° Engin correct" value={fixCh} onChangeText={(v) => setFixCh(v.toUpperCase())} autoCapitalize="characters" autoCorrect={false} />
                  <Button mode="contained" icon="printer" onPress={correct} loading={busy} disabled={busy}>Corriger et réimprimer</Button>
                  <Button onPress={() => setFixing(false)}>Annuler</Button>
                </>
              ) : (
                <Button icon="pencil" onPress={() => { setFixCh(delivered.moto.cadre); setFixing(true); }}>Corriger le N° Engin</Button>
              )}
            </Card.Content>
          </Card>
        ) : null}

        {recent.length > 0 ? (
          <Card style={styles.card}>
            <Card.Content style={{ gap: 4 }}>
              <Text variant="titleMedium" style={styles.title}>Dernières vignettes</Text>
              {recent.map((v) => (
                <View key={v.id} style={styles.row}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.strong}>{v.moto.cadre} · {v.annee}</Text>
                    <Text style={styles.muted}>{[v.categorie, v.detenteur, formatFcfa(v.montant.total)].filter(Boolean).join(' · ')}</Text>
                  </View>
                  <Pressable onPress={() => setPreview(v.carte_url)} hitSlop={10} style={styles.eye} accessibilityLabel="Aperçu de la vignette">
                    <MaterialCommunityIcons name="eye-outline" size={24} color={colors.primary} />
                  </Pressable>
                </View>
              ))}
            </Card.Content>
          </Card>
        ) : null}
      </ScrollView>
      <PreviewModal url={preview} title={`Vignette ${delivered?.annee ?? ''}`} onClose={() => setPreview(null)} />
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  content: { padding: 16, gap: 12 },
  card: { borderRadius: 14 },
  title: { fontWeight: '800', color: colors.text },
  ch: { fontSize: 24, fontWeight: '800', letterSpacing: 2 },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  strong: { fontWeight: '700', color: colors.text },
  big: { paddingVertical: 10 },
  bigLabel: { fontSize: 18 },
  error: { fontSize: 14 },
  muted: { color: colors.muted, fontSize: 13 },
  chipOn: { backgroundColor: '#d9f2e3' },
  total: { backgroundColor: '#f3f6f4', borderRadius: 10, padding: 10, gap: 2 },
  totalValue: { fontSize: 18, fontWeight: '800', color: colors.text },
  lateText: { fontSize: 13, fontWeight: '600', color: '#b91c1c' },
  row: { flexDirection: 'row', alignItems: 'center', paddingVertical: 8, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: '#d1d5db' },
  eye: { padding: 6 },
});
