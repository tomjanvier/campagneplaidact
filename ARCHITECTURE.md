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
- **Réglages à trois niveaux** : filtre de code
  (`plaidact_campaign_enhanced_signature_enabled`) > métabox par pétition
  (`_plaidact_enhanced_signature` : suivre la globale / activer / désactiver)
  > réglage global PLAID·ACT. Par défaut, les pétitions existantes suivent le
  réglage global et ne changent jamais de comportement après mise à jour.
- **Rétrocompatibilité** : le filtre de champs normalise les entrées legacy
  (null, chaîne illisible) sans erreur fatale — le migrateur du moteur
  convertit normalement les anciens formats en priorité 5 ; un champ
  homonyme déjà configuré par l'administrateur n'est jamais écrasé ; les
  signatures antérieures à l'intégration (propriétés personnalisées vides)
  conservent l'affichage historique. Le décodage local des propriétés
  (`get_submission_custom_properties()`) rend les organisations lisibles y
  compris sur les lignes SQL brutes transmises par le hook finalized.
- **Multilinguisme** : doublons et compteurs traitent les formulaires
  traduits d'une même pétition comme une entité unique
  (`av_petitioner_check_duplicate_email`,
  `av_petitioner_submission_count_form_ids`).
- **Effets de bord** : à la confirmation d'une signature
  (`petitioner_submission_finalized`, déjà exécutée hors requête AJAX par le
  moteur), l'intégration notifie l'équipe (contexte organisation inclus),
  envoie l'email au décideur si Petitioner ne le fait pas, et synchronise
  Brevo — sans écrire de statut d'erreur quand Brevo n'est pas configuré.
- **API interne** consommée par les shortcodes et l'admin :
  - `Petitioner_Integration::is_available()` : moteur embarqué présent ?
  - `::get_signature_count($form_id)` : signatures confirmées toutes
    traductions confondues, en une requête SQL agrégée ;
  - `::query_submissions($form_ids, $args)` : liste paginée des signataires
    (jauge, liste publique, page admin Signataires, export CSV) ;
  - `::get_submission_organization($submission)` : informations
    d'organisation décodées d'une signature, ou null.

Les shortcodes (`includes/class-plaidact-campaign-shortcodes.php`) délègent
toute cette logique à l'intégration : ils restent responsables du rendu seul.

### Tests

Les tests PHPUnit vivent dans `vendor/petitioner/tests/php/integrations/`
(WorDBless). Le moteur par défaut de WorDBless (« dbless ») simule options et
contenus mais pas les tables personnalisées : les tests qui insèrent de vraies
signatures se marquent alors « skipped » avec un message explicite ; sur MySQL
ou SQLite ils s'exécutent intégralement.

## Module Actyl (`includes/class-plaidact-actyl.php`)

Client singleton de l'API REST d'Actyl (plateforme de plaidoyer externe) :

- configuration isolée dans l'option `plaidact_actyl_settings` (URL HTTPS, token jamais affiché, activation) ; la synchronisation ne sort d'aucune donnée sans configuration complète **et** ping `/api/v1/ping` réussi ;
- signatures : poussées à l'événement `petitioner_submission_finalized` (données vérifiées, hors requête visiteur) vers `/api/v1/petitions/{slug}/signatures`, le slug étant lié par pétition via la métadonnée `_plaidact_actyl_campaign_slug` ; relance unique WP-Cron (+10 min) sur panne réseau ou 5xx ;
- newsletter : l'action `plaidact_newsletter_subscribed`, tirée par le handler du formulaire, alimente `/api/v1/supporters` (source `newsletter`) ;
- dons : hook `plaidact_actyl_record_donation` documenté, jamais déclenché automatiquement (pas de capture serveur fiable chez Givoly) ;
- rattrapage par lots de 20 avec curseur persistant (`plaidact_actyl_backfill_cursor`), bouton dans les réglages et commande `wp plaidact actyl-backfill` ;
- journal des 100 derniers événements (`plaidact_actyl_log`) consultable dans Réglages → PLAID·ACT.

Aucun appel n'est effectué pendant les imports CSV ou traitements en masse existants.

## Notes

- Si Polylang est actif, les chaînes textuelles configurées dans les réglages restent traduisibles.
