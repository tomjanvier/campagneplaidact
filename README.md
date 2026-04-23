# PLAID·ACT Campaign Factory

Boilerplate WordPress orienté **campagnes multisite** avec une séparation claire :

- **Plugin (`plaidact-campaign-core`)** : logique métier réutilisable (CPT, taxonomies, métadonnées, shortcodes).
- **Thème (`plaidact-campaign`)** : rendu one-page, UX, styles, sections éditoriales.

## Objectif

Ce repository permet de créer rapidement des sites de campagnes homogènes, tout en gardant chaque sous-site personnalisable via le Customizer.

## Arborescence GitHub (logique plugin / thème)

```text
.
├─ README.md
├─ ARCHITECTURE.md
└─ wp-content/
   ├─ plugins/
   │  └─ plaidact-campaign-core/
   │     ├─ plaidact-campaign-core.php
   │     └─ includes/
   │        ├─ class-plaidact-campaign-cpt.php
   │        └─ class-plaidact-campaign-shortcodes.php
   └─ themes/
      └─ plaidact-campaign/
         ├─ style.css
         ├─ functions.php
         ├─ front-page.php
         ├─ header.php
         ├─ footer.php
         ├─ inc/
         │  └─ customizer.php
         └─ template-parts/
            └─ sections/
               ├─ partners.php
               ├─ petition.php
               ├─ breves.php
               ├─ articles.php
               └─ social-wall.php
```

## Côté plugin : `plaidact-campaign-core`

### Contenu livré

- CPT `plaid_breve` pour les actualités courtes de campagne.
- CPT `plaid_partner` pour les organisations partenaires (avec ordre manuel).
- Taxonomies :
  - `plaid_breve_topic` (thématiques des brèves)
  - `plaid_partner_type` (classification partenaires)
- Métadonnée REST-compatible `_plaid_partner_url` avec metabox admin.
- Shortcodes de base :
  - `[petition_form]`
  - `[plaid_social_wall]`

### Activation

- Activer le plugin en **network activation** sur le multisite.

## Côté thème : `plaidact-campaign`

### Fonctionnalités

- Template `front-page.php` one-page (hero + sections).
- Sections découplées en `template-parts/sections/*`.
- Toggles Customizer pour activer/désactiver les blocs (pétition, social wall, articles).
- Hero personnalisable (titre, sous-titre, image/vidéo).
- Support logo personnalisé et menu one-page.
- CSS de base pour hero, cartes, CTA.

### Dépendances logiques

Le thème peut fonctionner seul visuellement, mais les sections **Brèves**, **Partenaires**, **Pétition**, **Social wall** prennent tout leur sens quand le plugin core est actif.

## Workflow recommandé

1. Activer le plugin `plaidact-campaign-core` (network).
2. Activer le thème `plaidact-campaign` sur un sous-site.
3. Configurer :
   - Menu one-page
   - Hero dans le Customizer
   - Contenus (Brèves, Partenaires, Articles)
4. Ajuster les shortcodes/embeds selon les outils réels de pétition et de social wall.

## Notes

- La police `gotham-noir.woff2` est optionnelle (fallback Inter/système).
- Le code est pensé pour être enrichi ensuite (connecteurs API, bloc Gutenberg, tracking analytics).
