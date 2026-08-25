# Instructions pour les agents travaillant sur ce dépôt

> **À lire intégralement avant toute analyse, modification, validation ou création de pull request.**
> Ces instructions font foi. Si un fichier `AGENTS.md` existe dans un sous-répertoire concerné par la tâche, il s'applique en complément et doit également être lu.

## Sommaire

1. [Stack technique constatée](#stack-technique-constatée)
2. [Architecture et organisation des dossiers](#architecture-et-organisation-des-dossiers)
3. [Commandes disponibles](#commandes-disponibles)
4. [Qualité du code](#qualité-du-code)
5. [Commentaires](#commentaires)
6. [Neutralité des contenus](#neutralité-des-contenus)
7. [WordPress et conservation des données](#wordpress-et-conservation-des-données)
8. [Inventaire des contenus, shortcodes et options](#inventaire-des-contenus-shortcodes-et-options)
9. [Sécurité](#sécurité)
10. [Compatibilité des extensions](#compatibilité-des-extensions)
11. [Tests et validation avant livraison](#tests-et-validation-avant-livraison)
12. [Git et pull requests](#git-et-pull-requests)

---

## Stack technique constatée

| Élément | Détail |
|---|---|
| Langage | PHP 8.1 minimum (`Requires PHP: 8.1`) |
| CMS | WordPress 6.5 minimum (`Requires at least: 6.5`) |
| Plugin | `plaidact-campaign-core`, version actuelle dans l'en-tête de `plaidact-campaign-core.php` |
| Moteur de pétitions | Module Petitioner embarqué dans `vendor/petitioner` (constante `AV_PETITIONER_PLUGIN_VERSION`), jamais activé comme plugin autonome |
| Dépendances PHP | Composer, uniquement dans `vendor/petitioner` (aucun `composer.json` à la racine) |
| Tests | PHPUnit 9 + WorDBless, configurés par `vendor/petitioner/phpunit.xml` |
| Front | CSS et JavaScript natifs, sans étape de build à la racine ; les fichiers `vendor/petitioner/dist*` sont précompilés |
| Base de données | Tables WordPress natives + table personnalisée `wp_av_petitioner_submissions` gérée par le module embarqué |
| Intégrations externes | API Brevo (contacts, double opt-in), pages de don Givoly (paramètres d'URL), Akismet et captchas (reCAPTCHA/hCaptcha/Turnstile) côté moteur |
| CI/CD | Aucune configuration présente : toutes les vérifications sont locales |

Ne documente ou n'introduis aucune technologie absente de cette liste sans justification écrite dans la pull request.

## Architecture et organisation des dossiers

```
plaidact-campaign-core.php   Bootstrap : constantes, chargement du module embarqué,
                             hooks d'activation/désactivation/désinstallation
includes/                    Classes métier (toutes final, majoritairement statiques) :
                             CPT (contenus et migrations), Shortcodes (rendu + réglages),
                             Blocks, Polylang, Petition_Workflows (effets de bord),
                             Petitioner_Integration (couche unique vers le moteur),
                             Association_Directory, PlaidAct_Contact_Directory
templates/                   Gabarits du répertoire associatif (archive, fiche, boucle, page)
assets/                      CSS/JS publics et blocs Gutenberg (chargement conditionnel)
acf-json/                    Groupe de champs ACF embarqué (ACF reste optionnel)
examples/                    Exemples CSV d'import (associations, contacts)
vendor/petitioner/           Moteur de pétitions embarqué + tests PHPUnit
```

Règles structurelles :

- La racine du dépôt est la racine installable du plugin : le fichier principal doit rester directement à la racine.
- Toute interaction avec le moteur de pétitions passe par `Petitioner_Integration` et ses filtres/actions publics. Ne modifie jamais `vendor/petitioner/inc` sauf nécessité absolue, documentée.
- Les shortcodes ne contiennent pas de logique métier : rendu uniquement, délégation aux classes dédiées.
- Deux styles de code coexistent (guillemets doubles/4 espaces dans les classes récentes, tabulations/guillemets simples dans les modules répertoires). Suis toujours le style du fichier modifié ; ne reformatte jamais un fichier entier.
- Les nouvelles classes vont dans le namespace `Plaidact\CampaignCore`. `PlaidAct_Contact_Directory` est un cas historique sans namespace, à préserver tel quel.
- Chaque fichier PHP commence par une garde `if (!defined("ABSPATH"))`.

## Commandes disponibles

```bash
# Installation des dépendances de test (seul composer.json du dépôt)
cd vendor/petitioner && composer install

# Suite de tests complète (WorDBless)
cd vendor/petitioner && ./vendor/bin/phpunit

# Tests ciblés sur une classe
cd vendor/petitioner && ./vendor/bin/phpunit --filter "NomDeTest"

# Vérification syntaxique (lint minimal disponible)
php -l <fichier.php>

# Vérification JS ponctuelle
node --check <fichier.js>
```

Notes importantes sur les tests :

- Le moteur WorDBless par défaut (« dbless ») simule les options et contenus mais **ne persiste pas les tables personnalisées**. Les tests qui insèrent des signatures se marquent alors `skipped` avec un message explicite : c'est normal. Sur un environnement MySQL ou SQLite ils s'exécutent intégralement.
- Certains échecs pré-existants peuvent concerner le module embarqué lui-même. Avant de corriger un test, vérifie s'il échouait déjà sur la branche de base.
- Il n'existe ni outil de lint PHP configuré (phpcs/phpstan) ni build front à la racine : ne prétends pas les avoir exécutés.

## Qualité du code

Le code produit doit être propre, lisible, maintenable, testé et optimisé. Il ne doit pas donner l'impression d'avoir été généré rapidement ou assemblé sans compréhension de l'existant.

Avant toute modification :

1. Inspecter le code existant et rechercher les implémentations similaires (ex. : un import CSV existe déjà pour les associations, réutilise ses mécanismes).
2. Comprendre les conventions du fichier et du module.
3. Limiter les changements au périmètre demandé.
4. Préserver les fonctionnalités et les données existantes.

Éviter :

- la duplication de logique (préférer une source unique de vérité déclarative, comme les définitions de champs de signature enrichie) ;
- les abstractions utiles à un seul appelant ;
- les fichiers ou fonctions excessivement volumineux ;
- les contournements temporaires non documentés ;
- toute dépendance supplémentaire sans justification écrite ;
- les modifications esthétiques ou refactorisations sans rapport avec la demande.

## Commentaires

Les commentaires ajoutés dans le code doivent être **rédigés en français**.

Ils expliquent :

- les décisions métier ;
- les contraintes importantes ;
- les comportements non évidents ;
- les mécanismes de sécurité ou de compatibilité ;
- les raisons de rétrocompatibilité avec les données existantes.

Ne commente pas les instructions évidentes ligne par ligne. Un commentaire doit améliorer la compréhension sans alourdir.

La documentation de méthode (PHPDoc) suit le même principe : elle décrit le contrat, les paramètres et les garanties, pas l'évidence.

## Neutralité des contenus

Il est interdit d'ajouter dans le code, les commentaires, les commits, la documentation, les fichiers générés ou les pull requests :

- le nom d'un assistant conversationnel ;
- le nom d'une entreprise fournissant des modèles ;
- le nom d'un outil de génération de code ;
- le nom ou la version d'un modèle utilisé ;
- toute mention présentant le contenu comme produit automatiquement plutôt que rédigé par un contributeur.

Les messages de commit et les descriptions de pull request restent professionnels et décrivent uniquement les changements techniques et fonctionnels.

Avant de pousser, relire son diff et ses messages pour vérifier l'absence de ces mentions.

## WordPress et conservation des données

Règle absolue : **ne jamais supprimer de données existantes**. Préserver IDs, contenus, statuts, dates, auteurs, slugs, médias, taxonomies, métadonnées et traductions.

Migrations de contenus :

- versionnées via une option dédiée (modèle existant : `plaidact_core_content_schema_version`) ;
- rejouables tant qu'elles ne sont pas marquées comme terminées ;
- marquées terminées **uniquement si aucun élément n'a échoué** (voir `migrate_legacy_breves()`, qui conserve IDs et URLs `/breves/{slug}/`) ;
- jamais testées par écriture directe en production.

Imports du répertoire associatif (`handle_asso_import`) :

- additifs : une fiche existante (retrouvée par slug) n'est jamais réinitialisée — contenu, statut, dates, auteur, extrait, slug et médias conservés ;
- un champ vide dans le CSV ne vide jamais une valeur existante (`sync_asso_meta()` n'écrit que si le champ courant est vide) ;
- les catégories importées s'ajoutent aux catégories présentes (`wp_set_object_terms(..., true)`), elles ne les remplacent pas ;
- une image mise en avant existante est conservée (`sync_asso_logo()` sort immédiatement si un thumbnail existe).

Imports de contacts (`merge_contacts_preserving_existing`) :

- fusion par identité normalisée (email en priorité, nom/prénom sinon) ;
- les nouvelles lignes complètent la liste, elles ne remplacent ni ne vident jamais une liste déjà enregistrée.

Toute nouvelle fonctionnalité d'import ou de synchronisation doit respecter ces mêmes garanties et ajouter des tests qui les démontrent quand c'est possible.

## Inventaire des contenus, shortcodes et options

Identifiants techniques à ne pas renommer (données de production attachées) :

- Types de contenus du cœur : `plaid_newsletter`, `plaid_breve` (héritier de l'ancien type `breves`, requêtes redirigées par `map_legacy_breve_query`), `plaid_agenda_event`, `plaid_partner`, `plaid_social_embed`.
- Répertoire : type `associations` avec taxonomy homonyme `associations`, URLs `/association/{slug}/` ; types annexes `agenda` (+ taxonomy `agenda_timeline`) et `pa_definition` (+ `pa_def_category`).
- Moteur de pétitions : type `petitioner-petition`, table `wp_av_petitioner_submissions`, métadonnées `_petitioner_*`, propriétés personnalisées stockées en JSON (colonne `custom_properties`).

Shortcodes publics (contrat stable, ne pas retirer d'attributs existants) :

`petition_form`, `petition_signers`, `plaid_petition_gauge` (attributs dont `goal`), `plaid_partners`, `plaid_social_wall`, `plaid_newsletter_form`, `plaid_send_campaign`, `plaidact_asso_directory`, `plaidact_timeline`, `plaidact_hover_term`, `plaidact_contact_directory` et son alias historique `plaidact_fluentcrm_directory`.

Options WordPress contenant des données réelles :

- `plaidact_campaign_settings` : réglages du plugin (inclut la clé API Brevo — jamais de secret en dur dans le code) ;
- `plaidact_contact_directory_lists` : **données de contacts**, à traiter comme des données de production ;
- `plaidact_contact_directory_visible_columns`, `plaidact_contact_directory_export_branding` ;
- `plaidact_core_content_schema_version`, `plaidact_core_rewrite_schema_version` : état des migrations ;
- Options `petitioner_*` et métadonnées `_petitioner_*` : domaine du moteur embarqué.

Autres points sensibles :

- Les toggles de modules (`enable_*` de `plaidact_campaign_settings`) sont appliqués aux shortcodes : toute nouvelle section doit passer par `Shortcodes::is_module_enabled()` avec valeur par défaut « activé ».
- Les caches statiques de réglages doivent être invalidés après mise à jour programmatique (`Shortcodes::reset_settings_cache()`).
- Le captcha est géré par le moteur (Réglages Petitioner) : n'ajoute pas un second mécanisme anti-robot sur les pétitions.

## Sécurité

Pratiques natives attendues sur tout nouveau code :

- validation et normalisation systématique des entrées (`sanitize_text_field`, `sanitize_email`, `absint`, etc.) ;
- échappement adapté au contexte de sortie (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`) ;
- contrôle des permissions (`current_user_can`) avant toute action sensible ;
- nonces sur tous les formulaires, imports, exports et handlers `admin_post` ;
- requêtes préparées exclusivement (`$wpdb->prepare`, placeholders `%d`/`%s`) ;
- protection contre les injections CSV (`escape_csv_formula()` côté exports de contacts ; préfixage des formules) ;
- contrôle strict des fichiers importés (extension vérifiée, extraction ZIP file-par-file contre le zip-slip, limites de nombre et de taille) ;
- aucun secret dans le dépôt : les clés API vivent dans les réglages, jamais dans le code ni les exemples ;
- en-têtes de téléchargement complets (`Content-Disposition` avec nom échappé, `X-Content-Type-Options: nosniff`).

## Compatibilité des extensions

- **Polylang** (optionnel) : toutes les lectures linguistiques passent par `Polylang` (helpers statiques). Les pétitions traduites forment une entité unique (doublons, compteurs). Toute nouvelle chaîne de réglage doit être ajoutée à `get_translatable_setting_keys()` et à `register_strings()`.
- **ACF** (optionnel) : lire/écrire les champs du répertoire via `plaidact_campaign_core_get_field()` / `plaidact_campaign_core_update_field()` qui retombent sur `get_post_meta` en l'absence d'ACF.
- **Thème actif** : fournit l'enveloppe (`get_header()`/`get_footer()`) ; les gabarits du plugin restent autonomes. L'action `plaidact_newsletter_form` permet aux thèmes de rendre le formulaire newsletter.
- **Brevo** : ne jamais écrire d'état d'erreur de configuration sur les signataires quand Brevo est volontairement désactivé (garantie implémentée dans `sync_signer_to_brevo()`).

## Tests et validation avant livraison

Une tâche n'est terminée qu'après :

1. exécution des tests pertinents (`./vendor/bin/phpunit` dans `vendor/petitioner`) ;
2. vérification syntaxique (`php -l` sur chaque fichier PHP modifié) ;
3. vérification JS (`node --check`) si un script a changé ;
4. recherche des références devenues invalides (grep sur les méthodes renommées, hooks, shortcodes) ;
5. relecture du diff complet final ;
6. confirmation explicite qu'aucune donnée ou fonctionnalité existante n'est supprimée.

Règles d'honnêteté :

- Si une vérification ne peut pas être exécutée (environnement sans base de données, outils absents), l'indiquer clairement dans la pull request.
- Ne jamais présenter un test non lancé comme réussi.
- Signaler les échecs pré-existants constatés plutôt que de les ignorer silencieusement.

## Git et pull requests

Avant de modifier le dépôt :

- vérifier `git status` et préserver les modifications existantes ;
- créer une branche dédiée au périmètre de la tâche ;
- produire des commits ciblés et compréhensibles (messages en français, style de l'historique) ;
- ne jamais inclure de fichiers temporaires, de dépendances installées (`vendor/petitioner/vendor/`, `vendor/petitioner/wordpress/`) ni de secrets.

Une pull request doit expliquer :

- le problème corrigé ;
- la solution retenue ;
- les garanties de compatibilité (notamment conservation des données et rétrocompatibilité) ;
- les conséquences éventuelles ;
- les vérifications réellement exécutées (avec leurs résultats) ;
- les vérifications restant à effectuer.
