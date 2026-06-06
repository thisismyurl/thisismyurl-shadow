<?php
/**
 * Admin Email Domain Match Diagnostic
 *
 * Checks whether the WordPress admin email address uses a known free consumer
 * email provider (gmail.com, hotmail.com, etc.) rather than the site's own
 * domain. Using a free-provider address for site notifications looks
 * unprofessional and reduces DMARC alignment on outbound emails.
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
 * Diagnostic_Admin_Email_Domain_Match Class
 *
 * Reads the admin_email option, extracts the domain, and checks it against a
 * list of well-known free consumer email providers. Returns a low-severity
 * finding when a free-provider domain is detected.
 *
 * @since 0.6095
 */
class Diagnostic_Admin_Email_Domain_Match extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'admin-email-domain-match';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Admin Email Uses Own Domain';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether the WordPress admin email address uses the site\'s own domain rather than a free consumer email provider. Using a @gmail.com or @hotmail.com address for site notifications looks unprofessional and reduces deliverability of transactional emails.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'settings';

	/**
	 * Confidence level of this diagnostic.
	 *
	 * @var string
	 */
	protected static $confidence = 'low';

	/**
	 * Severity of the finding.
	 *
	 * @var string
	 */
	protected static $severity = 'low';

	/**
	 * Estimated minutes to resolve.
	 *
	 * @var int
	 */
	protected static $time_to_fix_minutes = 5;

	/**
	 * Business impact statement.
	 *
	 * @var string
	 */
	protected static $impact = 'An admin email on a free provider domain undermines brand trust, reduces DMARC alignment on outbound WordPress notifications, and misses the opportunity to reinforce the business domain in every automated email.';

	/**
	 * Run the diagnostic check.
	 *
	 * Reads the admin_email WordPress option, extracts the domain portion, and
	 * compares it against a curated list of free consumer email providers.
	 * Returns null when the admin email uses a proprietary or business domain.
	 * Returns a low-severity finding when a free provider is detected.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when admin email uses a free provider, null when healthy.
	 */
	public static function check() {
		$email = (string) get_option( 'admin_email', '' );

		if ( ! is_email( $email ) ) {
			return null; // admin-email-deliverable handles invalid formats.
		}

		$parts  = explode( '@', strtolower( $email ), 2 );
		$domain = $parts[1] ?? '';

		$free_providers = array(
			'gmail.com',
			'googlemail.com',
			'yahoo.com',
			'yahoo.co.uk',
			'ymail.com',
			'hotmail.com',
			'hotmail.co.uk',
			'outlook.com',
			'live.com',
			'msn.com',
			'icloud.com',
			'me.com',
			'mac.com',
			'aol.com',
			'protonmail.com',
			'proton.me',
			'zohomail.com',
			'zoho.com',
			'mail.com',
			'inbox.com',
			'GMX.com',
		);

		if ( ! in_array( $domain, array_map( 'strtolower', $free_providers ), true ) ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
				/* translators: %s: the admin email domain */
				__( 'The WordPress admin email uses the free consumer email domain "%s". Site notifications, security alerts, and comment moderation emails are sent using this address. Switching to an address on your own domain looks more professional and improves DMARC alignment for outbound mail.', 'thisismyurl-shadow' ),
				$domain
			),
			'severity'     => 'low',
			'threat_level' => 15,
			'details'      => array(
				'admin_email'   => $email,
				'email_domain'  => $domain,
			),
		);
	}
}
