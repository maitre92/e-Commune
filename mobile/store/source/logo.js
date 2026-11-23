// Logo e-Commune (mairie stylisée + liseré du drapeau malien), source unique de toutes les images de l'application
// et de la fiche Play Store. Régénérer : node store/source/render.js (voir store/README.md).
const GREEN_LIGHT = '#1e7a46';
const GREEN_DARK = '#0f5132';
const MALI = ['#14b53a', '#fcd116', '#ce1126'];

/** Bâtiment dans une boîte 644 x 600 (origine 0,0). `fg` : couleur du bâtiment, `hole` : détails évidés. */
function building(fg = '#ffffff', hole = GREEN_DARK, stripe = true) {
  const cols = [70, 195, 349, 474].map((x) => `<rect x="${x}" y="225" width="100" height="235" rx="6" fill="${fg}"/>`).join('');
  const stripeSvg = stripe
    ? MALI.map((c, i) => `<rect x="${i * 214.67}" y="568" width="214.67" height="32" fill="${c}"/>`).join('')
    : '';
  return `
    <polygon points="322,0 624,150 20,150" fill="${fg}"/>
    <circle cx="322" cy="98" r="30" fill="${hole}"/>
    <rect x="40" y="162" width="564" height="46" rx="6" fill="${fg}"/>
    ${cols}
    <rect x="30" y="472" width="584" height="38" rx="6" fill="${fg}"/>
    <rect x="0" y="518" width="644" height="38" rx="6" fill="${fg}"/>
    ${stripeSvg}`;
}

/** Groupe centré sur (cx, cy) à l'échelle `scale`. */
const place = (inner, cx, cy, scale) => `<g transform="translate(${cx - 322 * scale} ${cy - 300 * scale}) scale(${scale})">${inner}</g>`;

const gradient = (id) => `<defs><linearGradient id="${id}" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="${GREEN_LIGHT}"/><stop offset="1" stop-color="${GREEN_DARK}"/></linearGradient></defs>`;

module.exports = {
  GREEN_DARK,
  /** Icône pleine (lanceur iOS/ancien Android, Play Store 512) : fond vert, bâtiment blanc. */
  icon: (size) => `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 1024 1024">${gradient('g')}<rect width="1024" height="1024" fill="url(#g)"/>${place(building(), 512, 530, 1.0)}</svg>`,
  /** Premier plan de l'icône adaptative Android : transparent, dans la zone sûre (cercle de 66 %). */
  adaptive: () => `<svg xmlns="http://www.w3.org/2000/svg" width="1024" height="1024" viewBox="0 0 1024 1024">${place(building('#ffffff', GREEN_DARK), 512, 520, 0.66)}</svg>`,
  /** Icône monochrome (Android 13+, icônes à thème) : une seule couleur, transparent. */
  monochrome: () => `<svg xmlns="http://www.w3.org/2000/svg" width="1024" height="1024" viewBox="0 0 1024 1024"><mask id="m"><rect width="1024" height="1024" fill="white"/>${place('<circle cx="322" cy="98" r="30" fill="black"/>', 512, 520, 0.66)}</mask><g mask="url(#m)">${place(building('#ffffff', '#ffffff', false), 512, 520, 0.66)}</g></svg>`,
  /** Écran de démarrage : bâtiment + « e-Commune », sur fond transparent (fond vert réglé dans app.json). */
  splash: () => `<svg xmlns="http://www.w3.org/2000/svg" width="1024" height="1024" viewBox="0 0 1024 1024">${place(building(), 512, 400, 0.85)}<text x="512" y="830" text-anchor="middle" font-family="Inter, 'DejaVu Sans', Arial, sans-serif" font-weight="800" font-size="120" fill="#ffffff">e-Commune</text></svg>`,
  /** Bannière Play Store 1024 x 500. */
  feature: () => `<svg xmlns="http://www.w3.org/2000/svg" width="1024" height="500" viewBox="0 0 1024 500">${gradient('g')}<rect width="1024" height="500" fill="url(#g)"/>
    <circle cx="905" cy="70" r="190" fill="#ffffff" opacity="0.05"/><circle cx="980" cy="470" r="150" fill="#ffffff" opacity="0.05"/>
    ${place(building(), 215, 250, 0.52)}
    <text x="420" y="205" font-family="Inter, 'DejaVu Sans', Arial, sans-serif" font-weight="800" font-size="76" fill="#ffffff">e-Commune</text>
    ${MALI.map((c, i) => `<rect x="${424 + i * 90}" y="228" width="90" height="10" fill="${c}"/>`).join('')}
    <text font-family="Inter, 'DejaVu Sans', Arial, sans-serif" font-size="33" fill="#e6f4ea">
      <tspan x="424" y="295">Vignettes, actes de naissance,</tspan>
      <tspan x="424" y="340">actes de mariage et caisse</tspan>
      <tspan x="424" y="385">de la mairie, sur téléphone.</tspan>
    </text></svg>`,
};
