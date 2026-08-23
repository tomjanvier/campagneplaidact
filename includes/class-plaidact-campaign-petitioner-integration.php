<?php
/**
 * Intégration du moteur Petitioner embarqué pour PLAID·ACT Core.
 *
 * Cette classe est LE point de contact unique entre le plugin et le moteur de
 * pétition embarqué (vendor/petitioner). Toute la logique métier liée à
 * Petitioner y est centralisée :
 *
 *  - signature enrichie (organisation ou personnalité publique) ;
 *  - unicité des signatures entre pétitions traduites (Polylang) ;
 *  - effets de bord d'une signature confirmée (notification admin, email au
 *    décideur, synchronisation Brevo) ;
 *  - API interne consommée par les shortcodes et l'administration
 *    (compteur agrégé, liste paginée des signataires).
 *
 * Le moteur Petiteur n'est jamais modifié directement : on s'appuie
 * exclusivement sur ses filtres et actions publics.
 *
 * @package PLAIDACT\CampaignCore
 */

namespace Plaidact\CampaignCore;

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Point d'intégration unique avec le moteur Petitioner embarqué.
 */
final class Petitioner_Integration
{
    /**
     * Clés des champs de signature enrichie, dans l'ordre d'affichage voulu
     * sous le champ email. Source unique de vérité partagée par le rendu
     * public, l'éditeur de formulaire et les propriétés personnalisées.
     */
    private const SIGNATURE_FIELD_KEYS = [
        "sign_as_organization",
        "organization_name",
        "organization_logo",
        "organization_public",
        "sign_as_personality",
        "signer_title",
        "signer_function",
    ];

    /**
     * Indique si le module Petitioner embarqué est présent et chargé.
     *
     * @return bool
     */
    public static function is_available(): bool
    {
        return class_exists("AV_Petitioner_Setup");
    }

    /**
     * Branche l'intégration sur les filtres et actions de Petitioner.
     *
     * Ne fait rien si le moteur embarqué est absent : le plugin reste
     * fonctionnel pour ses autres modules (newsletter, répertoires…).
     *
     * @return void
     */
    public static function boot(): void
    {
        if (!self::is_available()) {
            return;
        }

        // Rendu du formulaire public et de l'éditeur d'administration.
        add_filter("av_petitioner_form_fields", [__CLASS__, "add_signature_fields"], 10, 2);
        add_filter("av_petitioner_form_fields_admin", [__CLASS__, "add_signature_fields"], 10, 2);
        add_filter("av_petitioner_builder_fields", [__CLASS__, "expose_signature_fields_in_builder"]);
        add_filter("av_petitioner_field_order", [__CLASS__, "insert_signature_fields_after_email"], 10, 2);

        // Stockage des valeurs saisies par le signataire.
        add_filter("av_petitioner_get_custom_property_types", [__CLASS__, "register_signature_properties"]);
        add_filter("av_petitioner_submission_data_pre_save", [__CLASS__, "normalize_submission_identity"], 5, 2);

        // Règles métier multilingues (une pétition = N traductions).
        add_filter(
            "av_petitioner_check_duplicate_email",
            [__CLASS__, "check_duplicate_email_across_translations"],
            10,
            3
        );
        add_filter("av_petitioner_submission_count_form_ids", [__CLASS__, "expand_submission_count_form_ids"], 10, 2);

        // Effets de bord d'une signature confirmée (déjà exécutés hors requête
        // AJAX par Petitioner via wp_schedule_single_event).
        add_action("petitioner_submission_finalized", [__CLASS__, "handle_finalized_submission"], 10, 2);
    }

    /* ---------------------------------------------------------------------
     * Signature enrichie : organisation / personnalité publique
     * ---------------------------------------------------------------------
     *
     * Les champs ci-dessous sont ajoutés à chaque formulaire Petitioner,
     * juste après l'email. Le basculement se fait côté client via
     * assets/campaign-organization-signature.js : cocher « signer en tant
     * qu'organisation » remplace prénom/nom par le bloc organisation.
     *
     * Un réglage permet de désactiver complètement la fonctionnalité
     * (Réglages → PLAID·ACT → Signature d'organisation).
     */

    /**
     * Définitions déclaratives des champs de signature enrichie.
     *
     * Chaque entrée décrit un champ : type HTML, groupe logique, obligation,
     * libellés trilingues et placeholder éventuel. Toutes les surfaces
     * (rendu public, éditeur, propriétés personnalisées) dérivent de cette
     * seule source : ajouter un champ ici suffit à l'exposer partout.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function get_signature_field_definitions(): array
    {
        return [
            "sign_as_organization" => [
                "type" => "checkbox",
                "group" => "organization",
                "required" => false,
                "label" => [
                    "fr" => "Je souhaite signer en tant qu’organisation",
                    "en" => "I want to sign as an organization",
                    "es" => "Quiero firmar como organización",
                ],
                "field_name" => [
                    "fr" => "Signature d’organisation",
                    "en" => "Organization signature",
                    "es" => "Firma de organización",
                ],
            ],
            "organization_name" => [
                "type" => "text",
                "group" => "organization",
                "required" => true,
                "label" => [
                    "fr" => "Nom de l’organisation",
                    "en" => "Organization name",
                    "es" => "Nombre de la organización",
                ],
                "placeholder" => [
                    "fr" => "Ex. Association locale",
                    "en" => "E.g. Local association",
                    "es" => "Ej. Asociación local",
                ],
            ],
            "organization_logo" => [
                "type" => "url",
                "group" => "organization",
                "required" => false,
                "label" => [
                    "fr" => "Logo de l’organisation (URL)",
                    "en" => "Organization logo (URL)",
                    "es" => "Logotipo de la organización (URL)",
                ],
                "placeholder" => [
                    "fr" => "https://exemple.org/logo.png",
                    "en" => "https://example.org/logo.png",
                    "es" => "https://ejemplo.org/logo.png",
                ],
            ],
            "organization_public" => [
                "type" => "checkbox",
                "group" => "organization",
                "required" => false,
                "label" => [
                    "fr" => "J’accepte de rendre visible le nom/logo de mon organisation sur le site",
                    "en" => "I agree to make my organization name/logo visible on the site",
                    "es" => "Acepto que el nombre/logotipo de mi organización sea visible en el sitio",
                ],
            ],
            "sign_as_personality" => [
                "type" => "checkbox",
                "group" => "personality",
                "required" => false,
                "label" => [
                    "fr" => "Je signe avec mon titre et ma fonction",
                    "en" => "I want to sign with my title and role",
                    "es" => "Quiero firmar con mi título y cargo",
                ],
                "field_name" => [
                    "fr" => "Signature avec titre et fonction",
                    "en" => "Signature with title and role",
                    "es" => "Firma con título y cargo",
                ],
            ],
            "signer_title" => [
                "type" => "text",
                "group" => "personality",
                "required" => false,
                "label" => [
                    "fr" => "Titre",
                    "en" => "Title",
                    "es" => "Título",
                ],
                "placeholder" => [
                    "fr" => "Ex. Professeure, Dr, Maire",
                    "en" => "E.g. Professor, Dr, Mayor",
                    "es" => "Ej. Profesora, Dr., Alcaldesa",
                ],
            ],
            "signer_function" => [
                "type" => "text",
                "group" => "personality",
                "required" => false,
                "label" => [
                    "fr" => "Fonction",
                    "en" => "Role",
                    "es" => "Cargo",
                ],
                "placeholder" => [
                    "fr" => "Ex. Directrice de recherche",
                    "en" => "E.g. Research director",
                    "es" => "Ej. Directora de investigación",
                ],
            ],
        ];
    }

    /**
     * Indique si la signature d'organisation/personnalité est active.
     *
     * Ordre de priorité : filtre de code > réglage PLAID·ACT (activé par
     * défaut). Le filtre accepte true/false pour forcer l'état sans toucher
     * aux options, ou null pour suivre le réglage.
     *
     * @return bool
     */
    public static function is_enhanced_signature_enabled(): bool
    {
        $forced = apply_filters("plaidact_campaign_enhanced_signature_enabled", null);

        if (is_bool($forced)) {
            return $forced;
        }

        $settings = Shortcodes::get_settings(false);

        return "1" === (string) ($settings["petition_org_signature"] ?? "1");
    }

    /**
     * Construit la configuration d'un champ au format attendu par le
     * moteur Petitioner (rendu public comme éditeur de formulaire).
     *
     * @param string $field_key Clé du champ (voir SIGNATURE_FIELD_KEYS).
     * @return array<string, mixed>
     */
    private static function build_field_config(string $field_key): array
    {
        $definitions = self::get_signature_field_definitions();
        $definition = $definitions[$field_key] ?? null;

        if (null === $definition) {
            return [];
        }

        $config = [
            "fieldKey" => $field_key,
            "type" => (string) $definition["type"],
            // L'étiquette interne affichée dans l'éditeur de formulaire.
            "fieldName" => self::translate_definition((array) ($definition["field_name"] ?? $definition["label"])),
            "label" => self::translate_definition((array) $definition["label"]),
            "required" => !empty($definition["required"]),
            // Non supprimable dans l'éditeur : l'intégration garantit sa présence.
            "removable" => false,
        ];

        if (!empty($definition["placeholder"])) {
            $config["placeholder"] = self::translate_definition((array) $definition["placeholder"]);
        }

        if ("checkbox" === $definition["type"]) {
            $config["defaultValue"] = false;
        }

        return $config;
    }

    /**
     * Résout un triplet de libellés selon la langue cible de la requête.
     *
     * @param array<string,string> $texts Tableau indexé par fr|en|es.
     * @return string
     */
    private static function translate_definition(array $texts): string
    {
        $language = self::get_target_language();

        if (isset($texts[$language])) {
            // Français : passe par i18n pour rester surchargeable par un .mo.
            return "fr" === $language
                ? __($texts["fr"], "plaidact-campaign-core")
                : (string) $texts[$language];
        }

        return (string) ($texts["fr"] ?? "");
    }

    /**
     * Détermine la langue cible (fr|en|es) une seule fois par requête.
     *
     * Priorité à Polylang (langue de la page consultée), sinon locale du
     * site. Toute autre langue retombe sur le français, langue de référence
     * des campagnes PLAID·ACT.
     *
     * @return string
     */
    private static function get_target_language(): string
    {
        static $target_language = null;

        if (null !== $target_language) {
            return $target_language;
        }

        $language = Polylang::current_language();

        if (null === $language || "" === $language) {
            $locale = function_exists("determine_locale") ? determine_locale() : get_locale();
            $language = substr((string) $locale, 0, 2);
        }

        $language = substr((string) $language, 0, 2);
        $target_language = in_array($language, ["fr", "en", "es"], true) ? $language : "fr";

        return $target_language;
    }

    /**
     * Filtre av_petitioner_form_fields(_admin) : ajoute les champs de
     * signature enrichie à la configuration du formulaire.
     *
     * @param array $form_fields Champs existants (indexés par fieldKey).
     * @param int   $form_id     ID du formulaire rendu.
     * @return array
     */
    public static function add_signature_fields(array $form_fields, int $form_id): array
    {
        if (!self::is_enhanced_signature_enabled()) {
            return $form_fields;
        }

        foreach (self::SIGNATURE_FIELD_KEYS as $field_key) {
            // On n'écrase jamais un champ défini par l'administrateur.
            $config = self::build_field_config($field_key);

            if ([] !== $config && !isset($form_fields[$field_key])) {
                $form_fields[$field_key] = $config;
            }
        }

        return $form_fields;
    }

    /**
     * Filtre av_petitioner_field_order : insère les champs de signature
     * enrichie juste après l'email, quel que soit l'ordre choisi.
     *
     * @param array $field_order Ordre actuel des champs.
     * @param int   $form_id     ID du formulaire rendu.
     * @return array
     */
    public static function insert_signature_fields_after_email(array $field_order, int $form_id): array
    {
        if (!self::is_enhanced_signature_enabled()) {
            return $field_order;
        }

        // Retire les clés présentes pour éviter tout doublon avant insertion.
        $field_order = array_values(
            array_diff($field_order, self::SIGNATURE_FIELD_KEYS)
        );

        $email_position = array_search("email", $field_order, true);
        $insert_at = false === $email_position ? 0 : $email_position + 1;

        array_splice($field_order, $insert_at, 0, self::SIGNATURE_FIELD_KEYS);

        return $field_order;
    }

    /**
     * Filtre av_petitioner_builder_fields : expose les champs dans la palette
     * de l'éditeur afin qu'ils soient visibles (et documentés) pour
     * l'administrateur, sans doublon avec ce qui existerait déjà.
     *
     * @param array $builder_fields Groupes « defaults » et « draggable ».
     * @return array
     */
    public static function expose_signature_fields_in_builder(array $builder_fields): array
    {
        if (!self::is_enhanced_signature_enabled()) {
            return $builder_fields;
        }

        $builder_fields["draggable"] = (array) ($builder_fields["draggable"] ?? []);

        $existing_keys = [];
        foreach ((array) ($builder_fields["defaults"] ?? []) as $key => $_config) {
            // Le groupe « defaults » est indexé par fieldKey.
            if (is_string($key)) {
                $existing_keys[] = $key;
            }
        }
        foreach ($builder_fields["draggable"] as $field) {
            // Le groupe « draggable » est une liste de configurations.
            if (is_array($field) && isset($field["fieldKey"])) {
                $existing_keys[] = (string) $field["fieldKey"];
            }
        }

        foreach (self::SIGNATURE_FIELD_KEYS as $field_key) {
            if (!in_array($field_key, $existing_keys, true)) {
                $builder_fields["draggable"][] = self::build_field_config($field_key);
            }
        }

        return $builder_fields;
    }

    /**
     * Filtre av_petitioner_get_custom_property_types : enregistre chaque
     * champ auprès du stockage JSON « custom_properties » de Petitioner,
     * avec son callback de nettoyage dédié.
     *
     * @param array $property_types Propriétés déjà enregistrées.
     * @return array
     */
    public static function register_signature_properties(array $property_types): array
    {
        if (!self::is_enhanced_signature_enabled()) {
            return $property_types;
        }

        $sanitize_by_type = [
            "checkbox" => "sanitize_text_field",
            "text" => "sanitize_text_field",
            "url" => "esc_url_raw",
        ];

        foreach (self::get_signature_field_definitions() as $field_key => $definition) {
            $property_types[$field_key] = [
                "sanitize_callback" => $sanitize_by_type[(string) $definition["type"]] ?? "sanitize_text_field",
            ];
        }

        return $property_types;
    }

    /**
     * Filtre av_petitioner_submission_data_pre_save : quand la signature est
     * portée par une organisation, le nom de celle-ci devient l'identité
     * affichée (colonne fname du moteur, obligatoire), le nom personnel
     * étant conservé à part dans les propriétés personnalisées.
     *
     * @param array $data      Données sur le point d'être enregistrées.
     * @param array $post_data Données POST brutes.
     * @return array
     */
    public static function normalize_submission_identity(array $data, array $post_data): array
    {
        $signs_as_organization = !empty($post_data["petitioner_sign_as_organization"]);
        $organization_name = isset($post_data["petitioner_organization_name"])
            ? sanitize_text_field(wp_unslash($post_data["petitioner_organization_name"]))
            : "";

        if ($signs_as_organization && "" !== $organization_name) {
            $data["fname"] = $organization_name;
            $data["lname"] = "";
        }

        return $data;
    }

    /* ---------------------------------------------------------------------
     * Multilinguisme : une pétition, plusieurs formulaires traduits
     * ---------------------------------------------------------------------
     */

    /**
     * Filtre av_petitioner_check_duplicate_email : considère les formulaires
     * traduits d'une même pétition comme une seule pétition. Un email ayant
     * signé la version française ne peut pas re-signer la version anglaise.
     *
     * @param bool   $is_duplicate Statut de doublon calculé par le moteur.
     * @param string $email        Email soumis.
     * @param int    $form_id      Formulaire concerné.
     * @return bool
     */
    public static function check_duplicate_email_across_translations(
        bool $is_duplicate,
        string $email,
        int $form_id
    ): bool {
        if (
            $is_duplicate ||
            !class_exists("AV_Petitioner_Submissions_Model")
        ) {
            return $is_duplicate;
        }

        // Garde-fou anti-récursion : check_duplicate_email() déclenche à son
        // tour ce filtre pour chaque formulaire lié.
        static $is_checking_translations = false;

        if ($is_checking_translations) {
            return $is_duplicate;
        }

        $is_checking_translations = true;

        try {
            foreach (Shortcodes::get_linked_petitioner_form_ids($form_id) as $linked_form_id) {
                if ($linked_form_id === $form_id) {
                    continue;
                }

                if (\AV_Petitioner_Submissions_Model::check_duplicate_email($email, $linked_form_id)) {
                    return true;
                }
            }

            return false;
        } finally {
            $is_checking_translations = false;
        }
    }

    /**
     * Filtre av_petitioner_submission_count_form_ids : étend les compteurs
     * natifs de Petitioner à tous les formulaires traduits de la pétition.
     *
     * @param array<int> $form_ids IDs actuellement comptés.
     * @param int        $form_id  Formulaire source.
     * @return array<int>
     */
    public static function expand_submission_count_form_ids(array $form_ids, int $form_id): array
    {
        return array_values(array_unique(array_filter(array_map(
            "absint",
            array_merge($form_ids, Shortcodes::get_linked_petitioner_form_ids($form_id))
        ))));
    }

    /**
     * Retourne les IDs de tous les formulaires traduits d'une pétition.
     *
     * @param int $form_id ID d'un formulaire de la pétition.
     * @return array<int>
     */
    public static function get_linked_form_ids(int $form_id): array
    {
        return Shortcodes::get_linked_petitioner_form_ids($form_id);
    }

    /* ---------------------------------------------------------------------
     * API de lecture (compteur et signataires)
     * ---------------------------------------------------------------------
     *
     * Ces méthodes sont consommées par Shortcodes (jauge, liste publique,
     * page admin « Signataires ») : toute la logique SQL liée au moteur est
     * concentrée ici plutôt que dispersée dans le rendu.
     */

    /**
     * Nombre de signatures confirmées d'une pétition, toutes traductions
     * confondues, en une seule requête SQL agrégée.
     *
     * Repli : shortcode natif petitioner-submission-count si le modèle du
     * moteur n'est pas disponible.
     *
     * @param int $form_id Formulaire de référence de la pétition.
     * @return int
     */
    public static function get_signature_count(int $form_id): int
    {
        static $count_cache = [];

        $form_id = absint($form_id);

        if ($form_id <= 0) {
            return 0;
        }

        if (!class_exists("AV_Petitioner_Submissions_Model")) {
            return self::get_signature_count_via_shortcode($form_id);
        }

        if (isset($count_cache[$form_id])) {
            return $count_cache[$form_id];
        }

        global $wpdb;

        $linked_form_ids = self::get_linked_form_ids($form_id);
        $count = 0;

        if ([] !== $linked_form_ids) {
            $placeholders = implode(",", array_fill(0, count($linked_form_ids), "%d"));
            $table = \AV_Petitioner_Submissions_Model::table_name();

            // Même sémantique que AV_Petitioner_Submissions_Model::
            // get_submission_count() par défaut : seules les signatures
            // confirmées sont comptabilisées.
            $count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table}
                     WHERE form_id IN ({$placeholders}) AND approval_status = %s",
                    array_merge($linked_form_ids, ["Confirmed"])
                )
            );
        }

        $count_cache[$form_id] = max(0, $count);

        return $count_cache[$form_id];
    }

    /**
     * Compteur de secours via le shortcode natif du moteur.
     *
     * @param int $form_id Formulaire de référence.
     * @return int
     */
    private static function get_signature_count_via_shortcode(int $form_id): int
    {
        if (!shortcode_exists("petitioner-submission-count")) {
            return 0;
        }

        return max(0, absint(do_shortcode(sprintf('[petitioner-submission-count id="%d"]', $form_id))));
    }

    /**
     * Liste paginée des signatures de plusieurs formulaires (pétition
     * traduite), triée par date décroissante.
     *
     * Arguments acceptés :
     *  - per_page       (int)          nombre de lignes, 12 par défaut ;
     *  - offset         (int)          décalage de pagination ;
     *  - fields         (string|array) colonnes demandées, "*" par défaut ;
     *  - confirmed_only (bool)         restreindre aux confirmées.
     *
     * @param array<int>     $form_ids Formulaires de la pétition.
     * @param array<string,mixed> $args Paramètres de requête.
     * @return array{submissions: array<int, object>, total: int}
     */
    public static function query_submissions(array $form_ids, array $args = []): array
    {
        global $wpdb;

        $empty_result = ["submissions" => [], "total" => 0];

        if (!class_exists("AV_Petitioner_Submissions_Model")) {
            return $empty_result;
        }

        $form_ids = array_values(array_filter(array_map("absint", $form_ids)));

        if ([] === $form_ids) {
            return $empty_result;
        }

        // Liste blanche stricte des colonnes : aucune valeur externe
        // n'atteint la clause SELECT sans validation.
        $allowed_columns = \AV_Petitioner_Submissions_Model::$ALLOWED_FIELDS;
        $requested_fields = $args["fields"] ?? "*";

        if ("*" !== $requested_fields) {
            $columns = implode(", ", array_intersect((array) $requested_fields, $allowed_columns));
            $requested_fields = "" !== $columns ? $columns : "*";
        }

        $per_page = max(1, absint($args["per_page"] ?? 12));
        $offset = absint($args["offset"] ?? 0);

        $placeholders = implode(",", array_fill(0, count($form_ids), "%d"));
        $where = "form_id IN ($placeholders)";
        $params = $form_ids;

        if (!empty($args["confirmed_only"])) {
            $where .= " AND approval_status = %s";
            $params[] = "Confirmed";
        }

        $table = \AV_Petitioner_Submissions_Model::table_name();

        $total = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}", $params)
        );

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT {$requested_fields} FROM {$table}
                 WHERE {$where}
                 ORDER BY submitted_at DESC
                 LIMIT %d OFFSET %d",
                array_merge($params, [$per_page, $offset])
            )
        );

        return [
            "submissions" => is_array($rows) ? $rows : [],
            "total" => $total,
        ];
    }

    /* ---------------------------------------------------------------------
     * Effets de bord d'une signature finalisée
     * ---------------------------------------------------------------------
     *
     * Exécutés par Petitioner hors requête AJAX (tâche planifiée), une fois
     * la signature confirmée (directement, après double opt-in ou après
     * validation manuelle).
     */

    /**
     * Action petitioner_submission_finalized : déclenche les effets de bord
     * PLAID·ACT associés à une signature confirmée.
     *
     * @param object $submission Signataire hydraté par le moteur.
     * @param int    $form_id    Formulaire d'origine de la signature.
     * @return void
     */
    public static function handle_finalized_submission(object $submission, int $form_id): void
    {
        $language = Polylang::post_language($form_id);
        $settings = Shortcodes::get_settings(true, $language);
        $display_name = self::build_display_name($submission);

        // 1. Notification de l'équipe campagne.
        Petition_Workflows::maybe_notify_admin(
            $settings,
            $display_name,
            (string) ($submission->email ?? ""),
            (string) ($submission->postal_code ?? ""),
            (string) ($submission->phone ?? ""),
            $language,
            $form_id
        );

        // 2. Email au décideur, sauf si le moteur gère déjà cet envoi
        //    (réglage « envoyer au représentant » de la pétition).
        if (!self::does_engine_send_decision_maker_email($form_id)) {
            Petition_Workflows::maybe_send_decision_maker_email(
                $settings,
                $display_name,
                (string) ($submission->email ?? ""),
                (string) ($submission->postal_code ?? ""),
                $language
            );
        }

        // 3. Synchronisation Brevo (liste pétition + newsletter si opt-in).
        self::sync_signer_to_brevo($submission, $display_name, $language, $settings);
    }

    /**
     * Construit le nom complet d'affichage à partir d'un objet signataire.
     *
     * Pour une signature d'organisation, normalize_submission_identity()
     * place le nom de l'organisation dans fname : il devient donc naturellement
     * le nom d'affichage.
     *
     * @param object $submission Signataire.
     * @return string
     */
    private static function build_display_name(object $submission): string
    {
        $parts = array_filter([
            isset($submission->fname) ? sanitize_text_field((string) $submission->fname) : "",
            isset($submission->lname) ? sanitize_text_field((string) $submission->lname) : "",
        ]);

        return trim(implode(" ", $parts));
    }

    /**
     * Vérifie si Petitioner envoie lui-même l'email au décideur pour cette
     * pétition (destinataire configuré + option activée).
     *
     * @param int $form_id Formulaire de la pétition.
     * @return bool
     */
    private static function does_engine_send_decision_maker_email(int $form_id): bool
    {
        $engine_sends = (bool) get_post_meta($form_id, "_petitioner_send_to_representative", true);
        $engine_target = sanitize_email((string) get_post_meta($form_id, "_petitioner_email", true));

        return $engine_sends && "" !== $engine_target;
    }

    /**
     * Ajoute le signataire aux listes Brevo (pétition, et newsletter si
     * l'opt-in est coché) puis trace le résultat dans la fiche signataire.
     *
     * Ne fait rien lorsque Brevo n'est pas configuré : cela évite d'écrire
     * une erreur de configuration dans email_status pour chaque signature.
     *
     * @param object               $submission  Signataire.
     * @param string               $full_name   Nom complet d'affichage.
     * @param string|null          $language    Langue de la pétition.
     * @param array<string,mixed>  $settings    Réglages PLAID·ACT traduits.
     * @return void
     */
    private static function sync_signer_to_brevo(
        object $submission,
        string $full_name,
        ?string $language,
        array $settings
    ): void {
        $email = sanitize_email((string) ($submission->email ?? ""));

        if ("" === $email) {
            return;
        }

        $petition_list_id = absint($settings["brevo_list_petition"] ?? 0);
        $wants_newsletter = !empty($submission->newsletter);
        $newsletter_list_id = $wants_newsletter ? absint($settings["brevo_list_plaidact"] ?? 0) : 0;

        $api_key = (string) ($settings["brevo_api_key"] ?? "");
        $has_target_lists = [] !== array_filter([$petition_list_id, $newsletter_list_id]);

        if ("" === trim($api_key) || !$has_target_lists) {
            // Brevo volontairement non configuré : aucune synchronisation,
            // aucun statut d'erreur à tracer.
            return;
        }

        $result = Shortcodes::subscribe_to_brevo_lists(
            $email,
            $full_name,
            $language,
            true,
            $wants_newsletter
        );

        self::record_brevo_sync_status($submission, $result, $language);
    }

    /**
     * Persiste le résultat de la synchronisation Brevo dans le champ
     * email_status du signataire (JSON structuré, exploitable en admin).
     *
     * @param object              $submission Signataire.
     * @param mixed               $result     Résultat de subscribe_to_brevo_lists().
     * @param string|null         $language   Langue de la pétition.
     * @return void
     */
    private static function record_brevo_sync_status(object $submission, $result, ?string $language): void
    {
        $submission_id = isset($submission->id) ? absint($submission->id) : 0;

        if (
            $submission_id <= 0 ||
            !class_exists("AV_Petitioner_Submissions_Model") ||
            !method_exists(\AV_Petitioner_Submissions_Model::class, "update_submission")
        ) {
            return;
        }

        $status_payload = [
            "provider" => "brevo",
            "status" => is_wp_error($result) ? "error" : (string) $result,
            "message" => is_wp_error($result) ? $result->get_error_message() : "",
            "synced_at" => current_time("mysql"),
            "language" => $language,
        ];

        \AV_Petitioner_Submissions_Model::update_submission($submission_id, [
            "email_status" => wp_json_encode($status_payload),
        ]);
    }
}
