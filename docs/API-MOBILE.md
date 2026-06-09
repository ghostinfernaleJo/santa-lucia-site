# API Mobile Santa Lucia — Documentation backend

API REST **publique, en lecture seule**, qui expose les données **Fast Food** et **Bons Plans** du site
[complexesantalucia.com](https://complexesantalucia.com) pour l'application React Native.

> Les gestionnaires publient/modifient via l'admin WordPress du site. L'API renvoie **en temps réel**
> les mêmes données — aucune synchronisation manuelle n'est nécessaire.

- **Base URL** : `https://complexesantalucia.com/wp-json/santa-lucia/v1`
- **Méthode** : `GET` uniquement
- **Auth** : aucune (données déjà publiques). En-têtes : `Content-Type: application/json`.
- **Format** : JSON UTF-8.

---

## Conventions

Les listes paginées renvoient :
```json
{
  "items": [ ... ],
  "pagination": { "total": 120, "page": 1, "per_page": 50, "total_pages": 3 }
}
```
Paramètres de pagination communs : `page` (défaut 1), `per_page` (1–100, défaut 50).

Les **images** sont renvoyées en objet multi-tailles (ou `null` si absente) :
```json
"image": {
  "thumbnail": "https://.../img-150x150.jpg",
  "medium":    "https://.../img-300x200.jpg",
  "large":     "https://.../img-1024x683.jpg",
  "full":      "https://.../img.jpg"
}
```
Les prix sont en **FCFA** (nombres).

---

## 1. Agences

Liste des agences (partagées entre Fast Food et Bons Plans).

`GET /agences`

**Réponse :**
```json
[
  { "id": 41, "slug": "akwa", "nom": "Akwa", "nombre": 190 }
]
```
> Le `slug` (ex. `akwa`) est la clé à passer en filtre `agence` sur les autres endpoints.

---

## 2. Fast Food

### 2.1 Catégories
`GET /fastfood/categories`
```json
[
  { "id": 408, "slug": "plat-principal", "nom": "Plat principal", "nom_affiche": "Plats Classiques" }
]
```
`nom_affiche` = libellé montré côté public (utilisez-le dans l'app).

### 2.2 Menu (plats)
`GET /fastfood/menu`

| Param | Type | Description |
|---|---|---|
| `agence` | string | Slug d'agence (ex. `akwa`). Optionnel. |
| `jour` | string | `lundi`…`dimanche`, ou `today` (jour courant). Optionnel. |
| `category` | int | ID de catégorie. Optionnel. |
| `page`, `per_page` | int | Pagination. |

**Exemples :**
- Menu du jour d'une agence : `GET /fastfood/menu?agence=akwa&jour=today`
- Toute la semaine d'une agence : `GET /fastfood/menu?agence=akwa`

**Élément `items[]` :**
```json
{
  "id": 5389,
  "titre": "Poulet braisé",
  "agence": "akwa",
  "categorie": { "id": 408, "nom": "Plat principal", "nom_affiche": "Plats Classiques" },
  "jours": ["lundi","mardi","mercredi"],
  "disponible_aujourdhui": true,
  "promo": { "prix": 2500, "debut": "2026-06-01", "fin": "2026-06-30" },
  "image": { "thumbnail": "...", "medium": "...", "large": "...", "full": "..." }
}
```
- `jours` : jours où le plat est proposé. `disponible_aujourdhui` : raccourci basé sur la date serveur.
- `promo` : `null` si pas de promo.
- `image` : résolue par **nom de plat** (même image dans toutes les agences).

---

## 3. Bons Plans

### 3.1 Catégories
`GET /bons-plans/categories`
```json
[ { "id": 12, "slug": "alcools", "nom": "Alcools" } ]
```

### 3.2 Offres
`GET /bons-plans`

| Param | Type | Description |
|---|---|---|
| `agence` | string | Slug d'agence. Optionnel. |
| `categorie` | int | ID de catégorie (`sl_categorie_promo`). Optionnel. |
| `orderby` | string | `reduc` (plus grosse réduction), `prix_asc`, `prix_desc`, `date` (défaut). |
| `actifs` | bool | `true` (défaut) = uniquement les offres non expirées. `false` = toutes. |
| `page`, `per_page` | int | Pagination. |

**Exemple :** `GET /bons-plans?agence=akwa&orderby=reduc`

**Élément `items[]` :**
```json
{
  "id": 3801,
  "titre": "Vin Rouge Bordeaux 75cl",
  "agence": "akwa",
  "categorie": { "id": 12, "nom": "Alcools" },
  "prix_avant": 5000,
  "prix_apres": 4000,
  "reduction_pct": 20,
  "economie": 1000,
  "badge": "TOP VENTE",
  "date_fin": "2026-06-30",
  "image": { "thumbnail": "...", "medium": "...", "large": "...", "full": "..." }
}
```
- `actifs=true` filtre par `date_fin >= aujourd'hui` (ou sans date de fin).

---

## 4. Promotions (produits WooCommerce)

Produits en promotion = produits des **campagnes actives** + produits **en solde** WooCommerce.

### 4.1 Campagnes
`GET /promotions/campagnes`
```json
[ { "id": 1116, "titre": "Foire aux Vins", "categorie_id": 87, "date_debut": "2026-06-10", "date_fin": "2026-06-25", "active": true } ]
```

### 4.2 Produits en promo
`GET /promotions`

| Param | Type | Description |
|---|---|---|
| `campagne` | int | ID de campagne → ne garde que ses produits. Optionnel. |
| `category` | int | ID de catégorie produit WooCommerce (`product_cat`). Optionnel. |
| `page`, `per_page` | int | Pagination. |

**Élément `items[]` :**
```json
{
  "id": 5388,
  "titre": "Vin Santero Moscato 75cl",
  "prix_avant": 9500,
  "prix_apres": 8500,
  "reduction_pct": 11,
  "en_promo": true,
  "categories": [ { "id": 87, "slug": "foire-aux-vins", "nom": "Foire aux Vins" } ],
  "image": { "thumbnail": "...", "medium": "...", "large": "...", "full": "..." },
  "permalink": "https://complexesantalucia.com/produit/vin-santero-moscato/"
}
```

---

## Notes d'intégration

- **CORS** : appels natifs (React Native) non concernés. Pour un dashboard web sur un autre domaine,
  prévoir l'ajout d'en-têtes CORS côté serveur si besoin.
- **Cache** : les réponses peuvent être mises en cache (Varnish) côté hébergeur ; prévoir un petit TTL
  côté app si une fraîcheur immédiate est requise.
- **Écriture / gestion depuis l'app** : ces endpoints sont en lecture seule. Si vous voulez que des
  gestionnaires créent/modifient depuis l'app, il faudra des endpoints protégés par authentification
  (à ajouter ultérieurement).
- **Codes** : `200` OK. Paramètres invalides ignorés (valeurs par défaut appliquées).

---

## Récapitulatif des endpoints

| Méthode | Endpoint | Rôle |
|---|---|---|
| GET | `/agences` | Liste des agences |
| GET | `/fastfood/categories` | Catégories Fast Food |
| GET | `/fastfood/menu` | Plats (filtres : agence, jour, catégorie) |
| GET | `/bons-plans/categories` | Catégories Bons Plans |
| GET | `/bons-plans` | Offres (filtres : agence, catégorie, tri, actifs) |
| GET | `/promotions/campagnes` | Campagnes WooCommerce |
| GET | `/promotions` | Produits en promo (filtres : campagne, catégorie) |
