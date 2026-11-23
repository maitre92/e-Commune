import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, TextInput as RNTextInput, View } from 'react-native';
import { Button, Card, HelperText, IconButton, SegmentedButtons, Text, TextInput } from 'react-native-paper';
import { useAuth } from '@/context/AuthContext';
import { api, apiErrorMessage } from '@/lib/api';
import { frToIso, isoToFr } from '@/lib/format';
import PreviewModal from '@/components/PreviewModal';
import { colors, statusColors } from '@/lib/theme';
import type { Acte, ActeSummary, CivilSettings, SaveActeResponse } from '@/types/api';

type Form = Record<string, string>;

/** Les seules rubriques à taper : l'enfant et ses parents. Le reste vient des réglages de la mairie. */
const FIELDS = [
  'first_names', 'last_name', 'birth_date', 'birth_time', 'birth_place', 'gender',
  'father_first_names', 'father_last_name', 'father_profession', 'father_domicile',
  'mother_first_names', 'mother_last_name', 'mother_profession', 'mother_domicile',
] as const;

/**
 * Acte de naissance, au plus simple : (1) retrouver un acte et imprimer son extrait en un clic ; (2) saisir un
 * nouvel acte (enfant et parents seulement) puis « Enregistrer et imprimer ». En-tête, n°, date, officier et
 * certification viennent des réglages de la mairie, saisis une fois (écran « Réglages de l'état civil », Web ou mobile).
 */
export default function ActesScreen() {
  // Aperçu avant impression (adresse de la page imprimable, ou null).
  const [preview, setPreview] = useState<string | null>(null);
  // Aperçu d'un acte (œil) : « Imprimer » délivre alors l'extrait de cet acte.
  const [previewActe, setPreviewActe] = useState<number | null>(null);
  const [settings, setSettings] = useState<CivilSettings | null>(null);
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<ActeSummary[] | null>(null);
  const [acte, setActe] = useState<Acte | null>(null);
  const [form, setForm] = useState<Form>({});
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [message, setMessage] = useState<{ text: string; ok: boolean } | null>(null);
  const [busy, setBusy] = useState(false);

  const { user } = useAuth();
  const router = useRouter();
  const canConfigure = (user?.permissions.includes('actes-naissance.configure') || user?.permissions.includes('actes-mariage.configure')) ?? false;
  const canSave = user?.permissions.includes(acte ? 'actes-naissance.update' : 'actes-naissance.create') ?? false;
  const canPrint = user?.permissions.includes('actes-naissance.deliver') ?? false;

  const set = (field: string) => (value: string) => setForm((f) => ({ ...f, [field]: value }));

  // Derniers actes : ceux de l'agent (tous pour le maire), affichés dès l'ouverture de l'onglet.
  const [recent, setRecent] = useState<ActeSummary[]>([]);
  const allActs = user?.permissions.includes('actes-naissance.view_all') ?? false;

  const load = useCallback(async () => {
    try {
      const [{ data: reglages }, { data: liste }] = await Promise.all([
        api.get<{ data: CivilSettings }>('/actes/nouveau'),
        api.get<{ data: ActeSummary[] }>('/actes'),
      ]);
      setSettings(reglages.data);
      setRecent(liste.data);
    } catch (e) {
      setMessage({ text: apiErrorMessage(e), ok: false });
    }
  }, []);

  // Rechargé à chaque affichage de l'onglet (un acte saisi apparaît aussitôt dans la liste).
  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  const newActe = () => {
    setActe(null);
    setForm({});
    setErrors({});
    setMessage(null);
  };

  const search = async () => {
    setBusy(true);
    setMessage(null);
    try {
      const { data } = await api.get<{ data: ActeSummary[] }>('/actes', { params: { q: query } });
      setResults(data.data);
    } catch (e) {
      setMessage({ text: apiErrorMessage(e), ok: false });
    } finally {
      setBusy(false);
    }
  };

  /** Réimpression en un clic : extrait certifié par l'officier réglé, daté du jour. */
  const printExtract = async (id: number) => {
    setBusy(true);
    setMessage(null);
    try {
      const { data } = await api.post<{ extrait_url: string }>(`/actes/${id}/extrait`);
      setPreview(data.extrait_url);
      setMessage({ text: 'Extrait imprimé.', ok: true });
    } catch (e) {
      setMessage({ text: apiErrorMessage(e), ok: false });
    } finally {
      setBusy(false);
    }
  };

  const open = async (id: number) => {
    setBusy(true);
    try {
      const { data } = await api.get<{ data: Acte }>(`/actes/${id}`);
      const values: Form = {};
      for (const field of FIELDS) {
        const value = data.data[field];
        values[field] = value === null || value === undefined ? '' : field === 'birth_date' ? isoToFr(String(value)) : String(value);
      }
      setActe(data.data);
      setForm(values);
      setResults(null);
      setErrors({});
    } catch (e) {
      setMessage({ text: apiErrorMessage(e), ok: false });
    } finally {
      setBusy(false);
    }
  };

  const save = async (print: boolean) => {
    setBusy(true);
    setErrors({});
    setMessage(null);
    const body: Record<string, unknown> = { ...form, print };
    if (form.birth_date) {
      body.birth_date = frToIso(form.birth_date);
    }
    try {
      const { data } = acte
        ? await api.put<SaveActeResponse>(`/actes/${acte.id}`, body)
        : await api.post<SaveActeResponse>('/actes', body);
      if (data.extrait_url) {
        setPreview(data.extrait_url);
      }
      setMessage({ text: `Acte n° ${data.data.act_number}/${data.data.act_year} enregistré${print ? ' et imprimé' : ''}.`, ok: true });
      newActeKeepMessage();
      load();
    } catch (e) {
      const response = (e as { response?: { data?: { errors?: Record<string, string[]> } } }).response;
      setErrors(response?.data?.errors ?? {});
      setMessage({ text: apiErrorMessage(e), ok: false });
    } finally {
      setBusy(false);
    }
  };

  const newActeKeepMessage = () => {
    setActe(null);
    setForm({});
  };

  const line = (field: string, label: string, placeholder?: string) => (
    <View style={styles.row}>
      <Text style={styles.rowLabel}>{label}</Text>
      <RNTextInput
        value={form[field] ?? ''}
        onChangeText={set(field)}
        placeholder={placeholder}
        placeholderTextColor="#a8a293"
        autoCapitalize={field === 'birth_date' || field === 'birth_time' ? 'none' : 'characters'}
        keyboardType={field === 'birth_date' || field === 'birth_time' ? 'numbers-and-punctuation' : 'default'}
        style={[styles.typed, errors[field] ? styles.typedError : null]}
      />
    </View>
  );

  return (
    <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={{ flex: 1 }}>
      <ScrollView style={styles.screen} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
        {settings && !settings.pret ? (
          <Text style={styles.warning}>
            {canConfigure
              ? 'Réglez d\'abord l\'état civil de la mairie (centre et officier signataire) : bouton ci-dessous.'
              : 'L\'état civil de la mairie n\'est pas encore réglé (centre et officier signataire) : demandez au maire.'}
          </Text>
        ) : null}
        {canConfigure ? (
          <Button icon="cog-outline" mode="outlined" compact onPress={() => router.navigate('/etat-civil')} style={{ alignSelf: 'flex-end' }}>
            Réglages de l'état civil
          </Button>
        ) : null}

        {acte || results ? (
          <Button icon="arrow-left" mode="text" compact onPress={() => { newActe(); setResults(null); setQuery(''); }} style={{ alignSelf: 'flex-start' }}>
            Retour
          </Button>
        ) : null}

        <Card style={styles.card}>
          <Card.Content style={{ gap: 6 }}>
            <TextInput
              mode="outlined"
              value={query}
              onChangeText={setQuery}
              placeholder="Retrouver : n° d'acte, nom, prénom"
              onSubmitEditing={search}
              returnKeyType="search"
              right={<TextInput.Icon icon="magnify" onPress={search} />}
            />
            {results?.length === 0 ? <Text style={styles.muted}>Aucun acte trouvé : saisissez-le ci-dessous.</Text> : null}
            {!results && recent.length > 0 ? <Text style={styles.listTitle}>{allActs ? 'Derniers actes de la mairie' : 'Mes derniers actes'}</Text> : null}
            {!results && recent.length === 0 ? <Text style={styles.muted}>Aucun acte saisi pour l'instant.</Text> : null}
            {(results ?? recent).map((r) => (
              <View key={r.id} style={styles.result}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.strong} onPress={() => open(r.id)}>{r.nom_complet}</Text>
                  <Text style={styles.muted}>n° {r.act_number}/{r.act_year} · né(e) le {isoToFr(r.birth_date)}</Text>
                </View>
                {canPrint ? (
                  <Button mode="contained" compact icon="printer" onPress={() => printExtract(r.id)} disabled={busy || !settings?.pret}>
                    Imprimer
                  </Button>
                ) : null}
                <IconButton icon="eye-outline" size={22} iconColor={colors.primary} onPress={() => { setPreviewActe(r.id); setPreview(`/actes/${r.id}/apercu`); }} accessibilityLabel="Aperçu de l'extrait" />
                <IconButton icon="pencil" size={20} onPress={() => open(r.id)} />
              </View>
            ))}
          </Card.Content>
        </Card>

        <View style={styles.sheet}>
          <Text style={styles.republic}>REPUBLIQUE DU MALI</Text>
          <Text style={styles.title}>EXTRAIT D'ACTE DE NAISSANCE</Text>
          <Text style={styles.sub}>
            {acte ? `N° ${acte.act_number} de ${acte.act_year}` : `${settings?.centre ?? 'Centre à régler'} · n° et date automatiques`}
          </Text>

          <View style={styles.box}>
            {line('first_names', '1 Prénom(s)')}
            {line('last_name', '2 Nom')}
            {line('birth_date', '3 Date de naissance', 'JJ/MM/AAAA')}
            {line('birth_time', 'Heure', 'HH:MM (facultatif)')}
            {line('birth_place', '4 Localité de naissance', settings?.localite_par_defaut ? `par défaut : ${settings.localite_par_defaut}` : undefined)}
            <Text style={styles.rowLabel}>5 Sexe</Text>
            <SegmentedButtons
              value={form.gender ?? ''}
              onValueChange={set('gender')}
              buttons={[{ value: 'homme', label: 'Masculin' }, { value: 'femme', label: 'Féminin' }]}
              style={{ marginTop: 4 }}
            />
          </View>

          <Text style={styles.parent}>Père</Text>
          <View style={styles.box}>
            {line('father_first_names', '6 Prénom(s)', 'vide si non déclaré')}
            {line('father_last_name', '7 Nom', form.last_name ? form.last_name : 'même nom que l\'enfant')}
            {line('father_profession', '8 Profession')}
            {line('father_domicile', '9 Domicile')}
          </View>

          <Text style={styles.parent}>Mère</Text>
          <View style={styles.box}>
            {line('mother_first_names', '10 Prénom(s)')}
            {line('mother_last_name', '11 Nom')}
            {line('mother_profession', '12 Profession')}
            {line('mother_domicile', '13 Domicile', form.father_domicile ? form.father_domicile : 'même domicile que le père')}
          </View>
          <Text style={styles.auto}>Officier : {settings?.officier ?? '—'} (réglé une fois)</Text>
        </View>

        <HelperText type={message?.ok ? 'info' : 'error'} visible={!!message} style={[styles.message, message?.ok ? { color: statusColors.success.text } : null]}>
          {message?.text}
        </HelperText>

        {canSave && canPrint ? (
          <Button mode="contained" icon="printer" onPress={() => save(true)} loading={busy} disabled={busy || !settings?.pret} contentStyle={styles.big} labelStyle={styles.bigLabel}>
            Enregistrer et imprimer
          </Button>
        ) : null}
        {canSave ? (
          <Button mode="outlined" icon="content-save" onPress={() => save(false)} disabled={busy}>
            Enregistrer seulement
          </Button>
        ) : (
          <Text style={styles.muted}>Consultation seulement : la saisie et l'impression sont faites par les agents d'état civil.</Text>
        )}
        {acte ? (
          <Button icon="plus" onPress={newActe}>
            Nouvel acte
          </Button>
        ) : null}
      </ScrollView>
      <PreviewModal
        url={preview}
        title={previewActe ? 'Aperçu de l\'extrait' : 'Extrait d\'acte de naissance'}
        onClose={() => { setPreview(null); setPreviewActe(null); }}
        onPrint={previewActe && canPrint && settings?.pret ? () => printExtract(previewActe) : undefined}
      />
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  content: { padding: 12, gap: 10, paddingBottom: 40 },
  card: { borderRadius: 14 },
  muted: { color: colors.muted, fontSize: 13 },
  listTitle: { fontWeight: '800', color: colors.text, marginTop: 6 },
  strong: { fontWeight: '700', color: colors.text },
  warning: { backgroundColor: statusColors.warning.bg, color: statusColors.warning.text, padding: 10, borderRadius: 10, fontWeight: '600' },
  result: { flexDirection: 'row', alignItems: 'center', gap: 6, paddingVertical: 6, borderBottomWidth: 1, borderBottomColor: colors.border },
  sheet: { backgroundColor: '#fdfcf8', borderWidth: 2, borderColor: '#1f2937', padding: 10, gap: 2 },
  republic: { textAlign: 'center', fontWeight: '800', fontSize: 12, color: '#111' },
  title: { textAlign: 'center', fontWeight: '900', fontSize: 16, color: '#111', marginTop: 4 },
  sub: { textAlign: 'center', fontSize: 12, color: colors.muted, marginBottom: 4 },
  box: { borderWidth: 1.5, borderColor: '#1f2937', padding: 8, marginTop: 8 },
  parent: { marginTop: 10, fontWeight: '800', fontStyle: 'italic', color: '#111' },
  auto: { marginTop: 8, fontSize: 12, color: colors.muted, fontStyle: 'italic' },
  row: { marginTop: 4 },
  rowLabel: { fontSize: 12, fontWeight: '800', color: '#111' },
  typed: {
    fontFamily: Platform.select({ ios: 'Courier', default: 'monospace' }),
    fontSize: 16,
    color: '#1b1b1d',
    borderBottomWidth: 1,
    borderBottomColor: '#6b7280',
    paddingVertical: 4,
    paddingHorizontal: 2,
  },
  typedError: { backgroundColor: 'rgba(220,53,69,0.08)', borderBottomColor: colors.danger },
  message: { fontSize: 14 },
  big: { paddingVertical: 10 },
  bigLabel: { fontSize: 18 },
});
