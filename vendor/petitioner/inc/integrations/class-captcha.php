<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class AV_Petitioner_Captcha
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts',  [self::class, 'enqueue_scripts']);

        add_filter('av_petitioner_labels_defaults', [self::class, 'register_captcha_labels']);
    }

    /**
     * Register the captcha labels
     *
     * @param array $labels The labels array.
     * @return array The labels array.
     */
    public static function register_captcha_labels($labels)
    {
        $labels['captcha_verification_failed_title'] = __('CAPTCHA verification failed.', 'petitioner');
        $labels['captcha_verification_failed_message'] = __('CAPTCHA verification failed. Please try again.', 'petitioner');
        $labels['captcha_invalid_type'] = __('Invalid CAPTCHA type.', 'petitioner');
        $labels['captcha_missing_response'] = __('CAPTCHA response is missing.', 'petitioner');
        $labels['captcha_connection_failed'] = __('CAPTCHA verification failed: Unable to connect.', 'petitioner');
        $labels['captcha_verification_failed'] = __('CAPTCHA verification failed.', 'petitioner');
        $labels['captcha_score_too_low'] = __('reCAPTCHA score too low. Please try again.', 'petitioner');
        $labels['captcha_verification_completed'] = __('CAPTCHA verification completed.', 'petitioner');

        return $labels;
    }

    /**
     * Enqueue the captcha-related scripts
     */
    public static function enqueue_scripts()
    {
        $active_provider        = self::get_active_provider();
        $active_type            = $active_provider['type'] ?? '';
        $recaptcha_site_key     = get_option('petitioner_recaptcha_site_key');
        $hcaptcha_site_key      = get_option('petitioner_hcaptcha_site_key');
        $turnstile_site_key     = get_option('petitioner_turnstile_site_key');
        $provider_is_configured = $active_provider
            && !empty($active_provider['site_key'])
            && !empty($active_provider['secret_key']);

        // Only one provider is active, matching server-side validation.
        if ($provider_is_configured && $active_type === 'recaptcha') {
            wp_enqueue_script('petitioner-google-recaptcha-v3', 'https://www.google.com/recaptcha/api.js?render=' . esc_attr($recaptcha_site_key), [], null, true);
        } else if ($provider_is_configured && $active_type === 'hcaptcha') {
            wp_enqueue_script('hcaptcha', 'https://js.hcaptcha.com/1/api.js', [], null, true);
        } else if ($provider_is_configured && $active_type === 'turnstile') {
            wp_enqueue_script('petitioner-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', [], null, true);
        }

        wp_localize_script('petitioner-script', 'petitionerCaptcha', [
            'recaptchaSiteKey'  => $recaptcha_site_key,
            'hcaptchaSiteKey'   => $hcaptcha_site_key,
            'enableRecaptcha'   => $provider_is_configured && $active_type === 'recaptcha',
            'enableHcaptcha'    => $provider_is_configured && $active_type === 'hcaptcha',
            'enableTurnstile'   => $provider_is_configured && $active_type === 'turnstile',
            'turnstileSiteKey'  => $turnstile_site_key,
        ]);
    }

    public static function render_inputs()
    {
        $active_provider = self::get_active_provider();
        $active_type = $active_provider['type'] ?? '';

?>
        <?php if ($active_type === 'turnstile'): ?>
            <span class="petitioner-turnstile-container"></span>
            <input type="hidden" name="petitioner-turnstile-response" id="petitioner-turnstile-response">
        <?php endif; ?>

        <?php if ($active_type === 'recaptcha'): ?>
            <input type="hidden" name="petitioner-g-recaptcha-response" id="petitioner-g-recaptcha-response">
            <p class="petitioner-disclaimer-text">
                <?php
                // translators: %1$s is the opening anchor tag, %2$s is the closing anchor tag
                printf(
                    esc_html__(
                        'This site is protected by reCAPTCHA and the Google %1$sPrivacy Policy%2$s and %3$sTerms of Service%4$s apply.',
                        'petitioner'
                    ),
                    '<a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">',
                    '</a>',
                    '<a href="https://policies.google.com/terms" target="_blank" rel="noopener noreferrer">',
                    '</a>'
                );
                ?>
            </p>
        <?php endif; ?>

        <?php if ($active_type === 'hcaptcha'): ?>
            <span class="petitioner-h-captcha-container"></span>
            <input type="hidden" name="petitioner-h-captcha-response" class="petitioner-h-captcha-response">
            <p class="petitioner-disclaimer-text">
                <?php
                // translators: %1$s is the opening anchor tag, %2$s is the closing anchor tag
                printf(
                    esc_html__(
                        'This site is protected by hCaptcha and its %1$sPrivacy Policy%2$s and %3$sTerms of Service%4$s apply.',
                        'petitioner'
                    ),
                    '<a href="https://www.hcaptcha.com/privacy" target="_blank" rel="noopener noreferrer">',
                    '</a>',
                    '<a href="https://www.hcaptcha.com/terms" target="_blank" rel="noopener noreferrer">',
                    '</a>'
                );
                ?>
            </p>
        <?php endif; ?>
<?php
    }

    public static function validate_captcha($captcha_response)
    {
        $active_provider = self::get_active_provider();

        if (!$active_provider) {
            return true;
        }

        $field = $active_provider['response_field'];
        $response = isset($_POST[$field])
            ? sanitize_text_field(wp_unslash($_POST[$field]))
            : '';
        $result = self::verify_captcha($response, $active_provider['type']);

        if (!$result['success']) {
            wp_send_json_error([
                'title' => AV_Petitioner_Labels::get('captcha_verification_failed_title'),
                'message' => $result['message'],
            ]);
            wp_die();
        }

        return true;
    }

    /**
     * Return the first enabled CAPTCHA provider.
     *
     * The same provider must be used by rendering, JavaScript and validation.
     * This also keeps submissions deterministic if multiple settings are
     * accidentally enabled.
     *
     * @return array|null
     */
    private static function get_active_provider()
    {
        $providers = [
            'recaptcha' => [
                'site_key_option' => 'petitioner_recaptcha_site_key',
                'secret_key_option' => 'petitioner_recaptcha_secret_key',
                'response_field' => 'petitioner-g-recaptcha-response',
            ],
            'hcaptcha' => [
                'site_key_option' => 'petitioner_hcaptcha_site_key',
                'secret_key_option' => 'petitioner_hcaptcha_secret_key',
                'response_field' => 'petitioner-h-captcha-response',
            ],
            'turnstile' => [
                'site_key_option' => 'petitioner_turnstile_site_key',
                'secret_key_option' => 'petitioner_turnstile_secret_key',
                'response_field' => 'petitioner-turnstile-response',
            ],
        ];

        foreach ($providers as $type => $provider) {
            if (!get_option('petitioner_enable_' . $type, false)) {
                continue;
            }

            return [
                'type' => $type,
                'site_key' => get_option($provider['site_key_option'], ''),
                'secret_key' => get_option($provider['secret_key_option'], ''),
                'response_field' => $provider['response_field'],
            ];
        }

        return null;
    }

    /**
     * Verify the CAPTCHA response (Supports both Google reCAPTCHA v3 & hCaptcha).
     *
     * @param string $captcha_response The CAPTCHA response token from the form.
     * @param string $captcha_type The type of CAPTCHA ('recaptcha' or 'hcaptcha').
     * @return array Response array with 'success' boolean and 'message' string.
     * @since 0.2.3
     */
    public static function verify_captcha($captcha_response, $captcha_type = 'recaptcha')
    {
        // Lookup table for each CAPTCHA provider
        $providers = [
            'recaptcha' => [
                'secret_key_option' => 'petitioner_recaptcha_secret_key',
                'verify_url'        => 'https://www.google.com/recaptcha/api/siteverify',
            ],
            'hcaptcha' => [
                'secret_key_option' => 'petitioner_hcaptcha_secret_key',
                'verify_url'        => 'https://hcaptcha.com/siteverify',
            ],
            'turnstile' => [
                'secret_key_option' => 'petitioner_turnstile_secret_key',
                'verify_url'        => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            ],
        ];

        // Validate captcha type
        if (! isset($providers[$captcha_type])) {
            return [
                'success' => false,
                'message' => AV_Petitioner_Labels::get('captcha_invalid_type'),
            ];
        }

        $provider = $providers[$captcha_type];
        $captcha_secret = get_option($provider['secret_key_option'], '');

        if (empty($captcha_secret)) {
            return [
                'success' => false,
                'message' => AV_Petitioner_Labels::get('captcha_verification_failed'),
            ];
        }

        // Handle missing response
        if (empty($captcha_response)) {
            return [
                'success' => false,
                'message' => AV_Petitioner_Labels::get('captcha_missing_response'),
            ];
        }

        // Send request to verification API
        $api_response = wp_remote_post($provider['verify_url'], [
            'timeout' => 5,
            'redirection' => 0,
            'body' => [
                'secret'   => $captcha_secret,
                'response' => $captcha_response,
                'remoteip' => av_petitioner_get_remote_ip(),
            ],
        ]);

        // Handle connection failure
        if (is_wp_error($api_response)) {
            return [
                'success' => false,
                'message' => AV_Petitioner_Labels::get('captcha_connection_failed'),
            ];
        }

        // Decode API response
        $body = json_decode(wp_remote_retrieve_body($api_response), true);

        // Validate general success
        if (! isset($body['success']) || ! $body['success']) {
            return [
                'success' => false,
                'message' => AV_Petitioner_Labels::get('captcha_verification_failed'),
            ];
        }

        // Special case: Check reCAPTCHA v3 score
        if ($captcha_type === 'recaptcha' && isset($body['score']) && $body['score'] < 0.5) {
            return [
                'success' => false,
                'message' => AV_Petitioner_Labels::get('captcha_score_too_low'),
            ];
        }

        return [
            'success' => true,
            'message' => AV_Petitioner_Labels::get('captcha_verification_completed'),
        ];
    }
}
