<?php
/**
 * Spam Protection Enabled Diagnostic
 *
 * Checks whether a spam protection plugin is active to filter bot
 * submissions from WordPress comments and contact forms.
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
 * Spam Protection Enabled Diagnostic Class
 *
 * Checks option keys and loaded classes from known anti-spam plugins,
 * flagging sites where no recognised spam-filtering mechanism is active.
 *
 * @since 0.6095
 */
class Diagnostic_Spam_Protection_Enabled extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'spam-protection-enabled';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Spam Protection Enabled';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether a spam protection plugin is active to filter bot submissions from WordPress comments and contact forms.';

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
	 * Inspects well-known option keys and loaded classes from popular anti-spam
	 * plugins; returns a medium-severity finding when none are detected.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when no spam protection is active, null when healthy.
	 */
	public static function check() {
		// Check WordPress option signatures of common anti-spam plugins.
		$option_indicators = array(
			'wordpress_api_key',  // Akismet (stores the API key)
			'antispam_bee',       // Antispam Bee
			'cleantalk_license',  // CleanTalk
			'wparmour_settings',  // WP Armour (honeypot)
			'bws_spam_options',   // Anti-Spam by BestWebSoft
		);

		foreach ( $option_indicators as $option ) {
			if ( false !== get_option( $option, false ) ) {
				return null;
			}
		}

		// Class-based indicator (faster than option check for loaded plugins).
		$class_indicators = array(
			'Akismet',
			'AntispamBee',
			'CleanTalk',
		);

		foreach ( $class_indicators as $class ) {
			if ( class_exists( $class, false ) ) {
				return null;
			}
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'No spam protection plugin was detected on your site. Without it, comment sections, contact forms, and user registration pages are vulnerable to bot-submitted spam that clutters your database, wastes storage, and may harm your email reputation.', 'thisismyurl-shadow' ),
			'severity'     => 'medium',
			'threat_level' => 40,
			'details'      => array(
				'note' => __( 'Install Akismet, Antispam Bee, CleanTalk, or a similar plugin to filter spam submissions.', 'thisismyurl-shadow' ),
			),
		);
	}
}
