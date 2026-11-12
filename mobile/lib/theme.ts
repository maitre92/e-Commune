// Palette e-Commune (application mobile) : vert institutionnel, avec les couleurs du drapeau malien en accent.
export const colors = {
  primary: '#1e7a46', // vert e-Commune (boutons, onglet actif)
  dark: '#0f5132', // vert foncé (en-têtes)
  accent: '#fcd116', // jaune du drapeau
  lightBlue: '#bfe3cc', // texte clair sur fond vert
  background: '#f3f6f4',
  surface: '#ffffff',
  text: '#1f2937',
  muted: '#64748b',
  border: '#e2e8e4',
  danger: '#ce1126', // rouge du drapeau
  success: '#1e7a46',
  warning: '#e0a800',
  info: '#1d4e89',
} as const;

/** Couleurs du drapeau malien (liseré tricolore de l'en-tête et de l'accueil). */
export const mali = { green: '#14b53a', yellow: '#fcd116', red: '#ce1126' } as const;

/** Teintes des cartes d'action de l'accueil (vignette, acte, caisse), comme la maquette. */
export const tints = {
  green: { bg: '#e7f5ec', border: '#1e9e55', icon: '#1e7a46', circle: '#cdeedb', soft: '#9fd9b4' },
  blue: { bg: '#e7f0fb', border: '#1d6fd1', icon: '#1d4e89', circle: '#d3e4f8', soft: '#9cc3ee' },
  yellow: { bg: '#fff6dc', border: '#f2b705', icon: '#1e6b3a', circle: '#ffe7a0', soft: '#e9b824' },
  red: { bg: '#fdecee', border: '#ce1126', icon: '#a10e1f', circle: '#f9d0d5', soft: '#f0a3ad' },
} as const;

/** Pastilles de statut douces, mêmes couleurs que les badges `.bg-*-subtle` de la plateforme Web. */
export const statusColors = {
  primary: { bg: '#e7f5ec', text: '#1e7a46' },
  secondary: { bg: '#eceff3', text: '#4d5b6e' },
  success: { bg: '#e0f5e8', text: '#14803f' },
  danger: { bg: '#fde6e8', text: '#b4232e' },
  warning: { bg: '#fff1d6', text: '#a86400' },
  // .bg-light/.text-dark (Bootstrap standard, pas remplacé par le thème) : « En attente » côté Web.
  light: { bg: '#f8f9fa', text: '#212529' },
} as const;

