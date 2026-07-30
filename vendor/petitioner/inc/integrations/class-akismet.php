<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Class AV_Petitioner_Akismet
 *
 * This class integrates the Akismet service with the Petitioner plugin.
 * Akismet is used to detect and filter spam submissions.
 *
 */
class AV_Petitioner_Akismet
{
    public static function check_with_akismet(
        $email,
        $fname,
        $lname,
        $country,
        $form_id,
        $request_context = []
    )
    {
        $enable_akismet = get_option('petitioner_enable_akismet');

        // Skip when the Petitioner integration is not enabled.
        if (!$enable_akismet) {
            return false;
        }

        $akismet_api_key = get_option('wordpress_api_key');

        if (empty($akismet_api_key)) {
            return false;
        }

        $blog = get_option('home');

        $full_name = trim($fname . ' ' . $lname);

        $query = [
            'blog'                 => $blog,
            'user_ip'              => $request_context['user_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent'           => $request_context['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'referrer'             => $request_context['referrer'] ?? ($_SERVER['HTTP_REFERER'] ?? ''),
            'comment_type'         => 'signup',
            'comment_author'       => $full_name,
            'comment_author_email' => $email,
            'comment_author_url'   => '',
            'comment_content'      => 'Country: ' . $country . '. Form ID: ' . $form_id,
        ];

        $endpoint = sprintf(
            'https://%s.rest.akismet.com/1.1/comment-check',
            rawurlencode($akismet_api_key)
        );
        $response = wp_remote_post($endpoint, [
            'timeout' => 3,
            'redirection' => 0,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded; charset=' . get_option('blog_charset'),
                'User-Agent' => sprintf(
                    'WordPress/%s | Petitioner/%s',
                    get_bloginfo('version'),
                    AV_PETITIONER_PLUGIN_VERSION
                ),
            ],
            'body' => $query,
        ]);

        // Spam checking must never prevent a legitimate signature when Akismet
        // is slow or unavailable. Fail open and keep the honeypot protection.
        if (is_wp_error($response)) {
            av_ptr_error_log(
                'Petitioner Akismet warning: ' . $response->get_error_message()
            );
            return false;
        }

        if (trim(wp_remote_retrieve_body($response)) === 'true') {
            return true;
        }

        return false;
    }
}
