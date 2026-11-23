// Contrat de l'API e-Commune v1 (routes/api/v1 de la plateforme Web).

export interface Role {
  name: string;
  label: string;
}

export interface Institution {
  type: 'mairie';
  id: number;
  name: string;
}

export interface User {
  id: number;
  name: string;
  /** Photo de profil choisie par l'utilisateur (null : initiales). */
  photo_url: string | null;
  phone: string;
  role: Role | null;
  institution: Institution | null;
  must_change_password: boolean;
  permissions: string[];
}

/** Réponse de POST /auth/login et de PUT /auth/password. */
export interface AuthResponse {
  token: string;
  token_type: 'Bearer';
  expires_at: string;
  abilities: string[];
  password_change_required: boolean;
  user: User;
}

/** Corps d'erreur JSON de l'API. */
export interface ApiErrorBody {
  message?: string;
  code?: 'account_disabled' | 'channel_forbidden' | 'password_change_required' | 'forbidden' | 'already_issued';
  errors?: Record<string, string[]>;
}

// ---------------------------------------------------------------- Guichet vignette

export interface Moto {
  id: number;
  cadre: string;
  matricule: string | null;
  genre: string | null;
  couleur: string | null;
  derniere_vignette: number | null;
  a_jour: boolean;
  proprietaire: { nom: string; telephone: string } | null;
}

/** GET /vignettes/guichet : montant, catégories de tarif et, si un CH est donné, la moto déjà connue. */
export interface GuichetInfo {
  cadre: string;
  moto: Moto | null;
  annee: number;
  /** Prix de la catégorie de la moto connue (null : moto inconnue ou sans catégorie). */
  montant: number | null;
  categorie: string | null;
  /** Catégories de cylindrée actives de la mairie (« 51 à 125 cm³ »…). */
  categories: { nom: string; tranche: string; montant: number }[];
  /** Pénalité de retard réglée par la mairie. */
  retard: { mode: 'double' | 'fixe' | 'aucune'; libelle: string; montant_fixe: number; date_limite: string | null; annees_en_retard: number[] };
}

/** Vignette délivrée (POST /vignettes/guichet, GET /vignettes). */
export interface Vignette {
  id: number;
  annee: number;
  montant: { base: number; majoration: number; total: number };
  categorie: string | null;
  detenteur: string | null;
  telephone_detenteur: string | null;
  modele: string;
  delivree_le: string;
  moto: Moto;
  /** Carte imprimable (HTML, modèle de l'année), à charger avec le jeton puis à imprimer. */
  carte_url: string;
}

// ---------------------------------------------------------------- Actes de naissance

/** Réglages d'état civil de la mairie, repris automatiquement (GET /actes/nouveau). */
export interface CivilSettings {
  pret: boolean;
  centre: string | null;
  officier: string | null;
  localite_par_defaut: string | null;
}

/** Réglages complets de l'état civil (GET/PUT /etat-civil/reglages), écran « Réglages de l'état civil ». */
export interface CivilSettingsForm {
  mairie: string;
  pret: boolean;
  civil_officer_name: string | null;
  civil_officer_title: string | null;
  civil_centre_type: 'principal' | 'secondaire';
  civil_centre_name: string | null;
  civil_region: string | null;
  civil_cercle: string | null;
  civil_arrondissement: string | null;
  civil_commune: string | null;
  civil_birth_place: string | null;
  civil_extract_price: number | null;
  civil_marriage_extract_price: number | null;
  civil_print_mode: 'blanc' | 'preimprime';
  civil_print_offset_x: number | null;
  civil_print_offset_y: number | null;
}

export interface Acte {
  id: number;
  act_number: string;
  act_year: number;
  first_names: string;
  last_name: string;
  birth_date: string;
  birth_time: string | null;
  birth_place: string;
  gender: 'homme' | 'femme';
  father_first_names: string | null;
  father_last_name: string | null;
  father_profession: string | null;
  father_domicile: string | null;
  mother_first_names: string;
  mother_last_name: string;
  mother_profession: string | null;
  mother_domicile: string | null;
  extraits: { id: number; delivered_at: string; officer_name: string; url: string }[];
}

export interface ActeSummary {
  id: number;
  act_number: string;
  act_year: number;
  nom_complet: string;
  birth_date: string;
  centre: string;
}

/** POST /actes, PUT /actes/{id}. */
export interface SaveActeResponse {
  data: Acte;
  extrait_url: string | null;
}

// ---------------------------------------------------------------- Actes de mariage

/** Ligne de liste (GET /mariages). */
export interface MariageSummary {
  id: number;
  act_number: string;
  act_year: number;
  epoux: string;
  epouse: string;
  celebrated_at: string;
  centre: string;
}

/** Acte complet (GET /mariages/{id}) : champs aux noms des colonnes, dates ISO. */
export interface Mariage extends Record<string, unknown> {
  id: number;
  act_number: string;
  act_year: number;
  extraits: { id: number; delivered_at: string; url: string }[];
}
