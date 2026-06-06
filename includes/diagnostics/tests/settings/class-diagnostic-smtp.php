<?php
/**
 * SMTP Configured Diagnostic
 *
 * Checks whether an SMTP or transactional email plugin is active to ensure
 * outgoing emails are reliably delivered rather than relying on PHP mail().
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
 * Diagnostic_Smtp Class
 *
 * Uses WP_Settings::uses_smtp_plugin() to detect recognised SMTP/transactional
 * email plugins and returns a medium-severity finding when none are active.
 *
 * @since 0.6095
 */
class Diagnostic_Smtp extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'smtp';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'SMTP';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether an SMTP or transactional email plugin is active to ensure outgoing emails are reliably delivered rather than relying on PHP mail().';

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
	 * Calls WP_Settings::uses_smtp_plugin() to check whether a recognised SMTP
	 * or transactional email plugin is active. Returns null when one is found.
	 * Returns a medium-severity finding when PHP mail() is the only mail
	 * transport available.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when no SMTP plugin is active, null when healthy.
	 */
	public static function check() {
		if ( WP_Settings::uses_smtp_plugin() ) {
			return null;
		}

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => __( 'No SMTP or transactional email plugin was detected. WordPress uses PHP mail() by default, which is frequently blocked by hosting providers and flagged as spam. Install an SMTP plugin and configure a reliable mail provider to ensure emails (password resets, order confirmations, notifications) are delivered.', 'thisismyurl-shadow' ),
			'severity'     => 'medium',
			'threat_level' => 35,
			'details'      => array(
				'detected_plugin' => null,
				'note'            => __( 'Install WP Mail SMTP, FluentSMTP, Post SMTP Mailer, or a similar plugin to configure SMTP delivery.', 'thisismyurl-shadow' ),
			),
		);
	}
}
