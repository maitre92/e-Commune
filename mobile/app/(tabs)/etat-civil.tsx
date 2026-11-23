import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, View } from 'react-native';
import { ActivityIndicator, Button, Card, HelperText, SegmentedButtons, Text, TextInput } from 'react-native-paper';
import { useAuth } from '@/context/AuthContext';
import { api, apiErrorMessage } from '@/lib/api';
import { colors, statusColors } from '@/lib/theme';
import type { CivilSettingsForm } from '@/types/api';

type Form = Record<string, string>;

/**
 * Réglages de l'état civil de la mairie (même contenu que la fenêtre « Réglages de l'état civil » du Web) :
 * officier signataire, centre, en-tête des extraits, prix et papier. Saisis une fois, repris sur chaque acte.
 * Réservé aux comptes qui ont le droit de régler l'état civil (maire, agent polyvalent, délégation).
 */
export default function EtatCivilSettingsScreen() {
  const router = useRouter();
  const { user } = useAuth();
  const allowed = (user?.permissions.includes('actes-naissance.configure') || user?.permissions.includes('actes-mariage.configure')) ?? false;

  const [form, setForm] = useState<Form | null>(null);
  const [mairie, setMairie] = useState('');
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [message, setMessage] = useState<{ text: string; ok: boolean } | null>(null);
  const [busy, setBusy] = useState(false);

  const fill = (data: CivilSettingsForm) => {
    setMairie(data.mairie);
    const values: Form = {};
    for (const [key, value] of Object.entries(data)) {
      if (key.startsWith('civil_')) {
        values[key] = value === null || value === undefined ? '' : String(value);
      }
    }
    setForm(values);
  };

  const load = useCallback(async () => {
    if (!allowed) {
      return;
    }
    try {
      const { data } = await api.get<{ data: CivilSettingsForm }>('/etat-civil/reglages');
      fill(data.data);
      setErrors({});
    } catch (e) {
      setMessage({ text: apiErrorMessage(e), ok: false });
    }
  }, [allowed]);

  useFocusEffect(
    useCallback(() => {
      setMessage(null);
      load();
    }, [load]),
  );

  const set = (field: string) => (value: string) => setForm((f) => ({ ...(f ?? {}), [field]: value }));

  const save = async () => {
    if (!form) {
      return;
    }
    setBusy(true);
    setMessage(null);
    setErrors({});
    try {
      const { data } = await api.put<{ message: string; data: CivilSettingsForm }>('/etat-civil/reglages', form);
      fill(data.data);
      setMessage({ text: data.message, ok: true });
    } catch (e) {
      const response = (e as { response?: { data?: { errors?: Record<string, string[]> } } }).response;
      setErrors(response?.data?.errors ?? {});
      setMessage({ text: apiErrorMessage(e), ok: false });
    } finally {
      setBusy(false);
    }
  };

  if (!allowed) {
    return (
      <View style={[styles.screen, styles.content]}>
        <Text style={styles.warning}>Seuls le maire et les agents autorisés règlent l'état civil de la mairie.</Text>
        <Button icon="arrow-left" mode="outlined" onPress={() => (router.canGoBack() ? router.back() : router.navigate('/'))}>Retour</Button>
      </View>
    );
  }

  if (!form) {
    return (
      <View style={[styles.screen, styles.center]}>
        {message ? <Text style={styles.error}>{message.text}</Text> : <ActivityIndicator />}
      </View>
    );
  }

  const field = (name: string, label: string, options: { numeric?: boolean; hint?: string; caps?: boolean } = {}) => (
    <View>
      <TextInput
        mode="outlined"
        label={label}
        value={form[name] ?? ''}
        onChangeText={set(name)}
        keyboardType={options.numeric ? 'numeric' : 'default'}
        autoCapitalize={options.caps ? 'characters' : 'sentences'}
        error={!!errors[name]}
        dense
      />
      {errors[name] ? <HelperText type="error">{errors[name][0]}</HelperText> : options.hint ? <HelperText type="info">{options.hint}</HelperText> : null}
    </View>
  );

  return (
    <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={{ flex: 1 }}>
      <ScrollView style={styles.screen} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
        <Text style={styles.intro}>
          {mairie} — saisis une seule fois : repris sur chaque nouvel acte (en-tête, officier) et sur chaque extrait imprimé.
        </Text>

        <Card style={styles.card}>
          <Card.Title title="Officier signataire" titleStyle={styles.section} />
          <Card.Content style={styles.group}>
            {field('civil_officer_name', 'Prénom(s) et nom *')}
            {field('civil_officer_title', 'Qualité *')}
          </Card.Content>
        </Card>

        <Card style={styles.card}>
          <Card.Title title="Centre d'état civil" titleStyle={styles.section} />
          <Card.Content style={styles.group}>
            <SegmentedButtons
              value={form.civil_centre_type || 'principal'}
              onValueChange={set('civil_centre_type')}
              buttons={[
                { value: 'principal', label: 'Principal' },
                { value: 'secondaire', label: 'Secondaire' },
              ]}
            />
            {field('civil_centre_name', 'De (nom du centre) *')}
          </Card.Content>
        </Card>

        <Card style={styles.card}>
          <Card.Title title="En-tête de l'extrait" titleStyle={styles.section} />
          <Card.Content style={styles.group}>
            {field('civil_region', 'Région de', { caps: true })}
            {field('civil_cercle', 'Cercle de', { caps: true })}
            {field('civil_arrondissement', 'Arrondissement de', { caps: true })}
            {field('civil_commune', 'Commune de', { caps: true })}
            {field('civil_birth_place', 'Localité proposée par défaut', { hint: 'Naissance et mariage' })}
          </Card.Content>
        </Card>

        <Card style={styles.card}>
          <Card.Title title="Prix et impression" titleStyle={styles.section} />
          <Card.Content style={styles.group}>
            {field('civil_extract_price', 'Prix de l\'extrait de naissance (FCFA)', { numeric: true })}
            {field('civil_marriage_extract_price', 'Prix de l\'extrait de mariage (FCFA)', { numeric: true })}
            <Text style={styles.label}>Papier utilisé</Text>
            <SegmentedButtons
              value={form.civil_print_mode || 'blanc'}
              onValueChange={set('civil_print_mode')}
              buttons={[
                { value: 'blanc', label: 'Feuille blanche' },
                { value: 'preimprime', label: 'Pré-imprimé' },
              ]}
            />
            {form.civil_print_mode === 'preimprime' ? (
              <View style={styles.row}>
                <View style={{ flex: 1 }}>{field('civil_print_offset_x', 'Décalage → (mm)', { numeric: true })}</View>
                <View style={{ flex: 1 }}>{field('civil_print_offset_y', 'Décalage ↓ (mm)', { numeric: true })}</View>
              </View>
            ) : null}
          </Card.Content>
        </Card>

        {message ? <Text style={[styles.message, { color: message.ok ? colors.success : colors.danger }]}>{message.text}</Text> : null}

        <Button mode="contained" icon="content-save" onPress={save} loading={busy} disabled={busy} contentStyle={styles.big} labelStyle={styles.bigLabel}>
          Enregistrer les réglages
        </Button>
        <Button mode="text" icon="arrow-left" onPress={() => (router.canGoBack() ? router.back() : router.navigate('/actes'))}>
          Retour
        </Button>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  center: { alignItems: 'center', justifyContent: 'center', padding: 24 },
  content: { padding: 12, gap: 10, paddingBottom: 40 },
  card: { borderRadius: 14 },
  section: { fontWeight: '800', color: colors.dark, fontSize: 15 },
  group: { gap: 4 },
  row: { flexDirection: 'row', gap: 8 },
  label: { marginTop: 6, fontWeight: '700', color: colors.text },
  intro: { color: colors.muted, fontSize: 13 },
  warning: { backgroundColor: statusColors.warning.bg, color: statusColors.warning.text, padding: 10, borderRadius: 10, fontWeight: '600' },
  error: { color: colors.danger, textAlign: 'center' },
  message: { fontSize: 14, fontWeight: '600' },
  big: { paddingVertical: 10 },
  bigLabel: { fontSize: 18 },
});
