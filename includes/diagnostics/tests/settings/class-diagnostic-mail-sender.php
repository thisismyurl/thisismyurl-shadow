<?php
/**
 * Mail Sender Configured Diagnostic
 *
 * Checks whether the WordPress outgoing mail sender name and address have been
 * customised from the generic defaults. Probes via apply_filters() so SMTP
 * plugins and custom hooks are included in the evaluation.
 *
 * @package    Shadow by Christopher Ross
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
 * Diagnostic_Mail_Sender Class
 *
 * Probes the wp_mail_from and wp_mail_from_name filters to detect the active
 * sender identity. Flags when either the sender name or email address is still
 * the WordPress default.
 *
 * @since 0.6095
 */
class Diagnostic_Mail_Sender extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'mail-sender';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Mail Sender';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the WordPress outgoing mail sender name and email address have been customized from the generic WordPress defaults.';

	/**
	 * Gauge family/category for dashboard placement.
	 *
	 * @var string
	 */
	protected static $family = 'settings';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'standard';

	/**
	 * Run the diagnostic check.
	 *
	 * Builds the WordPress default from-email and from-name, then probes the
	 * wp_mail_from and wp_mail_from_name filters to see what is actually used.
	 * Returns null when both name and email differ from the defaults. Returns a
	 * low-severity finding listing whichever defaults are still in use.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when sender is uncustomised, null when healthy.
	 */
	public static function check() {
		// Get the sender name and email that WordPress would use for outgoing mail.
		// We probe via apply_filters() so active SMTP plugins and customisations are included.
		$default_email = 'wordpress@' . strtolower( preg_replace( '/^www\./', '', wp_parse_url( home_url(), PHP_URL_HOST ) ?? '' ) );
		$default_name  = 'WordPress';

		$active_email = apply_filters( 'wp_mail_from', $default_email );
		$active_name  = apply_filters( 'wp_mail_from_name', $default_name );

		// If both have been customised, sender identity is configured.
		if ( $active_email !== $default_email && $active_name !== $default_name ) {
			return null;
		}

		$issues = array();
		if ( $active_name === $default_name ) {
			$issues[] = __( 'Sender name is still the default "WordPress"', 'thisismyurl-shadow' );
		}
		if ( $active_email === $default_email ) {
			$issues[] = sprintf(
				/* translators: %s: the default from email address */
				__( 'Sender email is the WordPress default (%s)', 'thisismyurl-shadow' ),
				$active_email
			);
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'WordPress is sending email with its default sender identity. The default "wordpress@yoursite.com" address is often flagged as spam and provides no branding. Set a custom sender name and email address via an SMTP plugin or the wp_mail_from / wp_mail_from_name filters.', 'thisismyurl-shadow' ),
			'severity'     => 'low',
			'threat_level' => 20,
			'details'      => array(
				'active_email' => $active_email,
				'active_name'  => $active_name,
				'issues'       => $issues,
			),
		);
	}
}
