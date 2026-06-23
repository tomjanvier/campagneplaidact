# PLAID·ACT Campaign Factory

Plugin WordPress unique pour piloter des campagnes PLAID·ACT en multisite : contenus, pétitions, newsletter, shortcodes et blocs Gutenberg sont regroupés dans `plaidact-campaign-core`.

## Objectif

Le dépôt est directement installable comme extension WordPress : la racine GitHub correspond à la racine du plugin. L’idée est de cloner ou copier ce dépôt dans `wp-content/plugins/plaidact-campaign-core/`, d’activer un seul plugin réseau, puis d’activer dans ses réglages les éléments souhaités pour chaque campagne.

## Arborescence

```text
.
├─ README.md
├─ ARCHITECTURE.md
├─ plaidact-campaign-core.php
├─ assets/
│  ├─ blocks.js
│  ├─ campaign-givoly.js
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
- Compatibilité Givoly : après une signature Petitioner réussie, un bouton de don peut renvoyer vers une page `[givoly_form]` avec les coordonnées du signataire préremplies via paramètres d’URL.
- Outil **Outils → PLAID·ACT Démo** pour exporter/importer une démo.

## Activation

1. Cloner ou copier ce dépôt à l’emplacement `wp-content/plugins/plaidact-campaign-core/` de WordPress : le fichier `plaidact-campaign-core.php` doit être directement à la racine de ce dossier.
2. Activer `PLAID·ACT Campaign Core` en network activation sur le multisite.
3. Aller dans **Réglages → PLAID·ACT Campagne** pour configurer les services, listes Brevo (newsletter et pétition), textes et l’URL de la page de don Givoly, puis dans **Campagne → Modules** pour activer les blocs affichés.
4. Composer les pages dans Gutenberg avec les blocs PLAID·ACT ou les shortcodes disponibles.

## Notes

- Le dépôt est maintenant volontairement centré sur un plugin unique et sa racine GitHub est la racine installable de l’extension.
- Le thème actif du site fournit uniquement l’enveloppe WordPress (`get_header()` / `get_footer()`), tandis que les modules campagne sont rendus via shortcodes et blocs Gutenberg.
- `Petitioner` reste embarqué comme module interne et n’a pas vocation à être activé séparément.
