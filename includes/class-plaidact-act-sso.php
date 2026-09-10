<?php
/**
 * Connexion unique via Act (fournisseur d'identité OIDC).
 *
 * Act, déployé sur https://act.plaidact.org, authentifie les utilisateurs ;
 * WordPress suit : un bouton « Se connecter avec Act » initie un flux
 * OIDC Authorization Code + PKCE, puis le rappel provisionne le compte
 * WordPress de façon strictement additive (liaison par `sub` Act, puis par
 * email, création avec le rôle mappé, sans jamais écraser l'existant).
 *
 * Garanties fondamentales :
 * - désactivé par défaut : aucun bouton ni point d'entrée actif tant que le
 *   module `enable_sso` n'est pas coché et que le client n'est pas configuré ;
 * - aucun secret dans le code : identifiants dans l'option dédiée uniquement ;
 * - pas de bibliothèque externe : échanges via `wp_remote_*`, découverte OIDC
 *   mise en cache, validation par l'appel `userinfo` côté serveur.
 *
 * @package PLAIDACT\CampaignCore
 */

namespace Plaidact\CampaignCore;

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Client OIDC minimal dont Act est le fournisseur d'identité.
 */
final class Act_SSO
{
    /**
     * Option de configuration dédiée (séparée pour ne pas être écrasée).
     */
    private const OPTION_SETTINGS = "plaidact_sso_settings";

    /**
     * Métadonnée liant un utilisateur WordPress à son `sub` Act.
     */
    private const META_SUB = "_plaidact_act_sub";

    /**
     * Métadonnée d'horodatage de la dernière connexion via Act.
     */
    private const META_LAST_LOGIN = "_plaidact_act_last_login";

    /**
     * Préfixe des transients de session de connexion (état + PKCE).
     */
    private const STATE_PREFIX = "plaidact_sso_";

    /**
     * Durée de validité d'une session de connexion (secondes).
     */
    private const STATE_TTL = 600;

    /**
     * Durée de cache de la découverte OIDC (secondes).
     */
    private const DISCOVERY_TTL = 12 * HOUR_IN_SECONDS;

    /**
     * Transient de découverte OIDC.
     */
    private const DISCOVERY_TRANSIENT = "plaidact_sso_discovery";

    /**
     * URL de base d'Act par défaut.
     */
    private const DEFAULT_ISSUER = "https://act.plaidact.org";

    /**
     * Instance unique.
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

        self::$instance->register_hooks();

        return self::$instance;
    }

    /**
     * Enregistre les points d'entrée WordPress.
     *
     * @return void
     */
    private function register_hooks(): void
    {
        add_action("init", [__CLASS__, "register_strings"]);
        add_action("admin_init", [__CLASS__, "register_settings"]);
        add_action("plaidact_campaign_settings_page_end", [__CLASS__, "render_settings_section"]);

        // Lancement et rappel : accessibles aux visiteurs déconnectés.
        add_action("admin_post_nopriv_plaidact_act_sso_start", [__CLASS__, "handle_sso_start"]);
        add_action("admin_post_plaidact_act_sso_start", [__CLASS__, "handle_sso_start"]);
        add_action("admin_post_nopriv_plaidact_act_sso_callback", [__CLASS__, "handle_sso_callback"]);
        add_action("admin_post_plaidact_act_sso_callback", [__CLASS__, "handle_sso_callback"]);
        add_action("admin_post_plaidact_act_sso_discover", [__CLASS__, "handle_discover"]);

        // Bouton sur l'écran de connexion natif + shortcode public.
        add_action("login_form", [__CLASS__, "render_login_form_button"]);
        add_filter("login_message", [__CLASS__, "render_login_error"]);
        add_shortcode("plaidact_act_login", [__CLASS__, "render_act_login"]);

        // Autorise la redirection vers l'hôte Act configuré (wp_safe_redirect
        // refuse par défaut tout hôte externe, y compris en production).
        add_filter("allowed_redirect_hosts", [__CLASS__, "allow_act_host"]);
    }

    /* ---------------------------------------------------------------------
     * Configuration
     * ------------------------------------------------------------------ */

    /**
     * Valeurs par défaut (module désactivé, sans secret).
     *
     * @return array<string,string>
     */
    public static function get_default_sso_settings(): array
    {
        return [
            "issuer" => self::DEFAULT_ISSUER,
            "client_id" => "",
            "client_secret" => "",
            "roles_claim" => "roles",
            "role_map" => "admin=administrator\nediteur=editor\neditor=editor\nmembre=subscriber\nmember=subscriber",
            "require_verified_email" => "1",
            "sync_role_on_login" => "1",
            "button_label" => __("Se connecter avec Act", "plaidact-campaign-core"),
        ];
    }

    /**
     * Lit la configuration (sans cache statique pour suivre les tests).
     *
     * @return array<string,string>
     */
    public static function get_sso_settings(): array
    {
        return wp_parse_args(
            (array) get_option(self::OPTION_SETTINGS, []),
            self::get_default_sso_settings()
        );
    }

    /**
     * Déclare l'option avec son nettoyage dédié.
     *
     * @return void
     */
    public static function register_settings(): void
    {
        register_setting(
            self::OPTION_SETTINGS,
            self::OPTION_SETTINGS,
            ["sanitize_callback" => [__CLASS__, "sanitize_sso_settings"]]
        );
    }

    /**
     * Nettoie la configuration soumise.
     *
     * Règles de sécurité : émetteur en HTTPS strict, secret conservé quand le
     * champ est laissé vide, mappage de rôles limité aux rôles connus.
     *
     * @param mixed $input Valeurs brutes du formulaire.
     * @return array<string,string>
     */
    public static function sanitize_sso_settings($input): array
    {
        $input = is_array($input) ? $input : [];
        $existing = self::get_sso_settings();
        $clean = self::get_default_sso_settings();

        $raw_issuer = isset($input["issuer"]) ? trim((string) wp_unslash($input["issuer"])) : "";
        if ("" !== $raw_issuer) {
            $issuer = esc_url_raw($raw_issuer);
            $scheme = "" !== $issuer ? (string) wp_parse_url($issuer, PHP_URL_SCHEME) : "";
            $host = strtolower((string) wp_parse_url($issuer, PHP_URL_HOST));

            // Exception strictement locale (jamais en production) : autorise
            // http://localhost le temps des essais avec le mock de
            // développement, uniquement via le filtre dédié.
            $allow_insecure = in_array($host, ["localhost", "127.0.0.1", "::1"], true)
                && (bool) apply_filters("plaidact_act_sso_allow_insecure_issuer", false);

            if ("https" !== $scheme && !$allow_insecure) {
                add_settings_error(
                    self::OPTION_SETTINGS,
                    "sso_issuer",
                    __("L’URL Act doit commencer par https://", "plaidact-campaign-core")
                );
                $clean["issuer"] = (string) $existing["issuer"];
            } else {
                $clean["issuer"] = untrailingslashit($issuer);
            }
        } else {
            $clean["issuer"] = self::DEFAULT_ISSUER;
        }

        $clean["client_id"] = sanitize_text_field((string) ($input["client_id"] ?? ""));

        // Champ secret : vide = conservation de la valeur enregistrée.
        $raw_secret = isset($input["client_secret"]) ? trim((string) wp_unslash($input["client_secret"])) : "";
        $clean["client_secret"] = "" !== $raw_secret
            ? sanitize_text_field($raw_secret)
            : (string) $existing["client_secret"];

        $clean["roles_claim"] = sanitize_key((string) ($input["roles_claim"] ?? "roles"));
        if ("" === $clean["roles_claim"]) {
            $clean["roles_claim"] = "roles";
        }

        $clean["role_map"] = sanitize_textarea_field((string) ($input["role_map"] ?? ""));
        $clean["require_verified_email"] = !empty($input["require_verified_email"]) ? "1" : "0";
        $clean["sync_role_on_login"] = !empty($input["sync_role_on_login"]) ? "1" : "0";
        $clean["button_label"] = sanitize_text_field((string) ($input["button_label"] ?? ""));

        if ("" === $clean["button_label"]) {
            $clean["button_label"] = (string) self::get_default_sso_settings()["button_label"];
        }

        return $clean;
    }

    /**
     * Indique si le module est actif (toggle + client configuré).
     *
     * @return bool
     */
    public static function is_sso_enabled(): bool
    {
        if (class_exists(Shortcodes::class) && !Shortcodes::is_module_enabled("enable_sso")) {
            return false;
        }

        $settings = self::get_sso_settings();

        return "" !== trim((string) ($settings["client_id"] ?? ""));
    }

    /**
     * Ajoute l'hôte Act aux redirections autorisées.
     *
     * Sans cela, `wp_safe_redirect()` refuserait la redirection vers
     * l'autorisation Act (hôte externe) et retomberait sur l'admin.
     *
     * @param array<int,string> $hosts Hôtes déjà autorisés.
     * @return array<int,string>
     */
    public static function allow_act_host(array $hosts): array
    {
        $settings = self::get_sso_settings();
        $host = strtolower((string) wp_parse_url(trim((string) ($settings["issuer"] ?? "")), PHP_URL_HOST));

        if ("" !== $host && !in_array($host, $hosts, true)) {
            $hosts[] = $host;
        }

        return $hosts;
    }

    /* ---------------------------------------------------------------------
     * Découverte OIDC
     * ------------------------------------------------------------------ */

    /**
     * Résout les points d'accès OIDC d'Act (découverte mise en cache).
     *
     * Le filtre `plaidact_act_sso_discovery` permet aux tests et aux
     * intégrations de fournir les points d'accès sans appel réseau.
     *
     * @return array{authorization_endpoint:string,token_endpoint:string,userinfo_endpoint:string,end_session_endpoint:string}
     */
    public static function get_discovery(): array
    {
        $empty = [
            "authorization_endpoint" => "",
            "token_endpoint" => "",
            "userinfo_endpoint" => "",
            "end_session_endpoint" => "",
        ];

        $pre = apply_filters("plaidact_act_sso_discovery", null);

        if (is_array($pre)) {
            return array_merge($empty, $pre);
        }

        $cached = get_transient(self::DISCOVERY_TRANSIENT);

        if (is_array($cached) && !empty($cached["authorization_endpoint"])) {
            return array_merge($empty, $cached);
        }

        $settings = self::get_sso_settings();
        $issuer = untrailingslashit(trim((string) ($settings["issuer"] ?? "")));

        if ("" === $issuer) {
            return $empty;
        }

        $response = wp_remote_get($issuer . "/.well-known/openid-configuration", ["timeout" => 15]);

        if (is_wp_error($response)) {
            return $empty;
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);

        if (!is_array($data)) {
            return $empty;
        }

        $discovery = [
            "authorization_endpoint" => esc_url_raw((string) ($data["authorization_endpoint"] ?? "")),
            "token_endpoint" => esc_url_raw((string) ($data["token_endpoint"] ?? "")),
            "userinfo_endpoint" => esc_url_raw((string) ($data["userinfo_endpoint"] ?? "")),
            "end_session_endpoint" => esc_url_raw((string) ($data["end_session_endpoint"] ?? "")),
        ];

        if (
            "" === $discovery["authorization_endpoint"]
            || "" === $discovery["token_endpoint"]
            || "" === $discovery["userinfo_endpoint"]
        ) {
            return $empty;
        }

        set_transient(self::DISCOVERY_TRANSIENT, $discovery, self::DISCOVERY_TTL);

        return $discovery;
    }

    /**
     * URL de rappel à enregistrer comme `redirect_uri` côté Act.
     *
     * @return string
     */
    public static function get_redirect_uri(): string
    {
        return add_query_arg(
            "action",
            "plaidact_act_sso_callback",
            admin_url("admin-post.php")
        );
    }

    /* ---------------------------------------------------------------------
     * PKCE et URL d'autorisation (fonctions pures, testées)
     * ------------------------------------------------------------------ */

    /**
     * Dérive le défi PKCE S256 d'un vérifieur (RFC 7636).
     *
     * @param string $verifier Chaîne de 43 à 128 caractères non réservés.
     * @return string
     */
    public static function pkce_challenge(string $verifier): string
    {
        return rtrim(
            strtr(base64_encode(hash("sha256", $verifier, true)), "+/", "-_"),
            "="
        );
    }

    /**
     * Construit l'URL d'autorisation Act.
     *
     * @param array<string,string> $discovery Points d'accès OIDC.
     * @param string $client_id Identifiant client enregistré côté Act.
     * @param string $redirect_uri URL de rappel enregistrée côté Act.
     * @param string $state Jeton anti-CSRF à usage unique.
     * @param string $verifier Vérifieur PKCE conservé côté serveur.
     * @return string
     */
    public static function build_authorize_url(
        array $discovery,
        string $client_id,
        string $redirect_uri,
        string $state,
        string $verifier
    ): string {
        return add_query_arg(
            [
                "response_type" => "code",
                "client_id" => $client_id,
                "redirect_uri" => $redirect_uri,
                "scope" => "openid email profile",
                "state" => $state,
                "code_challenge" => self::pkce_challenge($verifier),
                "code_challenge_method" => "S256",
            ],
            (string) ($discovery["authorization_endpoint"] ?? "")
        );
    }

    /* ---------------------------------------------------------------------
     * Rôles
     * ------------------------------------------------------------------ */

    /**
     * Convertit le mappage texte `role_act=role_wp` en tableau.
     *
     * Seules les paires dont le rôle WordPress existe sont conservées.
     *
     * @param string $raw Contenu brut du champ de mappage.
     * @return array<string,string>
     */
    public static function parse_role_map_text(string $raw): array
    {
        $map = [];

        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim((string) $line);

            if ("" === $line || str_starts_with($line, "#")) {
                continue;
            }

            $parts = array_map("trim", explode("=", $line, 2));

            if (2 !== count($parts) || "" === $parts[0] || "" === $parts[1]) {
                continue;
            }

            $wp_role = sanitize_key($parts[1]);

            if (self::is_valid_wp_role($wp_role)) {
                $map[mb_strtolower($parts[0])] = $wp_role;
            }
        }

        /**
         * Filtre le mappage des rôles Act vers les rôles WordPress.
         *
         * @param array<string,string> $map Mappage `role_act` => `role_wp`.
         */
        return (array) apply_filters("plaidact_act_sso_role_map", $map);
    }

    /**
     * Vérifie qu'un rôle WordPress existe sur le site.
     *
     * @param string $role Slug du rôle.
     * @return bool
     */
    private static function is_valid_wp_role(string $role): bool
    {
        global $wp_roles;

        if ($wp_roles instanceof \WP_Roles) {
            return (bool) $wp_roles->is_role($role);
        }

        return in_array($role, ["administrator", "editor", "author", "contributor", "subscriber"], true);
    }

    /**
     * Indique si un rôle dispose du droit d'administration.
     *
     * @param string $role Slug du rôle.
     * @return bool
     */
    private static function role_can_manage_options(string $role): bool
    {
        $role_object = get_role($role);

        return $role_object instanceof \WP_Role && $role_object->has_cap("manage_options");
    }

    /**
     * Déduit le rôle WordPress depuis les rôles Act (repli abonné).
     *
     * @param array<int|string,mixed> $act_roles Rôles bruts fournis par Act.
     * @return string
     */
    public static function map_act_roles_to_wp_role(array $act_roles): string
    {
        $settings = self::get_sso_settings();
        $map = self::parse_role_map_text((string) ($settings["role_map"] ?? ""));

        foreach ($act_roles as $act_role) {
            $key = mb_strtolower(trim((string) $act_role));

            if ("" !== $key && isset($map[$key])) {
                return $map[$key];
            }
        }

        return "subscriber";
    }

    /* ---------------------------------------------------------------------
     * Validation des claims
     * ------------------------------------------------------------------ */

    /**
     * Normalise et valide la réponse `userinfo`.
     *
     * @param mixed $userinfo Données brutes décodées.
     * @return array{sub:string,email:string,name:string,roles:array<int,string>}| \WP_Error
     */
    public static function validate_userinfo($userinfo)
    {
        if (!is_array($userinfo)) {
            return new \WP_Error(
                "plaidact_sso_userinfo_failed",
                __("Réponse d'identité illisible.", "plaidact-campaign-core")
            );
        }

        $settings = self::get_sso_settings();
        $sub = trim((string) ($userinfo["sub"] ?? ""));
        $email = sanitize_email((string) ($userinfo["email"] ?? ""));

        if ("" === $sub || "" === $email) {
            return new \WP_Error(
                "plaidact_sso_invalid_claims",
                __("Identité Act incomplète.", "plaidact-campaign-core")
            );
        }

        if ("1" === (string) ($settings["require_verified_email"] ?? "1") && empty($userinfo["email_verified"])) {
            return new \WP_Error(
                "plaidact_sso_unverified",
                __("Adresse email non vérifiée côté Act.", "plaidact-campaign-core")
            );
        }

        $claim = trim((string) ($settings["roles_claim"] ?? "roles"));
        $raw_roles = $userinfo[$claim] ?? ($userinfo["role"] ?? []);

        if (!is_array($raw_roles)) {
            $raw_roles = [$raw_roles];
        }

        $roles = [];

        foreach ($raw_roles as $role) {
            $role = mb_strtolower(trim((string) $role));

            if ("" !== $role) {
                $roles[] = $role;
            }
        }

        $name = trim((string) ($userinfo["name"] ?? ""));

        if ("" === $name) {
            $name = trim(
                trim((string) ($userinfo["given_name"] ?? "")) . " " . trim((string) ($userinfo["family_name"] ?? ""))
            );
        }

        return [
            "sub" => $sub,
            "email" => $email,
            "name" => sanitize_text_field($name),
            "roles" => array_values(array_unique($roles)),
        ];
    }

    /* ---------------------------------------------------------------------
     * Flux de connexion
     * ------------------------------------------------------------------ */

    /**
     * Initie la connexion : crée l'état anti-CSRF et redirige vers Act.
     *
     * La protection CSRF repose sur le paramètre `state` à usage unique
     * conservé côté serveur (transient de 10 minutes).
     *
     * @return void
     */
    public static function handle_sso_start(): void
    {
        if (!self::is_sso_enabled()) {
            wp_safe_redirect(wp_login_url());
            exit;
        }

        $settings = self::get_sso_settings();
        $discovery = self::get_discovery();

        if ("" === $discovery["authorization_endpoint"]) {
            self::redirect_login_error("configuration");
        }

        $state = wp_generate_password(32, false);
        $verifier = wp_generate_password(64, false);
        $redirect_to = isset($_REQUEST["redirect_to"])
            ? wp_validate_redirect(wp_unslash($_REQUEST["redirect_to"]), home_url("/"))
            : home_url("/");

        set_transient(
            self::STATE_PREFIX . $state,
            ["verifier" => $verifier, "redirect_to" => $redirect_to],
            self::STATE_TTL
        );

        wp_safe_redirect(
            self::build_authorize_url(
                $discovery,
                trim((string) $settings["client_id"]),
                self::get_redirect_uri(),
                $state,
                $verifier
            )
        );
        exit;
    }

    /**
     * Rappel OIDC : échange le code, valide l'identité et connecte l'utilisateur.
     *
     * @return void
     */
    public static function handle_sso_callback(): void
    {
        $state = isset($_GET["state"]) ? sanitize_text_field(wp_unslash($_GET["state"])) : "";

        if (isset($_GET["error"])) {
            self::redirect_login_error("denied");
        }

        $code = isset($_GET["code"]) ? sanitize_text_field(wp_unslash($_GET["code"])) : "";

        if ("" === $state || "" === $code) {
            self::redirect_login_error("invalid_state");
        }

        // État à usage unique : toute rejeu échoue ici.
        $stored = get_transient(self::STATE_PREFIX . $state);
        delete_transient(self::STATE_PREFIX . $state);

        if (!is_array($stored) || empty($stored["verifier"])) {
            self::redirect_login_error("invalid_state");
        }

        $settings = self::get_sso_settings();
        $discovery = self::get_discovery();

        if ("" === $discovery["token_endpoint"] || "" === $discovery["userinfo_endpoint"]) {
            self::redirect_login_error("configuration");
        }

        $token_body = [
            "grant_type" => "authorization_code",
            "code" => $code,
            "redirect_uri" => self::get_redirect_uri(),
            "client_id" => trim((string) $settings["client_id"]),
            "code_verifier" => (string) $stored["verifier"],
        ];

        if ("" !== trim((string) $settings["client_secret"])) {
            $token_body["client_secret"] = trim((string) $settings["client_secret"]);
        }

        $token_response = wp_remote_post($discovery["token_endpoint"], [
            "timeout" => 15,
            "body" => $token_body,
        ]);

        if (is_wp_error($token_response)) {
            self::redirect_login_error("token_failed");
        }

        $token_data = json_decode((string) wp_remote_retrieve_body($token_response), true);
        $access_token = is_array($token_data) ? trim((string) ($token_data["access_token"] ?? "")) : "";

        if ("" === $access_token) {
            self::redirect_login_error("token_failed");
        }

        $userinfo_response = wp_remote_get($discovery["userinfo_endpoint"], [
            "timeout" => 15,
            "headers" => ["Authorization" => "Bearer " . $access_token],
        ]);

        if (is_wp_error($userinfo_response)) {
            self::redirect_login_error("userinfo_failed");
        }

        $identity = self::validate_userinfo(
            json_decode((string) wp_remote_retrieve_body($userinfo_response), true)
        );

        if (is_wp_error($identity)) {
            self::redirect_login_error("invalid_claims");
        }

        $user_id = self::provision_user(
            $identity["sub"],
            $identity["email"],
            $identity["name"],
            $identity["roles"]
        );

        if (is_wp_error($user_id) || $user_id <= 0) {
            self::redirect_login_error("user_failed");
        }

        $user = get_user_by("id", (int) $user_id);

        if (!$user instanceof \WP_User) {
            self::redirect_login_error("user_failed");
        }

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);

        /**
         * Signale une connexion via Act pour les intégrations.
         *
         * @param int $user_id Identifiant de l'utilisateur connecté.
         * @param array $identity Identité normalisée (sub, email, name, roles).
         */
        do_action("plaidact_act_sso_logged_in", $user->ID, $identity);

        wp_safe_redirect(wp_validate_redirect((string) ($stored["redirect_to"] ?? home_url("/")), home_url("/")));
        exit;
    }

    /**
     * Redirige vers l'écran de connexion avec un code d'erreur générique.
     *
     * Les détails techniques restent côté serveur (journal, transients) et ne
     * sont jamais exposés dans l'URL.
     *
     * @param string $code Code d'erreur public.
     * @return void
     */
    private static function redirect_login_error(string $code): void
    {
        wp_safe_redirect(add_query_arg("act_sso_error", sanitize_key($code), wp_login_url()));
        exit;
    }

    /**
     * Provisionne le compte WordPress lié à l'identité Act.
     *
     * Recherche par `sub` Act puis par email ; crée sinon avec le rôle mappé.
     * Les comptes existants ne sont jamais réinitialisés : identifiants,
     * contenus et réglages conservés, rôle synchronisé uniquement si l'option
     * l'autorise et sans jamais rétrograder un administrateur existant.
     *
     * @param string $sub Identifiant stable côté Act.
     * @param string $email Adresse email vérifiée.
     * @param string $name Nom d'affichage proposé.
     * @param array<int,string> $act_roles Rôles Act normalisés.
     * @return int| \WP_Error
     */
    public static function provision_user(string $sub, string $email, string $name, array $act_roles)
    {
        $users = get_users([
            "meta_key" => self::META_SUB,
            "meta_value" => $sub,
            "number" => 1,
            "fields" => "ID",
        ]);

        if (!empty($users)) {
            $user_id = (int) $users[0];
            self::maybe_sync_role($user_id, $act_roles);
            update_user_meta($user_id, self::META_LAST_LOGIN, time());

            return $user_id;
        }

        $existing = get_user_by("email", $email);

        if ($existing instanceof \WP_User) {
            // Liaison additive : le compte existant est conservé tel quel.
            if ("" === trim((string) get_user_meta($existing->ID, self::META_SUB, true))) {
                update_user_meta($existing->ID, self::META_SUB, $sub);
            }

            self::maybe_sync_role($existing->ID, $act_roles);
            update_user_meta($existing->ID, self::META_LAST_LOGIN, time());

            return $existing->ID;
        }

        $login_base = sanitize_user(current(explode("@", $email)), true);

        if ("" === $login_base) {
            $login_base = "act-user";
        }

        $login = $login_base;
        $suffix = 2;

        while (username_exists($login)) {
            $login = $login_base . "-" . $suffix;
            $suffix++;
        }

        $user_id = wp_insert_user([
            "user_login" => $login,
            "user_email" => $email,
            "user_pass" => wp_generate_password(32, true),
            "display_name" => "" !== $name ? $name : $login,
            "role" => self::map_act_roles_to_wp_role($act_roles),
        ]);

        if (is_wp_error($user_id) || $user_id <= 0) {
            return new \WP_Error(
                "plaidact_sso_user_failed",
                __("Création du compte impossible.", "plaidact-campaign-core")
            );
        }

        update_user_meta((int) $user_id, self::META_SUB, $sub);
        update_user_meta((int) $user_id, self::META_LAST_LOGIN, time());

        return (int) $user_id;
    }

    /**
     * Synchronise le rôle depuis Act quand l'option l'autorise.
     *
     * Un administrateur existant n'est jamais rétrogradé vers un rôle sans
     * droit d'administration : la gestion des accès reste sûre côté Act sans
     * risquer de verrouiller le site.
     *
     * @param int $user_id Identifiant de l'utilisateur.
     * @param array<int,string> $act_roles Rôles Act normalisés.
     * @return void
     */
    private static function maybe_sync_role(int $user_id, array $act_roles): void
    {
        $settings = self::get_sso_settings();

        if ("1" !== (string) ($settings["sync_role_on_login"] ?? "1")) {
            return;
        }

        $user = get_user_by("id", $user_id);

        if (!$user instanceof \WP_User) {
            return;
        }

        $mapped = self::map_act_roles_to_wp_role($act_roles);

        if (in_array($mapped, $user->roles, true)) {
            return;
        }

        if (user_can($user, "manage_options") && !self::role_can_manage_options($mapped)) {
            return;
        }

        $user->set_role($mapped);
    }

    /* ---------------------------------------------------------------------
     * Rendu public
     * ------------------------------------------------------------------ */

    /**
     * URL de lancement du flux, avec redirection de retour validée.
     *
     * @param string $redirect_to URL de retour après connexion.
     * @return string
     */
    public static function get_start_url(string $redirect_to = ""): string
    {
        if ("" === $redirect_to && isset($_REQUEST["redirect_to"])) {
            $redirect_to = (string) wp_unslash($_REQUEST["redirect_to"]);
        }

        return add_query_arg(
            [
                "action" => "plaidact_act_sso_start",
                "redirect_to" => wp_validate_redirect($redirect_to, home_url("/")),
            ],
            admin_url("admin-post.php")
        );
    }

    /**
     * Affiche le bouton sur l'écran de connexion natif.
     *
     * @return void
     */
    public static function render_login_form_button(): void
    {
        if (!self::is_sso_enabled()) {
            return;
        }

        $settings = self::get_sso_settings();
        $label = Polylang::translate_string(trim((string) ($settings["button_label"] ?? "")));

        echo '<p class="plaidact-act-sso-login"><a class="button button-secondary button-large" href="'
            . esc_url(self::get_start_url()) . '">'
            . esc_html("" !== $label ? $label : __("Se connecter avec Act", "plaidact-campaign-core"))
            . "</a></p>";
    }

    /**
     * Affiche les erreurs SSO sur l'écran de connexion.
     *
     * @param string $message Message existant.
     * @return string
     */
    public static function render_login_error(string $message): string
    {
        $code = isset($_GET["act_sso_error"]) ? sanitize_key(wp_unslash($_GET["act_sso_error"])) : "";

        if ("" === $code) {
            return $message;
        }

        $labels = [
            "denied" => __("Connexion Act refusée.", "plaidact-campaign-core"),
            "invalid_state" => __("Session de connexion expirée, veuillez réessayer.", "plaidact-campaign-core"),
            "token_failed" => __("Échange avec Act impossible, veuillez réessayer.", "plaidact-campaign-core"),
            "userinfo_failed" => __("Lecture de l'identité Act impossible.", "plaidact-campaign-core"),
            "invalid_claims" => __("Identité Act incomplète ou non vérifiée.", "plaidact-campaign-core"),
            "user_failed" => __("Création du compte WordPress impossible.", "plaidact-campaign-core"),
            "configuration" => __("Connexion Act non configurée.", "plaidact-campaign-core"),
        ];

        $text = $labels[$code] ?? __("Connexion Act impossible.", "plaidact-campaign-core");

        return $message . '<div id="login_error">' . esc_html($text) . "</div>";
    }

    /**
     * Shortcode du bouton de connexion Act.
     *
     * @param array<string,mixed> $atts Attributs (label, redirect).
     * @return string
     */
    public static function render_act_login(array $atts = []): string
    {
        if (!self::is_sso_enabled()) {
            return "";
        }

        $atts = shortcode_atts(
            ["label" => "", "redirect" => ""],
            $atts,
            "plaidact_act_login"
        );

        $settings = self::get_sso_settings();
        $label = trim((string) $atts["label"]);

        if ("" === $label) {
            $label = Polylang::translate_string(trim((string) ($settings["button_label"] ?? "")));
        }

        if ("" === $label) {
            $label = __("Se connecter avec Act", "plaidact-campaign-core");
        }

        $redirect = trim((string) $atts["redirect"]);

        return sprintf(
            '<p class="plaidact-act-sso-login"><a class="button button-secondary" href="%s">%s</a></p>',
            esc_url(self::get_start_url(wp_validate_redirect($redirect, home_url("/")))),
            esc_html($label)
        );
    }

    /* ---------------------------------------------------------------------
     * Administration et Polylang
     * ------------------------------------------------------------------ */

    /**
     * Enregistre le libellé du bouton comme chaîne traduisible.
     *
     * @return void
     */
    public static function register_strings(): void
    {
        if (!function_exists("pll_register_string")) {
            return;
        }

        $settings = self::get_sso_settings();
        $label = trim((string) ($settings["button_label"] ?? ""));

        if ("" !== $label) {
            pll_register_string("plaidact_act_sso_button_label", $label, "PLAID·ACT Core", false);
        }
    }

    /**
     * Affiche la section « Connexion Act (SSO) » des réglages.
     *
     * @return void
     */
    public static function render_settings_section(): void
    {
        if (!current_user_can("manage_options")) {
            return;
        }

        $settings = self::get_sso_settings();
        $discovery = get_transient(self::DISCOVERY_TRANSIENT);
        $status = isset($_GET["act_sso"]) ? sanitize_key(wp_unslash($_GET["act_sso"])) : "";
        ?>
        <div class="wrap">
            <h2><?php esc_html_e("Connexion Act (SSO)", "plaidact-campaign-core"); ?></h2>
            <?php if ("discovered" === $status) : ?>
                <div class="notice notice-success"><p><?php esc_html_e("Découverte OIDC réussie.", "plaidact-campaign-core"); ?></p></div>
            <?php elseif ("discovery_failed" === $status) : ?>
                <div class="notice notice-error"><p><?php esc_html_e("Découverte OIDC impossible : vérifiez l’URL Act.", "plaidact-campaign-core"); ?></p></div>
            <?php endif; ?>
            <p><?php esc_html_e("Act authentifie les utilisateurs ; WordPress suit et provisionne les comptes de façon additive. Activez le module dans PLAID·ACT → Modules, puis renseignez le client enregistré côté Act.", "plaidact-campaign-core"); ?></p>
            <p>
                <label for="plaidact_sso_redirect_uri"><strong><?php esc_html_e("URL de rappel à enregistrer côté Act", "plaidact-campaign-core"); ?></strong></label><br />
                <input id="plaidact_sso_redirect_uri" type="text" class="large-text code" readonly value="<?php echo esc_attr(self::get_redirect_uri()); ?>" onclick="this.select();" />
            </p>
            <p><?php esc_html_e("Découverte :", "plaidact-campaign-core"); ?>
                <strong><?php echo is_array($discovery) && !empty($discovery["authorization_endpoint"]) ? esc_html__("configurée", "plaidact-campaign-core") : esc_html__("non configurée", "plaidact-campaign-core"); ?></strong>
            </p>
            <form method="post" action="options.php">
                <?php settings_fields(self::OPTION_SETTINGS); ?>
                <table class="form-table" role="presentation">
                    <tr><th scope="row"><?php esc_html_e("Émetteur Act (issuer)", "plaidact-campaign-core"); ?></th><td><input name="<?php echo esc_attr(self::OPTION_SETTINGS); ?>[issuer]" type="url" value="<?php echo esc_attr((string) $settings["issuer"]); ?>" class="regular-text" placeholder="https://act.plaidact.org" /></td></tr>
                    <tr><th scope="row"><?php esc_html_e("Identifiant client", "plaidact-campaign-core"); ?></th><td><input name="<?php echo esc_attr(self::OPTION_SETTINGS); ?>[client_id]" type="text" value="<?php echo esc_attr((string) $settings["client_id"]); ?>" class="regular-text" autocomplete="off" /></td></tr>
                    <tr><th scope="row"><?php esc_html_e("Secret client", "plaidact-campaign-core"); ?></th><td><input name="<?php echo esc_attr(self::OPTION_SETTINGS); ?>[client_secret]" type="password" value="" class="regular-text" autocomplete="new-password" placeholder="••••••••" /><p class="description"><?php esc_html_e("Laissé vide, le secret enregistré est conservé.", "plaidact-campaign-core"); ?></p></td></tr>
                    <tr><th scope="row"><?php esc_html_e("Claim des rôles", "plaidact-campaign-core"); ?></th><td><input name="<?php echo esc_attr(self::OPTION_SETTINGS); ?>[roles_claim]" type="text" value="<?php echo esc_attr((string) $settings["roles_claim"]); ?>" class="small-text" /></td></tr>
                    <tr><th scope="row"><?php esc_html_e("Mappage des rôles", "plaidact-campaign-core"); ?></th><td><textarea name="<?php echo esc_attr(self::OPTION_SETTINGS); ?>[role_map]" class="large-text code" rows="5"><?php echo esc_textarea((string) $settings["role_map"]); ?></textarea><p class="description"><?php esc_html_e("Une ligne par rôle : role_act=role_wp. Rôle inconnu : abonné. Filtrable via plaidact_act_sso_role_map.", "plaidact-campaign-core"); ?></p></td></tr>
                    <tr><th scope="row"><?php esc_html_e("Exiger un email vérifié", "plaidact-campaign-core"); ?></th><td><label><input name="<?php echo esc_attr(self::OPTION_SETTINGS); ?>[require_verified_email]" type="checkbox" value="1" <?php checked((string) $settings["require_verified_email"], "1"); ?> /> <?php esc_html_e("Refuser les identités dont l’email n’est pas vérifié côté Act.", "plaidact-campaign-core"); ?></label></td></tr>
                    <tr><th scope="row"><?php esc_html_e("Synchroniser le rôle", "plaidact-campaign-core"); ?></th><td><label><input name="<?php echo esc_attr(self::OPTION_SETTINGS); ?>[sync_role_on_login]" type="checkbox" value="1" <?php checked((string) $settings["sync_role_on_login"], "1"); ?> /> <?php esc_html_e("Mettre à jour le rôle à chaque connexion (sans jamais rétrograder un administrateur).", "plaidact-campaign-core"); ?></label></td></tr>
                    <tr><th scope="row"><?php esc_html_e("Libellé du bouton", "plaidact-campaign-core"); ?></th><td><input name="<?php echo esc_attr(self::OPTION_SETTINGS); ?>[button_label]" type="text" value="<?php echo esc_attr((string) $settings["button_label"]); ?>" class="regular-text" /></td></tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url("admin-post.php")); ?>">
                <?php wp_nonce_field("plaidact_act_sso_discover"); ?>
                <input type="hidden" name="action" value="plaidact_act_sso_discover" />
                <?php submit_button(__("Tester la découverte OIDC", "plaidact-campaign-core"), "secondary"); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Rafraîchit la découverte OIDC depuis l'administration.
     *
     * @return void
     */
    public static function handle_discover(): void
    {
        if (!current_user_can("manage_options")) {
            wp_die(esc_html__("Accès refusé.", "plaidact-campaign-core"));
        }

        check_admin_referer("plaidact_act_sso_discover");
        delete_transient(self::DISCOVERY_TRANSIENT);

        $discovery = self::get_discovery();

        wp_safe_redirect(
            add_query_arg(
                "act_sso",
                "" !== $discovery["authorization_endpoint"] ? "discovered" : "discovery_failed",
                wp_get_referer() ?: admin_url("options-general.php?page=plaidact-campaign-settings")
            )
        );
        exit;
    }
}
