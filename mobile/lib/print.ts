import * as Print from 'expo-print';
import { api } from './api';

/** Charge, avec le jeton, la page imprimable renvoyée par l'API (carte de vignette, extrait d'acte de naissance). */
export async function fetchPrintable(url: string): Promise<string> {
  const { data } = await api.get<string>(url, { responseType: 'text', headers: { Accept: 'text/html' } });
  return data;
}

/** Confie une page à l'impression du téléphone (imprimante Wi-Fi/Bluetooth, ou PDF). */
export async function printHtml(html: string): Promise<void> {
  await Print.printAsync({ html });
}

/** Charge puis imprime directement (sans aperçu). */
export async function printFromApi(url: string): Promise<void> {
  await printHtml(await fetchPrintable(url));
}
