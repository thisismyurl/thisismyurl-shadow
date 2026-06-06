<?php
/**
 * Admin Email Deliverable Diagnostic
 *
 * Checks whether the WordPress admin email address is valid and not using a
 * known placeholder domain or generic alias prefix that is unlikely to be
 * actively monitored. Site notifications and security alerts rely on this
 * address being deliverable.
 *
 * @package    Christopher Ross Shadow
 * @subpackage Diagnostics
 * @since      0.6095
 */

declare(strict_types=1);

namespace ThisIsMyURL\Shadow\Diagnostics;

use ThisIsMyURL\Shadow\Core\Diagnostic_Base;
use ThisIsMyURL\Shadow\Diagnostics\Helpers\Diagnostic_WP_Settings_Helper as WP_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Diagnostic_Admin_Email_Deliverable Class
 *
 * Reads the admin_email option and validates it against format rules,
 * placeholder domain patterns, and generic prefix patterns. Returns the
 * appropriate severity finding or null when the address looks deliverable.
 *
 * @since 0.6095
 */
class Diagnostic_Admin_Email_Deliverable extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'admin-email-deliverable';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Admin Email Deliverable';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the WordPress admin email address is valid and not using a generic placeholder, ensuring site notifications are deliverable.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'settings';

	/**
	 * Whether this diagnostic is part of the core trusted set.
	 *
	 * @var bool
	 */
	protected static $is_core = true;

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'high';

	/**
	 * Run the diagnostic check.
	 *
	 * Reads the admin_email WordPress option and applies three progressive checks:
	 * invalid email format (high severity), known placeholder domain (high
	 * severity), and generic inbox prefix like info@ or noreply@ (low severity).
	 * Returns null when the address passes all checks.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when address has a deliverability issue, null when healthy.
	 */
	public static function check() {
		$email = WP_Settings::get_admin_email();

		if ( empty( $email ) ) {
			return null;
		}

		// Validate basic email format.
		if ( ! is_email( $email ) ) {
			return array(
				'id'           => self::$slug,
				'title'        => self::$title,
				'description'  => sprintf(
					/* translators: %s: the admin email address */
					__( 'The WordPress admin email address (%s) does not appear to be a valid email address. WordPress relies on this address for security alerts, update notifications, and comment moderation. Update it under Settings > General.', 'thisismyurl-shadow' ),
					esc_html( $email )
				),
				'severity'     => 'high',
				'threat_level' => 60,
				'details'      => array(
					'email'  => $email,
					'reason' => 'invalid_format',
				),
			);
		}

		// Detect known placeholder or unmonitored-inbox patterns.
		$lower        = strtolower( $email );
		$placeholder_domains = array( 'example.com', 'example.org', 'example.net', 'test.com', 'localhost' );
		$generic_prefixes    = array( 'info@', 'admin@', 'webmaster@', 'no-reply@', 'noreply@', 'postmaster@', 'mail@', 'contact@' );

		foreach ( $placeholder_domains as $domain ) {
			if ( str_contains( $lower, '@' . $domain ) ) {
				return array(
					'id'           => self::$slug,
					'title'        => self::$title,
					'description'  => sprintf(
						/* translators: %s: the admin email address */
						__( 'The WordPress admin email (%s) uses a placeholder domain and will not receive any mail. Update it to a real, monitored inbox under Settings > General.', 'thisismyurl-shadow' ),
						esc_html( $email )
					),
					'severity'     => 'high',
					'threat_level' => 65,
					'details'      => array(
						'email'  => $email,
						'reason' => 'placeholder_domain',
					),
				);
			}
		}

		foreach ( $generic_prefixes as $prefix ) {
			if ( str_starts_with( $lower, $prefix ) ) {
				return array(
					'id'           => self::$slug,
					'title'        => self::$title,
					'description'  => sprintf(
						/* translators: %s: the admin email address */
						__( 'The WordPress admin email (%s) uses a generic alias that is often unmonitored or routed to a shared inbox. WordPress sends security alerts and moderation notifications to this address. Update it to a personally monitored inbox.', 'thisismyurl-shadow' ),
						esc_html( $email )
					),
					'severity'     => 'low',
					'threat_level' => 20,
					'details'      => array(
						'email'  => $email,
						'reason' => 'generic_prefix',
					),
				);
			}
		}

		return null;
	}
}
