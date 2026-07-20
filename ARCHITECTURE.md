# Architecture PLAID·ACT Core

## Principe

Le dépôt est organisé autour d’un seul plugin WordPress : `plaidact-campaign-core`. La racine GitHub correspond à la racine installable de l’extension, ce qui permet de cloner directement le dépôt dans `wp-content/plugins/plaidact-campaign-core/`.

## Responsabilités du plugin

- Déclare les contenus PLAID·ACT (`plaid_breve`, `plaid_agenda_event`, `plaid_partner`, `plaid_social_embed`).
- Déclare les taxonomies métier utiles aux contenus.
- Gère les métadonnées partenaires et social wall.
- Expose les réglages PLAID·ACT et les toggles d’activation des modules frontend.
- Fournit les shortcodes transverses et les blocs Gutenberg dynamiques.

## Shortcodes et blocs Gutenberg

- Les pages sont composées manuellement avec les shortcodes (`[petition_form]`, `[plaid_newsletter_form]`, `[plaid_partners]`, `[plaid_send_campaign]`, `[plaid_social_wall]`) ou avec les blocs Gutenberg PLAID·ACT.
- Les blocs newsletter, jauge de signatures et partenaires réutilisent les callbacks serveur pour garder un seul rendu public.
- Le bloc newsletter peut aussi être appelé par un thème via `do_action('plaidact_newsletter_form', ['class' => 'ma-classe']);`. Le `<form>` généré reçoit la classe `stp-newsletter-form` (et accepte `formClass`/`form_class`) pour réutiliser les styles du thème tout en envoyant les contacts vers Brevo.
- Les partenaires sont accessibles depuis le menu **PLAID·ACT** du back office.
- Les modules activables sont : pétition, newsletter, envoi aux décideurs, répertoire, brèves, agenda, out/sorties, social wall, articles, partenaires et rapport PDF.

## Module Petitioner

- Le moteur Petitioner est embarqué dans `vendor/petitioner`.
- Il est chargé automatiquement par `plaidact-campaign-core.php`.
- Il ne doit pas être activé comme plugin autonome.
- Les couleurs et le CSS public se règlent directement dans **Petitioner → Settings**.

## Notes

- Si Polylang est actif, les chaînes textuelles configurées dans les réglages restent traduisibles.
