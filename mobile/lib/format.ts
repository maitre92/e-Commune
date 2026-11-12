// Mise en forme partagée entre les écrans (dates, montants) — mêmes conventions que la plateforme Web.

export function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
}

export function formatFcfa(amount: number): string {
  return `${amount.toLocaleString('fr-FR').replace(/ /g, ' ')} FCFA`;
}

/** « 2026-11-10 » → « 10/11/2026 » (saisie au format habituel du guichet). */
export function isoToFr(iso: string | null | undefined): string {
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso ?? '');
  return match ? `${match[3]}/${match[2]}/${match[1]}` : '';
}

/** « 10/11/2026 » → « 2026-11-10 » ; renvoie la saisie telle quelle si elle n'est pas reconnue (le serveur refusera). */
export function frToIso(value: string): string {
  const match = /^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$/.exec(value.trim());
  return match ? `${match[3]}-${match[2].padStart(2, '0')}-${match[1].padStart(2, '0')}` : value.trim();
}

/** Date du jour au format JJ/MM/AAAA. */
export function todayFr(): string {
  return isoToFr(new Date().toISOString().slice(0, 10));
}
