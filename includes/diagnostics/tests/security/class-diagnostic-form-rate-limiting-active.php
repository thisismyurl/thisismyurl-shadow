<?php
/**
 * Form Rate Limiting Active Diagnostic
 *
 * Checks whether rate limiting or anti-abuse controls are active on WordPress
 * forms to prevent brute-force and submission flooding attacks.
 *
 * @package    Christopher Ross Shadow
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Form Rate Limiting Active Diagnostic Class
 *
 * @since 0.6095
 */
class Diagnostic_Form_Rate_Limiting_Active extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'form-rate-limiting-active';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Form Rate Limiting Active';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether an active plugin enforces rate limiting or anti-spam protection on WordPress forms to block bot submissions.';

	/**
	 * Gauge family/category for dashboard placement.
	 *
	 * @var string
	 */
	protected static $family = 'security';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * Scans active plugins for known anti-spam or rate-limiting tools and
	 * flags the site when no recognized form-protection plugin is detected.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when no rate-limiting plugin is active, null when healthy.
	 */
	public static function check() {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		$antispam_plugins = array(
			'akismet/akismet.php'                                      => 'Akismet',
			'wordfence/wordfence.php'                                  => 'Wordfence',
			'google-captcha/google-captcha.php'                        => 'Google Captcha (reCAPTCHA)',
			'advanced-google-recaptcha/advanced-google-recaptcha.php'  => 'Advanced Google reCAPTCHA',
			'simple-cloudflare-turnstile/simple-cloudflare-turnstile.php' => 'Cloudflare Turnstile',
			'jetpack/jetpack.php'                                      => 'Jetpack (spam protection)',
			'antispam-bee/antispam-bee.php'                            => 'Antispam Bee',
			'zero-spam/plugin.php'                                     => 'WordPress Zero Spam',
			'cleantalk-spam-protect/cleantalk.php'                     => 'CleanTalk Anti-Spam',
			'contact-form-7/wp-contact-form-7.php'                    => null, // CF7 alone is not anti-spam.
		);

		$protection_found = false;
		foreach ( $antispam_plugins as $plugin_file => $plugin_name ) {
			if ( null !== $plugin_name && in_array( $plugin_file, $active_plugins, true ) ) {
				$protection_found = true;
				break;
			}
		}

		if ( $protection_found ) {
			return null;
		}

		// Check if comments are open (comment spam target requires anti-spam).
		$default_comment_status = get_option( 'default_comment_status', 'open' );
		$comments_open          = ( 'open' === $default_comment_status );

		if ( ! $comments_open ) {
			return null; // Comments disabled; reduced spam surface.
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'Comments are open but no anti-spam or form rate-limiting plugin is active. Without protection, public comment forms and contact forms are vulnerable to spam floods and brute-force submission attacks. Install Akismet Anti-Spam or a CAPTCHA plugin (such as Cloudflare Turnstile or Advanced Google reCAPTCHA) to protect public forms.', 'thisismyurl-shadow' ),
			'severity'     => 'medium',
			'threat_level' => 40,
			'details'      => array(
				'comments_open'       => $comments_open,
				'antispam_plugin'     => null,
			),
		);
	}
}
