<?php
/**
 * Bundled Petitioner module for PLAID·ACT Campaign Core.
 *
 * @package PLAIDACT\CampaignCore
 */

if (!defined('ABSPATH')) {
	exit();
}

if (
	defined('AV_PETITIONER_PLUGIN_VERSION') ||
	class_exists('AV_Petitioner_Setup', false)
) {
	return;
}

define('AV_PETITIONER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AV_PETITIONER_PLUGIN_VERSION', '0.8.2');

if (!function_exists('av_ptr_error_log')) {
	/**
	 * Writes Petitioner debug information to the PHP error log.
	 *
	 * @param mixed $data Debug payload.
	 * @return void
	 */
	function av_ptr_error_log($data)
	{
		if (defined('WP_DEBUG') && WP_DEBUG === true) {
			$caller =
				debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1][
					'function'
				] ?? 'global';

			error_log(
				wp_json_encode(
					[
						'data' => $data,
						'caller' => $caller,
					],
					JSON_PRETTY_PRINT
				)
			);
		}
	}
}

require_once AV_PETITIONER_PLUGIN_DIR .
	'inc/submissions/class-submissions-model.php';
require_once AV_PETITIONER_PLUGIN_DIR .
	'inc/submissions/class-submissions-controller.php';
require_once AV_PETITIONER_PLUGIN_DIR .
	'inc/submissions/class-csv-column-config.php';
require_once AV_PETITIONER_PLUGIN_DIR .
	'inc/submissions/class-csv-exporter.php';
require_once AV_PETITIONER_PLUGIN_DIR .
	'inc/submissions/class-custom-properties.php';
require_once AV_PETITIONER_PLUGIN_DIR . 'inc/integrations/class-captcha.php';
require_once AV_PETITIONER_PLUGIN_DIR . 'inc/integrations/class-akismet.php';
require_once AV_PETITIONER_PLUGIN_DIR . 'inc/class-field-registry.php';
require_once AV_PETITIONER_PLUGIN_DIR .
	'inc/integrations/class-form-migrator.php';
require_once AV_PETITIONER_PLUGIN_DIR . 'inc/class-goal-milestones.php';
require_once AV_PETITIONER_PLUGIN_DIR . 'inc/emails/class-email-controller.php';
require_once AV_PETITIONER_PLUGIN_DIR .
	'inc/emails/class-email-confirmations.php';
require_once AV_PETITIONER_PLUGIN_DIR . 'inc/emails/class-email-template.php';
require_once AV_PETITIONER_PLUGIN_DIR . 'inc/emails/class-mailer.php';
require_once AV_PETITIONER_PLUGIN_DIR . 'inc/frontend/class-frontend-ui.php';
require_once AV_PETITIONER_PLUGIN_DIR . 'inc/frontend/class-form-ui.php';
require_once AV_PETITIONER_PLUGIN_DIR . 'inc/frontend/class-shortcodes.php';
require_once AV_PETITIONER_PLUGIN_DIR .
	'inc/admin-ui/edit-form/class-admin-edit-ui.php';
require_once AV_PETITIONER_PLUGIN_DIR .
	'inc/admin-ui/settings/class-admin-settings-ui.php';
require_once AV_PETITIONER_PLUGIN_DIR .
	'inc/admin-ui/class-admin-component-preview-ui.php';
require_once AV_PETITIONER_PLUGIN_DIR . 'inc/admin-ui/class-admin-shared.php';
require_once AV_PETITIONER_PLUGIN_DIR . 'inc/gutenberg/class-gutenberg.php';
require_once AV_PETITIONER_PLUGIN_DIR . 'inc/class-setup.php';
require_once AV_PETITIONER_PLUGIN_DIR . 'inc/utilities.php';
require_once AV_PETITIONER_PLUGIN_DIR . 'inc/labels/class-labels.php';
require_once AV_PETITIONER_PLUGIN_DIR . 'inc/labels/class-label-overrides.php';

if (!function_exists('av_petitioner_boot')) {
	/**
	 * Boots the bundled Petitioner module exactly once.
	 *
	 * @return void
	 */
	function av_petitioner_boot(): void
	{
		static $booted = false;

		if ($booted) {
			return;
		}

		$booted = true;

		new AV_Petitioner_Setup();
		new AV_Email_Confirmations();

		AV_Petitioner_Form_Migrator::migrate_form_fields_to_builder_filters();
	}
}

av_petitioner_boot();
