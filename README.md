# Santa Lucia — Code custom du site WordPress

Contrôle de version du **code custom** du site [complexesantalucia.com](https://complexesantalucia.com).
Déploiement automatique vers l'hébergement (FTP) à chaque `push` sur `main`.

## Contenu versionné

| Dossier | Rôle |
|---|---|
| `wp-content/plugins/sl-agences-elementor/` | Plugin custom : widgets Elementor, Bons Plans, Campagnes Woo, Import Magique IA |
| `wp-content/plugins/sl-fastfood/` | Plugin custom : menus Fast Food, import/export, images des plats |
| `wp-content/themes/grogin-child/` | Thème enfant (surcharges CSS/JS/templates) |

## ⚠️ Ce qui N'EST PAS dans Git (important)

Git versionne le **code**, pas le **contenu**. Ne sont donc **pas** ici :
- Le cœur de WordPress et les plugins tiers (Elementor, WooCommerce, etc.)
- Les **médias** (`wp-content/uploads/`)
- La **base de données** : pages, produits, menus, layouts Elementor, repas, prix… vivent dans la BDD.
- `wp-config.php` et tout secret.

👉 Pour le contenu (BDD + médias), garder des **sauvegardes** séparées (WPvivid / Duplicator).

## Comment travailler

```bash
# 1. Modifier les fichiers (avec Claude Code, ou à la main)
# 2. Vérifier ce qui change
git status
git diff

# 3. Commiter
git add -A
git commit -m "Description claire du changement"

# 4. Pousser -> déploiement automatique
git push
```

Le push déclenche GitHub Actions (`.github/workflows/deploy.yml`) qui envoie les fichiers
modifiés sur le serveur via FTP. Suivi en direct dans l'onglet **Actions** du dépôt GitHub.

## Configuration requise (une seule fois) — secrets GitHub

Dans le dépôt GitHub : **Settings → Secrets and variables → Actions → New repository secret**, créer :

| Nom du secret | Valeur |
|---|---|
| `FTP_SERVER` | `complexesantalucia.com` |
| `FTP_USERNAME` | l'utilisateur FTP |
| `FTP_PASSWORD` | le mot de passe FTP |

Ces secrets sont chiffrés par GitHub et ne sont jamais visibles dans le code.

## ⚠️ Cache Varnish

Un fichier **JS/CSS modifié mais portant le même nom** est resservi par Varnish (cache par nom de fichier).
Pour ces cas : renommer le fichier (ex. `bons-plans-v3.css` → `v4.css`) et mettre à jour l'`enqueue`,
ou purger Varnish côté serveur. Le déploiement FTP seul ne purge pas le cache.

## Sécurité du déploiement

- Le déploiement n'écrase QUE les fichiers présents dans ce dépôt — il ne touche pas
  au cœur WordPress ni aux plugins tiers.
- L'action ne supprime sur le serveur que les fichiers retirés **de ce dépôt** entre deux déploiements.
