# PLAID·ACT Campaign Factory

Plugin WordPress unique pour piloter des campagnes PLAID·ACT en multisite : contenus, pétitions, newsletter, shortcodes et blocs Gutenberg sont regroupés dans `plaidact-campaign-core`.

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

## Fonctionnalités regroupées

- CPT `plaid_breve` et `plaid_partner`.
- Taxonomies de classification (`plaid_breve_topic`, `plaid_partner_type`).
- Module `Petitioner` embarqué dans `vendor/petitioner` et chargé par le core.
- Shortcodes : `[petition_form]`, `[plaid_newsletter_form]`, `[plaid_partners]`, `[plaid_send_campaign]`, `[plaid_social_wall]`.
- Blocs Gutenberg dynamiques pour la newsletter et les partenaires, et menu **Campagne → Modules** pour choisir les parties du plugin à activer : pétition Petitioner unique, bloc newsletter, envoi aux décideurs, répertoire, brèves, agenda, out/sorties, social wall, articles, partenaires et rapport PDF.
- Compatibilité Polylang pour les chaînes métier et la résolution des formulaires traduits.
- Outil **Outils → PLAID·ACT Démo** pour exporter/importer une démo.

## Activation

1. Installer le dossier `wp-content/plugins/plaidact-campaign-core/`.
2. Activer `PLAID·ACT Campaign Core` en network activation sur le multisite.
3. Aller dans **Réglages → PLAID·ACT Campagne** pour configurer les services, listes Brevo (newsletter et pétition) et textes, puis dans **Campagne → Modules** pour activer les blocs affichés.
4. Composer les pages dans Gutenberg avec les blocs PLAID·ACT ou les shortcodes disponibles.

## Notes

- Le dépôt est maintenant volontairement centré sur un plugin unique.
- Le thème actif du site fournit uniquement l’enveloppe WordPress (`get_header()` / `get_footer()`), tandis que les modules campagne sont rendus via shortcodes et blocs Gutenberg.
- `Petitioner` reste embarqué comme module interne et n’a pas vocation à être activé séparément.
