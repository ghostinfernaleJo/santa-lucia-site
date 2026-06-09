# Guide Frontend — App React Native Santa Lucia

Comment consommer l'API REST Santa Lucia dans l'application React Native.
Voir aussi `API-MOBILE.md` (référence complète des endpoints).

- **Base URL** : `https://complexesantalucia.com/wp-json/santa-lucia/v1`
- Lecture seule, JSON, sans authentification.
- Données identiques au site (mises à jour en temps réel par les gestionnaires).

---

## 1. Types TypeScript

```ts
export interface Image {
  thumbnail: string;
  medium: string;
  large: string;
  full: string;
}

export interface Agence {
  id: number;
  slug: string;       // ex: "akwa" — clé de filtre
  nom: string;
  nombre: number;
}

export interface Categorie {
  id: number;
  slug: string;
  nom: string;
  nom_affiche?: string; // Fast Food : libellé à afficher
}

export interface Repas {
  id: number;
  titre: string;
  agence: string | null;
  categorie: { id: number; nom: string; nom_affiche: string } | null;
  jours: string[];                  // ["lundi", ...]
  disponible_aujourdhui: boolean | null;
  promo: { prix: number; debut: string | null; fin: string | null } | null;
  image: Image | null;
}

export interface BonPlan {
  id: number;
  titre: string;
  agence: string | null;
  categorie: { id: number; nom: string } | null;
  prix_avant: number;
  prix_apres: number;
  reduction_pct: number;
  economie: number;
  badge: string | null;
  date_fin: string | null;
  image: Image | null;
}

export interface Promotion {
  id: number;
  titre: string;
  prix_avant: number | null;
  prix_apres: number | null;
  reduction_pct: number;
  en_promo: boolean;
  categories: { id: number; slug: string; nom: string }[];
  image: Image | null;
  permalink: string;
}

export interface Paginated<T> {
  items: T[];
  pagination: { total: number; page: number; per_page: number; total_pages: number };
}
```

---

## 2. Client API réutilisable

```ts
const BASE = 'https://complexesantalucia.com/wp-json/santa-lucia/v1';

async function api<T>(path: string, params: Record<string, any> = {}): Promise<T> {
  const qs = Object.entries(params)
    .filter(([, v]) => v !== undefined && v !== null && v !== '')
    .map(([k, v]) => `${k}=${encodeURIComponent(String(v))}`)
    .join('&');
  const url = `${BASE}${path}${qs ? `?${qs}` : ''}`;
  const res = await fetch(url, { headers: { Accept: 'application/json' } });
  if (!res.ok) throw new Error(`API ${res.status} sur ${path}`);
  return res.json();
}

// Endpoints
export const getAgences        = () => api<Agence[]>('/agences');
export const getFFCategories   = () => api<Categorie[]>('/fastfood/categories');
export const getMenu           = (p: { agence?: string; jour?: string; category?: number; page?: number; per_page?: number }) =>
                                   api<Paginated<Repas>>('/fastfood/menu', p);
export const getBPCategories   = () => api<Categorie[]>('/bons-plans/categories');
export const getBonsPlans      = (p: { agence?: string; categorie?: number; orderby?: 'reduc'|'prix_asc'|'prix_desc'|'date'; actifs?: boolean; page?: number; per_page?: number }) =>
                                   api<Paginated<BonPlan>>('/bons-plans', p);
export const getCampagnes      = () => api<any[]>('/promotions/campagnes');
export const getPromotions     = (p: { campagne?: number; category?: number; page?: number; per_page?: number }) =>
                                   api<Paginated<Promotion>>('/promotions', p);
```

---

## 3. Exemples par écran

### Écran « Menu du jour » (Fast Food)
```tsx
const [menu, setMenu] = useState<Repas[]>([]);
const agence = 'akwa';

useEffect(() => {
  getMenu({ agence, jour: 'today' })          // jour=today => menu du jour
    .then(r => setMenu(r.items))
    .catch(console.warn);
}, [agence]);
```
> Pour la semaine entière, ne pas passer `jour` : chaque plat renvoie son tableau `jours`.

### Écran « Bons Plans »
```tsx
getBonsPlans({ orderby: 'reduc', per_page: 20 })
  .then(r => setBonsPlans(r.items));
```
Carte d'un bon plan :
```tsx
<View>
  {bp.image && <Image source={{ uri: bp.image.medium }} style={{ width: '100%', height: 160 }} />}
  {bp.badge && <Text style={styles.badge}>{bp.badge}</Text>}
  <Text>{bp.titre}</Text>
  <Text style={styles.old}>{bp.prix_avant.toLocaleString('fr-FR')} FCFA</Text>
  <Text style={styles.new}>{bp.prix_apres.toLocaleString('fr-FR')} FCFA</Text>
  <Text>-{bp.reduction_pct}%</Text>
</View>
```

### Écran « Promotions »
```tsx
getPromotions({ per_page: 20 }).then(r => setPromos(r.items));
// ou filtrer par campagne :
const camps = await getCampagnes();
getPromotions({ campagne: camps[0].id });
```

---

## 4. Images (important)

- `image` est un objet ou `null`. **Toujours tester `null`** avant d'afficher.
- Choisir la taille selon l'usage : `thumbnail` (listes/petites vignettes), `medium` (cartes), `large` (plein écran).
```tsx
{item.image
  ? <Image source={{ uri: item.image.medium }} style={s.img} />
  : <Image source={require('./assets/placeholder.png')} style={s.img} />}
```
- ⚠️ Côté Fast Food, l'image peut être `null` tant que le gestionnaire n'a pas associé d'image au plat → prévoir un **placeholder**.

---

## 5. Pagination & filtres

```ts
async function loadAll(): Promise<BonPlan[]> {
  let page = 1, all: BonPlan[] = [], totalPages = 1;
  do {
    const r = await getBonsPlans({ page, per_page: 50 });
    all = all.concat(r.items);
    totalPages = r.pagination.total_pages;
    page++;
  } while (page <= totalPages);
  return all;
}
```
Filtres combinables : `getMenu({ agence:'akwa', category:408 })`, `getBonsPlans({ agence:'akwa', categorie:12, orderby:'reduc' })`.

---

## 6. Bonnes pratiques

- **Pull-to-refresh** : re-appeler l'endpoint (les données sont live).
- **Cache léger** : possibilité de mettre en cache 1–5 min côté app (l'hébergeur peut aussi cacher via Varnish).
- **Erreurs** : encapsuler les appels dans try/catch et afficher un état d'erreur + bouton réessayer.
- **Sélecteur d'agence** : charger `/agences` au démarrage, stocker le `slug` choisi, le passer à tous les écrans.
- **Jours** : valeurs `lundi`…`dimanche` (minuscules, sans accent).
</content>
