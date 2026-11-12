import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Modal, StyleSheet, Text, View } from 'react-native';
import { Button } from 'react-native-paper';
import { SafeAreaView } from 'react-native-safe-area-context';
import { WebView } from 'react-native-webview';
import { API_URL, apiErrorMessage } from '@/lib/api';
import { fetchPrintable, printHtml } from '@/lib/print';
import { colors } from '@/lib/theme';

interface Props {
  /** Adresse de la page imprimable (carte de vignette, extrait d'acte) ; null : fermé. */
  url: string | null;
  title: string;
  onClose: () => void;
  /** Remplace « Imprimer l'aperçu » (ex. délivrer l'extrait d'un acte : numéroté et encaissé). */
  onPrint?: () => void;
}

/**
 * Aperçu avant impression : le document s'affiche tel qu'il sera imprimé (carte de vignette recto + verso, extrait
 * d'acte de naissance), puis « Imprimer » ou « Fermer ».
 */
export default function PreviewModal({ url, title, onClose, onPrint }: Props) {
  const [html, setHtml] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [printing, setPrinting] = useState(false);

  useEffect(() => {
    setHtml(null);
    setError(null);
    if (url) {
      fetchPrintable(url)
        .then(setHtml)
        .catch((e) => setError(apiErrorMessage(e, 'Aperçu impossible.')));
    }
  }, [url]);

  const print = async () => {
    if (!html) {
      return;
    }
    setPrinting(true);
    try {
      await printHtml(html);
    } catch (e) {
      setError(apiErrorMessage(e, 'Impression impossible.'));
    } finally {
      setPrinting(false);
    }
  };

  // Les images et polices de la page sont servies par la plateforme (même adresse que l'API).
  const baseUrl = API_URL.replace(/\/api\/v1\/?$/, '/');

  return (
    <Modal visible={url !== null} animationType="slide" onRequestClose={onClose}>
      <SafeAreaView style={styles.screen}>
        <View style={styles.header}>
          <MaterialCommunityIcons name="file-eye-outline" size={24} color="#ffffff" />
          <Text style={styles.title}>Aperçu — {title}</Text>
        </View>

        <View style={styles.page}>
          {html ? (
            <WebView
              originWhitelist={['*']}
              source={{ html, baseUrl }}
              style={{ backgroundColor: '#e9e6df' }}
              setBuiltInZoomControls
              setDisplayZoomControls={false}
              scalesPageToFit
            />
          ) : error ? (
            <Text style={styles.error}>{error}</Text>
          ) : (
            <ActivityIndicator style={{ marginTop: 40 }} size="large" color={colors.primary} />
          )}
        </View>

        <View style={styles.actions}>
          <Button mode="outlined" icon="close" onPress={onClose} style={{ flex: 1 }}>
            Fermer
          </Button>
          <Button mode="contained" icon="printer" onPress={onPrint ? () => { onClose(); onPrint(); } : print} loading={printing} disabled={!html || printing} style={{ flex: 1 }}>
            Imprimer
          </Button>
        </View>
      </SafeAreaView>
    </Modal>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.dark },
  header: { flexDirection: 'row', alignItems: 'center', gap: 10, padding: 14 },
  title: { color: '#ffffff', fontSize: 17, fontWeight: '800', flex: 1 },
  page: { flex: 1, backgroundColor: '#e9e6df' },
  error: { color: colors.danger, padding: 20, fontSize: 15 },
  actions: { flexDirection: 'row', gap: 10, padding: 12, backgroundColor: '#ffffff' },
});
