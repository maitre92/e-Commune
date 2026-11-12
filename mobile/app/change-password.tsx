import { MaterialCommunityIcons } from '@expo/vector-icons';
import { Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { Button, Card, Text } from 'react-native-paper';
import { SafeAreaView } from 'react-native-safe-area-context';
import BrandTitle from '@/components/BrandTitle';
import PasswordForm from '@/components/PasswordForm';
import { useAuth } from '@/context/AuthContext';
import { colors } from '@/lib/theme';

/** Écran obligatoire tant que le mot de passe est temporaire (créé ou réinitialisé par un administrateur). */
export default function ChangePasswordScreen() {
  const { changePassword, logout } = useAuth();

  return (
    <SafeAreaView style={styles.screen}>
      <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
        {/* Retour à la connexion (le compte est déconnecté de ce téléphone). */}
        <Pressable onPress={logout} hitSlop={12} style={styles.back} accessibilityLabel="Retour à la connexion">
          <MaterialCommunityIcons name="arrow-left" size={24} color="#ffffff" />
          <Text style={styles.backText}>Retour</Text>
        </Pressable>

        <View style={styles.brand}>
          <BrandTitle size={36} onDark />
        </View>

        <Card style={styles.card}>
          <Card.Content style={{ gap: 12 }}>
            <Text variant="titleMedium" style={styles.title}>
              Choisissez un nouveau mot de passe
            </Text>
            <Text style={styles.text}>
              Votre mot de passe est <Text style={{ fontWeight: '800' }}>temporaire</Text> : vous devez le remplacer pour continuer.
              Tous vos autres appareils seront déconnectés.
            </Text>

            <PasswordForm submitLabel="Enregistrer" onSubmit={changePassword} />
            {/* Après succès, les gardes de navigation ouvrent l'application. */}
          </Card.Content>
        </Card>

        <Button onPress={logout} textColor="#ffffff">
          Se déconnecter
        </Button>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.dark },
  content: { flexGrow: 1, justifyContent: 'center', padding: 20, gap: 20 },
  back: { flexDirection: 'row', alignItems: 'center', gap: 6, alignSelf: 'flex-start' },
  backText: { color: '#ffffff', fontWeight: '700' },
  brand: { alignItems: 'center' },
  card: { borderRadius: 18 },
  title: { fontWeight: '800', color: colors.text },
  text: { color: colors.muted },
});
