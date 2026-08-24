<?php
/**
 * Synchronisation temps réel avec la plateforme Actyl (API REST /api/v1/*).
 *
 * Ce module pousse vers l'instance Actyl configurée :
 *  - les signatures de pétitions confirmées (temps réel + rattrapage) ;
 *  - les inscriptions newsletter du formulaire PLAID·ACT ;
 *  - les dons signalés par le hook plaidact_actyl_record_donation.
 *
 * Garanties fondamentales :
 *  - désactivé par défaut : aucune requête sortante tant que l'URL, le token
 *    et l'activation ne sont pas complétés d'un test de connexion réussi ;
 *  - jamais bloquant pour le visiteur : timeouts courts, échecs silencieux
 *    côté front, tout est tracé dans le journal de synchronisation ;
 *  - jamais destructeur : aucun appel pendant les imports CSV ou traitements
 *    en masse existants (le rattrapage est une opération dédiée et explicite).
 *
 * @package PLAIDACT\CampaignCore
 */

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Client singleton de l'API Actyl.
 */
final class PLAIDACT_Actyl
{
    /** Option de configuration (URL, token, activation). */
    private const OPTION_SETTINGS = "plaidact_actyl_settings";

    /** Journal des derniers événements de synchronisation (plafonné). */
    private const OPTION_LOG = "plaidact_actyl_log";

    /** Horodatage du dernier ping réussi (garde d'activation de la synchro). */
    private const OPTION_PING_OK_AT = "plaidact_actyl_ping_ok_at";

    /** Curseur du rattrapage : dernière ligne de signature traitée (id). */
    private const OPTION_BACKFILL_CURSOR = "plaidact_actyl_backfill_cursor";

    /** Métadonnée de liaison pétition → slug de campagne Actyl. */
    private const META_CAMPAIGN_SLUG = "_plaidact_actyl_campaign_slug";

    /** Événement planifié de nouvelle tentative d'envoi. */
    private const CRON_RETRY = "plaidact_actyl_retry_push";

    /** Événement planifié de ping automatique après sauvegarde des réglages. */
    private const CRON_AUTO_PING = "plaidact_actyl_auto_ping";

    /** Délai maximal de chaque appel sortant : l'utilisateur ne doit jamais attendre. */
    private const REQUEST_TIMEOUT = 5;

    /** Taille d'un lot de rattrapage. */
    private const BACKFILL_BATCH_SIZE = 20;

    /**
     * Pause entre deux envois du rattrapage : maintient le débit bien sous
     * la limite de l'API (60 requêtes/minute/token).
     */
    private const BACKFILL_PAUSE_MICROS = 300000;

    /** Nombre d'entrées conservées dans le journal. */
    private const LOG_MAX_ENTRIES = 100;

    /** Délai avant la tentative unique de rattrapage (secondes). */
    private const RETRY_DELAY = 600;

    /**
     * Instance unique (même motif que PlaidAct_Contact_Directory).
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Point d'entrée : instancie le module une seule fois.
     *
     * @return self
     */
    public static function init(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Branche tous les hooks. Le module étant désactivé par défaut, chaque
     * chemin sortant revérifie is_active() au moment de l'exécution : charger
     * la classe n'a donc aucun effet réseau tant que l'opérateur n'a pas
     * configuré et validé la connexion.
     */
    private function __construct()
    {
        // Réglages : section « Connexion Actyl » dans Réglages → PLAID·ACT.
        add_action("admin_init", [$this, "register_settings"]);
        add_action("plaidact_campaign_settings_page_end", [$this, "render_connection_section"]);
        add_action("admin_post_plaidact_actyl_test_connection", [$this, "handle_test_connection"]);
        add_action("admin_post_plaidact_actyl_clear_log", [$this, "handle_clear_log"]);

        // Liaison pétition → campagne Actyl.
        add_action("add_meta_boxes", [$this, "register_campaign_metabox"]);
        add_action("save_post_petitioner-petition", [$this, "save_campaign_metabox"], 10, 2);

        // Poussée temps réel.
        add_action("petitioner_submission_finalized", [$this, "push_signature_from_submission"], 10, 2);
        add_action("plaidact_newsletter_subscribed", [$this, "push_supporter_from_newsletter"], 10, 3);

        // Hook d'appel externe pour les dons (voir record_donation()).
        add_action("plaidact_actyl_record_donation", [$this, "record_donation"]);

        // Tâches différées.
        add_action(self::CRON_RETRY, [$this, "run_retry_push"]);
        add_action(self::CRON_AUTO_PING, [$this, "run_auto_ping"]);

        // Rattrapage en ligne de commande : wp plaidact actyl-backfill.
        if (defined("WP_CLI") && WP_CLI && class_exists("WP_CLI")) {
            \WP_CLI::add_command("plaidact actyl-backfill", [$this, "cli_backfill"]);
        }
    }

    /* ---------------------------------------------------------------------
     * Configuration et état de connexion
     * ---------------------------------------------------------------------
     */

    /**
     * Déclare l'option de configuration avec son nettoyage dédié.
     *
     * L'option est volontairement séparée de plaidact_campaign_settings :
     * celui-ci est reconstruit clé par clé lors de sa sauvegarde et écraserait
     * toute clé qu'il ne connaît pas.
     *
     * @return void
     */
    public function register_settings(): void
    {
        register_setting(
            self::OPTION_SETTINGS,
            self::OPTION_SETTINGS,
            ["sanitize_callback" => [$this, "sanitize_settings"]]
        );
    }

    /**
     * Valeurs par défaut de la connexion (tout désactivé).
     *
     * @return array<string,string>
     */
    private function get_default_settings(): array
    {
        return [
            "actyl_url" => "",
            "actyl_api_token" => "",
            "actyl_enabled" => "0",
        ];
    }

    /**
     * Lit la configuration brute.
     *
     * @return array<string,string>
     */
    private function get_settings(): array
    {
        return wp_parse_args(
            (array) get_option(self::OPTION_SETTINGS, []),
            $this->get_default_settings()
        );
    }

    /**
     * Nettoie la configuration soumise par le formulaire de réglages.
     *
     * Règles de sécurité :
     *  - l'URL doit être en HTTPS, sans slash final ;
     *  - le token doit commencer par le préfixe du contrat (actyl_) ;
     *  - un champ token vide conserve la valeur déjà enregistrée (motif
     *    classique des champs secrets : l'input n'affiche jamais la valeur) ;
     *  - toute modification d'URL ou de token invalide le ping précédent :
     *    la cible change, la validation doit être rejouée.
     *
     * @param mixed $input Valeurs brutes du formulaire.
     * @return array<string,string>
     */
    public function sanitize_settings($input): array
    {
        $input = is_array($input) ? $input : [];
        $existing = $this->get_settings();
        $clean = $this->get_default_settings();

        // URL : HTTPS strict, sans slash final.
        $raw_url = isset($input["actyl_url"]) ? trim((string) wp_unslash($input["actyl_url"])) : "";
        if ("" !== $raw_url) {
            $url = esc_url_raw($raw_url);
            $scheme = "" !== $url ? (string) wp_parse_url($url, PHP_URL_SCHEME) : "";

            if ("https" !== $scheme) {
                add_settings_error(
                    self::OPTION_SETTINGS,
                    "actyl_url",
                    __("L’URL Actyl doit commencer par https://", "plaidact-campaign-core")
                );
                $clean["actyl_url"] = (string) $existing["actyl_url"];
            } else {
                $clean["actyl_url"] = untrailingslashit($url);
            }
        }

        // Token : vide signifie « conserver la valeur existante ».
        $raw_token = isset($input["actyl_api_token"])
            ? trim((string) wp_unslash($input["actyl_api_token"]))
            : "";

        if ("" === $raw_token) {
            $clean["actyl_api_token"] = (string) $existing["actyl_api_token"];
        } elseif (0 !== strpos($raw_token, "actyl_")) {
            add_settings_error(
                self::OPTION_SETTINGS,
                "actyl_token",
                __("Le token Actyl doit commencer par « actyl_ ».", "plaidact-campaign-core")
            );
            $clean["actyl_api_token"] = (string) $existing["actyl_api_token"];
        } else {
            $clean["actyl_api_token"] = sanitize_text_field($raw_token);
        }

        // Activation explicite par l'opérateur.
        $clean["actyl_enabled"] = !empty($input["actyl_enabled"]) ? "1" : "0";

        // Cible modifiée : la validation précédente ne vaut plus rien.
        if (
            (string) $existing["actyl_url"] !== $clean["actyl_url"] ||
            (string) $existing["actyl_api_token"] !== $clean["actyl_api_token"]
        ) {
            delete_option(self::OPTION_PING_OK_AT);
        }

        // Activation demandée sur une connexion configurée mais pas encore
        // validée : planifie LE ping de validation (seul appel autorisé hors
        // état actif, puisque c'est précisément l'étape de validation).
        if (
            "1" === $clean["actyl_enabled"] &&
            "" !== $clean["actyl_url"] &&
            "" !== $clean["actyl_api_token"] &&
            !$this->has_valid_ping()
        ) {
            $this->schedule_auto_ping();
        }

        return $clean;
    }

    /**
     * Indique si URL et token sont renseignés.
     *
     * @return bool
     */
    public function is_configured(): bool
    {
        $settings = $this->get_settings();

        return "" !== (string) $settings["actyl_url"]
            && "" !== (string) $settings["actyl_api_token"];
    }

    /**
     * Indique si un ping réussi valide encore la connexion.
     *
     * @return bool
     */
    private function has_valid_ping(): bool
    {
        return (int) get_option(self::OPTION_PING_OK_AT, 0) > 0;
    }

    /**
     * État opérationnel de la synchronisation.
     *
     * C'est LA garde unique devant chaque envoi : activation explicite,
     * configuration complète et ping réussi. Toute valeur manquante coupe
     * toute sortie réseau du module.
     *
     * @return bool
     */
    public function is_active(): bool
    {
        $settings = $this->get_settings();

        return "1" === (string) $settings["actyl_enabled"]
            && $this->is_configured()
            && $this->has_valid_ping();
    }

    /* ---------------------------------------------------------------------
     * Couche HTTP et journal
     * ---------------------------------------------------------------------
     */

    /**
     * Effectue un appel authentifié vers l'API Actyl et journalise le résultat.
     *
     * Timeout volontairement court : aucun appel ne doit retenir une requête
     * visiteur ou une tâche planifiée plus de quelques secondes.
     *
     * @param string              $path   Chemin après le domaine (/api/v1/…).
     * @param string              $method Méthode HTTP (GET ou POST).
     * @param array<string,mixed> $body   Corps JSON (vide pour GET).
     * @return array{code:int, body:string, error:string}
     */
    private function request(string $path, string $method = "POST", array $body = []): array
    {
        $settings = $this->get_settings();
        $base = untrailingslashit((string) $settings["actyl_url"]);
        $token = (string) $settings["actyl_api_token"];

        $response = wp_remote_request($base . $path, [
            "method" => $method,
            "timeout" => self::REQUEST_TIMEOUT,
            "headers" => [
                "Authorization" => "Bearer " . $token,
                "Content-Type" => "application/json",
                "Accept" => "application/json",
            ],
            "body" => [] === $body ? null : wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            $this->log_event($path, 0, $response->get_error_message());

            return [
                "code" => 0,
                "body" => "",
                "error" => $response->get_error_message(),
            ];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $payload = (string) wp_remote_retrieve_body($response);

        $this->log_event($path, $code, $this->summarize_response($payload));

        return [
            "code" => $code,
            "body" => $payload,
            "error" => "",
        ];
    }

    /**
     * Décide si un échec justifie la tentative unique différée : erreur
     * réseau (code 0) ou panne serveur côté Actyl (5xx). Un 4xx traduit une
     * erreur de données ou d'autorisation : réessayer ne changerait rien.
     *
     * @param int $code Code HTTP retourné (0 = échec réseau).
     * @return bool
     */
    public function should_retry(int $code): bool
    {
        return 0 === $code || $code >= 500;
    }

    /**
     * Extrait un court libellé exploitable de la réponse pour le journal.
     *
     * @param string $body Corps brut de la réponse.
     * @return string
     */
    private function summarize_response(string $body): string
    {
        $decoded = json_decode($body, true);
        $message = "";

        if (is_array($decoded)) {
            foreach (["message", "error", "ok"] as $key) {
                if (isset($decoded[$key])) {
                    $message = is_bool($decoded[$key])
                        ? ($decoded[$key] ? "ok" : "ko")
                        : (string) $decoded[$key];
                    break;
                }
            }
        }

        if ("" === $message) {
            $message = wp_strip_all_tags(substr($body, 0, 120));
        }

        return mb_substr(trim($message), 0, 160);
    }

    /**
     * Ajoute un événement en tête de journal, plafonné aux dernières
     * entrées (les plus anciennes sortent naturellement).
     *
     * @param string $endpoint Chemin appelé.
     * @param int    $code     Code HTTP (0 = échec réseau).
     * @param string $message  Court résumé.
     * @return void
     */
    private function log_event(string $endpoint, int $code, string $message = ""): void
    {
        $log = get_option(self::OPTION_LOG, []);
        $log = is_array($log) ? $log : [];

        array_unshift($log, [
            "time" => current_time("mysql"),
            "endpoint" => $endpoint,
            "code" => $code,
            "message" => mb_substr(sanitize_text_field($message), 0, 160),
        ]);

        update_option(
            self::OPTION_LOG,
            array_slice($log, 0, self::LOG_MAX_ENTRIES),
            false
        );
    }

    /**
     * Retourne le journal (du plus récent au plus ancien).
     *
     * @return array<int,array<string,mixed>>
     */
    private function get_log(): array
    {
        $log = get_option(self::OPTION_LOG, []);

        return is_array($log) ? $log : [];
    }

    /* ---------------------------------------------------------------------
     * Ping et test de connexion
     * ---------------------------------------------------------------------
     */

    /**
     * Appelle /api/v1/ping et mémorise le succès (garde d'activation).
     *
     * Tout échec (dont un 401 sur token révoqué) invalide explicitement la
     * validation précédente : la synchro repart uniquement après un nouveau
     * test réussi.
     *
     * @return bool
     */
    public function ping(): bool
    {
        $result = $this->request("/api/v1/ping", "GET");

        if (200 === $result["code"]) {
            update_option(self::OPTION_PING_OK_AT, time(), false);

            return true;
        }

        delete_option(self::OPTION_PING_OK_AT);

        return false;
    }

    /**
     * Planifie le ping automatique de validation (une seule fois).
     *
     * @return void
     */
    private function schedule_auto_ping(): void
    {
        if (wp_next_scheduled(self::CRON_AUTO_PING)) {
            return;
        }

        wp_schedule_single_event(time() + 30, self::CRON_AUTO_PING);
    }

    /**
     * Tâche planifiée : rejoue le ping après une sauvegarde de réglages.
     *
     * @return void
     */
    public function run_auto_ping(): void
    {
        if ($this->is_configured()) {
            $this->ping();
        }
    }

    /**
     * Handler du bouton « Tester la connexion » : ping explicite puis retour
     * vers la page de réglages avec le résultat affiché inline.
     *
     * @return void
     */
    public function handle_test_connection(): void
    {
        if (!current_user_can("manage_options")) {
            wp_die(esc_html__("Accès refusé.", "plaidact-campaign-core"));
        }

        check_admin_referer("plaidact_actyl_test_connection");

        if (!$this->is_configured()) {
            wp_safe_redirect(add_query_arg(
                "actyl_test",
                "missing",
                $this->settings_page_url()
            ));
            exit;
        }

        // Tentative volontaire même sans activation : c'est l'outil de
        // diagnostic de l'opérateur, pas un envoi de données.
        $ok = $this->ping();

        wp_safe_redirect(add_query_arg(
            ["actyl_test" => $ok ? "ok" : "fail"],
            $this->settings_page_url()
        ));
        exit;
    }

    /**
     * Handler « Vider le journal ».
     *
     * @return void
     */
    public function handle_clear_log(): void
    {
        if (!current_user_can("manage_options")) {
            wp_die(esc_html__("Accès refusé.", "plaidact-campaign-core"));
        }

        check_admin_referer("plaidact_actyl_clear_log");

        delete_option(self::OPTION_LOG);

        wp_safe_redirect(add_query_arg("actyl_log_cleared", "1", $this->settings_page_url()));
        exit;
    }

    /**
     * URL de la page de réglages PLAID·ACT.
     *
     * @return string
     */
    private function settings_page_url(): string
    {
        return admin_url("options-general.php?page=plaidact-campaign-settings");
    }

    /* ---------------------------------------------------------------------
     * Liaison pétition → campagne Actyl
     * ---------------------------------------------------------------------
     */

    /**
     * Déclare la metabox de liaison sur l'écran d'édition des pétitions.
     *
     * @return void
     */
    public function register_campaign_metabox(): void
    {
        if (!class_exists("AV_Petitioner_Setup")) {
            return;
        }

        add_meta_box(
            "plaidact_actyl_campaign",
            __("Connexion Actyl", "plaidact-campaign-core"),
            [$this, "render_campaign_metabox"],
            "petitioner-petition",
            "side",
            "default"
        );
    }

    /**
     * Affiche le champ « Slug de campagne Actyl ».
     *
     * Le contrat Actyl n'expose pas de liste des campagnes : champ libre,
     * alimenté par le slug visible dans l'URL de la campagne côté Actyl.
     *
     * @param \WP_Post $post Pétition en cours d'édition.
     * @return void
     */
    public function render_campaign_metabox(\WP_Post $post): void
    {
        wp_nonce_field("plaidact_actyl_campaign_save", "plaidact_actyl_campaign_nonce");

        $slug = (string) get_post_meta($post->ID, self::META_CAMPAIGN_SLUG, true);
        ?>
        <p>
            <label for="plaidact_actyl_campaign_slug">
                <strong><?php esc_html_e("Slug de campagne Actyl", "plaidact-campaign-core"); ?></strong>
            </label>
            <input
                type="text"
                class="widefat"
                id="plaidact_actyl_campaign_slug"
                name="plaidact_actyl_campaign_slug"
                value="<?php echo esc_attr($slug); ?>"
                placeholder="ex. zones-humides"
            />
        </p>
        <p class="description">
            <?php esc_html_e(
                "Slug visible dans l’URL de la campagne côté Actyl. Laisser vide désactive la synchronisation de cette pétition.",
                "plaidact-campaign-core"
            ); ?>
        </p>
        <?php
    }

    /**
     * Enregistre le slug de campagne après les vérifications habituelles.
     *
     * @param int   $post_id ID de la pétition.
     * @param mixed $post    Objet poste (fourni par save_post_{type}).
     * @return void
     */
    public function save_campaign_metabox(int $post_id, $post = null): void
    {
        if (
            !isset($_POST["plaidact_actyl_campaign_nonce"]) ||
            !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST["plaidact_actyl_campaign_nonce"])),
                "plaidact_actyl_campaign_save"
            )
        ) {
            return;
        }

        if (
            (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE) ||
            !current_user_can("edit_post", $post_id)
        ) {
            return;
        }

        $slug = isset($_POST["plaidact_actyl_campaign_slug"])
            ? sanitize_title(wp_unslash($_POST["plaidact_actyl_campaign_slug"]))
            : "";

        if ("" !== $slug) {
            update_post_meta($post_id, self::META_CAMPAIGN_SLUG, $slug);
        } else {
            delete_post_meta($post_id, self::META_CAMPAIGN_SLUG);
        }
    }

    /**
     * Résout le slug de campagne d'une pétition, toutes traductions confondues.
     *
     * Avec Polylang, le slug peut être renseigné sur une seule traduction :
     * on interroge donc le groupe lié complet avant de conclure à l'absence
     * de liaison. Résultat mis en cache pour la durée de la requête.
     *
     * @param int $form_id Identifiant d'un formulaire de la pétition.
     * @return string Slug vide si aucune liaison.
     */
    public function resolve_campaign_slug(int $form_id): string
    {
        static $slug_cache = [];

        $form_id = absint($form_id);

        if ($form_id <= 0) {
            return "";
        }

        if (isset($slug_cache[$form_id])) {
            return $slug_cache[$form_id];
        }

        $slug = "";

        foreach (\Plaidact\CampaignCore\Shortcodes::get_linked_petitioner_form_ids($form_id) as $linked_id) {
            $candidate = (string) get_post_meta($linked_id, self::META_CAMPAIGN_SLUG, true);

            if ("" !== $candidate) {
                $slug = $candidate;
                break;
            }
        }

        $slug_cache[$form_id] = $slug;

        return $slug;
    }

    /* ---------------------------------------------------------------------
     * Poussée temps réel : signatures
     * ---------------------------------------------------------------------
     */

    /**
     * Action petitioner_submission_finalized : pousse la signature confirmée.
     *
     * Choix du point d'accroche : cet événement ne se déclenche qu'une fois
     * la signature vérifiée (directement, après double opt-in ou après
     * approbation manuelle) et il s'exécute hors requête AJAX grâce à la
     * planification interne du moteur. Les emails non confirmés ne sont donc
     * jamais poussés vers Actyl, et l'appel ne retient jamais le visiteur.
     *
     * @param object $submission Signataire (ligne hydratée par le moteur).
     * @param int    $form_id    Formulaire d'origine.
     * @return void
     */
    public function push_signature_from_submission(object $submission, int $form_id): void
    {
        if (!$this->is_active()) {
            return;
        }

        $campaign_slug = $this->resolve_campaign_slug($form_id);

        // Sans liaison explicite, aucune donnée ne quitte le site.
        if ("" === $campaign_slug) {
            return;
        }

        $email = sanitize_email((string) ($submission->email ?? ""));

        if ("" === $email) {
            return;
        }

        // Une signature d'organisation porte le nom de l'organisation dans
        // fname (normalisé par l'intégration Petitioner) : c'est donc lui
        // qui part naturellement comme nom d'affichage.
        $name = trim(
            sanitize_text_field((string) ($submission->fname ?? "")) .
            " " .
            sanitize_text_field((string) ($submission->lname ?? ""))
        );

        $payload = [
            "name" => "" !== $name ? $name : $email,
            "email" => $email,
            "city" => sanitize_text_field((string) ($submission->city ?? "")),
            "tags" => $this->signature_tags($form_id),
        ];

        // Champs vides exclus : l'API reçoit un corps minimal et net.
        $payload = array_filter($payload, static function ($value) {
            return "" !== $value && [] !== $value;
        });

        $this->push_signature($campaign_slug, $payload);
    }

    /**
     * Construit les balises de provenance : le slug de la pétition WordPress
     * permet de filtrer par source côté Actyl indépendamment du slug de
     * campagne distant.
     *
     * @param int $form_id Formulaire de la pétition.
     * @return array<int,string>
     */
    private function signature_tags(int $form_id): array
    {
        $wp_slug = (string) get_post_field("post_name", $form_id);

        return array_values(array_filter([
            "wordpress",
            "" !== $wp_slug ? $wp_slug : "petition-" . $form_id,
        ]));
    }

    /**
     * Envoie une signature vers la campagne Actyl et programme la tentative
     * unique de rattrapage en cas de panne transitoire.
     *
     * @param string              $campaign_slug Slug de campagne Actyl.
     * @param array<string,mixed> $payload       Corps de la requête.
     * @return bool Succès (2xx).
     */
    private function push_signature(string $campaign_slug, array $payload): bool
    {
        $path = "/api/v1/petitions/" . rawurlencode($campaign_slug) . "/signatures";
        $result = $this->request($path, "POST", $payload);

        if ($result["code"] >= 200 && $result["code"] < 300) {
            return true;
        }

        if ($this->should_retry($result["code"])) {
            $this->schedule_retry($path, $payload);
        }

        return false;
    }

    /**
     * Planifie une seule nouvelle tentative différée pour cet envoi (+10 min).
     *
     * Un verrou transient empêche l'empilement de tâches identiques si
     * plusieurs échecs surviennent au même moment ; l'API étant idempotente
     * par email, une exécution résiduelle reste sans effet de bord.
     *
     * @param string              $path    Chemin complet à rappeler.
     * @param array<string,mixed> $payload Corps original.
     * @return void
     */
    private function schedule_retry(string $path, array $payload): void
    {
        $task = ["path" => $path, "body" => $payload];
        $lock_key = "plaidact_actyl_lock_" . md5((string) wp_json_encode($task));

        if (get_transient($lock_key)) {
            return;
        }

        set_transient($lock_key, 1, self::RETRY_DELAY + 120);

        wp_schedule_single_event(time() + self::RETRY_DELAY, self::CRON_RETRY, [$task]);
    }

    /**
     * Tâche planifiée : rejoue un envoi en attente.
     *
     * La tâche embarque le chemin et le corps complets : le rappel ne dépend
     * plus de la résolution du slug ni des réglages de pétition. La tentative
     * unique est consommée quoi qu'il arrive (succès, 4xx définitif, ou nouvel
     * échec transitoire assumé — un rattrapage global peut alors compléter).
     *
     * @param mixed $task Tâche planifiée {path:string, body:array}.
     * @return void
     */
    public function run_retry_push($task = []): void
    {
        if (!$this->is_active() || !is_array($task) || empty($task["path"])) {
            return;
        }

        $lock_key = "plaidact_actyl_lock_" . md5((string) wp_json_encode($task));

        $this->request((string) $task["path"], "POST", (array) ($task["body"] ?? []));

        delete_transient($lock_key);
    }

    /* ---------------------------------------------------------------------
     * Poussée temps réel : soutiens (newsletter) et dons
     * ---------------------------------------------------------------------
     */

    /**
     * Action plaidact_newsletter_subscribed : pousse l'inscription newsletter
     * comme soutien. Politique identique aux signatures : silencieux, non
     * bloquant, journalisé. Pas de relance automatique : une nouvelle
     * inscription du même email met le contact à jour (API idempotente).
     *
     * @param string      $email    Adresse inscrite.
     * @param string      $name     Nom saisi (peut être vide).
     * @param string|null $language Langue courante (non transmise : hors contrat API).
     * @return void
     */
    public function push_supporter_from_newsletter(string $email, string $name, ?string $language = null): void
    {
        unset($language);

        if (!$this->is_active()) {
            return;
        }

        $email = sanitize_email($email);

        if ("" === $email) {
            return;
        }

        $payload = [
            "email" => $email,
            "source" => "newsletter",
            "category" => "SUPPORTER",
            "tags" => ["newsletter-site"],
        ];

        $full_name = sanitize_text_field($name);

        if ("" !== $full_name) {
            $payload["fullName"] = $full_name;
        }

        $this->push_supporter($payload);
    }

    /**
     * Envoie un soutien vers /api/v1/supporters.
     *
     * @param array<string,mixed> $payload Corps conforme au contrat.
     * @return bool Succès (2xx).
     */
    private function push_supporter(array $payload): bool
    {
        $result = $this->request("/api/v1/supporters", "POST", $payload);

        return $result["code"] >= 200 && $result["code"] < 300;
    }

    /**
     * Enregistre un don confirmé vers /api/v1/donations.
     *
     * Givoly ne permet pas de capture fiable côté serveur depuis WordPress :
     * cette méthode est mise à disposition des modules qui obtiennent la
     * confirmation autrement (retour de passerelle, import, webhook interne).
     * Elle n'est JAMAIS appelée automatiquement par ce plugin afin de ne pas
     * simuler un suivi de dons inexistant. Deux usages :
     *
     *     do_action("plaidact_actyl_record_donation", [
     *         "email"        => "donateur@exemple.fr",
     *         "full_name"    => "Jean Martin",           // facultatif
     *         "amount"       => 50,                      // unités, ou :
     *         "amount_cents" => 5000,                    // prioritaire si présent
     *         "label"        => "Don campagne zones humides",
     *         "occurred_at"  => "2026-08-24T12:00:00Z",  // défaut : maintenant
     *         "provider"     => "givoly",                // défaut
     *     ]);
     *
     *     PLAIDACT_Actyl::init()->record_donation($args);
     *
     * @param array<string,mixed> $args Arguments du don.
     * @return bool Succès (2xx), false si inactif, incomplet ou en échec.
     */
    public function record_donation(array $args): bool
    {
        if (!$this->is_active()) {
            return false;
        }

        $email = sanitize_email((string) ($args["email"] ?? ""));
        $amount_cents = isset($args["amount_cents"]) ? absint($args["amount_cents"]) : 0;
        $amount = isset($args["amount"]) ? (float) $args["amount"] : 0.0;

        if ("" === $email || ($amount_cents <= 0 && $amount <= 0)) {
            return false;
        }

        $payload = [
            "email" => $email,
            "provider" => sanitize_key((string) ($args["provider"] ?? "givoly")),
            "label" => sanitize_text_field((string) ($args["label"] ?? "")),
            "occurredAt" => (string) ($args["occurred_at"] ?? gmdate("Y-m-d\TH:i:s\Z")),
        ];

        $full_name = sanitize_text_field((string) ($args["full_name"] ?? ""));

        if ("" !== $full_name) {
            $payload["fullName"] = $full_name;
        }

        if ($amount_cents > 0) {
            $payload["amountCents"] = $amount_cents;
        } else {
            $payload["amount"] = (int) round($amount);
        }

        $result = $this->request("/api/v1/donations", "POST", $payload);

        return $result["code"] >= 200 && $result["code"] < 300;
    }

    /* ---------------------------------------------------------------------
     * Rattrapage (backfill) des signatures existantes
     * ---------------------------------------------------------------------
     */

    /**
     * Formulaires ciblés par le rattrapage.
     *
     * @param int $petition_filter Identifiant de pétition (0 = toutes).
     * @return array<int> Liste vide = toutes les signatures du site.
     */
    private function backfill_form_ids(int $petition_filter): array
    {
        if ($petition_filter <= 0 || !class_exists("AV_Petitioner_Submissions_Model")) {
            return [];
        }

        return \Plaidact\CampaignCore\Shortcodes::get_linked_petitioner_form_ids($petition_filter);
    }

    /**
     * Compte total et restant pour l'affichage de progression.
     *
     * Le « total » désigne toutes les lignes du périmètre (traitées ou non) :
     * la progression affichée est donc stable d'un lot à l'autre.
     *
     * @param array<int> $form_ids Formulaires ciblés (vide = tout).
     * @return array{total:int, remaining:int}
     */
    private function backfill_counts(array $form_ids = []): array
    {
        global $wpdb;

        if (!class_exists("AV_Petitioner_Submissions_Model")) {
            return ["total" => 0, "remaining" => 0];
        }

        $table = \AV_Petitioner_Submissions_Model::table_name();
        $cursor = (int) get_option(self::OPTION_BACKFILL_CURSOR, 0);

        $filter_sql = "";
        $params = [];

        if ([] !== $form_ids) {
            $filter_sql = " AND form_id IN (" . implode(",", array_fill(0, count($form_ids), "%d")) . ")";
            $params = array_map("absint", $form_ids);
        }

        $total = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE id >= 0{$filter_sql}", $params)
        );

        $remaining = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE id > %d{$filter_sql}",
                array_merge([$cursor], $params)
            )
        );

        return [
            "total" => $total,
            "remaining" => $remaining,
        ];
    }

    /**
     * Traite UN lot de signatures : lit les lignes suivantes après le curseur,
     * pousse chacune vers la campagne liée, avance le curseur quoi qu'il arrive.
     *
     * Le curseur avance systématiquement au-delà de la dernière ligne lue :
     * une ligne sans email ou sans campagne liée ne doit jamais bloquer la
     * progression. Rejouer un lot reste sûr, l'API étant idempotente par email.
     *
     * Aucune relance planifiée ici : le curseur constitue déjà le mécanisme de
     * reprise, et ce traitement n'est jamais déclenché pendant une requête
     * visiteur ni pendant les imports existants.
     *
     * @param int $petition_filter Identifiant de pétition (0 = toutes).
     * @return array{processed:int, pushed:int, skipped:int, done:bool}
     */
    public function process_backfill_batch(int $petition_filter = 0): array
    {
        global $wpdb;

        $result = ["processed" => 0, "pushed" => 0, "skipped" => 0, "done" => true];

        if (!class_exists("AV_Petitioner_Submissions_Model") || !$this->is_active()) {
            return $result;
        }

        $form_ids = $this->backfill_form_ids($petition_filter);
        $table = \AV_Petitioner_Submissions_Model::table_name();
        $cursor = (int) get_option(self::OPTION_BACKFILL_CURSOR, 0);

        $filter_sql = "";
        $params = [$cursor];

        if ([] !== $form_ids) {
            $filter_sql = " AND form_id IN (" . implode(",", array_fill(0, count($form_ids), "%d")) . ")";
            $params = array_merge($params, array_map("absint", $form_ids));
        }

        // Ordre stable par identifiant : le curseur garantit une reprise exacte.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, form_id, fname, lname, email, city
                 FROM {$table}
                 WHERE id > %d{$filter_sql}
                 ORDER BY id ASC
                 LIMIT %d",
                array_merge($params, [self::BACKFILL_BATCH_SIZE])
            )
        );

        $rows = is_array($rows) ? $rows : [];

        if ([] === $rows) {
            return $result;
        }

        foreach ($rows as $row) {
            // Pause entre chaque envoi pour rester sous la limite de débit API,
            // y compris en exécution continue côté ligne de commande.
            usleep(self::BACKFILL_PAUSE_MICROS);

            $email = sanitize_email((string) ($row->email ?? ""));
            $campaign_slug = "" !== $email
                ? $this->resolve_campaign_slug((int) ($row->form_id ?? 0))
                : "";

            if ("" === $email || "" === $campaign_slug) {
                // Ligne non synchronisable : comptée et contournée, jamais bloquante.
                $result["skipped"]++;
                $result["processed"]++;
                continue;
            }

            $name = trim(
                sanitize_text_field((string) ($row->fname ?? "")) .
                " " .
                sanitize_text_field((string) ($row->lname ?? ""))
            );

            $payload = [
                "name" => "" !== $name ? $name : $email,
                "email" => $email,
                "city" => sanitize_text_field((string) ($row->city ?? "")),
                "tags" => $this->signature_tags((int) ($row->form_id ?? 0)),
            ];

            // Champs vides exclus : corps minimal et net.
            $payload = array_filter($payload, static function ($value) {
                return "" !== $value && [] !== $value;
            });

            $path = "/api/v1/petitions/" . rawurlencode($campaign_slug) . "/signatures";
            $push = $this->request($path, "POST", $payload);

            $result["processed"]++;

            if ($push["code"] >= 200 && $push["code"] < 300) {
                $result["pushed"]++;
            } else {
                $result["skipped"]++;
            }
        }

        $last_row = end($rows);
        update_option(self::OPTION_BACKFILL_CURSOR, (int) ($last_row->id ?? $cursor), false);

        $result["done"] = count($rows) < self::BACKFILL_BATCH_SIZE;

        return $result;
    }

    /**
     * Handler web du rattrapage : traite un lot puis revient sur la page de
     * réglages avec la progression à jour. Un lot par requête évite tout
     * dépassement de temps d'exécution PHP, quelle que soit la taille de la base.
     *
     * @return void
     */
    public function handle_backfill_batch(): void
    {
        if (!current_user_can("manage_options")) {
            wp_die(esc_html__("Accès refusé.", "plaidact-campaign-core"));
        }

        check_admin_referer("plaidact_actyl_backfill");

        if (!$this->is_active()) {
            wp_safe_redirect(add_query_arg("actyl_backfill", "inactive", $this->settings_page_url()));
            exit;
        }

        $petition_filter = isset($_POST["petition_id"]) ? absint($_POST["petition_id"]) : 0;

        // « Recommencer depuis zéro » rembobine le curseur ; sinon reprise là
        // où la précédente exécution s'est arrêtée.
        if (isset($_POST["reset"])) {
            delete_option(self::OPTION_BACKFILL_CURSOR);
        }

        $stats = $this->process_backfill_batch($petition_filter);

        wp_safe_redirect(add_query_arg(
            [
                "actyl_backfill" => $stats["done"] ? "done" : "running",
                "ab_pushed" => $stats["pushed"],
                "ab_skipped" => $stats["skipped"],
                "ab_petition" => $petition_filter,
            ],
            $this->settings_page_url()
        ));
        exit;
    }

    /**
     * Commande WP-CLI équivalente au bouton de rattrapage :
     *
     *     wp plaidact actyl-backfill
     *     wp plaidact actyl-backfill --petition=12
     *     wp plaidact actyl-backfill --reset=1
     *
     * Boucle sur les lots jusqu'à épuisement, avec progression affichée.
     *
     * @param mixed               $args       Arguments positionnels (non utilisés).
     * @param array<string,mixed> $assoc_args Arguments nommés.
     * @return void
     */
    public function cli_backfill($args = [], $assoc_args = []): void
    {
        if (!$this->is_active()) {
            \WP_CLI::error(
                "Synchronisation inactive : renseignez URL et token, cochez l'activation puis réussissez un test de connexion."
            );
        }

        $petition_filter = isset($assoc_args["petition"]) ? absint($assoc_args["petition"]) : 0;

        if (!empty($assoc_args["reset"])) {
            delete_option(self::OPTION_BACKFILL_CURSOR);
            \WP_CLI::log("Curseur de rattrapage réinitialisé.");
        }

        $counts = $this->backfill_counts($this->backfill_form_ids($petition_filter));
        \WP_CLI::log(sprintf(
            "Rattrapage : %d signatures restantes sur %d au total.",
            $counts["remaining"],
            $counts["total"]
        ));

        $total_pushed = 0;
        $total_skipped = 0;

        while (true) {
            $batch = $this->process_backfill_batch($petition_filter);

            $total_pushed += $batch["pushed"];
            $total_skipped += $batch["skipped"];

            $progress = $this->backfill_counts($this->backfill_form_ids($petition_filter));
            \WP_CLI::log(sprintf(
                "Lot traité : +%d envoyées, %d ignorées — %d restantes.",
                $batch["pushed"],
                $batch["skipped"],
                $progress["remaining"]
            ));

            if ($batch["done"]) {
                break;
            }
        }

        \WP_CLI::success(sprintf(
            "Rattrapage terminé : %d poussées vers Actyl, %d ignorées.",
            $total_pushed,
            $total_skipped
        ));
    }

    /* ---------------------------------------------------------------------
     * Rendu de la section « Connexion Actyl » dans Réglages → PLAID·ACT
     * ---------------------------------------------------------------------
     */

    /**
     * Affiche la section complète : configuration, statut, test de connexion,
     * rattrapage et journal. Formulaire séparé du formulaire principal de la
     * page (option dédiée), donc aucune interférence avec les réglages
     * existants ni avec leur nettoyage.
     *
     * @return void
     */
    public function render_connection_section(): void
    {
        if (!current_user_can("manage_options")) {
            return;
        }

        $settings = $this->get_settings();
        $active = $this->is_active();
        $configured = $this->is_configured();
        $ping_at = (int) get_option(self::OPTION_PING_OK_AT, 0);
        ?>
        <hr />
        <h2 id="actyl"><?php esc_html_e("Connexion Actyl", "plaidact-campaign-core"); ?></h2>
        <p><?php esc_html_e(
            "Pousse en temps réel les signatures confirmées et les inscriptions newsletter vers votre instance Actyl. La synchronisation ne démarre qu’après un test de connexion réussi.",
            "plaidact-campaign-core"
        ); ?></p>

        <?php $this->render_section_notices(); ?>

        <form method="post" action="<?php echo esc_url(admin_url("options.php")); ?>">
            <?php settings_fields(self::OPTION_SETTINGS); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="plaidact_actyl_url"><?php esc_html_e("URL de l’instance Actyl", "plaidact-campaign-core"); ?></label></th>
                    <td>
                        <input type="url" id="plaidact_actyl_url" name="plaidact_actyl_settings[actyl_url]" value="<?php echo esc_attr((string) $settings["actyl_url"]); ?>" class="regular-text" placeholder="https://mon-instance.vercel.app" />
                        <p class="description"><?php esc_html_e("HTTPS uniquement, sans slash final.", "plaidact-campaign-core"); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="plaidact_actyl_token"><?php esc_html_e("Token API", "plaidact-campaign-core"); ?></label></th>
                    <td>
                        <input type="password" id="plaidact_actyl_token" name="plaidact_actyl_settings[actyl_api_token]" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo esc_attr("" !== (string) $settings["actyl_api_token"] ? "•••••••••••••••• (token enregistré)" : "actyl_…"); ?>" />
                        <button type="button" class="button button-small" id="plaidact_actyl_token_toggle"><?php esc_html_e("Remplacer", "plaidact-campaign-core"); ?></button>
                        <p class="description"><?php esc_html_e(
                            "Token créé dans Actyl → Réglages. Jamais affiché : laissez vide pour conserver la valeur enregistrée, cliquez sur « Remplacer » pour en saisir un nouveau.",
                            "plaidact-campaign-core"
                        ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e("Synchronisation", "plaidact-campaign-core"); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="plaidact_actyl_settings[actyl_enabled]" value="1" <?php checked((string) $settings["actyl_enabled"], "1"); ?> />
                            <?php esc_html_e("Activer la synchronisation", "plaidact-campaign-core"); ?>
                        </label>
                        <p class="description"><?php esc_html_e(
                            "Aucun envoi tant qu’un test de connexion n’a pas réussi ; modifier l’URL ou le token exige un nouveau test.",
                            "plaidact-campaign-core"
                        ); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(__("Enregistrer la connexion Actyl", "plaidact-campaign-core"), "primary", "submit", false); ?>
        </form>

        <p>
            <strong><?php esc_html_e("État :", "plaidact-campaign-core"); ?></strong>
            <?php if ($active) : ?>
                <span style="color:#00a32a;font-weight:600;"><?php esc_html_e("Synchro active", "plaidact-campaign-core"); ?></span>
                — <?php echo esc_html(sprintf(
                    /* translators: %s: date/heure du dernier ping réussi */
                    __("dernière validation le %s", "plaidact-campaign-core"),
                    wp_date("d/m/Y H:i", $ping_at)
                )); ?>
            <?php elseif ($configured) : ?>
                <span style="color:#dba617;font-weight:600;"><?php esc_html_e("Configurée mais inactive", "plaidact-campaign-core"); ?></span>
                — <?php esc_html_e("lancez un test de connexion puis enregistrez avec l’activation cochée.", "plaidact-campaign-core"); ?>
            <?php else : ?>
                <span style="color:#646970;font-weight:600;"><?php esc_html_e("Non configurée", "plaidact-campaign-core"); ?></span>
            <?php endif; ?>
        </p>

        <p>
            <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(
                admin_url("admin-post.php?action=plaidact_actyl_test_connection"),
                "plaidact_actyl_test_connection"
            )); ?>"><?php esc_html_e("Tester la connexion", "plaidact-campaign-core"); ?></a>
        </p>

        <h3><?php esc_html_e("Rattrapage des signatures existantes", "plaidact-campaign-core"); ?></h3>
        <?php $this->render_backfill_box(); ?>

        <h3><?php esc_html_e("Journal de synchronisation (100 derniers événements)", "plaidact-campaign-core"); ?></h3>
        <?php $this->render_log_table(); ?>

        <script>
        /* Bascule de saisie du token : ne révèle jamais la valeur stockée,
           permet seulement de saisir un nouveau secret à la place. */
        (function () {
            var toggle = document.getElementById("plaidact_actyl_token_toggle");
            var input = document.getElementById("plaidact_actyl_token");

            if (!toggle || !input) {
                return;
            }

            toggle.addEventListener("click", function () {
                input.type = "password" === input.type ? "text" : "password";
                input.value = "";
                input.focus();
            });
        })();
        </script>
        <?php
    }

    /**
     * Messages de retour inline : test de connexion, journal vidé, avancement
     * du rattrapage. Tout ce qui vient de l'URL est nettoyé avant affichage.
     *
     * @return void
     */
    private function render_section_notices(): void
    {
        if (isset($_GET["actyl_test"])) {
            $test = sanitize_key(wp_unslash($_GET["actyl_test"]));

            if ("ok" === $test) {
                printf(
                    '<div class="notice notice-success inline"><p>%s</p></div>',
                    esc_html__("Connexion Actyl validée : la synchronisation est opérationnelle.", "plaidact-campaign-core")
                );
            } elseif ("missing" === $test) {
                printf(
                    '<div class="notice notice-warning inline"><p>%s</p></div>',
                    esc_html__("Renseignez d’abord l’URL et le token.", "plaidact-campaign-core")
                );
            } elseif ("fail" === $test) {
                printf(
                    '<div class="notice notice-error inline"><p>%s</p></div>',
                    esc_html(sprintf(
                        /* translators: %s: code HTTP ou message d'échec réseau */
                        __("Test échoué (%s). Vérifiez l’URL, le token et que l’instance est en ligne — détail dans le journal ci-dessous.", "plaidact-campaign-core"),
                        $this->last_failure_label()
                    ))
                );
            }
        }

        if (isset($_GET["actyl_log_cleared"])) {
            printf(
                '<div class="notice notice-success inline"><p>%s</p></div>',
                esc_html__("Journal vidé.", "plaidact-campaign-core")
            );
        }

        if (!isset($_GET["actyl_backfill"])) {
            return;
        }

        $backfill_state = sanitize_key(wp_unslash($_GET["actyl_backfill"]));
        $pushed = absint($_GET["ab_pushed"] ?? 0);
        $skipped = absint($_GET["ab_skipped"] ?? 0);

        if ("done" === $backfill_state) {
            printf(
                '<div class="notice notice-success inline"><p>%s</p></div>',
                esc_html(sprintf(
                    /* translators: 1: poussées, 2: ignorées */
                    __("Rattrapage terminé : %1$d signature(s) poussée(s), %2$d ignorée(s).", "plaidact-campaign-core"),
                    $pushed,
                    $skipped
                ))
            );
        } elseif ("running" === $backfill_state) {
            printf(
                '<div class="notice notice-info inline"><p>%s</p></div>',
                esc_html(sprintf(
                    /* translators: 1: poussées, 2: ignorées */
                    __("Lot traité : %1$d poussée(s), %2$d ignorée(s). Le lot suivant démarre automatiquement…", "plaidact-campaign-core"),
                    $pushed,
                    $skipped
                ))
            );
        } elseif ("inactive" === $backfill_state) {
            printf(
                '<div class="notice notice-error inline"><p>%s</p></div>',
                esc_html__("Synchronisation inactive : validez d’abord la connexion.", "plaidact-campaign-core")
            );
        }
    }

    /**
     * Libellé du dernier échec du journal, affiché après un test raté.
     *
     * @return string
     */
    private function last_failure_label(): string
    {
        foreach ($this->get_log() as $entry) {
            $code = (int) ($entry["code"] ?? 0);

            if ($code < 200 || $code >= 300) {
                return $code > 0
                    ? sprintf("HTTP %d", $code)
                    : (string) ($entry["message"] ?? __("erreur réseau", "plaidact-campaign-core"));
            }
        }

        return __("réponse invalide", "plaidact-campaign-core");
    }

    /**
     * Encart de rattrapage : progression courante, bouton démarrer/reprendre,
     * relance automatique du lot suivant tant que des lignes restent à traiter.
     *
     * @return void
     */
    private function render_backfill_box(): void
    {
        $petition_filter = isset($_GET["ab_petition"]) ? absint(wp_unslash($_GET["ab_petition"])) : 0;
        $counts = $this->backfill_counts($this->backfill_form_ids($petition_filter));
        $processed = max(0, $counts["total"] - $counts["remaining"]);
        $percent = $counts["total"] > 0 ? (int) round(($processed / $counts["total"]) * 100) : 100;
        $running = isset($_GET["actyl_backfill"])
            && "running" === sanitize_key(wp_unslash($_GET["actyl_backfill"]));
        ?>
        <p>
            <?php echo esc_html(sprintf(
                /* translators: 1: parcourues, 2: total */
                __("%1$d / %2$d signatures parcourues.", "plaidact-campaign-core"),
                $processed,
                $counts["total"]
            )); ?>
            <?php esc_html_e("Les lignes sans campagne liée ou sans email sont ignorées ; rejouer un lot est sans risque (API idempotente).", "plaidact-campaign-core"); ?>
        </p>

        <div style="background:#f0f0f1;border-radius:4px;height:20px;max-width:520px;overflow:hidden;">
            <div style="background:#3858e9;height:100%;width:<?php echo esc_attr((string) $percent); ?>%;"></div>
        </div>

        <form method="post" action="<?php echo esc_url(admin_url("admin-post.php")); ?>" style="margin-top:10px;">
            <?php wp_nonce_field("plaidact_actyl_backfill"); ?>
            <input type="hidden" name="action" value="plaidact_actyl_backfill_batch" />
            <input type="hidden" name="petition_id" value="<?php echo esc_attr((string) $petition_filter); ?>" />

            <button
                type="submit"
                class="button button-secondary"
                id="plaidact_actyl_backfill_go"
                data-running="<?php echo esc_attr($running ? "1" : "0"); ?>"
            >
                <?php echo $processed > 0
                    ? esc_html__("Reprendre la synchronisation", "plaidact-campaign-core")
                    : esc_html__("Synchroniser les signatures existantes", "plaidact-campaign-core"); ?>
            </button>

            <?php if ($processed > 0) : ?>
                <button type="submit" name="reset" value="1" class="button-link" style="margin-left:12px;color:#b32d2e;">
                    <?php esc_html_e("Recommencer depuis zéro", "plaidact-campaign-core"); ?>
                </button>
            <?php endif; ?>
        </form>

        <?php if ($running && $counts["remaining"] > 0) : ?>
        <!-- Relance automatique : soumet le même formulaire après une courte
             pause pour enchaîner les lots sans clic supplémentaire. -->
        <script>
        (function () {
            var button = document.getElementById("plaidact_actyl_backfill_go");

            if (button && button.form) {
                window.setTimeout(function () {
                    button.form.submit();
                }, 800);
            }
        })();
        </script>
        <?php endif; ?>
        <?php
    }

    /**
     * Table du journal de synchronisation, du plus récent au plus ancien.
     *
     * @return void
     */
    private function render_log_table(): void
    {
        $log = $this->get_log();
        ?>
        <table class="widefat striped" style="max-width:860px;">
            <thead><tr>
                <th style="width:150px;"><?php esc_html_e("Horodatage", "plaidact-campaign-core"); ?></th>
                <th><?php esc_html_e("Endpoint", "plaidact-campaign-core"); ?></th>
                <th style="width:90px;"><?php esc_html_e("Code HTTP", "plaidact-campaign-core"); ?></th>
                <th><?php esc_html_e("Détail", "plaidact-campaign-core"); ?></th>
            </tr></thead>
            <tbody>
            <?php if ([] === $log) : ?>
                <tr><td colspan="4"><?php esc_html_e("Aucun événement pour le moment.", "plaidact-campaign-core"); ?></td></tr>
            <?php else : ?>
                <?php foreach ($log as $entry) : ?>
                    <?php $code = (int) ($entry["code"] ?? 0); ?>
                    <tr>
                        <td><?php echo esc_html((string) ($entry["time"] ?? "")); ?></td>
                        <td><?php echo esc_html((string) ($entry["endpoint"] ?? "")); ?></td>
                        <td><?php echo esc_html($code > 0 ? (string) $code : "—"); ?></td>
                        <td><?php echo esc_html((string) ($entry["message"] ?? "")); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <p style="margin-top:8px;">
            <a class="button-link" style="color:#b32d2e;" href="<?php echo esc_url(wp_nonce_url(
                admin_url("admin-post.php?action=plaidact_actyl_clear_log"),
                "plaidact_actyl_clear_log"
            )); ?>"><?php esc_html_e("Vider le journal", "plaidact-campaign-core"); ?></a>
        </p>
        <?php
    }
}
