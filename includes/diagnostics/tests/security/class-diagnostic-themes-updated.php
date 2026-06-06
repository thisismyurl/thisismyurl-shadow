<?php
/**
 * Themes Updated Diagnostic
 *
 * Checks whether any installed themes have available updates, as outdated
 * themes can contain unpatched security vulnerabilities.
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
 * Diagnostic_Themes_Updated Class
 *
 * Retrieves the list of themes that have pending updates and flags the
 * site when any outdated themes are found.
 *
 * @since 0.6095
 */
class Diagnostic_Themes_Updated extends Diagnostic_Base {

	/**
	 * Diagnostic slug.
	 *
	 * @var string
	 */
	protected static $slug = 'themes-updated';

	/**
	 * Diagnostic title.
	 *
	 * @var string
	 */
	protected static $title = 'Themes Updated';

	/**
	 * Diagnostic description.
	 *
	 * @var string
	 */
	protected static $description = 'Checks whether any installed themes have available updates, as outdated themes can contain unpatched security vulnerabilities.';

	/**
	 * Gauge family/category.
	 *
	 * @var string
	 */
	protected static $family = 'security';

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
	 * Reads the WordPress theme update transient via the WP_Settings helper to
	 * collect themes that have pending updates, returning a medium-severity
	 * finding when any are found.
	 *
	 * @since  0.6095
	 * @return array|null Finding array when outdated themes exist, null when healthy.
	 */
	public static function check() {
		$outdated = WP_Settings::get_themes_needing_updates();
		if ( empty( $outdated ) ) {
			return null;
		}

		$count = count( $outdated );
		$names = array_column( array_values( $outdated ), 'name' );

		return array(
			'id'           => self::$slug,
			'title'        => self::$title,
			'description'  => sprintf(
						/* translators: %d: number of themes with available updates. */
				_n(
					'%d theme has an available update. Outdated themes can contain security vulnerabilities - keep them patched even if inactive.',
					'%d themes have available updates. Outdated themes can contain security vulnerabilities - keep them patched even if inactive.',
					$count,
					'thisismyurl-shadow'
				),
				$count
			),
			'severity'     => 'medium',
			'threat_level' => 50,
			'details'      => array(
				'count'  => $count,
				'themes' => $names,
			),
		);
	}
}
