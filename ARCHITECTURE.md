# Architecture de la factory de campagne

## Principe

Le dépôt est désormais organisé autour d’un seul plugin WordPress : `plaidact-campaign-core`. Le thème dédié a été supprimé et les plugins auparavant séparés ont été fusionnés dans le core.

## Arborescence cible

```text
wp-content/
└─ plugins/
   └─ plaidact-campaign-core/
      ├─ plaidact-campaign-core.php
      ├─ assets/
      │  ├─ campagne-onepage.css
      │  └─ campagne-onepage.js
      ├─ includes/
      │  ├─ class-plaidact-campaign-cpt.php
      │  ├─ class-plaidact-campaign-demo.php
      │  ├─ class-plaidact-campaign-onepage.php
      │  ├─ class-plaidact-campaign-petitioner-integration.php
      │  ├─ class-plaidact-campaign-petition-workflows.php
      │  ├─ class-plaidact-campaign-polylang.php
      │  └─ class-plaidact-campaign-shortcodes.php
      ├─ templates/
      │  └─ campagne-onepage.php
      └─ vendor/
         └─ petitioner/
```

## Responsabilités du plugin

### Core campagne

- Déclare les contenus de campagne (`plaid_breve`, `plaid_partner`).
- Déclare les taxonomies métier, dont `campagne` pour générer les pages one-page.
- Gère les métadonnées partenaires.
- Expose les réglages de campagne et les toggles d’activation des blocs frontend.
- Fournit les shortcodes transverses.

### Rendu one-page

- Le fichier `includes/class-plaidact-campaign-onepage.php` déclare la taxonomie `campagne`.
- À la création d’une campagne, le plugin crée une page WordPress contenant le shortcode `[plaidact_campagne_onepage]`.
- Le template `templates/campagne-onepage.php` et les assets dans `assets/` rendent la campagne sans dépendre d’un thème dédié.
- Les blocs pétition, newsletter, envoi au décideur, social wall, articles et rapport PDF sont activables via **Réglages → PLAID·ACT Campagne**.

### Module Petitioner

- Le moteur Petitioner est embarqué dans `vendor/petitioner`.
- Il est chargé automatiquement par `plaidact-campaign-core.php`.
- Il ne doit pas être activé comme plugin autonome.

## Notes multisite

- Activer `PLAID·ACT Campaign Core` en network activation.
- Chaque sous-site conserve ses options `plaidact_campaign_settings`.
- Si Polylang est actif, les chaînes textuelles configurées dans les réglages restent traduisibles.
