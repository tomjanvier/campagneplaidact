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
      │  ├─ blocks.js
      │  └─ campaign-shortcodes.css
      ├─ includes/
      │  ├─ class-plaidact-campaign-blocks.php
      │  ├─ class-plaidact-campaign-cpt.php
      │  ├─ class-plaidact-campaign-demo.php
      │  ├─ class-plaidact-campaign-petitioner-integration.php
      │  ├─ class-plaidact-campaign-petition-workflows.php
      │  ├─ class-plaidact-campaign-polylang.php
      │  └─ class-plaidact-campaign-shortcodes.php
      └─ vendor/
         └─ petitioner/
```

## Responsabilités du plugin

### Core campagne

- Déclare les contenus de campagne (`plaid_breve`, `plaid_partner`).
- Déclare les taxonomies métier utiles aux contenus de campagne.
- Gère les métadonnées partenaires.
- Expose les réglages de campagne et les toggles d’activation des modules frontend.
- Fournit les shortcodes transverses.

### Shortcodes et blocs Gutenberg

- Les pages de campagne sont composées manuellement avec les shortcodes (`[petition_form]`, `[plaid_newsletter_form]`, `[plaid_partners]`, `[plaid_send_campaign]`, `[plaid_social_wall]`) ou avec les blocs Gutenberg PLAID·ACT.
- Les blocs newsletter et partenaires sont dynamiques : ils réutilisent les mêmes callbacks serveur que les shortcodes pour garder un seul rendu public.
- Les partenaires sont accessibles depuis le menu **Campagne** du back office et peuvent être insérés via le bloc PLAID·ACT — Partenaires.
- Les parties pétition, bloc newsletter, envoi aux décideurs, répertoire, brèves, agenda, out/sorties, social wall, articles, partenaires et rapport PDF sont activables via **Campagne → Modules**. La pétition publique est rendue uniquement par le module Petitioner embarqué ; ses signataires alimentent la liste Brevo dédiée à la pétition et, en cas d’opt-in, les listes newsletter.

### Module Petitioner

- Le moteur Petitioner est embarqué dans `vendor/petitioner`.
- Il est chargé automatiquement par `plaidact-campaign-core.php`.
- Il ne doit pas être activé comme plugin autonome.

## Notes multisite

- Activer `PLAID·ACT Campaign Core` en network activation.
- Chaque sous-site conserve ses options `plaidact_campaign_settings`.
- Si Polylang est actif, les chaînes textuelles configurées dans les réglages restent traduisibles.
