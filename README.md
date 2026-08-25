# PLAID·ACT Core

Plugin WordPress unique pour gérer les contenus PLAID·ACT : pétitions, newsletter, répertoires, shortcodes et blocs Gutenberg sont regroupés dans `plaidact-campaign-core`.

## Instructions pour les contributeurs et agents

**La lecture complète de [AGENTS.md](AGENTS.md) est obligatoire avant toute analyse ou modification du dépôt.** Ces instructions définissent la stack, l'architecture, les conventions de développement, les règles de sécurité, les validations attendues et les garanties de conservation des données. Elles s'appliquent à toute contribution humaine comme automatisée, y compris la validation et la création de pull requests.

## Objectif

Le dépôt est directement installable comme extension WordPress : la racine GitHub correspond à la racine du plugin. Clonez ou copiez ce dépôt dans `wp-content/plugins/plaidact-campaign-core/`, activez l’extension, puis choisissez les modules utiles dans ses réglages.

## Fonctionnalités regroupées

- CPT `plaid_newsletter`, `plaid_breve`, `plaid_agenda_event`, `plaid_partner`, `plaid_social_embed` et `associations`.
- Taxonomies de classification (`plaid_breve_topic`, `plaid_partner_type`).
- Publication des newsletters avec Gutenberg, archive publique `/newsletters/`, pages individuelles `/newsletter/{slug}/` et prise en charge de Polylang.
- Migration automatique et non destructive de l’ancien CPT `breves` vers `plaid_breve` : les IDs, contenus, médias, métadonnées, langues et URL `/breves/{slug}/` sont conservés, puis le menu en double est supprimé.
- Répertoire des associations rétrocompatible avec les fiches historiques `associations`, leurs catégories, leurs champs ACF et leurs URLs `/association/{slug}/`. Le shortcode `[plaidact_asso_directory]` affiche la recherche et les filtres ; un import/export CSV additif est disponible dans **Répertoire Asso → Import CSV** et ne vide jamais les contenus ou champs existants.
- Base de contacts autonome conservant les listes existantes dans les options WordPress historiques. Elle se gère depuis **Répertoire contacts** et s’affiche avec `[plaidact_contact_directory]` (l’ancien alias `[plaidact_fluentcrm_directory]` reste accepté). Les imports CSV ajoutent les nouveaux contacts sans supprimer ni remplacer ceux déjà enregistrés.
- Module `Petitioner` embarqué dans `vendor/petitioner` et chargé par le core.
- Design des pétitions configurable dans **Petitioner → Settings** ; les couleurs et le CSS personnalisé de Petitioner alimentent aussi les shortcodes PLAID·ACT.
- Shortcodes : `[petition_form]`, `[plaid_newsletter_form]`, `[plaid_partners]`, `[plaidact_asso_directory]`, `[plaidact_contact_directory]`, `[plaid_send_campaign]`, `[plaid_social_wall]`. Le formulaire newsletter rendu par `[plaid_newsletter_form]` porte aussi la classe `stp-newsletter-form` et soumet les inscriptions au flux Brevo configuré dans **Réglages → PLAID·ACT**.
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

## Intégration Actyl

Le plugin peut pousser en temps réel vers une instance [Actyl](*plateforme de plaidoyer*) les signatures confirmées et les inscriptions newsletter, via son API REST `/api/v1/*`.

### Configuration

1. Dans **Actyl → Réglages**, créer un token API (`actyl_…`).
2. Dans **WordPress → Réglages → PLAID·ACT**, section **Connexion Actyl** :
   - renseigner l'URL de l'instance (HTTPS uniquement) et le token ;
   - cocher « Activer la synchronisation » puis enregistrer ;
   - cliquer sur **« Tester la connexion »** : le résultat s'affiche inline (succès vert / code HTTP ou message réseau précis). La synchronisation ne démarre qu'après ce test réussi ; toute modification d'URL ou de token en exige un nouveau.
3. Pour chaque pétition à synchroniser, ouvrir la pétition dans Petitioner et renseigner le **slug de campagne Actyl** dans la metabox « Connexion Actyl ». Vide = aucune donnée envoyée pour cette pétition.

Comportements garantis :

- désactivé par défaut : aucune requête sortante sans configuration complète + ping réussi ;
- envois non bloquants (timeout 5 s), échecs silencieux côté visiteur, tout est tracé dans le **journal** (100 derniers événements : horodatage, endpoint, code HTTP) consultable dans la page de réglages ;
- panne d'Actyl (erreur réseau ou 5xx) : relance unique automatique via WP-Cron après 10 minutes ; pas de relance sur erreur définitive (4xx) ;
- l'API étant idempotente par email, aucun doublon n'est possible.

### Rattrapage des signatures existantes

Dans la section Connexion Actyl, le bouton **« Synchroniser les signatures existantes »** repousse toutes les signatures antérieures par lots de 20, avec barre de progression, reprise automatique là où il s'est arrêté (curseur `plaidact_actyl_backfill_cursor`) et bouton « Recommencer depuis zéro ». Équivalent en ligne de commande :

```bash
wp plaidact actyl-backfill                  # toutes les pétitions liées
wp plaidact actyl-backfill --petition=12    # une seule pétition
wp plaidact actyl-backfill --reset=1        # réinitialise le curseur
```

### Dons Givoly

Lorsque l'extension **Givoly** est active sur le même site, chaque don confirmé est automatiquement transmis à Actyl : Givoly émet l'action `givoly_donation_completed` à l'enregistrement de tout don validé (Stripe, HelloAsso, dons saisis manuellement), que le module Actyl relaie vers `/api/v1/donations`. La passerelle d'origine (`stripe`, `helloasso`, …) est transmise dans le champ `provider` ; elle est surchargeable via le filtre `plaidact_actyl_donation_provider`.

Pour les sources autres que Givoly, un hook manuel reste disponible :

```php
do_action("plaidact_actyl_record_donation", [
    "email"        => "donateur@exemple.fr",
    "full_name"    => "Jean Martin",          // facultatif
    "amount"       => 50,                     // en unités, ou :
    "amount_cents" => 5000,                   // prioritaire si présent
    "label"        => "Don campagne zones humides",
    "occurred_at"  => "2026-08-24T12:00:00Z", // défaut : maintenant
]);
```

Dans tous les cas, aucun don ne part si la synchronisation n'est pas active ; le contact est enrichi en catégorie DONOR côté Actyl.

### Test manuel de bout en bout — dons

1. Configurer Stripe ou HelloAsso dans Givoly (ou utiliser un don manuel via Givoly → Dons → Ajouter).
2. Réaliser un don test depuis `[givoly_form]`.
3. Vérifier dans le journal Actyl (Réglages → PLAID·ACT) la ligne `/api/v1/donations` avec code 201.
4. Dans Actyl : le don apparaît sur le contact, passé en catégorie DONOR.

### Test manuel de bout en bout

1. Créer un token dans **Actyl → Réglages**, créer (ou relever) le slug d'une campagne publiée.
2. Dans WordPress : Réglages → PLAID·ACT → Connexion Actyl → URL + token → enregistrer → **Tester la connexion** → bandeau vert attendu, état « Synchro active ».
3. Ouvrir la pétition côté Petitioner, renseigner le slug de campagne, mettre à jour.
4. Signer la pétition depuis le site public (email non déjà utilisé).
5. Dans Actyl : vérifier en moins de 5 secondes la signature dans l'onglet **Signataires** de la campagne correspondante, et le contact dans la base **Soutiens** (tags `wordpress` + slug de la pétition WordPress).
6. Re-soumettre le même email : le compteur ne double pas (mise à jour idempotente).
7. Arrêter l'instance Actyl, signer à nouveau : le site continue de fonctionner normalement, le journal enregistre l'échec réseau, puis après redémarrage d'Actyl la relance automatique (+10 min) fait apparaître la signature.
8. Soumettre le formulaire `[plaid_newsletter_form]` : le contact apparaît dans Actyl avec source `newsletter`, catégorie SUPPORTER et tag `newsletter-site`.

## Journal des optimisations (2.3.0)

- **Correctif** : le shortcode `[plaid_social_wall]` ne déclenche plus d’avertissement PHP (variable `$settings` non définie) et affiche désormais le titre/description configurés dans les réglages, avec attributs `title`/`description` en option.
- **Modules** : les toggles de **PLAID·ACT → Modules** sont maintenant appliqués aux shortcodes (`petition_form`, `plaid_petition_gauge`, `petition_signers`, `plaid_newsletter_form`, `plaid_send_campaign`, `plaid_partners`, `plaid_social_wall`). Par défaut tous les modules restent actifs.
- **Performance** : la navigation A→Z du répertoire des associations exécute une seule requête au lieu de 26 par affichage ; le compteur de signatures agrège toutes les pétitions traduites en une seule requête SQL ; les scripts pétition (Givoly, signature d’organisation) ne se chargent plus que sur les pages qui contiennent réellement un élément de pétition, en `defer` ; caches statiques ajoutés (versions d’assets, formulaires liés, compteur).
- **Sécurité** : honeypot + limiteur de débit par IP sur l’envoi au décideur ; extraction ZIP durcie contre le zip-slip avec limites de fichiers/taille ; en-têtes de téléchargement renforcés (`nosniff`, nom de fichier échappé) ; normalisation des listes de contacts pour tolérer les anciennes données.
- **Compatibilité** : paramètre `$escape` explicite sur `fgetcsv`/`fputcsv` (dépréciation PHP 8.4) ; les tags de cause des fiches associations pointent vers la page courante au lieu d’une URL codée en dur.

## Refonte de l’intégration Petitioner (2.3.0)

- **Source unique de vérité** : les 7 champs de signature enrichie (organisation / personnalité publique) sont désormais décrits une seule fois sous forme déclarative (type, groupe, obligation, libellés fr/en/es). Le rendu public, l’éditeur Petitioner, l’ordre des champs et les propriétés personnalisées en dérivent tous — plus aucune duplication.
- **Réglage dédié** : la signature d’organisation se désactive dans Réglages → PLAID·ACT (« Signature d’organisation »), ou via le filtre `plaidact_campaign_enhanced_signature_enabled`.
- **Contrôle par pétition** : un métabox « Signature organisation & personnalité » sur chaque pétition permet de forcer l’activation ou la désactivation indépendamment du réglage global. Par défaut (« suivre le réglage global »), **les pétitions existantes ne changent jamais de comportement après mise à jour**.
- **Compatibilité anciennes données** : les signatures antérieures à l’intégration (sans propriétés personnalisées) s’affichent exactement comme avant ; le filtre de champs tolère les formats legacy illisibles sans erreur fatale ; un champ homonyme créé par l’administrateur n’est jamais écrasé.
- **i18n propre** : langue cible (fr/en/es via Polylang puis locale du site) résolue une seule fois par requête au lieu de 21 appels `determine_locale()` par formulaire rendu.
- **Séparation des responsabilités** : toute la logique métier (compteur SQL agrégé, requêtes signataires, règles multilingues, effets de bord Brevo/décideur/notification) vit dans `Petitioner_Integration` ; les shortcodes ne font que du rendu et appellent son API (`get_signature_count()`, `query_submissions()`).
- **Robustesse** : la synchronisation Brevo n’écrit plus d’erreur « non configuré » dans chaque fiche signataire quand Brevo est désactivé ; gardes sur les identifiants de signature ; liste blanche stricte des colonnes SQL.
- **JS durci** : initialisation idempotente de la bascule organisation/personne + prise en charge des formulaires injectés dynamiquement (MutationObserver borné).

## Nouvelles fonctionnalités Petitioner (2.3.0)

- **Notification enrichie pour les organisations** : l’email de notification de l’équipe précise désormais le type de signature (organisation ou individu), le nom de l’organisation, son logo, l’acceptation d’affichage public et le titre/fonction du signataire.
- **Liste publique des signataires enrichie** : les organisations ayant accepté l’affichage apparaissent avec leur nom, leur logo et leur titre/fonction ; celles qui refusent sont anonymisées (« Organisation »). Les signataires classiques conservent l’affichage historique.
- **Export CSV complet des signataires** : bouton « Exporter tout en CSV (toutes traductions) » sur PLAID·ACT → Signataires — l’export natif Petitioner étant limité à un seul formulaire. Export par lots (1000 lignes) avec colonnes d’organisation (`type_signature`, `organisation`, `logo_organisation`…).
- **Jauge avec objectif imposé** : `[plaid_petition_gauge goal="5000"]` force l’objectif affiché — pratique pour les pétitions antérieures aux paliers d’objectifs.
- **Tests** : nouvelle suite `Test_Plaidact_Enhanced_Signature` (14 tests) couvrant champs, ordre, palette de l’éditeur, identité organisation, compteur agrégé, pagination, sécurité de la liste blanche, réglage par pétition et détection d’organisations sur anciennes signatures.
