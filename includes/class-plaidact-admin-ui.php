<?php
/**
 * Charte graphique de l'administration PLAID·ACT.
 *
 * @package PLAIDACT\CampaignCore
 */

namespace Plaidact\CampaignCore;

if (!defined("ABSPATH")) {
    exit();
}

/**
 * Gabarit partagé des pages d'administration de l'extension.
 *
 * Centralise ce qui relève de la présentation — chargement des ressources,
 * en-tête de marque, découpage en sections, pastilles d'état — afin que les
 * neuf écrans d'administration se ressemblent et n'aient chacun qu'une source
 * de vérité. Aucune logique métier ici : les pages fournissent leurs contenus.
 */
final class Admin_UI
{
    /**
     * Classe enveloppante de toute page d'administration de l'extension.
     *
     * Les variables CSS sont définies au niveau de la racine du document, mais
     * tous les sélecteurs de style restent descendants de cette classe : rien
     * ne fuit vers les écrans WordPress qui ne sont pas les nôtres.
     */
    public const WRAP_CLASS = "plaidact-admin-wrap";

    /**
     * Marqueurs d'identification des écrans où la charte s'applique.
     *
     * On compare le suffixe de hook et l'identifiant d'écran : selon le type
     * d'écran WordPress, l'un ou l'autre porte la nature de la requête (les
     * écrans de édition de contenu renvoient un suffixe générique comme
     * « post.php »).
     */
    private const SCREEN_MARKERS = [
        "plaidact",
        "plaid_",
        "petitioner-petition",
    ];

    /**
     * États possibles d'une pastille d'état.
     */
    public const STATE_NEUTRAL = "neutral";
    public const STATE_SUCCESS = "success";
    public const STATE_WARNING = "warning";
    public const STATE_DANGER = "danger";
    public const STATE_INFO = "info";

    /**
     * Enregistre les actions WordPress.
     *
     * @return void
     */
    public static function boot(): void
    {
        add_action("admin_enqueue_scripts", [__CLASS__, "enqueue_assets"]);
    }

    /**
     * Charge la feuille de style et le script de l'administration.
     *
     * La feuille est limitée aux écrans qui portent réellement de l'interface
     * PLAID·ACT : pages de réglages, écrans de contenu de la campagne, et
     * écrans de pétition où le plugin ajoute ses propres metaboxes.
     *
     * @param string $hook_suffix Suffixe de la page courante.
     * @return void
     */
    public static function enqueue_assets(string $hook_suffix): void
    {
        if (!self::is_plugin_screen($hook_suffix)) {
            return;
        }

        wp_enqueue_style(
            "plaidact-admin",
            PLAIDACT_CORE_URL . "assets/css/admin.css",
            [],
            plaidact_campaign_core_asset_version("assets/css/admin.css")
        );

        wp_enqueue_script(
            "plaidact-admin",
            PLAIDACT_CORE_URL . "assets/js/admin.js",
            [],
            plaidact_campaign_core_asset_version("assets/js/admin.js"),
            true
        );
    }

    /**
     * Indique si l'écran courant appartient à l'extension.
     *
     * @param string $hook_suffix Suffixe de la page courante.
     * @return bool
     */
    private static function is_plugin_screen(string $hook_suffix): bool
    {
        $screen = function_exists("get_current_screen") ? get_current_screen() : null;
        $haystack = $hook_suffix . " " . ($screen instanceof \WP_Screen ? $screen->id : "");

        foreach (self::SCREEN_MARKERS as $marker) {
            if (false !== strpos($haystack, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ouvre la page : conteneur, en-tête de marque et contenu.
     *
     * L'en-tête porte le logo Act, le titre de l'écran et, si elle est fournie,
     * l'action principale de la page. Toutes les pages d'administration
     * partagent ce même en-tête : c'est le point d'ancrage de l'identité.
     *
     * @param array<string,mixed> $args {
     *     @type string $title       Titre de l'écran, obligatoire.
     *     @type string $description Phrase d'introduction sous le titre.
     *     @type string $eyebrow     Sur-titre court, au-dessus du titre.
     *     @type array<int,array<string,mixed>> $actions Boutons d'action, chacun
     *         avec `label`, `url`, et selon le cas `primary` (booléen) et
     *         `download` (nom de fichier proposé au navigateur).
     * }
     * @return void
     */
    public static function page_start(array $args): void
    {
        $title = isset($args["title"]) ? (string) $args["title"] : "";
        $description = isset($args["description"]) ? (string) $args["description"] : "";
        $eyebrow = isset($args["eyebrow"]) ? (string) $args["eyebrow"] : "";
        $actions = isset($args["actions"]) && is_array($args["actions"]) ? $args["actions"] : [];
        ?>
        <div class="wrap <?php echo esc_attr(self::WRAP_CLASS); ?>">
            <header class="plaidact-masthead">
                <div class="plaidact-masthead__brand">
                    <?php self::logo(); ?>
                </div>
                <div class="plaidact-masthead__content">
                    <?php if ("" !== $eyebrow) : ?>
                        <p class="plaidact-masthead__eyebrow"><?php echo esc_html($eyebrow); ?></p>
                    <?php endif; ?>
                    <h1 class="plaidact-masthead__title"><?php echo esc_html($title); ?></h1>
                    <?php if ("" !== $description) : ?>
                        <p class="plaidact-masthead__desc"><?php echo esc_html($description); ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($actions) : ?>
                    <div class="plaidact-masthead__actions">
                    <?php foreach ($actions as $action) :
                        $label = isset($action["label"]) ? (string) $action["label"] : "";
                        $url = isset($action["url"]) ? (string) $action["url"] : "";
                        $primary = !empty($action["primary"]);

                        if ("" === $label || "" === $url) {
                            continue;
                        }

                        // Certains boutons servent à récupérer un modèle de
                        // fichier : ils portent alors un nom de téléchargement.
                        // La valeur est déjà échappée par sprintf.
                        $download = !empty($action["download"])
                            ? sprintf(' download="%s"', esc_attr((string) $action["download"]))
                            : "";
                        ?>
                        <a class="button<?php echo $primary ? " button-primary" : ""; ?>"
                           href="<?php echo esc_url($url); ?>"<?php echo $download; ?>>
                            <?php echo esc_html($label); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </header>
        <?php
    }

    /**
     * Ferme la page ouverte par page_start().
     *
     * @return void
     */
    public static function page_end(): void
    {
        echo "</div>";
    }

    /**
     * Affiche le logo Act dans son cadre de marque.
     *
     * Le lockup officiel se pose sur une surface claire : sa typographie est
     * en encre prune et resterait illisible sur un fond sombre. La variante
     * sombre du logo n'apporte donc aucun contraste supplémentaire et n'est
     * pas utilisée ici.
     *
     * @return void
     */
    public static function logo(): void
    {
        ?>
        <span class="plaidact-logo">
            <img src="<?php echo esc_url(PLAIDACT_CORE_URL . "assets/brand/act-clair-logo.svg"); ?>"
                 width="248" height="100" alt="<?php esc_attr_e("Act par PLAID·ACT", "plaidact-campaign-core"); ?>" />
        </span>
        <?php
    }

    /**
     * Ouvre une section : carte avec en-tête, description et corps.
     *
     * @param string               $title       Titre de la section.
     * @param string               $description Texte d'explication optionnel.
     * @param string               $icon        Classe dashicons optionnelle.
     * @param array<int,array<string,string>> $actions Boutons d'en-tête.
     * @return void
     */
    public static function section_start(
        string $title,
        string $description = "",
        string $icon = "",
        array $actions = []
    ): void {
        ?>
        <section class="plaidact-admin-card">
            <div class="plaidact-admin-card__header">
                <div class="plaidact-admin-card__heading">
                    <h2 class="plaidact-admin-card__title">
                        <?php if ("" !== $icon) : ?>
                            <span class="dashicons <?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
                        <?php endif; ?>
                        <?php echo esc_html($title); ?>
                    </h2>
                    <?php if ("" !== $description) : ?>
                        <p class="plaidact-admin-card__desc"><?php echo esc_html($description); ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($actions) : ?>
                    <div class="plaidact-admin-card__actions">
                        <?php foreach ($actions as $action) :
                            $label = isset($action["label"]) ? (string) $action["label"] : "";
                            $url = isset($action["url"]) ? (string) $action["url"] : "";

                            if ("" === $label || "" === $url) {
                                continue;
                            }
                            ?>
                            <a class="button button-small" href="<?php echo esc_url($url); ?>">
                                <?php echo esc_html($label); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="plaidact-admin-card__body">
        <?php
    }

    /**
     * Ferme la section ouverte par section_start().
     *
     * @return void
     */
    public static function section_end(): void
    {
        echo "</div></section>";
    }

    /**
     * Affiche une pastille d'état colorée.
     *
     * @param string $label Libellé du statut.
     * @param string $state Une constante STATE_*.
     * @return void
     */
    public static function status(string $label, string $state = self::STATE_NEUTRAL): void
    {
        printf(
            '<span class="plaidact-admin-badge plaidact-admin-badge--%s">%s</span>',
            esc_attr($state),
            esc_html($label)
        );
    }

    /**
     * Affiche un message d'état dans la charte du plugin.
     *
     * Destiné aux retours qui n'utilisent pas les notices WordPress natives,
     * afin qu'un même écran ne présente pas deux langues graphiques.
     *
     * @param string $message Message à afficher.
     * @param string $state   Une constante STATE_*.
     * @return void
     */
    public static function notice(string $message, string $state = self::STATE_INFO): void
    {
        printf(
            '<p class="plaidact-admin-notice plaidact-admin-notice--%s">%s</p>',
            esc_attr($state),
            esc_html($message)
        );
    }
}
