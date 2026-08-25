<?php
/**
 * Plugin Name: PLAID·ACT Core
 * Description: Noyau PLAID·ACT (pétitions, newsletters, contenus et shortcodes).
 * Version: 2.3.0
 * Author: PLAID·ACT
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Text Domain: plaidact-campaign-core
 */

if (!defined("ABSPATH")) {
    exit();
}

define("PLAIDACT_CORE_VERSION", "2.3.0");
define("PLAIDACT_CORE_PATH", plugin_dir_path(__FILE__));
define("PLAIDACT_CORE_URL", plugin_dir_url(__FILE__));
define("PLAIDACT_CORE_BASENAME", plugin_basename(__FILE__));
define(
    "PLAIDACT_CORE_BUNDLED_PETITIONER_PATH",
    PLAIDACT_CORE_PATH . "vendor/petitioner/petitioner.php"
);

/**
 * Retourne une version de ressource qui invalide correctement le cache.
 *
 * @param string $relative_path Chemin relatif depuis la racine de l'extension.
 * @return string
 */
function plaidact_campaign_core_asset_version(string $relative_path): string
{
    static $version_cache = [];

    if (isset($version_cache[$relative_path])) {
        return $version_cache[$relative_path];
    }

    $asset_path = PLAIDACT_CORE_PATH . ltrim($relative_path, "/");
    $version = PLAIDACT_CORE_VERSION;

    if (file_exists($asset_path)) {
        $mtime = filemtime($asset_path);

        if (is_int($mtime) && $mtime > 0) {
            $version = PLAIDACT_CORE_VERSION . "." . $mtime;
        }
    }

    $version_cache[$relative_path] = $version;

    return $version;
}

/**
 * Charge le module Petitioner embarqué lorsqu'il est présent.
 *
 * @return void
 */
function plaidact_campaign_core_load_bundled_petitioner(): void
{
    if (
        defined("AV_PETITIONER_PLUGIN_VERSION") ||
        class_exists("AV_Petitioner_Setup", false)
    ) {
        return;
    }

    if (file_exists(PLAIDACT_CORE_BUNDLED_PETITIONER_PATH)) {
        require_once PLAIDACT_CORE_BUNDLED_PETITIONER_PATH;
    }
}

plaidact_campaign_core_load_bundled_petitioner();

/**
 * Charge les traductions depuis le répertoire de langues de l'extension.
 *
 * @return void
 */
function plaidact_campaign_core_load_textdomain(): void
{
    load_plugin_textdomain(
        "plaidact-campaign-core",
        false,
        dirname(PLAIDACT_CORE_BASENAME) . "/languages"
    );
}
add_action("init", "plaidact_campaign_core_load_textdomain", 0);

/**
 * Affiche une alerte exploitable lorsque le moteur de pétition est absent.
 *
 * @return void
 */
function plaidact_campaign_core_missing_petitioner_notice(): void
{
    if (
        file_exists(PLAIDACT_CORE_BUNDLED_PETITIONER_PATH) ||
        !current_user_can("activate_plugins")
    ) {
        return;
    }

    echo '<div class="notice notice-warning"><p>';
    echo esc_html__(
        "PLAID·ACT Core est actif, mais le module Petitioner embarqué est absent. Réinstallez le dossier vendor/petitioner pour réactiver les formulaires de pétition.",
        "plaidact-campaign-core"
    );
    echo "</p></div>";
}
add_action("admin_notices", "plaidact_campaign_core_missing_petitioner_notice");

require_once PLAIDACT_CORE_PATH . "includes/class-plaidact-campaign-cpt.php";
require_once PLAIDACT_CORE_PATH .
    "includes/class-plaidact-campaign-polylang.php";
require_once PLAIDACT_CORE_PATH .
    "includes/class-plaidact-campaign-petition-workflows.php";
require_once PLAIDACT_CORE_PATH .
    "includes/class-plaidact-campaign-petitioner-integration.php";
require_once PLAIDACT_CORE_PATH .
    "includes/class-plaidact-campaign-shortcodes.php";
require_once PLAIDACT_CORE_PATH . "includes/class-plaidact-campaign-blocks.php";
require_once PLAIDACT_CORE_PATH . "includes/class-plaidact-association-directory.php";
require_once PLAIDACT_CORE_PATH . "includes/class-plaidact-contact-directory.php";
require_once PLAIDACT_CORE_PATH . "includes/class-plaidact-actyl.php";

/**
 * Rend disponible dans ACF le groupe de champs associatif embarqué.
 * Les groupes déjà enregistrés en base restent prioritaires et inchangés.
 *
 * @param array<int,string> $paths Chemins de chargement JSON d'ACF.
 * @return array<int,string>
 */
function plaidact_campaign_core_acf_json_paths(array $paths): array
{
    $paths[] = PLAIDACT_CORE_PATH . "acf-json";
    return array_values(array_unique($paths));
}
add_filter("acf/settings/load_json", "plaidact_campaign_core_acf_json_paths");

/**
 * Lit un champ associatif via ACF, avec repli natif sur les métadonnées afin
 * que le répertoire reste utilisable sans ACF.
 *
 * @param string   $key Nom du champ.
 * @param int|null $post_id Identifiant du contenu, ou contenu courant.
 * @return mixed
 */
function plaidact_campaign_core_get_field(string $key, ?int $post_id = null)
{
    $post_id = $post_id ?: get_the_ID();

    if (function_exists("get_field")) {
        return get_field($key, $post_id);
    }

    return get_post_meta($post_id, $key, true);
}

/**
 * Met à jour un champ associatif sans rendre ACF obligatoire.
 *
 * @param string $key Nom du champ.
 * @param mixed  $value Valeur du champ.
 * @param int    $post_id Identifiant du contenu.
 * @return mixed
 */
function plaidact_campaign_core_update_field(string $key, $value, int $post_id)
{
    if (function_exists("update_field")) {
        return update_field($key, $value, $post_id);
    }

    return update_post_meta($post_id, $key, $value);
}

/**
 * Active les modules embarqués.
 *
 * @return void
 */
function plaidact_campaign_core_activate(): void
{
    plaidact_campaign_core_load_bundled_petitioner();

    if (class_exists("AV_Petitioner_Setup")) {
        AV_Petitioner_Setup::plugin_activation();
    }

    \Plaidact\CampaignCore\Association_Directory::register_asso_cpt_and_taxonomies();

    flush_rewrite_rules();
}

/**
 * Désactive les modules embarqués.
 *
 * @return void
 */
function plaidact_campaign_core_deactivate(): void
{
    if (class_exists("AV_Petitioner_Setup")) {
        AV_Petitioner_Setup::plugin_deactivation();
    }


    flush_rewrite_rules();
}

/**
 * Désinstalle les modules embarqués.
 *
 * @return void
 */
function plaidact_campaign_core_uninstall(): void
{
    plaidact_campaign_core_load_bundled_petitioner();

    if (class_exists("AV_Petitioner_Setup")) {
        AV_Petitioner_Setup::plugin_uninstall();
    }
}

register_activation_hook(__FILE__, "plaidact_campaign_core_activate");
register_deactivation_hook(__FILE__, "plaidact_campaign_core_deactivate");
register_uninstall_hook(__FILE__, "plaidact_campaign_core_uninstall");

/**
 * Initialise les modules de l'extension.
 *
 * @return void
 */
function plaidact_campaign_core_init(): void
{
    \Plaidact\CampaignCore\CPT::boot();
    \Plaidact\CampaignCore\Polylang::boot();
    \Plaidact\CampaignCore\Petitioner_Integration::boot();
    \Plaidact\CampaignCore\Shortcodes::boot();
    \Plaidact\CampaignCore\Blocks::boot();
    \Plaidact\CampaignCore\Association_Directory::init();
    \PlaidAct_Contact_Directory::init();

    // Synchronisation Actyl : singleton désactivé par défaut, sans effet
    // réseau tant que la connexion n'est pas configurée et validée.
    \Plaidact\CampaignCore\Actyl::init();
}
add_action("plugins_loaded", "plaidact_campaign_core_init");
