import { useEffect, useState } from 'react';
import { KeyboardAvoidingView, Platform, ScrollView, StyleSheet, View } from 'react-native';
import { Button, Card, HelperText, Text, TextInput } from 'react-native-paper';
import { LinearGradient } from 'expo-linear-gradient';
import { ImageBackground } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import BrandTitle from '@/components/BrandTitle';
import { useAuth } from '@/context/AuthContext';
import { api, API_URL, apiErrorMessage } from '@/lib/api';
import { colors } from '@/lib/theme';

// Le bâtiment de la mairie en arrière-plan.
const MAIRIE = require('../assets/images/mairie.jpg');

export default function LoginScreen() {
  const { login, notice } = useAuth();
  const [phone, setPhone] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  // Le serveur est-il joignable ? (message clair au lieu d'un échec de connexion incompréhensible)
  const [serverDown, setServerDown] = useState(false);

  useEffect(() => {
    api.get('/health').catch(() => setServerDown(true));
  }, []);

  const submit = async () => {
    if (!phone.trim() || !password) {
      setError('Saisissez votre numéro de téléphone et votre mot de passe.');
      return;
    }

    setError(null);
    setLoading(true);
    try {
      await login(phone.trim(), password);
      // La navigation est pilotée par les gardes de app/_layout.tsx.
    } catch (e) {
      setError(apiErrorMessage(e, 'Connexion impossible. Réessayez.'));
    } finally {
      setLoading(false);
    }
  };

  return (
    <ImageBackground source={MAIRIE} style={styles.screen} resizeMode="cover">
      {/* Voile vert : le bâtiment reste visible, le logo et le formulaire restent lisibles. */}
      <LinearGradient colors={['#0f5132e6', '#0f513299', '#0f5132dd']} locations={[0, 0.45, 1]} style={StyleSheet.absoluteFill} />
      <SafeAreaView style={{ flex: 1 }}>
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={{ flex: 1 }}>
        <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
          <View style={styles.brand}>
            <BrandTitle size={44} onDark />
            <Text style={styles.tagline}>VIGNETTES · ÉTAT CIVIL · CAISSE</Text>
            {serverDown ? <Text style={styles.down}>Serveur injoignable : {API_URL}</Text> : null}
          </View>

          <Card style={styles.card}>
            <Card.Content style={{ gap: 12 }}>
              <Text variant="titleMedium" style={styles.title}>
                Connexion
              </Text>

              {notice ? (
                <HelperText type="error" visible style={styles.notice}>
                  {notice}
                </HelperText>
              ) : null}

              <TextInput
                mode="outlined"
                label="Numéro de téléphone"
                placeholder="70 00 00 00"
                value={phone}
                onChangeText={setPhone}
                keyboardType="phone-pad"
                autoComplete="tel"
                left={<TextInput.Icon icon="phone" />}
              />
              <HelperText type="info" visible style={styles.helper}>
                L'indicatif +223 est ajouté automatiquement.
              </HelperText>

              <TextInput
                mode="outlined"
                label="Mot de passe"
                value={password}
                onChangeText={setPassword}
                secureTextEntry={!showPassword}
                autoCapitalize="none"
                autoComplete="password"
                left={<TextInput.Icon icon="lock" />}
                right={<TextInput.Icon icon={showPassword ? 'eye-off' : 'eye'} onPress={() => setShowPassword((v) => !v)} />}
              />

              <HelperText type="error" visible={!!error}>
                {error}
              </HelperText>

              <Button mode="contained" onPress={submit} loading={loading} disabled={loading} contentStyle={styles.buttonContent}>
                Connexion
              </Button>
              <Text style={styles.hint}>Réservé au maire et aux agents de la mairie.</Text>
            </Card.Content>
          </Card>
        </ScrollView>
      </KeyboardAvoidingView>
      </SafeAreaView>
    </ImageBackground>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.dark },
  content: { flexGrow: 1, justifyContent: 'center', padding: 20, gap: 28 },
  brand: { alignItems: 'center', gap: 6 },
  tagline: { color: 'rgba(255,255,255,0.7)', fontSize: 11, fontWeight: '700', letterSpacing: 1 },
  card: { borderRadius: 18 },
  title: { fontWeight: '800', color: colors.text },
  notice: { fontSize: 13 },
  helper: { marginTop: -8 },
  hint: { textAlign: 'center', color: colors.muted, fontSize: 12 },
  down: { color: '#ffd7a8', fontSize: 12, textAlign: 'center', marginTop: 6 },
  buttonContent: { paddingVertical: 6 },
});
