<?php

if (!defined('ABSPATH')) {
	exit(); // Exit if accessed directly.
}

class AV_Petitioner_Setup
{
	public static $FRONTEND_FORM_NONCE_LABEL = 'petitioner_form_nonce';

	public function __construct()
	{
		// db schema
		add_action('plugins_loaded', [$this, 'on_plugins_loaded']);
		// assets
		add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
		add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);

		add_filter('wp_script_attributes', function ($attributes) {
			if (
				'petitioner-script-js' === $attributes['id'] ||
				'petitioner-admin-script-js' === $attributes['id'] ||
				'petitioner-form-block-js' === $attributes['id']
			) {
				$attributes['type'] = 'module';
			}

			return $attributes;
		});

		add_action('init', function () {
			// cpt
			$this->register_post_types();

			// edit admin fields
			if (class_exists('AV_Petitioner_Admin_Edit_UI')) {
				new AV_Petitioner_Admin_Edit_UI();
			}

			// shortcodes
			if (class_exists('AV_Petitioner_Shortcodes')) {
				new AV_Petitioner_Shortcodes();
			}

			// settings admin fields
			if (class_exists('AV_Petitioner_Admin_Settings_UI')) {
				new AV_Petitioner_Admin_Settings_UI();
			}

			if (class_exists('AV_Petitioner_Admin_Component_Preview_UI')) {
				new AV_Petitioner_Admin_Component_Preview_UI();
			}

			// shared admin settings
			if (class_exists('AV_Petitioner_Admin_Shared')) {
				new AV_Petitioner_Admin_Shared();
			}

			// add label overrides
			if (class_exists('AV_Petitioner_Label_Overrides')) {
				new AV_Petitioner_Label_Overrides();
			}

			// custom properties
			if (class_exists('AV_Petitioner_Custom_Properties')) {
				new AV_Petitioner_Custom_Properties();
			}

			// register blocks
			if (class_exists('AV_Petitioner_Gutenberg')) {
				new AV_Petitioner_Gutenberg();
			}

			// add captcha
			if (class_exists('AV_Petitioner_Captcha')) {
				new AV_Petitioner_Captcha();
			}
		});

		// api endpoints
		add_action('wp_ajax_petitioner_form_submit', [
			'AV_Petitioner_Submissions_Controller',
			'api_handle_form_submit',
		]);
		add_action('wp_ajax_nopriv_petitioner_form_submit', [
			'AV_Petitioner_Submissions_Controller',
			'api_handle_form_submit',
		]);
		add_action('wp_ajax_petitioner_fetch_submissions', [
			'AV_Petitioner_Submissions_Controller',
			'api_fetch_form_submissions',
		]);
		add_action('wp_ajax_petitioner_get_submissions', [
			'AV_Petitioner_Submissions_Controller',
			'api_get_form_submissions',
		]);
		add_action('wp_ajax_nopriv_petitioner_get_submissions', [
			'AV_Petitioner_Submissions_Controller',
			'api_get_form_submissions',
		]);
		add_action('wp_ajax_petitioner_change_status', [
			'AV_Petitioner_Submissions_Controller',
			'api_change_submission_status',
		]);
		add_action('wp_ajax_petitioner_resend_confirmation_email', [
			'AV_Petitioner_Submissions_Controller',
			'api_resend_confirmation_email',
		]);
		add_action('wp_ajax_petitioner_resend_all_confirmation_emails', [
			'AV_Petitioner_Submissions_Controller',
			'api_resend_all_confirmation_emails',
		]);
		add_action('wp_ajax_petitioner_check_unconfirmed_count', [
			'AV_Petitioner_Submissions_Controller',
			'api_check_unconfirmed_count',
		]);
		add_action('wp_ajax_petitioner_update_submission', [
			'AV_Petitioner_Submissions_Controller',
			'api_update_form_submission',
		]);
		add_action('wp_ajax_petitioner_delete_submission', [
			'AV_Petitioner_Submissions_Controller',
			'api_delete_form_submission',
		]);
		add_action('wp_ajax_petitioner_get_submission_count', [
			'AV_Petitioner_Submissions_Controller',
			'api_get_submission_count',
		]);
		add_action('wp_ajax_petitioner_get_nonce', [
			$this,
			'api_get_frontend_nonce',
		]);
		add_action('wp_ajax_nopriv_petitioner_get_nonce', [
			$this,
			'api_get_frontend_nonce',
		]);
		add_action('wp_ajax_petitioner_get_csv_example', [
			'AV_Petitioner_CSV_Exporter',
			'api_admin_petitioner_get_csv_example',
		]);

		add_action('admin_post_petitioner_export_csv', [
			'AV_Petitioner_CSV_Exporter',
			'api_admin_petitioner_export_csv',
		]);
	}
	/**
	 * Plugin activation callback.
	 */
	public static function plugin_activation()
	{
		add_option('petitioner_plugin_version', AV_PETITIONER_PLUGIN_VERSION);
		AV_Petitioner_Submissions_Model::create_db_table();

		AV_Petitioner_Form_Migrator::migrate_all_forms_to_builder();
	}

	/**
	 * Check if the plugin is updated and perform necessary actions.
	 */
	public function on_plugins_loaded()
	{
		// Load plugin text domain for translations
		load_plugin_textdomain(
			'petitioner',
			false,
			basename(dirname(__DIR__)) . '/languages/'
		);

		// Check if the plugin is updated
		$current_version = get_option(
			'petitioner_plugin_version',
			AV_PETITIONER_PLUGIN_VERSION
		);

		if (empty($current_version)) {
			// Fresh install setup
			// Update the database schema or perform any other necessary updates
			AV_Petitioner_Submissions_Model::create_db_table();
			update_option(
				'petitioner_plugin_version',
				AV_PETITIONER_PLUGIN_VERSION
			);
		} elseif (
			version_compare($current_version, AV_PETITIONER_PLUGIN_VERSION, '<')
		) {
			// Update the database schema or perform any other necessary updates
			AV_Petitioner_Submissions_Model::create_db_table();
			update_option(
				'petitioner_plugin_version',
				AV_PETITIONER_PLUGIN_VERSION
			);
		}
	}

	/**
	 * Plugin deactivation callback.
	 */
	public static function plugin_deactivation()
	{
		flush_rewrite_rules();
	}

	/**
	 * Plugin uninstall callback.
	 */
	public static function plugin_uninstall()
	{
		delete_option('petitioner_plugin_version');
	}

	/**
	 * Register custom post types for the plugin.
	 */
	public function register_post_types()
	{
		$labels = [
			'name' => _x('Petitions', 'post type general name', 'petitioner'),
			'singular_name' => _x(
				'Petition',
				'post type singular name',
				'petitioner'
			),
			'menu_name' => _x('Pétition', 'admin menu', 'petitioner'),
			'name_admin_bar' => _x(
				'Petition',
				'add new on admin bar',
				'petitioner'
			),
			'add_new' => _x('Add New', 'petition', 'petitioner'),
			'add_new_item' => __('Add New Petition', 'petitioner'),
			'new_item' => __('New Petition', 'petitioner'),
			'edit_item' => __('Edit Petition', 'petitioner'),
			'view_item' => __('View Petition', 'petitioner'),
			'all_items' => __('All Petitions', 'petitioner'),
			'search_items' => __('Search Petitions', 'petitioner'),
			'not_found' => __('No petitions found.', 'petitioner'),
			'not_found_in_trash' => __(
				'No petitions found in Trash.',
				'petitioner'
			),
		];

		register_post_type('petitioner-petition', [
			'public' => true,
			'labels' => $labels,
			'supports' => ['title'],
			'has_archive' => false,
			'show_in_menu' => true,
			'show_in_rest' => true,
			'exclude_from_search' => true,
			'hierarchical' => false,
			'publicly_queryable' => false,
			'menu_icon' =>
				plugin_dir_url(dirname(__FILE__)) . 'petitioner-glyph.svg',
		]);
	}

	/**
	 * Enqueue plugin scripts and styles.
	 */
	public function enqueue_frontend_assets()
	{
		if (is_admin()) {
			return;
		}

		$frontend_css_url = plugin_dir_url(dirname(__FILE__)) . 'dist/main.css';
		$frontend_css_file = AV_PETITIONER_PLUGIN_DIR . 'dist/main.css';
		$frontend_js_url = plugin_dir_url(dirname(__FILE__)) . 'dist/main.js';
		$frontend_js_file = AV_PETITIONER_PLUGIN_DIR . 'dist/main.js';

		if (file_exists($frontend_css_file)) {
			wp_enqueue_style(
				'petitioner-style',
				$frontend_css_url,
				[],
				AV_PETITIONER_PLUGIN_VERSION
			);
		}

		// Custom CSS styles
		$custom_css = $this->generate_custom_css();

		if (!empty($custom_css)) {
			if (!wp_style_is('petitioner-style', 'registered')) {
				wp_register_style('petitioner-style', false, [], AV_PETITIONER_PLUGIN_VERSION);
			}

			wp_enqueue_style('petitioner-style');
			wp_add_inline_style('petitioner-style', $custom_css);
		}

		if (!file_exists($frontend_js_file)) {
			return;
		}

		wp_register_script(
			'petitioner-script',
			$frontend_js_url,
			[],
			AV_PETITIONER_PLUGIN_VERSION,
			true
		);
		wp_enqueue_script('petitioner-script');

		wp_localize_script('petitioner-script', 'petitionerFormSettings', [
			'actionPath' =>
				admin_url('admin-ajax.php') . '?action=petitioner_form_submit',
			'nonce' => wp_create_nonce(self::$FRONTEND_FORM_NONCE_LABEL),
			'nonceEndpoint' =>
				admin_url('admin-ajax.php') . '?action=petitioner_get_nonce',
			'labels' => [
				'emailConfirmedSuccess' => sanitize_text_field(
					AV_Petitioner_Labels::get('email_confirmed_success')
				),
				'emailConfirmedError' => sanitize_text_field(
					AV_Petitioner_Labels::get('email_confirmed_error')
				),
			],
		]);

		wp_localize_script(
			'petitioner-script',
			'petitionerSubmissionSettings',
			[
				'actionPath' =>
					admin_url('admin-ajax.php') .
					'?action=petitioner_get_submissions',
				'nonce' => wp_create_nonce('petitioner_submissions_nonce'),
				'labels' => [
					'prevPage' => __('Previous page', 'petitioner'),
					'nextPage' => __('Next page', 'petitioner'),
				],
			]
		);
	}

	public function generate_custom_css()
	{
		$custom_css = '';
		$primary_color = $this->sanitize_css_color(get_option('petitioner_primary_color', ''));
		$dark_color = $this->sanitize_css_color(get_option('petitioner_dark_color', ''));
		$grey_color = $this->sanitize_css_color(get_option('petitioner_grey_color', ''));

		$theme_colors = '';
		$theme_colors .= '--ptr-color-primary: var(--color-brand-pink, var(--wp--preset--color--primary, var(--wp--preset--color--accent, #ff99cc)));';
		$theme_colors .= '--ptr-btn-bg: var(--ptr-color-primary);';
		$theme_colors .= '--ptr-progress-inner-bg: var(--ptr-color-primary);';
		$theme_colors .= '--ptr-color-dark: var(--color-neutral-black, var(--wp--preset--color--contrast, #202d55));';
		$theme_colors .= '--ptr-color-grey: color-mix(in srgb, var(--ptr-color-dark) 14%, transparent);';
		$theme_colors .= '--ptr-wrapper-bg: var(--color-neutral-beige, var(--wp--preset--color--base, #f2ede4));';
		$theme_colors .= '--ptr-wrapper-radius: var(--wp--custom--border--radius, 0);';
		$theme_colors .= '--ptr-input-border-color: var(--ptr-color-dark);';
		$theme_colors .= '--ptr-input-border-color-active: var(--ptr-color-primary);';
		$theme_colors .= '--ptr-button-border-radius: var(--wp--custom--button--border--radius, 0);';
		$theme_colors .= '--ptr-button-border-color: var(--ptr-color-dark);';

		$default_colors = $theme_colors;

		if (!empty($primary_color)) {
			$default_colors .= '--ptr-color-primary: ' . $primary_color . ' !important;';
			$default_colors .= '--ptr-btn-bg: ' . $primary_color . ' !important;';
			$default_colors .= '--ptr-progress-inner-bg: ' . $primary_color . ' !important;';
		}

		if (!empty($dark_color)) {
			$default_colors .= '--ptr-color-dark: ' . $dark_color . ' !important;';
			$default_colors .= '--ptr-input-border-color: ' . $dark_color . ' !important;';
			$default_colors .= '--ptr-button-border-color: ' . $dark_color . ' !important;';
		}

		if (!empty($grey_color)) {
			$default_colors .= '--ptr-color-grey: ' . $grey_color . ' !important;';
		}

		$custom_css .= '.petitioner, .petitioner-submissions {' . $default_colors . '} ';
		$custom_css .= '.petitioner, .petitioner-submissions {font-family: var(--font-sans, inherit); color: var(--ptr-color-dark);} ';
		$custom_css .= '.petitioner {border: 2px solid var(--ptr-color-dark); border-radius: var(--ptr-wrapper-radius); box-shadow: var(--shadow-neo, none);} ';
		$custom_css .= '.petitioner__title {font-family: var(--font-display, inherit); color: var(--ptr-color-dark); text-transform: uppercase; letter-spacing: .02em;} ';
		$custom_css .= '.petitioner__btn {border: 2px solid var(--ptr-button-border-color); border-radius: var(--ptr-button-border-radius); box-shadow: var(--shadow-neo, none); font-family: var(--font-display, inherit); font-weight: 900; letter-spacing: .04em; text-transform: uppercase;} ';
		$custom_css .= '.petitioner__btn:hover, .petitioner__btn:focus {box-shadow: var(--shadow-neo-sm, none); transform: translate(2px, 2px);} ';
		$custom_css .= '.petitioner__input input, .petitioner__input select, .petitioner__input textarea {border: 2px solid var(--ptr-input-border-color); border-radius: 0; background: #fff; color: var(--ptr-color-dark);} ';
		$custom_css .= '.petitioner__input input:focus, .petitioner__input select:focus, .petitioner__input textarea:focus {border-color: var(--ptr-input-border-color-active); outline: 2px solid var(--ptr-input-border-color-active); outline-offset: 2px;} ';
		$custom_css .= '.petitioner-submissions {border: 2px solid var(--ptr-color-dark); border-radius: var(--ptr-wrapper-radius); box-shadow: var(--shadow-neo, none); background: var(--ptr-wrapper-bg);} ';

		$custom_css .= wp_strip_all_tags((string) get_option('petitioner_custom_css', ''));

		return apply_filters('av_petitioner_custom_css', $custom_css);
	}

	private function sanitize_css_color($color): string
	{
		$color = trim((string) $color);

		if ($color === '') {
			return '';
		}

		$hex_color = sanitize_hex_color($color);

		return $hex_color ?: '';
	}

	/**
	 * Enqueue admin scripts and styles.
	 */
	public function enqueue_admin_assets()
	{
		$screen = get_current_screen();
		$admin_css_file = AV_PETITIONER_PLUGIN_DIR . 'dist/admin.css';
		$admin_css_url = plugin_dir_url(dirname(__FILE__)) . 'dist/admin.css';
		$admin_js_url = plugin_dir_url(dirname(__FILE__)) . 'dist/admin.js';

		// Check if on the edit or add new page for 'petitioner_petition' post type
		if ($screen && $screen->post_type === 'petitioner-petition') {
			// Enqueue scripts
			wp_enqueue_script('wp-blocks');
			wp_enqueue_script('wp-block-editor');
			wp_enqueue_script('wp-element');
			wp_enqueue_script('wp-components');
			wp_enqueue_script('wp-tinymce');

			// Enqueue styles
			wp_enqueue_style('wp-components');
		}

		if (file_exists($admin_css_file)) {
			wp_enqueue_style(
				'petitioner-admin-style',
				$admin_css_url,
				[],
				AV_PETITIONER_PLUGIN_VERSION
			);
		}

		wp_enqueue_script(
			'petitioner-admin-script',
			$admin_js_url,
			[
				'wp-blocks',
				'wp-block-editor',
				'wp-element',
				'wp-components',
				'wp-data',
				'wp-i18n',
				'wp-hooks',
			],
			AV_PETITIONER_PLUGIN_VERSION,
			true
		);
	}

	/**
	 * Get the frontend nonce.
	 *
	 */
	public function api_get_frontend_nonce()
	{
		wp_send_json_success([
			'nonce' => wp_create_nonce(self::$FRONTEND_FORM_NONCE_LABEL),
		]);
	}
}
