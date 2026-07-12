# PLAID·ACT Core

Plugin WordPress unique pour gérer les contenus PLAID·ACT : pétitions, newsletter, shortcodes et blocs Gutenberg sont regroupés dans `plaidact-campaign-core`.

## Objectif

Le dépôt est directement installable comme extension WordPress : la racine GitHub correspond à la racine du plugin. Clonez ou copiez ce dépôt dans `wp-content/plugins/plaidact-campaign-core/`, activez l’extension, puis choisissez les modules utiles dans ses réglages.

## Fonctionnalités regroupées

- CPT `plaid_breve`, `plaid_agenda_event`, `plaid_partner` et `plaid_social_embed`.
- Taxonomies de classification (`plaid_breve_topic`, `plaid_partner_type`).
- Module `Petitioner` embarqué dans `vendor/petitioner` et chargé par le core.
- Design des pétitions configurable dans **Petitioner → Settings** ; les couleurs et le CSS personnalisé de Petitioner alimentent aussi les shortcodes PLAID·ACT.
- Shortcodes : `[petition_form]`, `[plaid_newsletter_form]`, `[plaid_partners]`, `[plaid_send_campaign]`, `[plaid_social_wall]`.
- Blocs Gutenberg dynamiques pour la newsletter, la jauge de signatures et les partenaires.
- Menu **PLAID·ACT → Modules** pour choisir les parties du plugin à activer : pétition Petitioner, newsletter, envoi aux décideurs, répertoire, brèves, agenda, out/sorties, social wall, articles, partenaires et rapport PDF.
- Compatibilité Polylang pour les chaînes métier et la résolution des formulaires traduits.
- Compatibilité Givoly : après une signature Petitioner réussie, un bouton de don peut renvoyer vers une page `[givoly_form]` avec les coordonnées du signataire préremplies via paramètres d’URL.

## Activation

1. Cloner ou copier ce dépôt à l’emplacement `wp-content/plugins/plaidact-campaign-core/` de WordPress : le fichier `plaidact-campaign-core.php` doit être directement à la racine de ce dossier.
2. Activer `PLAID·ACT Core` dans l’administration WordPress.
3. Aller dans **Réglages → PLAID·ACT** pour configurer les services, listes Brevo, textes et l’URL de la page de don Givoly, puis dans **PLAID·ACT → Modules** pour activer les blocs affichés.
4. Composer les pages dans Gutenberg avec les blocs PLAID·ACT ou les shortcodes disponibles.

## Notes

- Le dépôt est centré sur un plugin unique et sa racine GitHub est la racine installable de l’extension.
- Le thème actif fournit l’enveloppe WordPress (`get_header()` / `get_footer()`), tandis que les modules sont rendus via shortcodes et blocs Gutenberg.
- `Petitioner` reste embarqué comme module interne et n’a pas vocation à être activé séparément.
