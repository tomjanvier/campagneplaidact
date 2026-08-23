# Architecture PLAID·ACT Core

## Principe

Le dépôt est organisé autour d’un seul plugin WordPress : `plaidact-campaign-core`. La racine GitHub correspond à la racine installable de l’extension, ce qui permet de cloner directement le dépôt dans `wp-content/plugins/plaidact-campaign-core/`.

## Responsabilités du plugin

- Déclare les contenus PLAID·ACT (`plaid_newsletter`, `plaid_breve`, `plaid_agenda_event`, `plaid_partner`, `plaid_social_embed`).
- `plaid_newsletter` publie les newsletters dans Gutenberg avec une archive publique. Lors de la mise à niveau 2.1.0, les anciens contenus `breves` sont migrés vers `plaid_breve` sans changer leurs IDs ni leurs URL, puis les anciennes requêtes du thème sont redirigées vers le type canonique.
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

### Couche d'intégration (`includes/class-plaidact-campaign-petitioner-integration.php`)

Toute la logique métier liée au moteur passe par cette classe, jamais par une
modification directe du vendor. Elle s'appuie uniquement sur les filtres et
actions publics de Petitioner :

- **Signature enrichie** : champs « organisation » et « titre/fonction »
  ajoutés après l'email via `av_petitioner_form_fields`, ordre garanti via
  `av_petitioner_field_order`, stockage JSON via
  `av_petitioner_get_custom_property_types`. Les libellés trilingues
  (fr/en/es) dérivent d'une définition déclarative unique ; la langue cible
  est résolue une seule fois par requête.
- **Réglage** : la fonctionnalité se désactive dans Réglages → PLAID·ACT
  (« Signature d'organisation »), ou en code via le filtre
  `plaidact_campaign_enhanced_signature_enabled`.
- **Multilinguisme** : doublons et compteurs traitent les formulaires
  traduits d'une même pétition comme une entité unique
  (`av_petitioner_check_duplicate_email`,
  `av_petitioner_submission_count_form_ids`).
- **Effets de bord** : à la confirmation d'une signature
  (`petitioner_submission_finalized`, déjà exécutée hors requête AJAX par le
  moteur), l'intégration notifie l'équipe, envoie l'email au décideur si
  Petitioner ne le fait pas, et synchronise Brevo — sans écrire de statut
  d'erreur quand Brevo n'est pas configuré.
- **API interne** consommée par les shortcodes et l'admin :
  - `Petitioner_Integration::is_available()` : moteur embarqué présent ?
  - `::get_signature_count($form_id)` : signatures confirmées toutes
    traductions confondues, en une requête SQL agrégée ;
  - `::query_submissions($form_ids, $args)` : liste paginée des signataires
    (jauge, liste publique, page admin Signataires).

Les shortcodes (`includes/class-plaidact-campaign-shortcodes.php`) délègent
toute cette logique à l'intégration : ils restent responsables du rendu seul.

### Tests

Les tests PHPUnit vivent dans `vendor/petitioner/tests/php/integrations/`
(WorDBless). Le moteur par défaut de WorDBless (« dbless ») simule options et
contenus mais pas les tables personnalisées : les tests qui insèrent de vraies
signatures se marquent alors « skipped » avec un message explicite ; sur MySQL
ou SQLite ils s'exécutent intégralement.

## Notes

- Si Polylang est actif, les chaînes textuelles configurées dans les réglages restent traduisibles.
