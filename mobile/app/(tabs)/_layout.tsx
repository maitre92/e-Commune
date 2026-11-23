import { MaterialCommunityIcons } from '@expo/vector-icons';
import { Tabs, usePathname, useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { AppState, type ColorValue, Pressable, View } from 'react-native';
import { Divider, Menu } from 'react-native-paper';
import { HeaderBackground, HeaderTitle } from '@/components/AppHeader';
import { useAuth } from '@/context/AuthContext';
import { colors } from '@/lib/theme';

const ICON = { color: '#ffffff', size: 28 } as const;

/**
 * Bouton gauche de l'en-tête : le menu (☰) sur l'accueil, la flèche de retour ailleurs (retour à l'écran
 * précédent, sinon à l'accueil).
 */
function HeaderLeft() {
  const router = useRouter();
  const pathname = usePathname();
  const { user, logout } = useAuth();
  const [open, setOpen] = useState(false);
  const can = (permission: string) => user?.permissions.includes(permission) ?? false;

  if (pathname !== '/') {
    return (
      <Pressable onPress={() => (router.canGoBack() ? router.back() : router.navigate('/'))} hitSlop={12} style={{ paddingHorizontal: 16 }} accessibilityLabel="Retour">
        <MaterialCommunityIcons name="arrow-left" {...ICON} />
      </Pressable>
    );
  }

  const go = (href: string) => {
    setOpen(false);
    router.navigate(href as never);
  };

  return (
    <Menu
      visible={open}
      onDismiss={() => setOpen(false)}
      anchor={
        <Pressable onPress={() => setOpen(true)} hitSlop={12} style={{ paddingHorizontal: 16 }} accessibilityLabel="Menu">
          <MaterialCommunityIcons name="menu" {...ICON} />
        </Pressable>
      }>
      <Menu.Item leadingIcon="home-outline" title="Accueil" onPress={() => go('/')} />
      {can('demandes-vgt.create') ? <Menu.Item leadingIcon="card-account-details-outline" title="Vendre une vignette" onPress={() => go('/vignette')} /> : null}
      {can('actes-naissance.view') ? <Menu.Item leadingIcon="baby-face-outline" title="Acte de naissance" onPress={() => go('/actes')} /> : null}
      {can('actes-mariage.view') ? <Menu.Item leadingIcon="ring" title="Acte de mariage" onPress={() => go('/mariages')} /> : null}
      {can('actes-naissance.configure') || can('actes-mariage.configure') ? <Menu.Item leadingIcon="cog-outline" title="Réglages de l'état civil" onPress={() => go('/etat-civil')} /> : null}
      {can('caisse.view_own') ? <Menu.Item leadingIcon="cash-register" title="Caisse" onPress={() => go('/caisse')} /> : null}
      <Menu.Item leadingIcon="account-circle-outline" title="Mon profil" onPress={() => go('/profile')} />
      <Divider />
      <Menu.Item leadingIcon="logout" title="Se déconnecter" onPress={() => { setOpen(false); logout(); }} />
    </Menu>
  );
}

/** Cloche : ouvre la caisse (recettes du jour, versements). */
function HeaderRight() {
  const router = useRouter();
  const { user } = useAuth();

  if (!user?.permissions.includes('caisse.view_own')) {
    return <View style={{ width: 44 }} />;
  }

  return (
    <Pressable onPress={() => router.navigate('/caisse')} hitSlop={12} style={{ paddingHorizontal: 16 }} accessibilityLabel="Caisse">
      <MaterialCommunityIcons name="bell-outline" {...ICON} />
    </Pressable>
  );
}

/**
 * Onglets selon la fonction du compte : Accueil (toujours), Vignette (vendeur, polyvalent, maire), Acte de
 * naissance, Caisse (sa caisse, ou toute la mairie pour le maire), Profil.
 */
export default function TabsLayout() {
  const { user, refreshUser } = useAuth();
  const can = (permission: string) => user?.permissions.includes(permission) ?? false;

  // Les droits du compte sont relus à l'ouverture et à chaque retour dans l'application : un changement de
  // rôle (agent devenu maire…) est pris en compte sans se reconnecter.
  useEffect(() => {
    refreshUser().catch(() => undefined);
    const subscription = AppState.addEventListener('change', (state) => {
      if (state === 'active') {
        refreshUser().catch(() => undefined);
      }
    });
    return () => subscription.remove();
  }, [refreshUser]);

  const tab = (icon: string) =>
    ({ color, size }: { color: ColorValue; size: number }) => <MaterialCommunityIcons name={icon as never} color={color} size={size} />;

  return (
    <Tabs
      // « history » : la flèche de retour (et le bouton retour d'Android) ramène à l'onglet précédent.
      backBehavior="history"
      screenOptions={({ route }) => ({
        headerBackground: () => <HeaderBackground />,
        headerTitle: () => <HeaderTitle title={titles[route.name] ?? ''} />,
        headerTitleAlign: 'center',
        headerStyle: { height: 150 },
        headerShadowVisible: false,
        headerLeft: () => <HeaderLeft />,
        headerRight: () => <HeaderRight />,
        tabBarActiveTintColor: colors.primary,
        tabBarInactiveTintColor: '#4b5563',
        tabBarActiveBackgroundColor: '#e3f3e8',
        tabBarItemStyle: { borderRadius: 16, marginHorizontal: 4, marginVertical: 6 },
        tabBarStyle: { height: 74, paddingBottom: 6, paddingTop: 2 },
        tabBarLabelStyle: { fontSize: 12, fontWeight: '600' },
      })}>
      <Tabs.Screen name="index" options={{ title: 'Accueil', tabBarIcon: tab('home') }} />
      <Tabs.Screen name="vignette" options={{ href: can('demandes-vgt.create') ? undefined : null, title: 'Vignette', tabBarIcon: tab('card-account-details-outline') }} />
      <Tabs.Screen name="actes" options={{ href: can('actes-naissance.view') ? undefined : null, title: 'Naissance', tabBarIcon: tab('baby-face-outline') }} />
      <Tabs.Screen name="mariages" options={{ href: can('actes-mariage.view') ? undefined : null, title: 'Mariage', tabBarIcon: tab('ring') }} />
      <Tabs.Screen name="caisse" options={{ href: can('caisse.view_own') ? undefined : null, title: 'Caisse', tabBarIcon: tab('cash-register') }} />
      {/* Réglages de l'état civil : écran sans onglet, ouvert depuis Naissance, Mariage ou le menu. */}
      <Tabs.Screen name="etat-civil" options={{ href: null, title: 'Réglages de l\'état civil' }} />
      <Tabs.Screen name="profile" options={{ title: 'Profil', tabBarIcon: tab('account-outline') }} />
    </Tabs>
  );
}

/** Nom affiché sous « e-Commune » dans l'en-tête. */
const titles: Record<string, string> = {
  index: 'Accueil',
  vignette: 'Vignette',
  actes: 'Acte de naissance',
  mariages: 'Acte de mariage',
  caisse: 'Caisse',
  profile: 'Mon profil',
  'etat-civil': 'Réglages de l\'état civil',
};
