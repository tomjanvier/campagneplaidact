# PLAID·ACT Campaign Factory

Plugin WordPress unique pour piloter des campagnes PLAID·ACT en multisite : contenus, pétitions, newsletter, pages one-page et rendu frontend sont regroupés dans `plaidact-campaign-core`.

## Objectif

Le dépôt ne livre plus de thème WordPress dédié ni de plugin séparé à activer. L’idée est d’installer et d’activer un seul plugin réseau, puis d’activer dans ses réglages les éléments souhaités pour chaque campagne.

## Arborescence

```text
.
├─ README.md
├─ ARCHITECTURE.md
└─ wp-content/
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

## Fonctionnalités regroupées

- CPT `plaid_breve` et `plaid_partner`.
- Taxonomies de campagne et de classification (`campagne`, `plaid_breve_topic`, `plaid_partner_type`).
- Création automatique d’une page one-page quand une campagne est créée.
- Template frontend et assets one-page directement fournis par le plugin.
- Module `Petitioner` embarqué dans `vendor/petitioner` et chargé par le core.
- Shortcodes : `[petition_form]`, `[plaid_newsletter_form]`, `[plaid_send_campaign]`, `[plaid_social_wall]`, `[plaidact_campagne_onepage]`.
- Réglages **Réglages → PLAID·ACT Campagne** pour choisir les éléments actifs : pétition, newsletter, envoi au décideur, social wall, articles et rapport PDF.
- Compatibilité Polylang pour les chaînes métier et la résolution des formulaires traduits.
- Outil **Outils → PLAID·ACT Démo** pour exporter/importer une démo.

## Activation

1. Installer le dossier `wp-content/plugins/plaidact-campaign-core/`.
2. Activer `PLAID·ACT Campaign Core` en network activation sur le multisite.
3. Aller dans **Réglages → PLAID·ACT Campagne** pour configurer les services, textes et éléments one-page à afficher.
4. Créer des termes dans la taxonomie **Campagnes** : le plugin génère les pages correspondantes.

## Notes

- Le dépôt est maintenant volontairement centré sur un plugin unique.
- Le thème actif du site fournit uniquement l’enveloppe WordPress (`get_header()` / `get_footer()`), tandis que le rendu campagne est assuré par le plugin.
- `Petitioner` reste embarqué comme module interne et n’a pas vocation à être activé séparément.
