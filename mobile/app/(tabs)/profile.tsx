import { MaterialCommunityIcons } from '@expo/vector-icons';
import * as ImagePicker from 'expo-image-picker';
import { LinearGradient } from 'expo-linear-gradient';
import { useEffect, useState } from 'react';
import { Alert, Image, KeyboardAvoidingView, Platform, Pressable, ScrollView, StyleSheet, View } from 'react-native';
import { Button, Card, HelperText, Text, TextInput } from 'react-native-paper';
import PasswordForm from '@/components/PasswordForm';
import { useAuth } from '@/context/AuthContext';
import { api, apiErrorMessage } from '@/lib/api';
import { colors } from '@/lib/theme';

/**
 * Mon profil : chacun modifie ses propres informations (nom, numéro — le numéro sert à se connecter, le changer
 * demande le mot de passe actuel), ajoute ou change sa photo, change son mot de passe, se déconnecte.
 */
export default function ProfileScreen() {
  const { user, changePassword, logout, refreshUser } = useAuth();
  const [name, setName] = useState(user?.name ?? '');
  const [phone, setPhone] = useState(user?.phone ?? '');
  const [currentPassword, setCurrentPassword] = useState('');
  const [changingPassword, setChangingPassword] = useState(false);
  const [message, setMessage] = useState<{ text: string; ok: boolean } | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    setName(user?.name ?? '');
    setPhone(user?.phone ?? '');
  }, [user?.name, user?.phone]);

  const phoneChanged = phone.replace(/\s/g, '') !== (user?.phone ?? '') && phone.replace(/\s/g, '') !== (user?.phone ?? '').replace('+223', '');

  const saveInfo = async () => {
    setBusy(true);
    setMessage(null);
    try {
      await api.put('/auth/profile', { name, phone, phone_password: phoneChanged ? currentPassword : undefined });
      await refreshUser();
      setCurrentPassword('');
      setMessage({ text: 'Vos informations ont été mises à jour.', ok: true });
    } catch (e) {
      setMessage({ text: apiErrorMessage(e), ok: false });
    } finally {
      setBusy(false);
    }
  };

  const uploadPhoto = async (fromCamera: boolean) => {
    const permission = fromCamera ? await ImagePicker.requestCameraPermissionsAsync() : await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!permission.granted) {
      setMessage({ text: fromCamera ? 'Autorisez l\'appareil photo pour prendre votre photo.' : 'Autorisez l\'accès aux photos pour en choisir une.', ok: false });
      return;
    }

    const options: ImagePicker.ImagePickerOptions = { mediaTypes: ['images'], allowsEditing: true, aspect: [1, 1], quality: 0.7 };
    const result = fromCamera ? await ImagePicker.launchCameraAsync(options) : await ImagePicker.launchImageLibraryAsync(options);
    if (result.canceled || !result.assets[0]) {
      return;
    }

    const asset = result.assets[0];
    const form = new FormData();
    form.append('photo', { uri: asset.uri, name: asset.fileName ?? 'photo.jpg', type: asset.mimeType ?? 'image/jpeg' } as unknown as Blob);

    setBusy(true);
    setMessage(null);
    try {
      await api.post('/auth/photo', form, { headers: { 'Content-Type': 'multipart/form-data' } });
      await refreshUser();
      setMessage({ text: 'Votre photo a été mise à jour.', ok: true });
    } catch (e) {
      setMessage({ text: apiErrorMessage(e, 'Envoi de la photo impossible.'), ok: false });
    } finally {
      setBusy(false);
    }
  };

  const choosePhoto = () =>
    Alert.alert('Ma photo', 'D\'où vient la photo ?', [
      { text: 'Prendre une photo', onPress: () => uploadPhoto(true) },
      { text: 'Choisir dans la galerie', onPress: () => uploadPhoto(false) },
      ...(user?.photo_url ? [{ text: 'Retirer ma photo', style: 'destructive' as const, onPress: removePhoto }] : []),
      { text: 'Annuler', style: 'cancel' as const },
    ]);

  const removePhoto = async () => {
    try {
      await api.delete('/auth/photo');
      await refreshUser();
    } catch (e) {
      setMessage({ text: apiErrorMessage(e), ok: false });
    }
  };

  const confirmLogout = () =>
    Alert.alert('Se déconnecter ?', 'Vous devrez saisir à nouveau votre numéro et votre mot de passe.', [
      { text: 'Annuler', style: 'cancel' },
      { text: 'Se déconnecter', style: 'destructive', onPress: logout },
    ]);

  const initials = (user?.name ?? '?').split(' ').map((part) => part[0]).slice(0, 2).join('').toUpperCase();

  return (
    <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={{ flex: 1 }}>
      <ScrollView style={styles.screen} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
        {/* Photo et identité */}
        <LinearGradient colors={['#13693c', '#1e8a4c']} style={styles.hero}>
          <Pressable onPress={choosePhoto} style={styles.avatarWrap} accessibilityLabel="Changer ma photo">
            {user?.photo_url ? (
              <Image source={{ uri: user.photo_url }} style={styles.avatar} />
            ) : (
              <View style={[styles.avatar, styles.avatarEmpty]}>
                <Text style={styles.initials}>{initials}</Text>
              </View>
            )}
            <View style={styles.camera}>
              <MaterialCommunityIcons name="camera" size={18} color="#ffffff" />
            </View>
          </Pressable>
          <Text style={styles.heroName}>{user?.name}</Text>
          <Text style={styles.heroRole}>
            {user?.role?.label}
            {user?.institution ? ` · ${user.institution.name}` : ''}
          </Text>
        </LinearGradient>

        <HelperText type={message?.ok ? 'info' : 'error'} visible={!!message} style={[styles.message, message?.ok ? { color: colors.primary } : null]}>
          {message?.text}
        </HelperText>

        <Card style={styles.card}>
          <Card.Title title="Mes informations" titleStyle={styles.cardTitle} left={(p) => <MaterialCommunityIcons {...p} name="account-edit-outline" size={26} color={colors.primary} />} />
          <Card.Content style={{ gap: 10 }}>
            <TextInput mode="outlined" label="Nom et prénom(s)" value={name} onChangeText={setName} />
            <TextInput mode="outlined" label="Numéro de téléphone (identifiant)" value={phone} onChangeText={setPhone} keyboardType="phone-pad" />
            {phoneChanged ? (
              <TextInput mode="outlined" label="Mot de passe actuel (pour changer de numéro)" value={currentPassword} onChangeText={setCurrentPassword} secureTextEntry />
            ) : null}
            <Button mode="contained" icon="content-save" onPress={saveInfo} loading={busy} disabled={busy}>
              Enregistrer
            </Button>
          </Card.Content>
        </Card>

        <Card style={styles.card}>
          <Card.Title title="Mot de passe" titleStyle={styles.cardTitle} left={(p) => <MaterialCommunityIcons {...p} name="lock-outline" size={26} color={colors.primary} />} />
          <Card.Content style={{ gap: 10 }}>
            {changingPassword ? (
              <>
                <PasswordForm submitLabel="Enregistrer" onSubmit={changePassword} onSuccess={() => setChangingPassword(false)} />
                <Text style={styles.small}>Vos autres appareils seront déconnectés.</Text>
                <Button onPress={() => setChangingPassword(false)}>Annuler</Button>
              </>
            ) : (
              <Button mode="outlined" icon="lock-reset" onPress={() => setChangingPassword(true)}>
                Changer mon mot de passe
              </Button>
            )}
          </Card.Content>
        </Card>

        <Button mode="contained" icon="logout" buttonColor={colors.danger} onPress={confirmLogout}>
          Se déconnecter
        </Button>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  content: { padding: 16, gap: 12, paddingBottom: 30 },
  hero: { borderRadius: 18, alignItems: 'center', paddingVertical: 20, gap: 4 },
  avatarWrap: { marginBottom: 6 },
  avatar: { width: 104, height: 104, borderRadius: 52, borderWidth: 3, borderColor: '#ffffff' },
  avatarEmpty: { backgroundColor: '#1d6fd1', alignItems: 'center', justifyContent: 'center' },
  initials: { color: '#ffffff', fontSize: 36, fontWeight: '900' },
  camera: { position: 'absolute', right: 0, bottom: 2, width: 34, height: 34, borderRadius: 17, backgroundColor: colors.dark, borderWidth: 2, borderColor: '#ffffff', alignItems: 'center', justifyContent: 'center' },
  heroName: { color: '#ffffff', fontSize: 20, fontWeight: '900' },
  heroRole: { color: 'rgba(255,255,255,0.85)' },
  message: { fontSize: 14 },
  card: { borderRadius: 14, backgroundColor: '#ffffff' },
  cardTitle: { fontWeight: '800' },
  small: { color: colors.muted, fontSize: 12 },
});
