import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, TextInput as RNTextInput, View } from 'react-native';
import { Button, Card, HelperText, IconButton, SegmentedButtons, Text, TextInput } from 'react-native-paper';
import { useAuth } from '@/context/AuthContext';
import { api, apiErrorMessage } from '@/lib/api';
import { frToIso, isoToFr } from '@/lib/format';
import PreviewModal from '@/components/PreviewModal';
import { colors, statusColors } from '@/lib/theme';
import type { CivilSettings, Mariage, MariageSummary } from '@/types/api';

type Form = Record<string, string>;

const DATES = ['husband_birth_date', 'wife_birth_date', 'celebrated_at', 'declared_at'] as const;

/** Les seules rubriques à taper : les époux, leurs parents, la célébration, le régime et l'option. */
const FIELDS = [
  'husband_first_names', 'husband_last_name', 'husband_birth_date', 'husband_birth_place', 'husband_profession', 'husband_domicile',
  'wife_first_names', 'wife_last_name', 'wife_birth_date', 'wife_birth_place', 'wife_profession', 'wife_domicile',
  'husband_father_first_names', 'husband_father_last_name', 'husband_mother_first_names', 'husband_mother_last_name',
  'wife_father_first_names', 'wife_father_last_name', 'wife_mother_first_names', 'wife_mother_last_name',
  'celebrated_at', 'declared_at', 'marriage_place', 'regime', 'option',
] as const;

/**
 * Acte de mariage, comme l'acte de naissance : (1) retrouver un acte et imprimer son extrait en un clic (œil pour
 * l'aperçu) ; (2) saisir un nouvel acte sur le modèle d'extrait puis « Enregistrer et imprimer ». En-tête, n°,
 * officier et certification viennent des réglages de la mairie, saisis une fois (écran « Réglages de l'état civil », Web ou mobile).
 */
export default function MariagesScreen() {
  const [preview, setPreview] = useState<string | null>(null);
  const [previewActe, setPreviewActe] = useState<number | null>(null);
  const [settings, setSettings] = useState<CivilSettings | null>(null);
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<MariageSummary[] | null>(null);
  const [recent, setRecent] = useState<MariageSummary[]>([]);
  const [acte, setActe] = useState<Mariage | null>(null);
  const [form, setForm] = useState<Form>({});
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [message, setMessage] = useState<{ text: string; ok: boolean } | null>(null);
  const [busy, setBusy] = useState(false);

  const { user } = useAuth();
  const router = useRouter();
  const canConfigure = (user?.permissions.includes('actes-naissance.configure') || user?.permissions.includes('actes-mariage.configure')) ?? false;
  const canSave = user?.permissions.includes(acte ? 'actes-mariage.update' : 'actes-mariage.create') ?? false;
  const canPrint = user?.permissions.includes('actes-mariage.deliver') ?? false;
  const allActs = user?.permissions.includes('actes-mariage.view_all') ?? false;

  const set = (field: string) => (value: string) => setForm((f) => ({ ...f, [field]: value }));

  const load = useCallback(async () => {
    try {
      const { data } = await api.get<{ data: MariageSummary[] }>('/mariages');
      setRecent(data.data);
    } catch (e) {
      setMessage({ text: apiErrorMessage(e), ok: false });
    }
    try {
      const { data } = await api.get<{ data: CivilSettings }>('/actes/nouveau');
      setSettings(data.data);
    } catch {
      setSettings(null);
    }
  }, []);

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
      const { data } = await api.get<{ data: MariageSummary[] }>('/mariages', { params: { q: query } });
      setResults(data.data);
    } catch (e) {
      setMessage({ text: apiErrorMessage(e), ok: false });
    } finally {
      setBusy(false);
    }
  };

  /** Délivrance en un clic : extrait certifié par l'officier réglé, daté du jour, encaissé. */
  const printExtract = async (id: number) => {
    setBusy(true);
    setMessage(null);
    try {
      const { data } = await api.post<{ extrait_url: string }>(`/mariages/${id}/extrait`);
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
      const { data } = await api.get<{ data: Mariage }>(`/mariages/${id}`);
      const values: Form = {};
      for (const field of FIELDS) {
        const value = data.data[field];
        values[field] = value === null || value === undefined ? '' : (DATES as readonly string[]).includes(field) ? isoToFr(String(value)) : String(value);
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
    for (const field of DATES) {
      if (form[field]) {
        body[field] = frToIso(form[field]);
      }
    }
    try {
      const { data } = acte
        ? await api.put<{ data: Mariage; extrait_url: string | null }>(`/mariages/${acte.id}`, body)
        : await api.post<{ data: Mariage; extrait_url: string | null }>('/mariages', body);
      if (data.extrait_url) {
        setPreview(data.extrait_url);
      }
      setMessage({ text: `Acte de mariage n° ${data.data.act_number}/${data.data.act_year} enregistré${print ? ' et imprimé' : ''}.`, ok: true });
      setActe(null);
      setForm({});
      load();
    } catch (e) {
      const response = (e as { response?: { data?: { errors?: Record<string, string[]> } } }).response;
      setErrors(response?.data?.errors ?? {});
      setMessage({ text: apiErrorMessage(e), ok: false });
    } finally {
      setBusy(false);
    }
  };

  const line = (field: string, label: string, placeholder?: string) => {
    const isDate = (DATES as readonly string[]).includes(field);
    return (
      <View style={styles.row}>
        <Text style={styles.rowLabel}>{label}</Text>
        <RNTextInput
          value={form[field] ?? ''}
          onChangeText={set(field)}
          placeholder={placeholder ?? (isDate ? 'JJ/MM/AAAA' : undefined)}
          placeholderTextColor="#a8a293"
          autoCapitalize={isDate ? 'none' : 'characters'}
          keyboardType={isDate ? 'numbers-and-punctuation' : 'default'}
          style={[styles.typed, errors[field] ? styles.typedError : null]}
        />
      </View>
    );
  };

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
              placeholder="Retrouver : n° d'acte, nom d'un époux"
              onSubmitEditing={search}
              returnKeyType="search"
              right={<TextInput.Icon icon="magnify" onPress={search} />}
            />
            {results?.length === 0 ? <Text style={styles.muted}>Aucun acte trouvé : saisissez-le ci-dessous.</Text> : null}
            {!results && recent.length > 0 ? <Text style={styles.listTitle}>{allActs ? 'Derniers mariages de la mairie' : 'Mes derniers actes de mariage'}</Text> : null}
            {!results && recent.length === 0 ? <Text style={styles.muted}>Aucun acte de mariage saisi pour l'instant.</Text> : null}
            {(results ?? recent).map((r) => (
              <View key={r.id} style={styles.result}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.strong} onPress={() => open(r.id)}>{r.epoux}</Text>
                  <Text style={styles.muted}>et {r.epouse} · n° {r.act_number}/{r.act_year} · {isoToFr(r.celebrated_at)}</Text>
                </View>
                {canPrint ? (
                  <Button mode="contained" compact icon="printer" onPress={() => printExtract(r.id)} disabled={busy || !settings?.pret}>
                    Imprimer
                  </Button>
                ) : null}
                <IconButton icon="eye-outline" size={22} iconColor={colors.primary} onPress={() => { setPreviewActe(r.id); setPreview(`/mariages/${r.id}/apercu`); }} accessibilityLabel="Aperçu de l'extrait" />
                <IconButton icon="pencil" size={20} onPress={() => open(r.id)} />
              </View>
            ))}
          </Card.Content>
        </Card>

        <View style={styles.sheet}>
          <Text style={styles.republic}>REPUBLIQUE DU MALI</Text>
          <Text style={styles.title}>EXTRAIT D'ACTE DE MARIAGE</Text>
          <Text style={styles.sub}>
            {acte ? `N° ${acte.act_number} de ${acte.act_year}` : `${settings?.centre ?? 'Centre à régler'} · n° et date automatiques`}
          </Text>

          <Text style={styles.parent}>Époux</Text>
          <View style={styles.box}>
            {line('husband_first_names', '1 Prénom(s)')}
            {line('husband_last_name', '2 Nom')}
            {line('husband_birth_date', '3 Date de naissance')}
            {line('husband_birth_place', '4 Localité de naissance')}
            <Text style={styles.fixed}>5 Sexe : MASCULIN</Text>
            {line('husband_profession', '6 Profession')}
            {line('husband_domicile', '7 Domicile')}
          </View>

          <Text style={styles.parent}>Épouse</Text>
          <View style={styles.box}>
            {line('wife_first_names', '8 Prénom(s)')}
            {line('wife_last_name', '9 Nom')}
            {line('wife_birth_date', '10 Date de naissance')}
            {line('wife_birth_place', '11 Localité de naissance')}
            <Text style={styles.fixed}>12 Sexe : FÉMININ</Text>
            {line('wife_profession', '13 Profession')}
            {line('wife_domicile', '14 Domicile')}
          </View>

          <Text style={styles.parent}>Filiation de l'époux</Text>
          <View style={styles.box}>
            {line('husband_father_first_names', '15 Père — prénom(s)')}
            {line('husband_father_last_name', 'Nom', form.husband_last_name || 'même nom que l\'époux')}
            {line('husband_mother_first_names', '16 Mère — prénom(s)')}
            {line('husband_mother_last_name', 'Nom')}
          </View>

          <Text style={styles.parent}>Filiation de l'épouse</Text>
          <View style={styles.box}>
            {line('wife_father_first_names', '15 Père — prénom(s)')}
            {line('wife_father_last_name', 'Nom', form.wife_last_name || 'même nom que l\'épouse')}
            {line('wife_mother_first_names', '16 Mère — prénom(s)')}
            {line('wife_mother_last_name', 'Nom')}
          </View>

          <Text style={styles.parent}>Mariage</Text>
          <View style={styles.box}>
            {line('celebrated_at', '18 Date de célébration', 'JJ/MM/AAAA (vide : aujourd\'hui)')}
            {line('declared_at', '17 Date de déclaration', 'JJ/MM/AAAA (vide : même date)')}
            {line('marriage_place', '19 Localité du mariage', settings?.localite_par_defaut ? `par défaut : ${settings.localite_par_defaut}` : undefined)}
            <Text style={[styles.rowLabel, { marginTop: 8 }]}>20 Régime matrimonial</Text>
            <SegmentedButtons
              value={form.regime ?? ''}
              onValueChange={set('regime')}
              buttons={[{ value: 'separation_biens', label: 'Séparation' }, { value: 'communaute_biens', label: 'Communauté' }]}
              style={{ marginTop: 4 }}
            />
            <Text style={[styles.rowLabel, { marginTop: 8 }]}>21 Option matrimoniale</Text>
            <SegmentedButtons
              value={form.option ?? ''}
              onValueChange={set('option')}
              buttons={[{ value: 'monogamie', label: 'Monogamie' }, { value: 'polygamie', label: 'Polygamie' }]}
              style={{ marginTop: 4 }}
            />
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
        title={previewActe ? 'Aperçu de l\'extrait de mariage' : 'Extrait d\'acte de mariage'}
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
  result: { flexDirection: 'row', alignItems: 'center', gap: 4, paddingVertical: 6, borderBottomWidth: 1, borderBottomColor: colors.border },
  sheet: { backgroundColor: '#fdfcf8', borderWidth: 2, borderColor: '#1f2937', padding: 10, gap: 2 },
  republic: { textAlign: 'center', fontWeight: '800', fontSize: 12, color: '#111' },
  title: { textAlign: 'center', fontWeight: '900', fontSize: 16, color: '#111', marginTop: 4 },
  sub: { textAlign: 'center', fontSize: 12, color: colors.muted, marginBottom: 4 },
  box: { borderWidth: 1.5, borderColor: '#1f2937', padding: 8, marginTop: 6 },
  parent: { marginTop: 10, fontWeight: '800', fontStyle: 'italic', color: '#111' },
  fixed: { marginTop: 6, fontSize: 13, fontWeight: '700', color: '#111', fontFamily: Platform.select({ ios: 'Courier', default: 'monospace' }) },
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
